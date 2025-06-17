<?php

namespace App\Http\Controllers;

use Spatie\Permission\Models\Role;
use App\Models\EmpresaCapital;
use App\Models\CapitalPrestamista;
use App\Models\User;
use Illuminate\Http\Request;

class CapitalEmpresaController extends Controller
{
    /**
     * Mostrar el capital disponible y el formulario de asignación.
     */
   

public function index()
{
    $capital = EmpresaCapital::latest()->first();

    // Solo traer usuarios con roles válidos
    $usuarios = User::role(['ADMINISTRADOR', 'SUPERVISOR', 'PRESTAMISTA'])->get();

    return view('admin.capital.index', compact('capital', 'usuarios'));
}

    /**
     * Guardar nuevo capital general.
     */
    public function store(Request $request)
    {
        $request->validate([
            'capital_total' => 'required|numeric|min:0'
        ]);

        $capital = new EmpresaCapital();
        $capital->capital_total = $request->capital_total;
        $capital->capital_disponible = $request->capital_total;
        $capital->save();

        return redirect()->back()->with('success', 'Capital registrado correctamente.');
    }

    /**
     * Asignar capital individualmente a prestamistas y restar del capital disponible.
     */
    public function asignarCapital(Request $request)
    {
        $userId = $request->input('asignar_id');
        $monto = floatval($request->input("montos.$userId"));

        if ($monto <= 0) {
            return back()->with('error', 'El monto debe ser mayor a 0.');
        }

        $capitalEmpresa = EmpresaCapital::latest()->first();

        if (!$capitalEmpresa || $capitalEmpresa->capital_disponible < $monto) {
            return back()->with('error', 'Fondos insuficientes en la empresa.');
        }

        // Crear o actualizar el capital del prestamista
        $registro = CapitalPrestamista::firstOrNew(['user_id' => $userId]);
        $registro->monto_asignado += $monto;
        $registro->monto_disponible += $monto;
        $registro->save();

        // Descontar del capital disponible de la empresa
        $capitalEmpresa->capital_disponible -= $monto;
        $capitalEmpresa->save();

        return back()->with('success', 'Capital asignado correctamente.');
    }
}
