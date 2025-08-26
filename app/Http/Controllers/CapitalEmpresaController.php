<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\EmpresaCapital;
use App\Models\RegistroCapital;
use App\Models\CapitalPrestamista;
use App\Models\User;
use App\Models\Prestamo;
use App\Models\Pago;

class CapitalEmpresaController extends Controller
{
   public function index()
{
    // 0) Opcional: si quieres excluir items marcados como "reportado"
    $filtrarReportado = false;

    // 1) Base persistida
    $capital = EmpresaCapital::latest()->first();
    $capitalDisponiblePersistido = (int) ($capital->capital_disponible ?? 0);

    // 2) Total cobrado confirmado (histórico)
    $totalCobrado = Pago::query()
        ->when($filtrarReportado, fn($q) => $q->whereNull('reportado'))
        ->where('estado', 'Confirmado')
        ->sum('monto_pagado');

    // 3) Dinero circulando (saldo restante considerando intereses)
    $pagosPorPrestamo = Pago::select('prestamo_id', DB::raw('SUM(monto_pagado) AS pagado'))
        ->when($filtrarReportado, fn($q) => $q->whereNull('reportado'))
        ->where('estado', 'Confirmado')
        ->groupBy('prestamo_id');

    $restantes = Prestamo::query()
        ->leftJoinSub($pagosPorPrestamo, 'pg', 'pg.prestamo_id', '=', 'prestamos.id')
        ->when($filtrarReportado, fn($q) => $q->whereNull('prestamos.reportado'))
        ->where('prestamos.estado', 'Pendiente')
        ->select(
            'prestamos.id',
            DB::raw('GREATEST(prestamos.monto_total - COALESCE(pg.pagado,0), 0) AS restante')
        )
        ->get();

    $dineroCirculando = (int) $restantes->sum('restante');
    $prestamosActivos = (int) $restantes->where('restante', '>', 0)->count();

    // 4) Caja disponible = base persistida + cobros confirmados
    $capitalDisponible = $capitalDisponiblePersistido + $totalCobrado;

    // (Esto NO duplica nada porque capital_disponible no aumenta con pagos; solo cambia con agregar/asignar/devolver)
    $totalGeneral = $capitalDisponible + $dineroCirculando;

    // (opcional) capital asignado total si lo muestras en la tarjeta
    $capitalAsignadoTotal = (int) CapitalPrestamista::sum('monto_asignado');

    $usuarios = User::role(['ADMINISTRADOR', 'SUPERVISOR', 'PRESTAMISTA'])->get();

    return view('admin.capital.index', [
        'capital'               => $capital,
        'usuarios'              => $usuarios,
        'capitalDisponible'     => $capitalDisponible,
        'dineroCirculando'      => $dineroCirculando,
        'totalGeneral'          => $totalGeneral,
        'prestamosActivos'      => $prestamosActivos,
        'capitalAsignadoTotal'  => $capitalAsignadoTotal,
    ]);
}

public function resumenJson()
{
    $filtrarReportado = false;

    $capital = EmpresaCapital::latest()->first();
    $capitalDisponiblePersistido = (int) ($capital->capital_disponible ?? 0);

    $totalCobrado = Pago::query()
        ->when($filtrarReportado, fn($q) => $q->whereNull('reportado'))
        ->where('estado', 'Confirmado')
        ->sum('monto_pagado');

    $pagosPorPrestamo = Pago::select('prestamo_id', DB::raw('SUM(monto_pagado) AS pagado'))
        ->when($filtrarReportado, fn($q) => $q->whereNull('reportado'))
        ->where('estado', 'Confirmado')
        ->groupBy('prestamo_id');

    $restantes = Prestamo::query()
        ->leftJoinSub($pagosPorPrestamo, 'pg', 'pg.prestamo_id', '=', 'prestamos.id')
        ->when($filtrarReportado, fn($q) => $q->whereNull('prestamos.reportado'))
        ->where('prestamos.estado', 'Pendiente')
        ->select(DB::raw('GREATEST(prestamos.monto_total - COALESCE(pg.pagado,0), 0) AS restante'))
        ->get();

    $dineroCirculando = (int) $restantes->sum('restante');
    $prestamosActivos = (int) $restantes->where('restante', '>', 0)->count();

    $capitalDisponible = $capitalDisponiblePersistido + $totalCobrado;
    $totalGeneral      = $capitalDisponible + $dineroCirculando;

    return response()->json([
        'capitalDisponible'     => $capitalDisponible,
        'dineroCirculando'      => $dineroCirculando,
        'totalGeneral'          => $totalGeneral,
        'prestamosActivos'      => $prestamosActivos,
    ]);
}


