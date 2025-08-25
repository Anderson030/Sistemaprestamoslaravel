<?php

namespace App\Http\Controllers;

use App\Models\Prestamo;
use App\Models\Pago;
use App\Models\GastoDiario;
use App\Models\RegistroCapital;           // asignaciones / devoluciones
use App\Models\PagoParcialAuditoria;      // pagos parciales del día
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AuditoriaController extends Controller
{
    public function index(Request $request)
    {
        /* ================================
         * 1) Rango de fechas
         * ================================*/
        $desdeIn = $request->input('desde');
        $hastaIn = $request->input('hasta');
        $desde   = $desdeIn ? Carbon::parse($desdeIn)->toDateString() : now()->subDays(30)->toDateString();
        $hasta   = $hastaIn ? Carbon::parse($hastaIn)->toDateString() : now()->toDateString();

        /* ================================
         * 2) Calendario base con TODAS las fuentes (fechas únicas)
         * ================================*/
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
                ) t", [
                    $desde,$hasta,  // prestamos
                    $desde,$hasta,  // pagos confirmados
                    $desde,$hasta,  // gastos
                    $desde,$hasta,  // registros_capital
                    $desde,$hasta,  // pagos parciales auditoría
                ]);
            }, 'u')->selectRaw('dia')->groupBy('dia')
        , 'cal');

        /* ================================
         * 3) Métricas por día (cada una aislada)
         * ================================*/
        // Prestado (salidas)
        $prestado = Prestamo::query()
            ->selectRaw("DATE(fecha_inicio) AS dia, SUM(monto_prestado) AS total_prestado, COUNT(*) AS nro_prestamos")
            ->whereBetween(DB::raw('DATE(fecha_inicio)'), [$desde, $hasta])
            ->groupBy('dia');

        // Cobrado (ingresos confirmados)
        $cobrado = Pago::query()
            ->selectRaw("DATE(fecha_cancelado) AS dia, SUM(monto_pagado) AS total_cobrado, COUNT(*) AS nro_pagos")
            ->where('estado', 'Confirmado')
            ->whereBetween(DB::raw('DATE(fecha_cancelado)'), [$desde, $hasta])
            ->groupBy('dia');

        // Gastos  ➜ tomar el ÚLTIMO gasto registrado por día (no sumatoria)
        $gastosUltimoId = GastoDiario::query()
            ->whereBetween(DB::raw('DATE(fecha)'), [$desde, $hasta])
            ->selectRaw('DATE(fecha) AS dia, MAX(id) AS gasto_id')
            ->groupBy('dia');

        $gastos = DB::query()
            ->fromSub($gastosUltimoId, 'g')
            ->join('gastos_diarios as gd', 'gd.id', '=', 'g.gasto_id') // <- nombre de tabla correcto
            ->selectRaw("
                g.dia AS dia,
                gd.monto AS gastos_dia,
                COALESCE(NULLIF(gd.descripcion,''), '') AS descripciones
            ");

        // Asignado del día (NETO): + asignado, - devuelto
        $asignado = RegistroCapital::query()
            ->selectRaw("
                DATE(created_at) AS dia,
                SUM(
                    CASE
                        WHEN tipo_accion LIKE 'Capital asignado a prestamista:%' THEN monto
                        WHEN tipo_accion LIKE 'Capital devuelto por prestamista:%' THEN -monto
                        ELSE 0
                    END
                ) AS asignado_dia
            ")
            ->whereBetween(DB::raw('DATE(created_at)'), [$desde, $hasta])
            ->where(function ($q) {
                $q->where('tipo_accion', 'LIKE', 'Capital asignado a prestamista:%')
                  ->orWhere('tipo_accion', 'LIKE', 'Capital devuelto por prestamista:%');
            })
            ->groupBy('dia');

        // Pagos parciales del día
        $parciales = PagoParcialAuditoria::query()
            ->selectRaw("DATE(fecha) AS dia, COALESCE(SUM(monto),0) AS pagos_parciales")
            ->whereBetween(DB::raw('DATE(fecha)'), [$desde, $hasta])
            ->groupBy('dia');

        /* ================================
         * 4) Unión de todo contra el calendario
         * ================================*/
        $rows = DB::query()
            ->fromSub($baseDias, 'cal')
            ->leftJoinSub($prestado,  'pr',  'pr.dia',   '=', 'cal.dia')
            ->leftJoinSub($cobrado,   'co',  'co.dia',   '=', 'cal.dia')
            ->leftJoinSub($gastos,    'ga',  'ga.dia',   '=', 'cal.dia')
            ->leftJoinSub($asignado,  'asig','asig.dia', '=', 'cal.dia')
            ->leftJoinSub($parciales, 'pp',  'pp.dia',   '=', 'cal.dia')
            ->selectRaw("
                cal.dia,
                COALESCE(pr.total_prestado,0)  AS total_prestado,
                COALESCE(co.total_cobrado,0)   AS total_cobrado,
                COALESCE(ga.gastos_dia,0)      AS gastos_dia,
                COALESCE(asig.asignado_dia,0)  AS asignado_dia,     -- NETO
                COALESCE(pp.pagos_parciales,0) AS pagos_parciales,
                COALESCE(pr.nro_prestamos,0)   AS nro_prestamos,
                COALESCE(co.nro_pagos,0)       AS nro_pagos,
                COALESCE(ga.descripciones,'')  AS descripciones
            ")
            ->orderBy('cal.dia','desc')
            ->get();

        /* ================================
         * 5) Balance por tu fórmula + caja histórica
         *    BALANCE = ASIGNADO_NETO + COBRADO + PARCIALES - GASTOS - PRESTADO
         * ================================*/
        $auditoria = $rows->map(function ($r) {
            $asignado  = (float)$r->asignado_dia;
            $cobrado   = (float)$r->total_cobrado;
            $parciales = (float)$r->pagos_parciales;
            $gastos    = (float)$r->gastos_dia;
            $prestado  = (float)$r->total_prestado;

            $r->balance = ($asignado + $cobrado + $parciales) - ($gastos + $prestado);

            // caja “histórica” (si la vista la usa aún)
            $r->caja = $cobrado - $prestado - $gastos;

            $r->descripciones = trim((string)$r->descripciones) !== '' ? $r->descripciones : '-';
            return $r;
        })->values();

        /* ================================
         * 6) KPIs cabecera (neto asignado en rango + restante al corte)
         * ================================*/
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

        // Asignado restante al corte = asignado histórico − prestado histórico (no negativo)
        $asignadoRestanteCorte = max(0, $asignadoHistoricoHastaCorte - $prestadoHistoricoHastaCorte);

        return view('admin.auditorias.index', [
            'auditorias'            => $auditoria,
            'desde'                 => $desde,
            'hasta'                 => $hasta,
            'asignadoTotalRango'    => $asignadoTotalRango,
            'asignadoRestanteCorte' => $asignadoRestanteCorte,
        ]);
    }

    /**
     * Registrar gasto del día (modal existente) — ÚLTIMO VALOR MANDA
     * Si ya existe gasto en esa fecha, se ACTUALIZA; si no, se CREA.
     */
    public function storeGasto(Request $request)
    {
        $data = $request->validate([
            'fecha'       => ['required','date'],
            'monto'       => ['nullable','numeric','min:0'],
            'descripcion' => ['nullable','string','max:255'],
        ]);

        $monto = (int) ($data['monto'] ?? 0);
        $desc  = $data['descripcion'] ?? null;

        DB::transaction(function () use ($data, $monto, $desc) {
            // Buscamos por fecha (ignorando hora, si la columna fuera datetime)
            $existing = GastoDiario::whereDate('fecha', $data['fecha'])
                ->orderByDesc('id')
                ->first();

            if ($existing) {
                // ÚLTIMO VALOR MANDA: se reemplaza el valor del día
                $existing->update([
                    'monto'       => $monto,
                    'descripcion' => $desc,
                    'user_id'     => auth()->id(),
                ]);
            } else {
                // No había registro para esa fecha
                GastoDiario::create([
                    'fecha'       => $data['fecha'],
                    'monto'       => $monto,
                    'descripcion' => $desc,
                    'user_id'     => auth()->id(),
                ]);
            }
        });

        return back()->with('ok','Gasto del día guardado. Se tomó el último valor para esa fecha.');
    }

    /**
     * Registrar Pago Parcial del día (solo Fecha y Monto)
     */
    public function storePagoParcial(Request $request)
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'min:0.01'],
        ]);

        PagoParcialAuditoria::create([
            'fecha'     => $data['fecha'],
            'monto'     => $data['monto'],
            'idusuario' => auth()->id(), // si la columna existe y admite null
        ]);

        return back()->with('ok', 'Pago parcial del día registrado.');
    }
}
