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

   public function store(Request $request)
{
    // Normalizar montos (quitar separadores)
    $request->merge([
        'monto_prestado' => str_replace(['.', ','], '', $request->monto_prestado),
        'monto_total'    => str_replace(['.', ','], '', $request->monto_total),
        'monto_cuota'    => str_replace(['.', ','], '', $request->monto_cuota),
    ]);

    $request->validate([
        'cliente_id'     => 'required',
        'monto_prestado' => 'required|numeric|min:1',
        'tasa_interes'   => 'required|numeric',
        'modalidad'      => 'required|in:Diario,Semanal,Quincenal,Mensual,Anual',
        'nro_cuotas'     => 'required|integer|min:1',
        'fecha_inicio'   => 'required|date',
        'monto_total'    => 'required|numeric|min:1',
        'monto_cuota'    => 'required|numeric|min:1',
    ]);

    $usuario         = auth()->user();
    $ownerId         = (int) ($request->input('prestamista_id') ?: $usuario->id);
    $montoSolicitado = (int) $request->monto_prestado;

    // Asegura que exista registro de capital de empresa
    $empresaExiste = EmpresaCapital::latest('id')->first();
    if (!$empresaExiste) {
        return back()->withInput()
            ->with('mensaje', 'Debes crear primero el registro de capital de empresa.')
            ->with('icono', 'error');
    }

    try {
        DB::transaction(function () use ($request, $ownerId, $montoSolicitado) {

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

            // 1) Primero usar saldo de asesores (disminuye capital_asignado_total)
            if ($saldoAsesores > 0) {
                $aTransferir = min($montoSolicitado, $saldoAsesores);

                $empresa->capital_anterior       = (int) $saldoAsesores;          // guarda el anterior del campo que modificas
                $empresa->capital_asignado_total = $saldoAsesores - $aTransferir; // ↓
                $empresa->save();

                $cp->monto_disponible = (int) $cp->monto_disponible + $aTransferir; // ↑
                $cp->save();

                RegistroCapital::create([
                    'monto'       => -(int) $aTransferir,
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

            // 2) Si aun así no alcanza, lanza una excepción controlada
            if ((int) $cp->monto_disponible < $montoSolicitado) {
                throw new \DomainException('__NO_CAPITAL__');
            }

            // 3) Crear préstamo
            $prestamo = new Prestamo();
            $prestamo->cliente_id     = (int) $request->cliente_id;
            $prestamo->monto_prestado = $montoSolicitado;
            $prestamo->tasa_interes   = $request->tasa_interes;
            $prestamo->modalidad      = $request->modalidad;
            $prestamo->nro_cuotas     = (int) $request->nro_cuotas;
            $prestamo->fecha_inicio   = $request->fecha_inicio;
            $prestamo->monto_total    = (int) $request->monto_total;
            $prestamo->idusuario      = $ownerId;
            $prestamo->save();

            // 4) Cronograma
            for ($i = 1; $i <= (int) $request->nro_cuotas; $i++) {
                $pago = new Pago();
                $pago->prestamo_id  = $prestamo->id;
                $pago->monto_pagado = (int) $request->monto_cuota;

                $fechaInicio = \Carbon\Carbon::parse($request->fecha_inicio);
                switch ($request->modalidad) {
                    case 'Diario':    $fechaVencimiento = $fechaInicio->copy()->addDays($i); break;
                    case 'Semanal':   $fechaVencimiento = $fechaInicio->copy()->addWeeks($i); break;
                    case 'Quincenal': $fechaVencimiento = $fechaInicio->copy()->addWeeks($i * 2 - 1); break;
                    case 'Mensual':   $fechaVencimiento = $fechaInicio->copy()->addMonths($i); break;
                    case 'Anual':     $fechaVencimiento = $fechaInicio->copy()->addYears($i); break;
                }

                $pago->fecha_pago      = $fechaVencimiento;
                $pago->metodo_pago     = 'Efectivo';
                $pago->referencia_pago = 'Pago de la cuota ' . $i;
                $pago->estado          = 'Pendiente';
                $pago->save();
            }

            // 5) Descontar capital del prestamista (disponible y, si aplica, asignado)
            $asignadoAntes = (int) $cp->monto_asignado;
            $cp->monto_disponible = max(0, (int) $cp->monto_disponible - $montoSolicitado);
            $cp->monto_asignado   = max(0, $asignadoAntes - min($montoSolicitado, $asignadoAntes));
            $cp->save();

            if (class_exists(MovimientoCapitalPrestamista::class)) {
                MovimientoCapitalPrestamista::create([
                    'user_id'     => $ownerId,
                    'monto'       => $montoSolicitado,
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
        throw $e; // otras DomainException no esperadas
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
        // Normalizar montos
        $request->merge([
            'monto_prestado' => str_replace(['.', ','], '', $request->monto_prestado),
            'monto_total'    => str_replace(['.', ','], '', $request->monto_total),
        ]);

        $request->validate([
            'cliente_id'     => 'required',
            'monto_prestado' => 'required|numeric|min:1',
            'tasa_interes'   => 'required|numeric',
            'modalidad'      => 'required|in:Diario,Semanal,Quincenal,Mensual,Anual',
            'nro_cuotas'     => 'required|integer|min:1',
            'fecha_inicio'   => 'required|date',
            'monto_total'    => 'required|numeric|min:1',
        ]);

        $prestamo = Prestamo::findOrFail($id);

        if (auth()->user()->hasRole('PRESTAMISTA') && $prestamo->idusuario !== auth()->id()) {
            return redirect()->route('admin.prestamos.index')
                ->with('mensaje', 'No tienes permiso para actualizar este préstamo.')
                ->with('icono', 'error');
        }

        $prestamo->update([
            'cliente_id'     => (int) $request->cliente_id,
            'monto_prestado' => (int) $request->monto_prestado,
            'tasa_interes'   => $request->tasa_interes,
            'modalidad'      => $request->modalidad,
            'nro_cuotas'     => (int) $request->nro_cuotas,
            'fecha_inicio'   => $request->fecha_inicio,
            'monto_total'    => (int) $request->monto_total,
        ]);

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