    // ================== CRUD de capital ==================

    // Guardar "capital total disponible" (inicializa caja)
    public function store(Request $request)
    {
        $request->merge(['capital_total' => str_replace(['.', ','], '', $request->capital_total)]);
        $request->validate(['capital_total' => 'required|numeric|min:1']);

        DB::beginTransaction();
        try {
            $capital = EmpresaCapital::create([
                'capital_total'      => $request->capital_total,
                'capital_disponible' => $request->capital_total, // ✅ Caja = lo ingresado
                'capital_anterior'   => $request->capital_total,
            ]);

            RegistroCapital::create([
                'monto'       => $request->capital_total,
                'user_id'     => auth()->id(),
                'tipo_accion' => 'Capital total inicial registrado',
            ]);

            DB::commit();
            return back()->with('success', 'Capital registrado correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al registrar el capital: ' . $e->getMessage());
        }
    }

    // Agregar capital adicional (suma a caja disponible)
    public function agregar(Request $request)
    {
        $request->merge(['monto' => str_replace(['.', ','], '', $request->monto)]);
        $request->validate(['monto' => 'required|numeric|min:1']);

        DB::beginTransaction();
        try {
            $capital = EmpresaCapital::latest()->first();

            $capital->capital_anterior    = $capital->capital_disponible;
            $capital->capital_total      += $request->monto;
            $capital->capital_disponible += $request->monto; // ✅ suma a Caja
            $capital->save();

            RegistroCapital::create([
                'monto'       => $request->monto,
                'user_id'     => auth()->id(),
                'tipo_accion' => 'Capital adicional agregado',
            ]);

            DB::commit();
            return back()->with('success', 'Capital adicional agregado correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al agregar capital adicional.');
        }
    }

    // Asignar capital a prestamistas (resta de caja)
    public function asignar(Request $request)
    {
        $capital = EmpresaCapital::latest()->first();

        $montoCrudo = $request->montos[$request->asignar_id] ?? 0;
        $monto = (int) str_replace(['.', ','], '', $montoCrudo);

        if (!$monto || $monto <= 0) {
            return back()->with('error', 'Debe ingresar un monto válido para asignar.');
        }

        if ($monto > ($capital->capital_disponible ?? 0)) {
            return back()->with('error', 'El monto excede el capital disponible.');
        }

        DB::beginTransaction();
        try {
            $capitalPrestamista = CapitalPrestamista::firstOrCreate([
                'user_id' => $request->asignar_id,
            ]);

            $capitalPrestamista->monto_asignado   += $monto;
            $capitalPrestamista->monto_disponible += $monto;
            $capitalPrestamista->save();

            // ✅ resta de Caja disponible
            $capital->capital_anterior    = $capital->capital_disponible;
            $capital->capital_disponible -= $monto;
            $capital->save();

            $prestamista = User::find($request->asignar_id);
            RegistroCapital::create([
                'monto'       => $monto,
                'user_id'     => auth()->id(),
                'tipo_accion' => 'Capital asignado a prestamista: ' . ($prestamista->name ?? ''),
            ]);

            DB::commit();
            return back()->with('success', 'Capital asignado correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al asignar capital.');
        }
    }
}
