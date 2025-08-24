<?php

namespace App\Http\Controllers;

use App\Models\Prestamo;
use App\Models\Pago;
use App\Models\GastoDiario;
use App\Models\RegistroCapital;           // asignaciones/devoluciones
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
        $desde = $desdeIn ? Carbon::parse($desdeIn)->toDateString() : now()->subDays(30)->toDateString();
        $hasta = $hastaIn ? Carbon::parse($hastaIn)->toDateString() : now()->toDateString();

        /* ================================
         * 2) Calendario base con TODAS las fuentes (fechas únicas)
         * ================================*/
        $baseDias = DB::query()->fromSub(
            DB::query()->fromSub(function ($q) use ($desde, $hasta) {
                $q->fromRaw("(
                    SELECT DATE(fecha_inicio)    AS dia FROM prestamos              WHERE DATE(fecha_inicio)    BETWEEN ? AND ?
                    UNION
                    SELECT DATE(fecha_cancelado) AS dia FROM pagos                  WHERE estado='Confirmado' AND DATE(fecha_cancelado) BETWEEN ? AND ?
                    UNION
                    SELECT DATE(fecha)           AS dia FROM gastos_diarios         WHERE DATE(fecha)           BETWEEN ? AND ?
                    UNION
                    SELECT DATE(created_at)      AS dia FROM registros_capital      WHERE DATE(created_at)      BETWEEN ? AND ?
                    UNION
                    SELECT DATE(fecha)           AS dia FROM pagoparcialauditoria   WHERE DATE(fecha)           BETWEEN ? AND ?
                ) t", [
                    $desde,$hasta,  // prestamos
                    $desde,$hasta,  // pagos confirmados
                    $desde,$hasta,  // gastos
                    $desde,$hasta,  // registros_capital
                    $desde,$hasta,  // pagoparcialauditoria
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

        // Gastos
        $gastos = GastoDiario::query()
            ->selectRaw("
                DATE(fecha) AS dia,
                COALESCE(SUM(monto),0) AS gastos_dia,
                GROUP_CONCAT(NULLIF(descripcion,'') SEPARATOR ' | ') AS descripciones
            ")
            ->whereBetween(DB::raw('DATE(fecha)'), [$desde, $hasta])
            ->groupBy('dia');

        // Asignado del día (BRUTO): solo lo asignado, SIN restar devoluciones
        $asignado = RegistroCapital::query()
            ->selectRaw("
                DATE(created_at) AS dia,
                SUM(monto) AS asignado_dia
            ")
            ->whereBetween(DB::raw('DATE(created_at)'), [$desde, $hasta])
            ->where('tipo_accion', 'LIKE', 'Capital asignado a prestamista:%')
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
            ->leftJoinSub($prestado,  'pr',  'pr.dia',  '=', 'cal.dia')
            ->leftJoinSub($cobrado,   'co',  'co.dia',  '=', 'cal.dia')
            ->leftJoinSub($gastos,    'ga',  'ga.dia',  '=', 'cal.dia')
            ->leftJoinSub($asignado,  'asig','asig.dia','=', 'cal.dia')
            ->leftJoinSub($parciales, 'pp',  'pp.dia',  '=', 'cal.dia')
            ->selectRaw("
                cal.dia,
                COALESCE(pr.total_prestado,0)  AS total_prestado,
                COALESCE(co.total_cobrado,0)   AS total_cobrado,
                COALESCE(ga.gastos_dia,0)      AS gastos_dia,
                COALESCE(asig.asignado_dia,0)  AS asignado_dia,
                COALESCE(pp.pagos_parciales,0) AS pagos_parciales,
                COALESCE(pr.nro_prestamos,0)   AS nro_prestamos,
                COALESCE(co.nro_pagos,0)       AS nro_pagos,
                COALESCE(ga.descripciones,'')  AS descripciones
            ")
            ->orderBy('cal.dia','desc')
            ->get();

        /* ================================
         * 5) Balance por tu fórmula + caja histórica
         * ================================*/
        $auditoria = $rows->map(function ($r) {
            $asignado  = (float)$r->asignado_dia;
            $cobrado   = (float)$r->total_cobrado;
            $parciales = (float)$r->pagos_parciales;
            $gastos    = (float)$r->gastos_dia;
            $prestado  = (float)$r->total_prestado;

            // ⚖️ Tu regla exacta
            $r->balance = ($asignado + $cobrado + $parciales) - ($gastos + $prestado);

            // Caja “histórica” (compatibilidad)
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
     * Registrar gasto del día (modal existente)
     */
    public function storeGasto(Request $request)
    {
        $data = $request->validate([
            'fecha'       => ['required','date'],
            'monto'       => ['nullable','numeric','min:0'],
            'descripcion' => ['nullable','string','max:255'],
        ]);

        GastoDiario::create([
            'fecha'       => $data['fecha'],
            'monto'       => $data['monto'] ?? 0,
            'descripcion' => $data['descripcion'] ?? null,
            'user_id'     => auth()->id(),
        ]);

        return back()->with('ok','Gasto del día registrado correctamente.');
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
            'fecha'      => $data['fecha'],
            'monto'      => $data['monto'],
            'idusuario'  => auth()->id(), // si existe la columna y es nullable
        ]);

        return back()->with('ok', 'Pago parcial del día registrado.');
    }
}
