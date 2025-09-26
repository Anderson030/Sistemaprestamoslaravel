<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\EmpresaCapital;
use App\Models\RegistroCapital;
use App\Models\Abono; // Para calcular abonos por cuota
use App\Models\CapitalPrestamista;
use App\Models\MovimientoCapitalPrestamista;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class PagoController extends Controller
{
    public function index()
    {
        $clientes = Cliente::all();

        // Trae todos los pagos y calcula: monto_real_pagado y flag de pago parcial
        $pagos = Pago::orderBy('id', 'desc')->get();
        foreach ($pagos as $p) {
            // nro de cuota dentro del préstamo
            $nroCuota = $this->resolverNroCuota($p);

            // Total abonado previamente a esa cuota
            $abonosCuota = (int) Abono::where('prestamo_id', $p->prestamo_id)
                ->where('nro_cuota', $nroCuota)
                ->sum(DB::raw('COALESCE(monto, monto_abonado, 0)'));

            // Lo que realmente entró al confirmar la cuota
            // (si aún está pendiente, lo dejamos en 0 para que la vista decida)
            $p->monto_real_pagado = $p->estado === 'Confirmado'
                ? max(0, (int) $p->monto_pagado - $abonosCuota)
                : 0;

            // Marca si esta cuota tuvo abonos antes del pago
            $p->es_pago_parcial = $abonosCuota > 0;
            $p->nro_cuota_calc  = $nroCuota; // por si lo quieres mostrar en la tabla
        }

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
     * - Suma el IMPORTE NETO al saldo de asesores (empresa.capital_asignado_total).
     * - Acredita ese NETO al capital del PRESTAMISTA dueño del préstamo (monto_disponible).
     * - Registra en registro_capital y (opcional) movimiento del prestamista.
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
                $empresa->capital_asignado_total = max(0, (int) ($empresa->capital_asignado_total ?? 0)) + $neto;
                $empresa->save();
            }

            // 3.b) Acreditar NETO al PRESTAMISTA dueño del préstamo (para que pueda volver a prestar)
            $prestamo = Prestamo::find($pago->prestamo_id);
            $ownerId  = $prestamo->idusuario ?? auth()->id();

            if ($neto > 0 && $ownerId) {
                $cp = CapitalPrestamista::query()
                    ->lockForUpdate()
                    ->firstOrCreate(['user_id' => $ownerId], [
                        'monto_asignado' => 0,
                        'monto_disponible' => 0,
                    ]);

                $cp->monto_disponible = (int) $cp->monto_disponible + (int) $neto;
                $cp->save();

                // (Opcional) Log del prestamista
                if (class_exists(MovimientoCapitalPrestamista::class)) {
                    MovimientoCapitalPrestamista::create([
                        'user_id'     => $ownerId,
                        'monto'       => (int) $neto,
                        'descripcion' => 'Cobro de cuota (neto) préstamo #' . $pago->prestamo_id,
                    ]);
                }
            }

            // 4) Log de movimiento general
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
            ->with('mensaje', 'Pago registrado (neto sumado a saldo de asesores y al capital del prestamista).')
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
     * - Resta ese NETO del capital del PRESTAMISTA dueño del préstamo.
     * - Deja el pago como Pendiente y registra trazas.
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

                // Empresa (saldo asesores)
                $empresa = EmpresaCapital::query()->lockForUpdate()->latest('id')->first();
                if ($empresa && $neto > 0) {
                    $empresa->capital_anterior       = (int) ($empresa->capital_asignado_total ?? 0);
                    $empresa->capital_asignado_total = max(0, (int) ($empresa->capital_asignado_total ?? 0) - $neto);
                    $empresa->save();
                }

                // Prestamista dueño del préstamo
                $prestamo = Prestamo::find($pago->prestamo_id);
                $ownerId  = $prestamo->idusuario ?? auth()->id();

                if ($neto > 0 && $ownerId) {
                    $cp = CapitalPrestamista::query()
                        ->lockForUpdate()
                        ->firstOrCreate(['user_id' => $ownerId], [
                            'monto_asignado' => 0,
                            'monto_disponible' => 0,
                        ]);

                    $cp->monto_disponible = max(0, (int) $cp->monto_disponible - (int) $neto);
                    $cp->save();

                    if (class_exists(MovimientoCapitalPrestamista::class)) {
                        MovimientoCapitalPrestamista::create([
                            'user_id'     => $ownerId,
                            'monto'       => -(int) $neto,
                            'descripcion' => 'Reverso de cobro (neto) préstamo #' . $pago->prestamo_id,
                        ]);
                    }
                }

                RegistroCapital::create([
                    'monto'       => (int) ($neto * -1),
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
