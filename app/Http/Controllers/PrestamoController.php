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
     * Convierte un string con formato colombiano/español a float en pesos.
     * "1.234.567,89" -> 1234567.89
     */
    private function parsePesos(null|string $s): float
    {
        if ($s === null) return 0.0;
        $s = str_replace('.', '', $s);   // quita miles
        $s = str_replace(',', '.', $s);  // coma -> punto decimal
        return (float) $s;
    }

    public function store(Request $request)
    {
        $request->validate([
            'cliente_id'     => 'required',
            'monto_prestado' => 'required',   // lo parseamos nosotros
            'tasa_interes'   => 'required|numeric',
            'modalidad'      => 'required|in:Diario,Semanal,Quincenal,Mensual,Anual',
            'nro_cuotas'     => 'required|integer|min:1',
            'fecha_inicio'   => 'required|date',
            // 'monto_total' y 'monto_cuota' se ignoran si vienen del front
        ]);

        $usuario         = auth()->user();
        $ownerId         = (int) ($request->input('prestamista_id') ?: $usuario->id);

        // ✅ Parseo seguro y recálculo en backend
        $principal = $this->parsePesos($request->monto_prestado); // pesos
        $rate      = (float) $request->tasa_interes;               // 20 -> 0.20
        if ($rate > 1) { $rate = $rate / 100; }

        $cuotas = max((int) $request->nro_cuotas, 1);

        $montoTotal = round($principal * (1 + $rate), 2);
        $montoCuota = round($montoTotal / $cuotas, 2);

        // Asegura que exista registro de capital de empresa
        $empresaExiste = EmpresaCapital::latest('id')->first();
        if (!$empresaExiste) {
            return back()->withInput()
                ->with('mensaje', 'Debes crear primero el registro de capital de empresa.')
                ->with('icono', 'error');
        }

        try {
            DB::transaction(function () use ($request, $ownerId, $principal, $montoTotal, $montoCuota, $cuotas) {

                // Bloquear filas involucradas
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

                $saldoAsesores = (int) ($empresa->capital_asignado_total ?? 0);
                $montoSolicitadoInt = (int) round($principal, 0); // pesos enteros para movimientos

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

                // 2) Verifica capital disponible
                if ((int) $cp->monto_disponible < $montoSolicitadoInt) {
                    throw new \DomainException('__NO_CAPITAL__');
                }

                // 3) Crear préstamo (montos en pesos, sin *100)
                $prestamo = new Prestamo();
                $prestamo->cliente_id     = (int) $request->cliente_id;
                $prestamo->monto_prestado = $principal;       // DECIMAL(15,2) recomendado
                $prestamo->tasa_interes   = (float) $request->tasa_interes;
                $prestamo->modalidad      = $request->modalidad;
                $prestamo->nro_cuotas     = $cuotas;
                $prestamo->fecha_inicio   = $request->fecha_inicio;
                $prestamo->monto_total    = $montoTotal;      // SIN *100
                $prestamo->idusuario      = $ownerId;
                $prestamo->estado         = 'Pendiente';
                $prestamo->save();

                // 4) Cronograma (guardar cuota correcta en pesos)
                $fechaInicio = Carbon::parse($request->fecha_inicio);
                for ($i = 1; $i <= $cuotas; $i++) {
                    switch ($request->modalidad) {
                        case 'Diario':    $venc = $fechaInicio->copy()->addDays($i); break;
                        case 'Semanal':   $venc = $fechaInicio->copy()->addWeeks($i); break;
                        case 'Quincenal': $venc = $fechaInicio->copy()->addWeeks($i * 2 - 1); break;
                        case 'Mensual':   $venc = $fechaInicio->copy()->addMonths($i); break;
                        case 'Anual':     $venc = $fechaInicio->copy()->addYears($i); break;
                    }

                    $pago = new Pago();
                    $pago->prestamo_id     = $prestamo->id;
                    $pago->monto_pagado    = $montoCuota;          // SIN *100 ni (int)
                    $pago->fecha_pago      = $venc->toDateString();
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
        $montoTotal = round($principal * (1 + $rate), 2);

        $prestamo->update([
            'cliente_id'     => (int) $request->cliente_id,
            'monto_prestado' => $principal,
            'tasa_interes'   => (float) $request->tasa_interes,
            'modalidad'      => $request->modalidad,
            'nro_cuotas'     => $cuotas,
            'fecha_inicio'   => $request->fecha_inicio,
            'monto_total'    => $montoTotal,
        ]);

        // Nota: si permites cambiar nro_cuotas, deberías regenerar cronograma aquí (cuidando cuotas ya pagadas).

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
            $nuevo->fecha_inicio   = now()->toDateString();
            $nuevo->monto_total    = round($nuevo->monto_prestado * (1 + ($nuevo->tasa_interes / 100)), 2);
            $nuevo->estado         = 'Pendiente';
            $nuevo->save();

            $montoCuota = round($nuevo->monto_total / max(1, $nuevo->nro_cuotas), 2);
            $inicio     = Carbon::parse($nuevo->fecha_inicio);

            for ($i = 1; $i <= $nuevo->nro_cuotas; $i++) {
                switch ($nuevo->modalidad) {
                    case 'Diario':    $venc = $inicio->copy()->addDays($i); break;
                    case 'Semanal':   $venc = $inicio->copy()->addWeeks($i); break;
                    case 'Quincenal': $venc = $inicio->copy()->addWeeks($i * 2 - 1); break;
                    case 'Mensual':   $venc = $inicio->copy()->addMonths($i); break;
                    case 'Anual':     $venc = $inicio->copy()->addYears($i); break;
                }

                $pago = new Pago();
                $pago->prestamo_id     = $nuevo->id;
                $pago->monto_pagado    = $montoCuota;          // SIN *100
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
