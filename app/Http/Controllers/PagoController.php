<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\EmpresaCapital;
use App\Models\RegistroCapital;
use App\Models\Abono; // Para calcular abonos por cuota
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class PagoController extends Controller
{
    public function index()
    {
        $clientes = Cliente::all();
        $pagos = Pago::all();
        return view('admin.pagos.index', compact('pagos', 'clientes'));
    }

    public function cargar_prestamos_cliente($id)
    {
        $cliente = Cliente::find($id);
        $prestamos = Prestamo::where('cliente_id', $cliente->id)->get();
        return view('admin.pagos.cargar_prestamos_cliente', compact('cliente', 'prestamos'));
    }

    public function create($id)
    {
        // Préstamo + cronograma de pagos
        $prestamo = Prestamo::with('cliente')->findOrFail($id);
        $pagos = Pago::where('prestamo_id', $id)
            ->orderBy('fecha_pago')
            ->get();

        // Total de abonos acumulados por nro_cuota (soporta monto y monto_abonado)
        $abonosPorCuota = Abono::where('prestamo_id', $id)
            ->selectRaw('nro_cuota, SUM(COALESCE(monto, monto_abonado, 0)) as total')
            ->groupBy('nro_cuota')
            ->pluck('total', 'nro_cuota');

        // ==================== SALDO SIN DOBLE CONTEO ====================
        // 1) Suma de cuotas confirmadas
        $totalConfirmado = $pagos->where('estado', 'Confirmado')->sum('monto_pagado');

        // 2) Mapa nro_cuota => [estado, monto]
        $porNro = [];
        foreach ($pagos as $i => $p) {
            $porNro[$i + 1] = ['estado' => $p->estado, 'monto' => (float) $p->monto_pagado];
        }

        // 3) Sumar SOLO abonos de cuotas PENDIENTES (cap al valor de la cuota)
        $abonosPendientes = 0.0;
        foreach ($abonosPorCuota as $nro => $total) {
            $estado = $porNro[$nro]['estado'] ?? 'Pendiente';
            $montoCuota = (float) ($porNro[$nro]['monto'] ?? 0);
            if ($estado !== 'Confirmado') {
                $abonosPendientes += min((float) $total, $montoCuota);
            }
        }

        // 4) Si el modelo tiene accesor saldo_actual úsalo; si no, usa el fallback calculado arriba
        $saldoCalc = max(0, (float) $prestamo->monto_total - ($totalConfirmado + $abonosPendientes));
        $saldoActual = property_exists($prestamo, 'saldo_actual') ? ($prestamo->saldo_actual ?? $saldoCalc) : $saldoCalc;
        // =================================================================

        // Enviar ambas claves por compatibilidad con la vista
        return view(
            'admin.pagos.create',
            compact('prestamo', 'pagos', 'abonosPorCuota') + [
                'saldoActual'  => $saldoActual,
                'saldo_actual' => $saldoActual,
            ]
        );
    }

    public function cargar_datos($id)
    {
        $datosCliente = Cliente::find($id);
        $clientes = Cliente::all();
        return view('admin.pagos.cargar_datos', compact('datosCliente', 'clientes'));
    }

    /**
     * Confirma un pago (por id de pago).
     * - Marca la cuota como Confirmado.
     * - Suma el IMPORTE NETO al "Saldo disponible total de los asesores"
     *   (empresa.capital_asignado_total) => cuota - abonos de esa cuota.
     * - Registra en registro_capital.
     */
    public function store($id)
    {
        $pago = Pago::findOrFail($id);

        // Si ya estaba confirmado, salida limpia
        if ($pago->estado === 'Confirmado') {
            return redirect()->back()
                ->with('mensaje', 'El pago ya estaba confirmado.')
                ->with('icono', 'info');
        }

        DB::transaction(function () use ($pago) {
            // 1) Confirmar pago
            $pago->estado = 'Confirmado';
            $pago->fecha_cancelado = date('Y-m-d');
            $pago->save();

            // 2) Calcular NETO = cuota - abonos de esta cuota
            $nroCuota = $this->resolverNroCuota($pago);

            $abonosCuota = (int) Abono::where('prestamo_id', $pago->prestamo_id)
                ->where('nro_cuota', $nroCuota)
                ->sum(DB::raw('COALESCE(monto, monto_abonado, 0)'));

            $montoCuota = (int) $pago->monto_pagado;
            $neto = max(0, $montoCuota - $abonosCuota);

            // 3) Acumular SOLO el NETO en SALDO DE ASESORES (no a caja)
            $empresa = EmpresaCapital::query()->lockForUpdate()->latest('id')->first();
            if ($empresa && $neto > 0) {
                $empresa->capital_anterior       = (int) ($empresa->capital_asignado_total ?? 0);
                $empresa->capital_asignado_total = (int) ($empresa->capital_asignado_total ?? 0) + $neto;
                $empresa->save();
            }

            // 4) Log de movimiento
            RegistroCapital::create([
                'monto'       => (int) $neto,
                'user_id'     => auth()->id(),
                'tipo_accion' => 'Ingreso recibido por asesor (cuota neta) → Saldo asesores (préstamo #' . $pago->prestamo_id . ', cuota ' . $nroCuota . ')',
            ]);

            // 5) ¿Fue la última cuota?
            $faltantes = Pago::where('prestamo_id', $pago->prestamo_id)
                ->where('estado', 'Pendiente')
                ->count();

            if ($faltantes === 0) {
                $prestamo = Prestamo::find($pago->prestamo_id);
                if ($prestamo) {
                    $prestamo->estado = 'Cancelado';
                    $prestamo->save();
                }
            }
        });

        return redirect()->back()
            ->with('mensaje', 'Pago registrado (acumulado en Saldo de asesores por el neto).')
            ->with('icono', 'success');
    }

    public function comprobantedepago($id)
    {
        $pago = Pago::find($id);
        $prestamo = Prestamo::where('id', $pago->prestamo_id)->first();
        $cliente = Cliente::where('id', $prestamo->cliente_id)->first();
        $fecha_cancelado = $pago->fecha_cancelado;
        $timestamp = strtotime($fecha_cancelado);
        $dia = date('j', $timestamp);
        $mes = date('F', $timestamp);
        $ano = date('Y', $timestamp);

        $meses = [
            'January' => 'enero',
            'February' => 'febrero',
            'March' => 'marzo',
            'April' => 'abril',
            'May' => 'mayo',
            'June' => 'junio',
            'July' => 'julio',
            'August' => 'agosto',
            'September' => 'septiembre',
            'October' => 'octubre',
            'November' => 'noviembre',
            'December' => 'diciembre',
        ];

        $mes_espanol = $meses[$mes];
        $fecha_literal = $dia . " de " . $mes_espanol . " de " . $ano;

        $configuracion = Configuracion::latest()->first();
        $pdf = Pdf::loadView('admin.pagos.comprobantedepago', compact('pago', 'configuracion', 'fecha_literal', 'prestamo', 'cliente'));
        return $pdf->stream();
    }

    public function show($id)
    {
        $pago = Pago::find($id);
        $prestamo = Prestamo::where('id', $pago->prestamo_id)->first();
        $cliente = Cliente::where('id', $prestamo->cliente_id)->first();

        return view('admin.pagos.show', compact('pago', 'prestamo', 'cliente'));
    }

    public function edit(Pago $pago) { /* ... */ }

    public function update(Request $request, Pago $pago) { /* ... */ }

    /**
     * Revierte la confirmación de un pago:
     * - Resta del SALDO DE ASESORES el MISMO NETO que se sumó.
     * - Deja el pago como Pendiente.
     * - Deja traza en registro_capital.
     */
    public function destroy($id)
    {
        $pago = Pago::findOrFail($id);

        DB::transaction(function () use ($pago) {
            // Si estaba confirmado, revertir saldo de asesores por el NETO
            if ($pago->estado === 'Confirmado') {

                $nroCuota = $this->resolverNroCuota($pago);
                $abonosCuota = (int) Abono::where('prestamo_id', $pago->prestamo_id)
                    ->where('nro_cuota', $nroCuota)
                    ->sum(DB::raw('COALESCE(monto, monto_abonado, 0)'));

                $montoCuota = (int) $pago->monto_pagado;
                $neto = max(0, $montoCuota - $abonosCuota);

                $empresa = EmpresaCapital::query()->lockForUpdate()->latest('id')->first();
                if ($empresa && $neto > 0) {
                    $empresa->capital_anterior       = (int) ($empresa->capital_asignado_total ?? 0);
                    $empresa->capital_asignado_total = (int) ($empresa->capital_asignado_total ?? 0) - $neto;
                    if ($empresa->capital_asignado_total < 0) {
                        $empresa->capital_asignado_total = 0; // por seguridad
                    }
                    $empresa->save();
                }

                RegistroCapital::create([
                    'monto'       => (int) $neto * -1,
                    'user_id'     => auth()->id(),
                    'tipo_accion' => 'Reverso de cuota (neto) desde saldo asesores (préstamo #' . $pago->prestamo_id . ', cuota ' . $nroCuota . ')',
                ]);
            }

            // Revertir estado del pago
            $pago->fecha_cancelado = null;
            $pago->estado = 'Pendiente';
            $pago->save();

            // Si el préstamo quedó con cuotas pendientes, marcarlo Pendiente (por consistencia)
            $faltantes = Pago::where('prestamo_id', $pago->prestamo_id)
                ->where('estado', 'Pendiente')
                ->count();

            if ($faltantes > 0) {
                $prestamo = Prestamo::find($pago->prestamo_id);
                if ($prestamo) {
                    $prestamo->estado = 'Pendiente';
                    $prestamo->save();
                }
            }
        });

        return redirect()->route('admin.pagos.index')
            ->with('mensaje', 'Se eliminó el pago del cliente correctamente.')
            ->with('icono', 'success');
    }

    /**
     * Calcula el número de cuota de un Pago dentro de su préstamo,
     * sin tener columna en DB. Cuenta pagos anteriores por fecha e id.
     */
    private function resolverNroCuota(Pago $pago): int
    {
        return (int) Pago::where('prestamo_id', $pago->prestamo_id)
            ->where(function ($q) use ($pago) {
                $q->where('fecha_pago', '<', $pago->fecha_pago)
                  ->orWhere(function ($qq) use ($pago) {
                      $qq->where('fecha_pago', $pago->fecha_pago)
                         ->where('id', '<=', $pago->id);
                  });
            })
            ->count();
    }
}
