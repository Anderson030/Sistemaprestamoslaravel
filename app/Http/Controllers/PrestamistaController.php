<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Prestamo;
use App\Models\Pago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrestamistaController extends Controller
{
 public function index()
{
    // Traer usuarios con rol ADMINISTRADOR, SUPERVISOR o PRESTAMISTA, excluyendo DEV
    $prestamistas = User::whereHas('roles', function ($q) {
        $q->whereIn('name', ['ADMINISTRADOR', 'SUPERVISOR', 'PRESTAMISTA']);
    })->whereDoesntHave('roles', function ($q) {
        $q->where('name', 'DEV');
    })->get()->map(function ($usuario) {
        $prestamos = Prestamo::where('idusuario', $usuario->id)
            ->whereNull('reportado')
            ->get();

        $totalPrestado = $prestamos->sum('monto_prestado');
        $idsPrestamos = $prestamos->pluck('id');

        $totalCobrado = Pago::whereIn('prestamo_id', $idsPrestamos)
            ->where('estado', 'Confirmado')
            ->whereNull('reportado')
            ->sum('monto_pagado');

        $clientesAtendidos = $prestamos->unique('cliente_id')->count();

        return [
            'usuario' => $usuario,
            'prestado' => $totalPrestado,
            'cobrado' => $totalCobrado,
            'clientes' => $clientesAtendidos,
        ];
    });

    return view('admin.prestamistas.index', compact('prestamistas'));
}

    

  public function detalle($id)
{
    $usuario = User::with('roles')->findOrFail($id);

    // Solo mostramos el detalle si el usuario consultado tiene rol válido
    if (!$usuario->hasAnyRole(['ADMINISTRADOR', 'SUPERVISOR', 'PRESTAMISTA'])) {
        abort(403, 'Este usuario no es un prestamista válido.');
    }

    // Obtener préstamos
    $prestamos = Prestamo::where('idusuario', $usuario->id)
        ->whereNull('reportado')
        ->with('cliente')
        ->get();

    $idsPrestamos = $prestamos->pluck('id');

    // Obtener pagos
    $pagos = Pago::whereIn('prestamo_id', $idsPrestamos)
        ->where('estado', 'Confirmado')
        ->whereNull('reportado')
        ->with(['prestamo.cliente'])
        ->get();

    return view('admin.prestamistas.show', compact('usuario', 'prestamos', 'pagos'));
}


    public function reset()
    {
        DB::table('prestamos')->update(['reportado' => now()]);
        DB::table('pagos')->update(['reportado' => now()]);

        return redirect()->route('admin.prestamistas.index')
            ->with('mensaje', 'Los totales fueron reiniciados correctamente.')
            ->with('icono', 'success');
    }
}
