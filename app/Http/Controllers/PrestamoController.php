<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\CapitalPrestamista;
use App\Models\MovimientoCapitalPrestamista;
use App\Models\Abono; // ← para sumar abonos
use App\Models\EmpresaCapital;      // ← capital empresa (tablero)
use App\Models\RegistroCapital;     // ← log de movimientos de capital
use Carbon\Carbon;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB; // ← para transacciones

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
        if (auth()->user()->hasRole('PRESTAMISTA')) {
            $clientes = Cliente::where('idusuario', auth()->id())->get();
        } else {
            $clientes = Cliente::all();
        }

        return view('admin.prestamos.create', compact('clientes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'cliente_id'     => 'required',
            'monto_prestado' => 'required',
            'tasa_interes'   => 'required',
            'modalidad'      => 'required',
            'nro_cuotas'     => 'required',
            'fecha_inicio'   => 'required',
            'monto_total'    => 'required',
            'monto_cuota'    => 'required',
        ]);

        $prestamo = new Prestamo();
        $prestamo->cliente_id     = $request->cliente_id;
        $prestamo->monto_prestado = $request->monto_prestado;
        $prestamo->tasa_interes   = $request->tasa_interes;
        $prestamo->modalidad      = $request->modalidad;
        $prestamo->nro_cuotas     = $request->nro_cuotas;
        $prestamo->fecha_inicio   = $request->fecha_inicio;
        $prestamo->monto_total    = $request->monto_total;
        $prestamo->idusuario      = auth()->id();
        $prestamo->save();

        for ($i = 1; $i <= $request->nro_cuotas; $i++) {
            $pago = new Pago();
            $pago->prestamo_id  = $prestamo->id;
            $pago->monto_pagado = $request->monto_cuota;

            $fechaInicio = Carbon::parse($request->fecha_inicio);
            switch ($request->modalidad) {
                case 'Diario':    $fechaVencimiento = $fechaInicio->copy()->addDays($i); break;
                case 'Semanal':   $fechaVencimiento = $fechaInicio->copy()->addWeeks($i); break;
                case 'Quincenal': $fechaVencimiento = $fechaInicio->copy()->addWeeks($i * 2 - 1); break;
                case 'Mensual':   $fechaVencimiento = $fechaInicio->copy()->addMonths($i); break;
                case 'Anual':     $fechaVencimiento = $fechaInicio->copy()->addYears($i); break;
            }

            $pago->fecha_pago      = $fechaVencimiento;
            $pago->metodo_pago     = "Efectivo";
            $pago->referencia_pago = "Pago de la cuota " . $i;
            $pago->estado          = "Pendiente";
            $pago->save();
        }

        $usuario = auth()->user();

        if ($usuario->hasRole(['ADMINISTRADOR', 'SUPERVISOR', 'PRESTAMISTA'])) {
            $capitalPrestamista = CapitalPrestamista::firstOrCreate(['user_id' => $usuario->id]);

            if ($capitalPrestamista->monto_disponible < $request->monto_prestado) {
                session()->flash('mensaje', '⚠️ No tienes suficiente capital disponible, pero el préstamo fue registrado correctamente.');
                session()->flash('icono', 'warning');
            }

            $capitalPrestamista->monto_disponible -= $request->monto_prestado;
            $capitalPrestamista->monto_asignado   -= $request->monto_prestado;
            $capitalPrestamista->save();

            MovimientoCapitalPrestamista::create([
                'user_id'     => $usuario->id,
                'monto'       => $request->monto_prestado,
                'descripcion' => 'Préstamo realizado ID ' . $prestamo->id,
            ]);
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
            'tasa_interes'   => 'required',
            'modalidad'      => 'required',
            'nro_cuotas'     => 'required',
            'fecha_inicio'   => 'required',
            'monto_total'    => 'required',
        ]);

        $prestamo = Prestamo::findOrFail($id);

        if (auth()->user()->hasRole('PRESTAMISTA') && $prestamo->idusuario !== auth()->id()) {
            return redirect()->route('admin.prestamos.index')
                ->with('mensaje', 'No tienes permiso para actualizar este préstamo.')
                ->with('icono', 'error');
        }

        $prestamo->update([
            'cliente_id'     => $request->cliente_id,
            'monto_prestado' => $request->monto_prestado,
            'tasa_interes'   => $request->tasa_interes,
            'modalidad'      => $request->modalidad,
            'nro_cuotas'     => $request->nro_cuotas,
            'fecha_inicio'   => $request->fecha_inicio,
            'monto_total'    => $request->monto_total,
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
     * - Calcula saldo pendiente con el mismo criterio de la pantalla:
     *   cuotas confirmadas + abonos de cuotas pendientes (tope al valor de la cuota).
     * - Valida que el principal nuevo cubra ese saldo.
     * - Liquida el préstamo actual y crea uno nuevo con el cronograma.
     * - Descuenta del CAPITAL ASIGNADO TOTAL el NETO entregado al cliente
     *   (principal_nuevo - saldo_pendiente) y registra el movimiento.
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

        // 1) Saldo pendiente (consistente con UI)
        $saldoPendiente = $this->calcularSaldoPendiente($prestamo);

        if ((float) $data['principal_nuevo'] < $saldoPendiente) {
            return back()
                ->with('mensaje', 'El principal del retanqueo no cubre el saldo pendiente.')
                ->with('icono', 'error');
        }

        // 2) Neto a entregar al cliente
        $netoEntregar = max(0, (float)$data['principal_nuevo'] - (float)$saldoPendiente);

        $nuevoPrestamo = DB::transaction(function () use ($prestamo, $data, $saldoPendiente, $netoEntregar) {

            // 2.1) Liquidar préstamo actual (confirmar cuotas pendientes)
            Pago::where('prestamo_id', $prestamo->id)
                ->where('estado', '!=', 'Confirmado')
                ->update([
                    'estado'          => 'Confirmado',
                    'fecha_cancelado' => now()->toDateString()
                ]);

            $prestamo->estado = 'Cancelado';
            $prestamo->save();

            // 2.2) Crear nuevo préstamo
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

            // 2.3) Cronograma del nuevo préstamo
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

            // 2.4) Movimiento de CAPITAL ASIGNADO TOTAL por el desembolso neto
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

        // Abonos por cuota
        $abonosPorCuota = Abono::where('prestamo_id', $prestamo->id)
            ->selectRaw('nro_cuota, SUM(COALESCE(monto, monto_abonado, 0)) as total')
            ->groupBy('nro_cuota')
            ->pluck('total', 'nro_cuota');

        // Sumar SOLO abonos de cuotas PENDIENTES (tope al valor de la cuota)
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
