<?php

namespace App\Http\Controllers;

use App\Models\Prestamo;
use App\Models\Pago;
use App\Models\GastoDiario;
use App\Models\RegistroCapital;           // asignaciones / devoluciones / ajustes de caja
use App\Models\PagoParcialAuditoria;      // pagos parciales del día (manuales)
use App\Models\Abono;                     // abonos de clientes
use App\Models\EmpresaCapital;            // para afectar caja (en storeGasto)
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AuditoriaController extends Controller
{
    public function index(Request $request)
    {
        // 1) Rango de fechas
        $desdeIn = $request->input('desde');
        $hastaIn = $request->input('hasta');
        $desde   = $desdeIn ? Carbon::parse($desdeIn)->toDateString() : now()->subDays(30)->toDateString();
        $hasta   = $hastaIn ? Carbon::parse($hastaIn)->toDateString() : now()->toDateString();

        // 2) Calendario base (fechas únicas) + ABONOS usando COALESCE(fecha_pago, created_at)
        $baseDias = DB::query()->fromSub(
            DB::query()->fromSub(function ($q) use ($desde, $hasta) {
                $q->fromRaw("(
                    SELECT DATE(fecha_inicio)    AS dia FROM prestamos            WHERE DATE(fecha_inicio)    BETWEEN ? AND ?
                    UNION
                    SELECT DATE(fecha_cancelado) AS dia FROM pagos                WHERE estado='Confirmado' AND DATE(fecha_cancelado) BETWEEN ? AND ?
                    UNION
                    SELECT DATE(fecha)           AS dia FROM gastos_diarios       WHERE DATE(fecha)           BETWEEN ? AND ?
                    UNION
                    SELECT DATE(created_at)      AS dia FROM registros_capital    WHERE DATE(created_at)      BETWEEN ? AND ?
                    UNION
                    SELECT DATE(fecha)           AS dia FROM pagoparcialauditoria WHERE DATE(fecha)           BETWEEN ? AND ?
                    UNION
                    SELECT DATE(COALESCE(fecha_pago, created_at)) AS dia FROM abonos WHERE DATE(COALESCE(fecha_pago, created_at)) BETWEEN ? AND ?
                ) t", [
                    $desde,$hasta,  // prestamos
                    $desde,$hasta,  // pagos confirmados
                    $desde,$hasta,  // gastos
                    $desde,$hasta,  // registros_capital
                    $desde,$hasta,  // pagos parciales auditoría
                    $desde,$hasta,  // abonos (fecha_pago o created_at)
                ]);
            }, 'u')->selectRaw('dia')->groupBy('dia')
        , 'cal');

        // 3) Métricas por día
        // Prestado
        $prestado = Prestamo::query()
            ->selectRaw("DATE(fecha_inicio) AS dia, SUM(monto_prestado) AS total_prestado, COUNT(*) AS nro_prestamos")
            ->whereBetween(DB::raw('DATE(fecha_inicio)'), [$desde, $hasta])
            ->groupBy('dia');

        // (A) Abonos por préstamo/día para netear pagos del MISMO préstamo y MISMO día
        $abonosPrestamoDia = DB::table('abonos')
            ->whereBetween(DB::raw('DATE(COALESCE(fecha_pago, created_at))'), [$desde, $hasta])
            ->selectRaw('prestamo_id, DATE(COALESCE(fecha_pago, created_at)) AS dia, SUM(monto) AS abonado_dia')
            ->groupBy('prestamo_id','dia');

        // (B) Pagos confirmados por préstamo/día
        $pagosPrestamoDia = DB::table('pagos')
            ->where('estado', 'Confirmado')
            ->whereBetween(DB::raw('DATE(fecha_cancelado)'), [$desde, $hasta])
            ->selectRaw('prestamo_id, DATE(fecha_cancelado) AS dia, SUM(monto_pagado) AS pagado_dia, COUNT(*) AS nro_pagos_dia')
            ->groupBy('prestamo_id','dia');

        // (C) Neteo por préstamo/día y luego totalizo por día
        $cobrado = DB::query()
            ->fromSub($pagosPrestamoDia, 'p')
            ->leftJoinSub($abonosPrestamoDia, 'ab', function($j){
                $j->on('ab.prestamo_id','=','p.prestamo_id')
                  ->on('ab.dia','=','p.dia');
            })
            ->selectRaw('p.dia AS dia,
                         SUM(GREATEST(p.pagado_dia - COALESCE(ab.abonado_dia,0),0)) AS total_cobrado,
                         SUM(p.nro_pagos_dia) AS nro_pagos')
            ->groupBy('p.dia');

        // Gastos: último del día
        $gastosUltimoId = GastoDiario::query()
            ->whereBetween(DB::raw('DATE(fecha)'), [$desde, $hasta])
            ->selectRaw('DATE(fecha) AS dia, MAX(id) AS gasto_id')
            ->groupBy('dia');

        $gastos = DB::query()
            ->fromSub($gastosUltimoId, 'g')
            ->join('gastos_diarios as gd', 'gd.id', '=', 'g.gasto_id')
            ->selectRaw("g.dia AS dia, gd.monto AS gastos_dia, COALESCE(NULLIF(gd.descripcion,''), '') AS descripciones");

        // Asignado (neto)
        $asignado = RegistroCapital::query()
            ->selectRaw("
                DATE(created_at) AS dia,
                SUM(CASE
                        WHEN tipo_accion LIKE 'Capital asignado a prestamista:%' THEN monto
                        WHEN tipo_accion LIKE 'Capital devuelto por prestamista:%' THEN -monto
                        ELSE 0
                    END) AS asignado_dia
            ")
            ->whereBetween(DB::raw('DATE(created_at)'), [$desde, $hasta])
            ->where(function ($q) {
                $q->where('tipo_accion', 'LIKE', 'Capital asignado a prestamista:%')
                  ->orWhere('tipo_accion', 'LIKE', 'Capital devuelto por prestamista:%');
            })
            ->groupBy('dia');

        // Pagos parciales (manuales)
        $parciales = PagoParcialAuditoria::query()
            ->selectRaw("DATE(fecha) AS dia, COALESCE(SUM(monto),0) AS pagos_parciales")
            ->whereBetween(DB::raw('DATE(fecha)'), [$desde, $hasta])
            ->groupBy('dia');

        // Abonos (aparecen en "Pagos parciales")
        $abonos = Abono::query()
            ->selectRaw("DATE(COALESCE(fecha_pago, created_at)) AS dia, COALESCE(SUM(monto),0) AS abonos_dia")
            ->whereBetween(DB::raw('DATE(COALESCE(fecha_pago, created_at))'), [$desde, $hasta])
            ->groupBy('dia');

        // 4) Unión contra el calendario
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

        // 5) Balance
        $auditoria = $rows->map(function ($r) {
            $asignado  = (float)$r->asignado_dia;
            $cobrado   = (float)$r->total_cobrado;
            $parciales = (float)$r->pagos_parciales;
            $gastos    = (float)$r->gastos_dia;
            $prestado  = (float)$r->total_prestado;

            $r->balance = ($asignado + $cobrado + $parciales) - ($gastos + $prestado);
            $r->caja    = $cobrado - $prestado - $gastos; // histórico (si lo usas)
            $r->descripciones = trim((string)$r->descripciones) !== '' ? $r->descripciones : '-';
            return $r;
        })->values();

        // 6) KPIs cabecera
        $asignadoTotalRango = (int) RegistroCapital::query()
            ->whereBetween(DB::raw('DATE(created_at)'), [$desde, $hasta])
            ->where(function ($q) {
                $q->where('tipo_accion', 'LIKE', 'Capital asignado a prestamista:%')
                  ->orWhere('tipo_accion', 'LIKE', 'Capital devuelto por prestamista:%');
            })
            ->selectRaw("COALESCE(SUM(CASE
                        WHEN tipo_accion LIKE 'Capital asignado a prestamista:%' THEN monto
                        WHEN tipo_accion LIKE 'Capital devuelto por prestamista:%' THEN -monto
                        ELSE 0 END),0) AS neto")
            ->value('neto');

        $asignadoHistoricoHastaCorte = (int) RegistroCapital::query()
            ->whereDate('created_at', '<=', $hasta)
            ->where(function ($q) {
                $q->where('tipo_accion', 'LIKE', 'Capital asignado a prestamista:%')
                  ->orWhere('tipo_accion', 'LIKE', 'Capital devuelto por prestamista:%');
            })
            ->selectRaw("COALESCE(SUM(CASE
                        WHEN tipo_accion LIKE 'Capital asignado a prestamista:%' THEN monto
                        WHEN tipo_accion LIKE 'Capital devuelto por prestamista:%' THEN -monto
                        ELSE 0 END),0) AS neto")
            ->value('neto');

        $prestadoHistoricoHastaCorte = (int) Prestamo::query()
            ->whereDate('fecha_inicio','<=',$hasta)
            ->sum('monto_prestado');

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

    // Registrar gasto del día — “último valor manda” + ajusta caja con delta
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

                // Bloqueamos registro de empresa
                $empresa = EmpresaCapital::query()->lockForUpdate()->latest('id')->firstOrFail();

                // Último gasto registrado para la fecha dada
                $existing = GastoDiario::whereDate('fecha', $data['fecha'])
                    ->orderByDesc('id')
                    ->first();

                $viejoMonto = $existing ? (int)$existing->monto : 0;

                // Δ: >0 aumenta gasto (resta caja), <0 reduce gasto (devuelve caja)
                $delta = $nuevoMonto - $viejoMonto;

                // Si aumenta el gasto, valida que la caja alcance
                if ($delta > 0 && $delta > (int)$empresa->capital_disponible) {
                    abort(400, 'El gasto excede la caja disponible.');
                }

                // Guardar/actualizar “último valor” del gasto del día
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

                // Ajuste de caja en base al Δ
                if ($delta !== 0) {
                    // Caja nueva = caja actual - Δ (si Δ>0 resta; si Δ<0 suma)
                    $empresa->capital_anterior   = (int) $empresa->capital_disponible;
                    $empresa->capital_disponible = (int) $empresa->capital_disponible - (int) $delta;
                    $empresa->save();

                    // Registro en historial (negativo = egreso, positivo = devolución)
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

    // Registrar pago parcial del día (manual)
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
