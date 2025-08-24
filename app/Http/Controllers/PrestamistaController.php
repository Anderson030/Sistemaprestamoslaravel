<?php

namespace App\Http\Controllers;

use App\Models\CapitalPrestamista;
use App\Models\User;
use App\Models\Prestamo;
use App\Models\Pago;
use App\Models\RegistroCapital; // 👈 para loguear devoluciones
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrestamistaController extends Controller
{
    public function index()
    {
        $prestamistas = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['ADMINISTRADOR', 'SUPERVISOR', 'PRESTAMISTA']);
        })->whereDoesntHave('roles', function ($q) {
            $q->where('name', 'DEV');
        })->get()->map(function ($usuario) {

            $prestamos = Prestamo::where('idusuario', $usuario->id)
                ->whereNull('reportado')
                ->get();

            $totalPrestado   = $prestamos->sum('monto_prestado');
            $totalRecaudado  = $prestamos->sum('monto_total');
            $idsPrestamos    = $prestamos->pluck('id');

            $totalCobrado = Pago::whereIn('prestamo_id', $idsPrestamos)
                ->where('estado', 'Confirmado')
                ->whereNull('reportado')
                ->sum('monto_pagado');

            $clientesAtendidos = $prestamos->unique('cliente_id')->count();

            $capital = CapitalPrestamista::where('user_id', $usuario->id)->first();
            $capitalAsignado = $capital ? (int) $capital->monto_asignado : 0;

            // Ganancia estimada y real
            $gananciaProyectada = $totalRecaudado - $totalPrestado;
            $gananciaReal = 0;

            if ($totalRecaudado > 0 && $totalCobrado > 0) {
                $gananciaReal = $totalCobrado - ($totalCobrado / $totalRecaudado * $totalPrestado);
            }

            return [
                'usuario'          => $usuario,
                'capital_asignado' => $capitalAsignado,
                'prestado'         => $totalPrestado,
                'cobrado'          => $totalCobrado,
                'recaudado'        => $totalRecaudado,
                'ganancia'         => $gananciaProyectada,
                'ganancia_real'    => $gananciaReal,
                'clientes'         => $clientesAtendidos,
            ];
        });

        // Totales generales
        $totalCapitalAsignado   = $prestamistas->sum('capital_asignado');
        $totalPrestado          = $prestamistas->sum('prestado');
        $totalCobrado           = $prestamistas->sum('cobrado');
        $totalRecaudado         = $prestamistas->sum('recaudado');
        $totalGanancia          = $prestamistas->sum('ganancia');
        $totalGananciaCobrada   = $prestamistas->sum('ganancia_real');
        $totalClientes          = $prestamistas->sum('clientes');

        return view('admin.prestamistas.index', compact(
            'prestamistas',
            'totalCapitalAsignado',
            'totalPrestado',
            'totalCobrado',
            'totalRecaudado',
            'totalGanancia',
            'totalGananciaCobrada',
            'totalClientes'
        ));
    }

    public function detalle($id)
    {
        $usuario = User::with('roles')->findOrFail($id);

        if (!$usuario->hasAnyRole(['ADMINISTRADOR', 'SUPERVISOR', 'PRESTAMISTA'])) {
            abort(403, 'Este usuario no es un prestamista válido.');
        }

        $prestamos = Prestamo::where('idusuario', $usuario->id)
            ->whereNull('reportado')
            ->with('cliente')
            ->get();

        $idsPrestamos = $prestamos->pluck('id');

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
        DB::table('capital_prestamistas')->update(['monto_asignado' => 0]);

        return redirect()->route('admin.prestamistas.index')
            ->with('mensaje', 'Los totales fueron reiniciados correctamente.')
            ->with('icono', 'success');
    }

    public function eliminarCapital($id)
    {
        $usuario = User::findOrFail($id);

        if (
            $usuario->hasRole(['ADMINISTRADOR', 'SUPERVISOR', 'PRESTAMISTA']) &&
            !$usuario->hasRole('DEV')
        ) {
            DB::beginTransaction();
            try {
                $capital = CapitalPrestamista::where('user_id', $usuario->id)->lockForUpdate()->first();

                if ($capital && $capital->monto_asignado > 0) {
                    $montoDevolver = (int) $capital->monto_asignado;

                    // 1) Actualizar capital de empresa (último registro)
                    $empresa = DB::table('empresa_capital')->latest('id')->lockForUpdate()->first();
                    if ($empresa) {
                        DB::table('empresa_capital')->where('id', $empresa->id)->update([
                            'capital_disponible' => $empresa->capital_disponible + $montoDevolver,
                            'capital_anterior'   => $empresa->capital_disponible, // opcional pero útil para dif
                        ]);
                    }

                    // 2) Registrar devolución para auditorías
                    RegistroCapital::create([
                        'monto'       => $montoDevolver,
                        'user_id'     => auth()->id(),
                        'tipo_accion' => 'Capital devuelto por prestamista: ' . $usuario->name,
                    ]);

                    // 3) Poner en cero el capital del prestamista
                    $capital->monto_disponible = max(0, (int)$capital->monto_disponible - $montoDevolver);
                    $capital->monto_asignado   = 0;
                    $capital->save();
                }

                DB::commit();
                return redirect()->back()->with('success', 'Capital eliminado y devuelto al capital disponible de la empresa.');
            } catch (\Throwable $e) {
                DB::rollBack();
                return redirect()->back()->with('error', 'No se pudo eliminar el capital: ' . $e->getMessage());
            }
        }

        return redirect()->back()->with('error', 'Usuario inválido para esta operación.');
    }
}
