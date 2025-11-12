<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\CapitalPrestamista;
use App\Models\MovimientoCapitalPrestamista;
use App\Models\Abono;
use App\Models\EmpresaCapital;
use App\Models\RegistroCapital;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class PrestamoController extends Controller
{
    public function index()
    {
        if (auth()->user()->hasRole('PRESTAMISTA')) {
            $prestamos = Prestamo::where('idusuario', auth()->id())->get();
        } else {
            $prestamos = Prestamo::all();
        }

        foreach ($prestamos as $prestamo) {
            $prestamo->tiene_cuota_pagada = Pago::whereNotNull('fecha_cancelado')
                ->where('prestamo_id', $prestamo->id)
                ->exists();
        }

        return view('admin.prestamos.index', compact('prestamos'));
    }

    public function create()
    {
        $clientes = auth()->user()->hasRole('PRESTAMISTA')
            ? Cliente::where('idusuario', auth()->id())->get()
            : Cliente::all();

        return view('admin.prestamos.create', compact('clientes'));
    }

    /**
     * Convierte "1.234.567,89" -> 1234567.89
     */
    private function parsePesos(null|string $s): float
    {
        if ($s === null) return 0.0;
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
        return (float) $s;
    }

    /**
     * Días totales (para cronograma).
     */
    private function diasTotalesPlan(string $modalidad, int $cuotas): int
    {
        $cuotas = max(1, $cuotas);
        return match ($modalidad) {
            'Diario'    => 1 * $cuotas,
            'Semanal'   => 7 * $cuotas,
            'Quincenal' => 15 * $cuotas,   // 15 días exactos
            'Mensual'   => 30 * $cuotas,   // aproximado, consistente
            'Anual'     => 365 * $cuotas,
            default     => 7 * $cuotas,
        };
    }

    /**
     * Monto total con regla:
     *   - Aplica +20% por CADA semana adicional **después de la 4ª** SOLO si modalidad = 'Semanal'.
     *   - Si es Diario/Quincenal/Mensual/Anual, NO se aplica el recargo extra.
     * Interés ADITIVO (no compuesto).
     */
    private function calcularMontoTotalConRegla(float $principal, float $tasaBase, string $modalidad, int $cuotas): float
    {
        $tasaEfectiva = $tasaBase;

        if ($modalidad === 'Semanal') {
            // En semanal, cada cuota es una semana
            $extraSemanas = max(0, $cuotas - 4);
            $tasaEfectiva += (0.20 * $extraSemanas); // +20% por semana extra (>4)
        }

        return round($principal * (1 + $tasaEfectiva), 2);
    }

    public function store(Request $request)
    {
        $request->validate([
            'cliente_id'     => 'required',
            'monto_prestado' => 'required',
            'tasa_interes'   => 'required|numeric',
            'modalidad'      => 'required|in:Diario,Semanal,Quincenal,Mensual,Anual',
            'nro_cuotas'     => 'required|integer|min:1',
            'fecha_inicio'   => 'required|date',
        ]);

        $usuario = auth()->user();
        $ownerId = (int) ($request->input('prestamista_id') ?: $usuario->id);

        // Parseo y normalización
        $principal = $this->parsePesos($request->monto_prestado);
        $rate      = (float) $request->tasa_interes;   // puede venir 20 => 0.20
        if ($rate > 1) { $rate = $rate / 100; }

        $cuotas    = max((int) $request->nro_cuotas, 1);

        // REGLA + quincenal real
        $montoTotal = $this->calcularMontoTotalConRegla($principal, $rate, $request->modalidad, $cuotas);
        $montoCuota = round($montoTotal / $cuotas, 2);

        // Asegurar capital empresa
        $empresaExiste = EmpresaCapital::latest('id')->first();
        if (!$empresaExiste) {
            return back()->withInput()
                ->with('mensaje', 'Debes crear primero el registro de capital de empresa.')
                ->with('icono', 'error');
        }

        try {
            DB::transaction(function () use ($request, $ownerId, $principal, $montoTotal, $montoCuota, $cuotas) {

                // Bloqueos
                $cp = CapitalPrestamista::query()
                    ->lockForUpdate()
                    ->firstOrCreate(['user_id' => $ownerId], [
                        'monto_asignado'   => 0,
                        'monto_disponible' => 0,
                    ]);

                $empresa = EmpresaCapital::query()
                    ->lockForUpdate()
                    ->latest('id')
                    ->firstOrFail();

                $saldoAsesores      = (int) ($empresa->capital_asignado_total ?? 0);
                $montoSolicitadoInt = (int) round($principal, 0);

                // 1) Usar saldo de asesores
                if ($saldoAsesores > 0) {
                    $aTransferir = min($montoSolicitadoInt, $saldoAsesores);

                    $empresa->capital_anterior       = (int) $saldoAsesores;
                    $empresa->capital_asignado_total = $saldoAsesores - $aTransferir;
                    $empresa->save();

                    $cp->monto_disponible = (int) $cp->monto_disponible + $aTransferir;
                    $cp->save();

                    RegistroCapital::create([
                        'monto'       => -$aTransferir,
                        'user_id'     => $ownerId,
                        'tipo_accion' => 'Traspaso desde saldo de asesores al prestamista #' . $ownerId . ' (para préstamo)',
                    ]);

                    if (class_exists(MovimientoCapitalPrestamista::class)) {
                        MovimientoCapitalPrestamista::create([
                            'user_id'     => $ownerId,
                            'monto'       => $aTransferir,
                            'descripcion' => 'Traspaso desde saldo de asesores',
                        ]);
                    }
                }

                // 2) Verifica capital disponible del prestamista
                if ((int) $cp->monto_disponible < $montoSolicitadoInt) {
                    throw new \DomainException('__NO_CAPITAL__');
                }

                // 3) Crear préstamo
                $fechaInicioLocal = Carbon::parse($request->fecha_inicio, 'America/Bogota')->startOfDay();
                $fechaInicioUtc   = $fechaInicioLocal->copy()->timezone('UTC'); // se guarda UTC para Auditorías

                $prestamo = new Prestamo();
                $prestamo->cliente_id     = (int) $request->cliente_id;
                $prestamo->monto_prestado = $principal;                  // DECIMAL(15,2) recomendado
                $prestamo->tasa_interes   = (float) $request->tasa_interes; // se guarda como lo ingresó el usuario
                $prestamo->modalidad      = $request->modalidad;
                $prestamo->nro_cuotas     = $cuotas;
                $prestamo->fecha_inicio   = $fechaInicioUtc;             // UTC
                $prestamo->monto_total    = $montoTotal;                 // con la regla
                $prestamo->idusuario      = $ownerId;
                $prestamo->estado         = 'Pendiente';
                $prestamo->save();

                // 4) Cronograma (Quincenal = +15 días exactos)
                $inicio = $fechaInicioLocal->copy(); // trabajar en local para fechas de vencimiento visibles
                for ($i = 1; $i <= $cuotas; $i++) {
                    switch ($request->modalidad) {
                        case 'Diario':    $venc = $inicio->copy()->addDays($i); break;
                        case 'Semanal':   $venc = $inicio->copy()->addWeeks($i); break;
                        case 'Quincenal': $venc = $inicio->copy()->addDays(15 * $i); break; // fix 15 días
                        case 'Mensual':   $venc = $inicio->copy()->addMonths($i); break;
                        case 'Anual':     $venc = $inicio->copy()->addYears($i); break;
                        default:          $venc = $inicio->copy()->addWeeks($i); break;
                    }

                    $pago = new Pago();
                    $pago->prestamo_id     = $prestamo->id;
                    $pago->monto_pagado    = $montoCuota;
                    $pago->fecha_pago      = $venc->toDateString(); // fecha visible en local
                    $pago->metodo_pago     = 'Efectivo';
                    $pago->referencia_pago = 'Pago de la cuota ' . $i;
                    $pago->estado          = 'Pendiente';
                    $pago->save();
                }

                // 5) Descontar capital del prestamista
                $asignadoAntes = (int) $cp->monto_asignado;
                $cp->monto_disponible = max(0, (int) $cp->monto_disponible - $montoSolicitadoInt);
                $cp->monto_asignado   = max(0, $asignadoAntes - min($montoSolicitadoInt, $asignadoAntes));
                $cp->save();

                if (class_exists(MovimientoCapitalPrestamista::class)) {
                    MovimientoCapitalPrestamista::create([
                        'user_id'     => $ownerId,
                        'monto'       => $montoSolicitadoInt,
                        'descripcion' => 'Préstamo realizado ID ' . $prestamo->id,
                    ]);
                }
            }, 3);
        } catch (\DomainException $e) {
            if ($e->getMessage() === '__NO_CAPITAL__') {
                return back()
                    ->withInput()
                    ->with('mensaje', '❌ No hay capital asignado suficiente para realizar este préstamo para el prestamista seleccionado.')
                    ->with('icono', 'error');
            }
            throw $e;
        }

        return redirect()->route('admin.prestamos.index')
            ->with('mensaje', 'Se registró el préstamo correctamente')
            ->with('icono', 'success');
    }

    public function edit($id)
    {
        $prestamo = Prestamo::findOrFail($id);

        if (auth()->user()->hasRole('PRESTAMISTA') && $prestamo->idusuario !== auth()->id()) {
            return redirect()->route('admin.prestamos.index')
                ->with('mensaje', 'No tienes permiso para editar este préstamo.')
                ->with('icono', 'error');
        }

        $clientes = auth()->user()->hasRole('PRESTAMISTA')
            ? Cliente::where('idusuario', auth()->id())->get()
            : Cliente::all();

        return view('admin.prestamos.edit', compact('prestamo', 'clientes'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'cliente_id'     => 'required',
            'monto_prestado' => 'required',
            'tasa_interes'   => 'required|numeric',
            'modalidad'      => 'required|in:Diario,Semanal,Quincenal,Mensual,Anual',
            'nro_cuotas'     => 'required|integer|min:1',
            'fecha_inicio'   => 'required|date',
        ]);

        $prestamo = Prestamo::findOrFail($id);

        if (auth()->user()->hasRole('PRESTAMISTA') && $prestamo->idusuario !== auth()->id()) {
            return redirect()->route('admin.prestamos.index')
                ->with('mensaje', 'No tienes permiso para actualizar este préstamo.')
                ->with('icono', 'error');
        }

        $principal = $this->parsePesos($request->monto_prestado);
        $rate      = (float) $request->tasa_interes;
        if ($rate > 1) { $rate = $rate / 100; }

        $cuotas     = max((int) $request->nro_cuotas, 1);
        $montoTotal = $this->calcularMontoTotalConRegla($principal, $rate, $request->modalidad, $cuotas);

        // Guardar fecha_inicio en UTC (inicio del día local)
        $fechaInicioUtc = Carbon::parse($request->fecha_inicio, 'America/Bogota')->startOfDay()->timezone('UTC');

        $prestamo->update([
            'cliente_id'     => (int) $request->cliente_id,
            'monto_prestado' => $principal,
            'tasa_interes'   => (float) $request->tasa_interes,
            'modalidad'      => $request->modalidad,
            'nro_cuotas'     => $cuotas,
            'fecha_inicio'   => $fechaInicioUtc,
            'monto_total'    => $montoTotal,
        ]);

        // Nota: si cambiaste nro_cuotas/modalidad, idealmente regenerar cronograma respetando cuotas ya pagadas.

        return redirect()->route('admin.prestamos.index')
            ->with('mensaje', 'Préstamo actualizado correctamente.')
            ->with('icono', 'success');
    }

    public function show($id)
    {
        $prestamo = Prestamo::with('cliente')->findOrFail($id);
        $pagos = Pago::where('prestamo_id', $prestamo->id)->get();

        return view('admin.prestamos.show', compact('prestamo', 'pagos'));
    }

    public function destroy($id)
    {
        $prestamo = Prestamo::findOrFail($id);

        if (auth()->user()->hasRole('PRESTAMISTA') && $prestamo->idusuario !== auth()->id()) {
            return redirect()->route('admin.prestamos.index')
                ->with('mensaje', 'No tienes permiso para eliminar este préstamo.')
                ->with('icono', 'error');
        }

        $prestamo->delete();

        return redirect()->route('admin.prestamos.index')
            ->with('mensaje', 'Préstamo eliminado correctamente.')
            ->with('icono', 'success');
    }

    public function contratos($id)
    {
        $prestamo      = Prestamo::with('cliente')->findOrFail($id);
        $configuracion = Configuracion::first();
        $pagos         = Pago::where('prestamo_id', $prestamo->id)->get();

        $pdf = Pdf::loadView('admin.prestamos.contratos', compact('prestamo', 'configuracion', 'pagos'));

        return $pdf->download('prestamo_' . $prestamo->id . '.pdf');
    }

    public function obtenerCliente($id)
    {
        $cliente = Cliente::find($id);

        if (!$cliente) {
            return response()->json(['error' => 'Cliente no encontrado'], 404);
        }

        return response()->json($cliente);
    }

    /**
     * RETANQUEO
     */
    public function retanqueo(Request $request, Prestamo $prestamo)
    {
        $data = $request->validate([
            'principal_nuevo' => ['required', 'numeric', 'min:0.01'],
            'tasa_interes'    => ['required', 'numeric', 'min:0'],
            'modalidad'       => ['required', 'in:Diario,Semanal,Quincenal,Mensual,Anual'],
            'nro_cuotas'      => ['required', 'integer', 'min:1'],
            'observaciones'   => ['nullable', 'string', 'max:500'],
        ]);

        $saldoPendiente = $this->calcularSaldoPendiente($prestamo);

        if ((float) $data['principal_nuevo'] < $saldoPendiente) {
            return back()
                ->with('mensaje', 'El principal del retanqueo no cubre el saldo pendiente.')
                ->with('icono', 'error');
        }

        $netoEntregar = max(0, (float)$data['principal_nuevo'] - (float)$saldoPendiente);

        $nuevoPrestamo = DB::transaction(function () use ($prestamo, $data, $saldoPendiente, $netoEntregar) {

            Pago::where('prestamo_id', $prestamo->id)
                ->where('estado', '!=', 'Confirmado')
                ->update([
                    'estado'          => 'Confirmado',
                    'fecha_cancelado' => now()->toDateString()
                ]);

            $prestamo->estado = 'Cancelado';
            $prestamo->save();

            $nuevo = new Prestamo();
            $nuevo->cliente_id     = $prestamo->cliente_id;
            $nuevo->idusuario      = auth()->id() ?? $prestamo->idusuario;
            $nuevo->monto_prestado = (float) $data['principal_nuevo'];
            $nuevo->tasa_interes   = (float) $data['tasa_interes'];
            $nuevo->modalidad      = $data['modalidad'];
            $nuevo->nro_cuotas     = (int) $data['nro_cuotas'];

            // fecha_inicio en UTC (inicio de día local)
            $fechaInicioLocal = Carbon::now('America/Bogota')->startOfDay();
            $nuevo->fecha_inicio = $fechaInicioLocal->copy()->timezone('UTC');

            // Regla de +20% solo para semanal
            $rate = (float) $data['tasa_interes'];
            if ($rate > 1) { $rate = $rate / 100; }
            $nuevo->monto_total = $this->calcularMontoTotalConRegla(
                (float) $data['principal_nuevo'],
                $rate,
                $data['modalidad'],
                (int) $data['nro_cuotas']
            );

            $nuevo->estado = 'Pendiente';
            $nuevo->save();

            $montoCuota = round($nuevo->monto_total / max(1, $nuevo->nro_cuotas), 2);
            $inicio     = $fechaInicioLocal->copy();

            for ($i = 1; $i <= $nuevo->nro_cuotas; $i++) {
                switch ($nuevo->modalidad) {
                    case 'Diario':    $venc = $inicio->copy()->addDays($i); break;
                    case 'Semanal':   $venc = $inicio->copy()->addWeeks($i); break;
                    case 'Quincenal': $venc = $inicio->copy()->addDays(15 * $i); break; // 15 días exactos
                    case 'Mensual':   $venc = $inicio->copy()->addMonths($i); break;
                    case 'Anual':     $venc = $inicio->copy()->addYears($i); break;
                    default:          $venc = $inicio->copy()->addWeeks($i); break;
                }

                $pago = new Pago();
                $pago->prestamo_id     = $nuevo->id;
                $pago->monto_pagado    = $montoCuota;
                $pago->fecha_pago      = $venc->toDateString();
                $pago->metodo_pago     = 'Efectivo';
                $pago->referencia_pago = 'Pago de la cuota ' . $i;
                $pago->estado          = 'Pendiente';
                $pago->save();
            }

            if ($netoEntregar > 0) {
                $empresa = EmpresaCapital::query()->lockForUpdate()->latest('id')->first();
                if ($empresa) {
                    $empresa->capital_anterior       = (int) ($empresa->capital_asignado_total ?? 0);
                    $nuevoAsignado = (int) ($empresa->capital_asignado_total ?? 0) - (int) $netoEntregar;
                    $empresa->capital_asignado_total = max(0, $nuevoAsignado);
                    $empresa->save();

                    RegistroCapital::create([
                        'monto'       => -(int) $netoEntregar,
                        'user_id'     => auth()->id(),
                        'tipo_accion' => 'Retanqueo: desembolso neto $' . number_format($netoEntregar, 0, ',', '.') .
                                         ' (liquidación préstamo #' . $prestamo->id . ' por $' . number_format($saldoPendiente, 0, ',', '.') .
                                         ', nuevo #' . $nuevo->id . ')',
                    ]);
                }
            }

            return $nuevo;
        });

        return redirect()
            ->route('admin.pagos.create', $nuevoPrestamo->id)
            ->with('mensaje', 'Retanqueo aplicado. Desembolso neto $' . number_format($netoEntregar, 0, ',', '.') .
                              '. Capital asignado total actualizado.')
            ->with('icono', 'success');
    }

    /**
     * Saldo pendiente = monto_total - (cuotas confirmadas + abonos de cuotas pendientes cap al valor de la cuota)
     */
    private function calcularSaldoPendiente(Prestamo $prestamo): float
    {
        $pagos = Pago::where('prestamo_id', $prestamo->id)
            ->orderBy('fecha_pago')
            ->get(['id','estado','monto_pagado']);

        $totalConfirmado = (float) $pagos->where('estado','Confirmado')->sum('monto_pagado');

        // nro_cuota -> [estado, monto]
        $porNro = [];
        foreach ($pagos as $i => $p) {
            $porNro[$i+1] = ['estado' => $p->estado, 'monto' => (float)$p->monto_pagado];
        }

        // Abonos por cuota (cap al valor de la cuota y solo de cuotas pendientes)
        $abonosPorCuota = Abono::where('prestamo_id', $prestamo->id)
            ->selectRaw('nro_cuota, SUM(COALESCE(monto, monto_abonado, 0)) as total')
            ->groupBy('nro_cuota')
            ->pluck('total', 'nro_cuota');

        $abonosPendientes = 0.0;
        foreach ($abonosPorCuota as $nro => $total) {
            $estado = $porNro[$nro]['estado'] ?? 'Pendiente';
            $montoCuota = (float)($porNro[$nro]['monto'] ?? 0);
            if ($estado !== 'Confirmado') {
                $abonosPendientes += min((float)$total, $montoCuota);
            }
        }

        return max(0, (float)$prestamo->monto_total - ($totalConfirmado + $abonosPendientes));
    }
}
