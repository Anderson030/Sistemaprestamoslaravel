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
        $capital = EmpresaCapital::latest()->first();
        $capitalDisponibleBase = $capital->capital_disponible ?? 0;

        // Total cobrado (histórico). Si usas "reportado" para cortes,
        // cámbialo por ->whereNull('reportado').
        $totalCobrado = Pago::where('estado', 'Confirmado')->sum('monto_pagado');

        // Dinero circulando = principal aún no recuperado
        $pagosPorPrestamo = Pago::select('prestamo_id', DB::raw('SUM(monto_pagado) as pagado'))
            ->where('estado', 'Confirmado')
            ->groupBy('prestamo_id');

        $restantes = Prestamo::leftJoinSub($pagosPorPrestamo, 'pg', 'pg.prestamo_id', '=', 'prestamos.id')
            ->select(
                'prestamos.id',
                DB::raw('GREATEST(prestamos.monto_prestado - COALESCE(pg.pagado,0), 0) as restante')
            )
            ->get();

        $dineroCirculando = (int) $restantes->sum('restante');
        $prestamosActivos = (int) $restantes->where('restante', '>', 0)->count();

        // Caja (dinámica) = caja base + todo lo cobrado
        $capitalDisponible = (int) ($capitalDisponibleBase + $totalCobrado);

        // Capital asignado total (asignado a prestamistas)
        $capitalAsignadoTotal = (int) CapitalPrestamista::sum('monto_asignado');

        // ✅ Total general = Caja + Asignado + Circulando (como lo pediste)
        $totalGeneral = $capitalDisponible + $capitalAsignadoTotal + $dineroCirculando;

        // Usuarios para la tabla de asignaciones
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

    // === JSON para refrescar KPIs sin recargar (opcional) ===
    public function resumenJson()
    {
        $capital = EmpresaCapital::latest()->first();
        $capitalDisponibleBase = $capital->capital_disponible ?? 0;

        $totalCobrado = Pago::where('estado', 'Confirmado')->sum('monto_pagado');

        $pagosPorPrestamo = Pago::select('prestamo_id', DB::raw('SUM(monto_pagado) as pagado'))
            ->where('estado', 'Confirmado')
            ->groupBy('prestamo_id');

        $restantes = Prestamo::leftJoinSub($pagosPorPrestamo, 'pg', 'pg.prestamo_id', '=', 'prestamos.id')
            ->select(
                'prestamos.id',
                DB::raw('GREATEST(prestamos.monto_prestado - COALESCE(pg.pagado,0), 0) as restante')
            )
            ->get();

        $dineroCirculando = (int) $restantes->sum('restante');
        $prestamosActivos = (int) $restantes->where('restante', '>', 0)->count();

        $capitalDisponible    = (int) ($capitalDisponibleBase + $totalCobrado);
        $capitalAsignadoTotal = (int) CapitalPrestamista::sum('monto_asignado');

        // ✅ Mismo criterio que en index()
        $totalGeneral = $capitalDisponible + $capitalAsignadoTotal + $dineroCirculando;

        return response()->json([
            'capitalDisponible'     => $capitalDisponible,
            'dineroCirculando'      => $dineroCirculando,
            'totalGeneral'          => $totalGeneral,
            'prestamosActivos'      => $prestamosActivos,
            'capitalAsignadoTotal'  => $capitalAsignadoTotal,
        ]);
    }

    // ================== CRUD de capital (igual que tenías) ==================

    public function store(Request $request)
    {
        $request->merge(['capital_total' => str_replace(['.', ','], '', $request->capital_total)]);

        $request->validate([
            'capital_total' => 'required|numeric|min:1',
        ]);

        DB::beginTransaction();
        try {
            $capital = EmpresaCapital::create([
                'capital_total'      => $request->capital_total,
                'capital_disponible' => $request->capital_total,
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

    public function agregar(Request $request)
    {
        $request->merge(['monto' => str_replace(['.', ','], '', $request->monto)]);
        $request->validate(['monto' => 'required|numeric|min:1']);

        DB::beginTransaction();
        try {
            $capital = EmpresaCapital::latest()->first();
            $capital->capital_anterior    = $capital->capital_disponible;
            $capital->capital_total      += $request->monto;
            $capital->capital_disponible += $request->monto;
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
