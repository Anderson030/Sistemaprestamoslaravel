<?php

namespace App\Http\Controllers;

use App\Models\Prestamo;
use App\Models\GastoDiario;
use App\Models\RegistroCapital;
use App\Models\PagoParcialAuditoria;
use App\Models\Abono;
use App\Models\EmpresaCapital;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class AuditoriaController extends Controller
{
    public function index(Request $request)
    {
        // ----------------------------
        // 1) Fechas (zona local Bogotá) y derivados
        // ----------------------------
        $desdeIn = $request->input('desde');
        $hastaIn = $request->input('hasta');

        $desdeLocal = $desdeIn
            ? Carbon::parse($desdeIn, 'America/Bogota')->startOfDay()
            : now('America/Bogota')->subDays(30)->startOfDay();

        $hastaLocal = $hastaIn
            ? Carbon::parse($hastaIn, 'America/Bogota')->endOfDay()
            : now('America/Bogota')->endOfDay();

        // Para DATETIME almacenados en UTC
        $startUtc = $desdeLocal->clone()->timezone('UTC');
        $endUtc   = $hastaLocal->clone()->timezone('UTC');

        // Para columnas DATE
        $desde = $desdeLocal->toDateString();
        $hasta = $hastaLocal->toDateString();

        // Proyección de DATETIME(UTC) -> día local
        $TZ_FROM   = '+00:00';
        $TZ_TO     = '-05:00'; // America/Bogota
        $exprLocal = fn($col) => "DATE(CONVERT_TZ($col,'$TZ_FROM','$TZ_TO'))";

        // ============================
        // Helper: expresiones/columnas según esquema
        // ============================

        // 1) Fecha de cobro contable (DATE)
        $fechaCobroExpr = Schema::hasColumn('pagos', 'fecha_contable')
            ? 'DATE(p.fecha_contable)'
            : "DATE(COALESCE(p.fecha_cancelado, p.fecha_pago))";

        // 2) Columna monetaria en pagos
        if (Schema::hasColumn('pagos', 'cuota_pagada')) {
            $colMontoPago = 'p.cuota_pagada';
        } elseif (Schema::hasColumn('pagos', 'monto_pagado')) {
            $colMontoPago = 'p.monto_pagado';
        } elseif (Schema::hasColumn('pagos', 'monto')) {
            $colMontoPago = 'p.monto';
        } else {
            $colMontoPago = '0';
        }

        // 3) fecha_inicio de prestamos es DATE → no convertir TZ
        $fechaInicioDiaExpr = 'DATE(fecha_inicio)';

        // ----------------------------
        // 2) Calendario base (días con actividad)
        // ----------------------------
        $baseDias = DB::query()->fromSub(
            DB::query()->fromSub(function ($q) use ($exprLocal, $startUtc, $endUtc, $desde, $hasta, $fechaCobroExpr, $fechaInicioDiaExpr) {
                $q->fromRaw("(
                    -- Prestamos.fecha_inicio (DATE)
                    SELECT {$fechaInicioDiaExpr} AS dia
                    FROM prestamos
                    WHERE DATE(fecha_inicio) BETWEEN ? AND ?

                    UNION
                    -- Pagos (DATE) -> usa fecha_contable si existe; si no, coalesce(fecha_cancelado, fecha_pago)
                    SELECT {$fechaCobroExpr} AS dia
                    FROM pagos p
                    WHERE p.estado = 'Confirmado'
                      AND {$fechaCobroExpr} BETWEEN ? AND ?

                    UNION
                    -- Gastos_diarios.fecha (DATE)
                    SELECT DATE(fecha) AS dia
                    FROM gastos_diarios
                    WHERE DATE(fecha) BETWEEN ? AND ?

                    UNION
                    -- Registros_capital.created_at (DATETIME/UTC)
                    SELECT {$exprLocal('created_at')} AS dia
                    FROM registros_capital
                    WHERE created_at BETWEEN ? AND ?

                    UNION
                    -- PagoParcialAuditoria.fecha (DATE)
                    SELECT DATE(fecha) AS dia
                    FROM pagoparcialauditoria
                    WHERE DATE(fecha) BETWEEN ? AND ?

                    UNION
                    -- Abonos: si hay fecha_pago (DATE) úsala; si no, created_at (DATETIME/UTC)
                    SELECT DATE(COALESCE(fecha_pago, CONVERT_TZ(created_at,'+00:00','-05:00'))) AS dia
                    FROM abonos
                    WHERE (
                        (fecha_pago IS NOT NULL AND DATE(fecha_pago) BETWEEN ? AND ?)
                        OR (fecha_pago IS NULL AND created_at BETWEEN ? AND ?)
                    )
                ) t", [
                    // prestamos (DATE)
                    $desde, $hasta,
                    // pagos (DATE)
                    $desde, $hasta,
                    // gastos_diarios (DATE)
                    $desde, $hasta,
                    // registros_capital (UTC)
                    $startUtc, $endUtc,
                    // pago parcial auditoria (DATE)
                    $desde, $hasta,
                    // abonos fecha_pago (DATE)
                    $desde, $hasta,
                    // abonos created_at (UTC)
                    $startUtc, $endUtc,
                ]);
            }, 'u')->selectRaw('dia')->groupBy('dia')
        , 'cal');

        // ----------------------------
        // 3) Métricas por día
        // ----------------------------
        $usaDesembolso   = Schema::hasColumn('prestamos', 'desembolso_neto');
        $sumPrestadoExpr = $usaDesembolso
            ? 'SUM(COALESCE(desembolso_neto, monto_prestado))'
            : 'SUM(monto_prestado)';

        // Prestado (se agrupa por día de fecha_inicio - DATE)
        $prestado = Prestamo::query()
            ->selectRaw("{$fechaInicioDiaExpr} AS dia, {$sumPrestadoExpr} AS total_prestado, COUNT(*) AS nro_prestamos")
            ->whereBetween(DB::raw('DATE(fecha_inicio)'), [$desde, $hasta])
            ->groupBy('dia');

        // ========= COBRADO NETO (pago - abonos del mismo préstamo y día) =========

        // A) Abonos por préstamo/día (para neteo)
        $abonosPrestamoDia = DB::table('abonos as a')
            ->selectRaw("
                a.prestamo_id,
                DATE(COALESCE(a.fecha_pago, CONVERT_TZ(a.created_at,'+00:00','-05:00'))) AS dia,
                SUM(a.monto) AS abonado_dia
            ")
            ->where(function($q) use ($desde, $hasta, $startUtc, $endUtc){
                $q->where(function($qq) use ($desde, $hasta){
                    $qq->whereNotNull('a.fecha_pago')
                       ->whereBetween(DB::raw('DATE(a.fecha_pago)'), [$desde, $hasta]);
                })->orWhere(function($qq) use ($startUtc, $endUtc){
                    $qq->whereNull('a.fecha_pago')
                       ->whereBetween('a.created_at', [$startUtc, $endUtc]);
                });
            })
            ->groupBy('a.prestamo_id','dia');

        // B) Pagos confirmados por préstamo/día (fecha = fecha_contable o fallback)
        $pagosPrestamoDia = DB::table('pagos as p')
            ->selectRaw("
                p.prestamo_id,
                {$fechaCobroExpr} AS dia,
                SUM(COALESCE({$colMontoPago},0)) AS pagado_dia,
                COUNT(*) AS nro_pagos_dia
            ")
            ->where(function($q){
                $q->where('p.estado', 'Confirmado')
                  ->orWhereNotNull('p.fecha_cancelado');
            })
            ->when(
                Schema::hasColumn('pagos', 'es_retanqueo'),
                fn($q) => $q->where(function ($qq) {
                    $qq->whereNull('p.es_retanqueo')
                       ->orWhere('p.es_retanqueo', false)
                       ->orWhere('p.es_retanqueo', 0);
                })
            )
            ->whereBetween(DB::raw($fechaCobroExpr), [$desde, $hasta])
            ->groupBy('p.prestamo_id','dia');

        // C) Neteo por préstamo/día y agrupación por día
        $cobrado = DB::query()
            ->fromSub($pagosPrestamoDia, 'p')
            ->leftJoinSub($abonosPrestamoDia, 'ab', function($j){
                $j->on('ab.prestamo_id','=','p.prestamo_id')
                  ->on('ab.dia','=','p.dia');
            })
            ->selectRaw('
                p.dia AS dia,
                SUM(GREATEST(p.pagado_dia - COALESCE(ab.abonado_dia,0),0)) AS total_cobrado,
                SUM(p.nro_pagos_dia) AS nro_pagos
            ')
            ->groupBy('p.dia');

        // 3.2 Gastos del día (“último valor manda”)
        $gastosUltimoId = DB::table('gastos_diarios')
            ->selectRaw('DATE(fecha) AS dia, MAX(id) AS gasto_id')
            ->whereBetween(DB::raw('DATE(fecha)'), [$desde, $hasta])
            ->groupBy('dia');

        $gastos = DB::query()
            ->fromSub($gastosUltimoId, 'g')
            ->join('gastos_diarios as gd', 'gd.id', '=', 'g.gasto_id')
            ->selectRaw("g.dia AS dia, gd.monto AS gastos_dia, COALESCE(NULLIF(gd.descripcion,''), '') AS descripciones");

        // 3.3 Asignado neto (registros_capital)
        $asignado = RegistroCapital::query()
            ->selectRaw("
                {$exprLocal('created_at')} AS dia,
                SUM(
                    CASE
                        WHEN tipo_accion LIKE 'Capital asignado a prestamista:%' THEN monto
                        WHEN tipo_accion LIKE 'Capital devuelto por prestamista:%' THEN -monto
                        WHEN tipo_accion LIKE 'Ingreso recibido por asesor (cuota neta)%' THEN monto
                        WHEN tipo_accion LIKE 'Traslado de abonos a Caja%' THEN -monto
                        ELSE 0
                    END
                ) AS asignado_dia
            ")
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->groupBy('dia');

        // 3.4 Pagos parciales (manuales) y Abonos
        $parciales = PagoParcialAuditoria::query()
            ->selectRaw("DATE(fecha) AS dia, COALESCE(SUM(monto),0) AS pagos_parciales")
            ->whereBetween(DB::raw('DATE(fecha)'), [$desde, $hasta])
            ->groupBy('dia');

        $abonos = Abono::query()
            ->selectRaw("
                DATE(COALESCE(fecha_pago, CONVERT_TZ(created_at,'+00:00','-05:00'))) AS dia,
                COALESCE(SUM(monto),0) AS abonos_dia
            ")
            ->where(function($q) use ($desde, $hasta, $startUtc, $endUtc){
                $q->where(function($qq) use ($desde, $hasta){
                    $qq->whereNotNull('fecha_pago')
                       ->whereBetween(DB::raw('DATE(fecha_pago)'), [$desde, $hasta]);
                })->orWhere(function($qq) use ($startUtc, $endUtc){
                    $qq->whereNull('fecha_pago')
                       ->whereBetween('created_at', [$startUtc, $endUtc]);
                });
            })
            ->groupBy('dia');

        // ----------------------------
        // 4) Unión al calendario
        // ----------------------------
        $rows = DB::query()
            ->fromSub($baseDias, 'cal')
            ->leftJoinSub($prestado,  'pr',   'pr.dia',   '=', 'cal.dia')
            ->leftJoinSub($cobrado,   'co',   'co.dia',   '=', 'cal.dia')
            ->leftJoinSub($gastos,    'ga',   'ga.dia',   '=', 'cal.dia')
            ->leftJoinSub($asignado,  'asig', 'asig.dia', '=', 'cal.dia')
            ->leftJoinSub($parciales, 'pp',   'pp.dia',   '=', 'cal.dia')
            ->leftJoinSub($abonos,    'ab',   'ab.dia',   '=', 'cal.dia')
            ->selectRaw("
                cal.dia,
                COALESCE(pr.total_prestado,0)      AS total_prestado,
                COALESCE(co.total_cobrado,0)       AS total_cobrado,
                COALESCE(ga.gastos_dia,0)          AS gastos_dia,
                COALESCE(asig.asignado_dia,0)      AS asignado_dia,
                (COALESCE(pp.pagos_parciales,0) + COALESCE(ab.abonos_dia,0)) AS pagos_parciales,
                COALESCE(pr.nro_prestamos,0)       AS nro_prestamos,
                COALESCE(co.nro_pagos,0)           AS nro_pagos,
                COALESCE(ga.descripciones,'')      AS descripciones
            ")
            ->orderBy('cal.dia','desc')
            ->get();

        // ----------------------------
        // 5) Balance por fila
        // ----------------------------
        $auditoria = $rows->map(function ($r) {
            $asignado  = (float)$r->asignado_dia;
            $cobrado   = (float)$r->total_cobrado;
            $parciales = (float)$r->pagos_parciales; // (abonos + manuales)
            $gastos    = (float)$r->gastos_dia;
            $prestado  = (float)$r->total_prestado;

            // Entradas - Salidas
            $r->balance = ($asignado + $cobrado + $parciales) - ($gastos + $prestado);
            $r->caja    = $cobrado - $prestado - $gastos;
            $r->descripciones = trim((string)$r->descripciones) !== '' ? $r->descripciones : '-';
            return $r;
        })->values();

        // ----------------------------
        // 6) KPIs cabecera
        // ----------------------------
        $asignadoTotalRango = (int) RegistroCapital::query()
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->where(function ($q) {
                $q->where('tipo_accion', 'LIKE', 'Capital asignado a prestamista:%')
                  ->orWhere('tipo_accion', 'LIKE', 'Capital devuelto por prestamista:%');
            })
            ->selectRaw("COALESCE(SUM(CASE
                        WHEN tipo_accion LIKE 'Capital asignado a prestamista:%' THEN monto
                        WHEN tipo_accion LIKE 'Capital devuelto por prestamista:%' THEN -monto
                        ELSE 0 END),0) AS neto")
            ->value('neto');

        $hastaUtcEod = $hastaLocal->clone()->timezone('UTC');

        $asignadoHistoricoHastaCorte = (int) RegistroCapital::query()
            ->where('created_at', '<=', $hastaUtcEod)
            ->where(function ($q) {
                $q->where('tipo_accion', 'LIKE', 'Capital asignado a prestamista:%')
                  ->orWhere('tipo_accion', 'LIKE', 'Capital devuelto por prestamista:%');
            })
            ->selectRaw("COALESCE(SUM(CASE
                        WHEN tipo_accion LIKE 'Capital asignado a prestamista:%' THEN monto
                        WHEN tipo_accion LIKE 'Capital devuelto por prestamista:%' THEN -monto
                        ELSE 0 END),0) AS neto")
            ->value('neto');

        $prestadoHistoricoHastaCorte = (int) DB::table('prestamos')
            ->where('fecha_inicio', '<=', $hastaUtcEod)
            ->selectRaw(($usaDesembolso
                ? 'COALESCE(SUM(COALESCE(desembolso_neto, monto_prestado)),0)'
                : 'COALESCE(SUM(monto_prestado),0)'
            ).' AS tot')
            ->value('tot');

        $asignadoRestanteCorte = max(0, $asignadoHistoricoHastaCorte - $prestadoHistoricoHastaCorte);

        return view('admin.auditorias.index', [
            'auditorias'            => $auditoria,
            'desde'                 => $desde,
            'hasta'                 => $hasta,
            'asignadoTotalRango'    => $asignadoTotalRango,
            'asignadoRestanteCorte' => $asignadoRestanteCorte,
        ]);
    }

    // ========= Formularios =========

    public function storeGasto(Request $request)
    {
        $data = $request->validate([
            'fecha'       => ['required','date'],
            'monto'       => ['nullable','numeric','min:0'],
            'descripcion' => ['nullable','string','max:255'],
        ]);

        $nuevoMonto = (int) ($data['monto'] ?? 0);
        $desc       = $data['descripcion'] ?? null;

        try {
            DB::transaction(function () use ($data, $nuevoMonto, $desc) {
                $empresa = EmpresaCapital::query()->lockForUpdate()->latest('id')->firstOrFail();

                $existing = GastoDiario::whereDate('fecha', $data['fecha'])
                    ->orderByDesc('id')
                    ->first();

                $viejoMonto = $existing ? (int)$existing->monto : 0;
                $delta = $nuevoMonto - $viejoMonto;

                if ($delta > 0 && $delta > (int)$empresa->capital_disponible) {
                    abort(400, 'El gasto excede la caja disponible.');
                }

                if ($existing) {
                    $existing->update([
                        'monto'       => $nuevoMonto,
                        'descripcion' => $desc,
                        'user_id'     => auth()->id(),
                    ]);
                } else {
                    GastoDiario::create([
                        'fecha'       => $data['fecha'],
                        'monto'       => $nuevoMonto,
                        'descripcion' => $desc,
                        'user_id'     => auth()->id(),
                    ]);
                }

                if ($delta !== 0) {
                    $empresa->capital_anterior   = (int) $empresa->capital_disponible;
                    $empresa->capital_disponible = (int) $empresa->capital_disponible - (int) $delta;
                    $empresa->save();

                    RegistroCapital::create([
                        'monto'       => $delta > 0 ? -abs((int)$delta) : abs((int)$delta),
                        'user_id'     => auth()->id(),
                        'tipo_accion' => $delta > 0 ? 'Gasto del día' : 'Gasto del día (ajuste)',
                        'descripcion' => $desc,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok','Gasto del día guardado. Caja ajustada según el último valor de esa fecha.');
    }

    public function storePagoParcial(Request $request)
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'min:0.01'],
        ]);

        PagoParcialAuditoria::create([
            'fecha'     => $data['fecha'],
            'monto'     => $data['monto'],
            'idusuario' => auth()->id(),
        ]);

        return back()->with('ok', 'Pago parcial del día registrado.');
    }
}
