<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmpresaCapital;
use App\Models\User;
use App\Models\RegistroCapital;
use App\Models\CapitalPrestamista;
use Illuminate\Support\Facades\DB;

class CapitalEmpresaController extends Controller
{
    public function index()
    {
        $capital = EmpresaCapital::latest()->first();
        $usuarios = User::role(['ADMINISTRADOR', 'SUPERVISOR', 'PRESTAMISTA'])->get();

        return view('admin.capital.index', compact('capital', 'usuarios'));
    }

    public function store(Request $request)
    {
        // Limpiar puntos o comas del input
        $request->merge([
            'capital_total' => str_replace(['.', ','], '', $request->capital_total)
        ]);

        $request->validate([
            'capital_total' => 'required|numeric|min:1',
        ]);

        DB::beginTransaction();
        try {
            $capital = EmpresaCapital::create([
                'capital_total' => $request->capital_total,
                'capital_disponible' => $request->capital_total,
                'capital_anterior' => $request->capital_total,
            ]);

            RegistroCapital::create([
                'monto' => $request->capital_total,
                'user_id' => auth()->id(),
                'tipo_accion' => 'Capital total inicial registrado',
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Capital registrado correctamente.');
        } catch (\Exception $e) {
    DB::rollBack();
    return redirect()->back()->with('error', 'Error al registrar el capital: ' . $e->getMessage());
}

    }

    public function agregar(Request $request)
    {
        // Limpiar puntos o comas del input
        $request->merge([
            'monto' => str_replace(['.', ','], '', $request->monto)
        ]);

        $request->validate([
            'monto' => 'required|numeric|min:1',
        ]);

        DB::beginTransaction();
        try {
            $capital = EmpresaCapital::latest()->first();

            $capital->capital_anterior = $capital->capital_disponible;
            $capital->capital_total += $request->monto;
            $capital->capital_disponible += $request->monto;
            $capital->save();

            RegistroCapital::create([
                'monto' => $request->monto,
                'user_id' => auth()->id(),
                'tipo_accion' => 'Capital adicional agregado',
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Capital adicional agregado correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error al agregar capital adicional.');
        }
    }

    public function asignar(Request $request)
    {
        $capital = EmpresaCapital::latest()->first();

        // Obtener y limpiar el valor del monto para este usuario
        $montoCrudo = $request->montos[$request->asignar_id] ?? 0;
        $monto = str_replace(['.', ','], '', $montoCrudo);

        if (!$monto || $monto <= 0) {
            return redirect()->back()->with('error', 'Debe ingresar un monto válido para asignar.');
        }

        if ($monto > $capital->capital_disponible) {
            return redirect()->back()->with('error', 'El monto excede el capital disponible.');
        }

        DB::beginTransaction();
        try {
            $capitalPrestamista = CapitalPrestamista::firstOrCreate([
                'user_id' => $request->asignar_id,
            ]);

            $capitalPrestamista->monto_asignado += $monto;
            $capitalPrestamista->monto_disponible += $monto;
            $capitalPrestamista->save();

            $capital->capital_anterior = $capital->capital_disponible;
            $capital->capital_disponible -= $monto;
            $capital->save();

            $prestamista = User::find($request->asignar_id);
            RegistroCapital::create([
                'monto' => $monto,
                'user_id' => auth()->id(),
                'tipo_accion' => 'Capital asignado a prestamista: ' . $prestamista->name,
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Capital asignado correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error al asignar capital.');
        }
    }
}
