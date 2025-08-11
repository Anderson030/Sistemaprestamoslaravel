<?php

namespace App\Http\Controllers;

use App\Models\Prestamo;
use App\Models\Pago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AuditoriaController extends Controller
{
    public function index(Request $request)
    {
        // Fechas del request (yyyy-mm-dd) o últimos 30 días
        $desdeIn = $request->input('desde');
        $hastaIn = $request->input('hasta');

        $desde = $desdeIn ? Carbon::parse($desdeIn)->toDateString()
                          : now()->subDays(30)->toDateString();
        $hasta = $hastaIn ? Carbon::parse($hastaIn)->toDateString()
                          : now()->toDateString();

        // A) Salidas → préstamos
        $salidas = Prestamo::query()
            ->selectRaw("
                DATE(fecha_inicio)     AS dia,
                SUM(monto_prestado)    AS total_prestado,
                0                      AS total_cobrado,
                COUNT(*)               AS nro_prestamos,
                0                      AS nro_pagos
            ")
            ->whereDate('fecha_inicio', '>=', $desde)
            ->whereDate('fecha_inicio', '<=', $hasta)
            ->groupBy('dia');

        // B) Entradas → pagos confirmados
        $ingresos = Pago::query()
            ->selectRaw("
                DATE(fecha_cancelado)  AS dia,
                0                      AS total_prestado,
                SUM(monto_pagado)      AS total_cobrado,
                0                      AS nro_prestamos,
                COUNT(*)               AS nro_pagos
            ")
            ->where('estado', 'Confirmado')
            ->whereDate('fecha_cancelado', '>=', $desde)
            ->whereDate('fecha_cancelado', '<=', $hasta)
            ->groupBy('dia');

        // C) UNION ALL y agregado final (FULL por día)
        $auditoria = DB::query()
            ->fromSub($salidas->unionAll($ingresos), 't')
            ->selectRaw("
                dia,
                SUM(total_prestado)                   AS total_prestado,
                SUM(total_cobrado)                    AS total_cobrado,
                SUM(total_cobrado - total_prestado)   AS balance_dia,
                SUM(nro_prestamos)                    AS nro_prestamos,
                SUM(nro_pagos)                        AS nro_pagos
            ")
            ->groupBy('dia')
            ->orderByDesc('dia')
            ->get();

        // Devolver las fechas para que queden en los inputs
        return view('admin.auditorias.index', [
            'auditoria' => $auditoria,
            'desde'     => $desde,
            'hasta'     => $hasta,
        ]);
    }
}
