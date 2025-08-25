<?php

namespace App\Http\Controllers;

use App\Models\CapitalPrestamista;
use App\Models\User;
use App\Models\Prestamo;
use App\Models\Pago;
use App\Models\RegistroCapital;           // log de asignaciones/devoluciones
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrestamistaController extends Controller
{
    public function index()
    {
        // Usuarios con rol válido (excluye DEV)
        $prestamistas = User::whereHas('roles', function ($q) {
                $q->whereIn('name', ['ADMINISTRADOR', 'SUPERVISOR', 'PRESTAMISTA']);
            })
            ->whereDoesntHave('roles', function ($q) {
                $q->where('name', 'DEV');
            })
            ->get()
            ->map(function ($usuario) {

                // Préstamos del usuario (no reportados)
                $prestamos = Prestamo::where('idusuario', $usuario->id)
                    ->whereNull('reportado')
                    ->get();

                $totalPrestado  = (int) $prestamos->sum('monto_prestado');
                $totalRecaudado = (int) $prestamos->sum('monto_total');
                $idsPrestamos   = $prestamos->pluck('id');

                // Pagos confirmados (no reportados)
                $totalCobrado = (int) Pago::whereIn('prestamo_id', $idsPrestamos)
                    ->where('estado', 'Confirmado')
                    ->whereNull('reportado')
                    ->sum('monto_pagado');

                // # de clientes distintos atendidos
                $clientesAtendidos = $prestamos->unique('cliente_id')->count();

                // Capital asignado al prestamista
                $capital = CapitalPrestamista::where('user_id', $usuario->id)->first();
                $capitalAsignado = $capital ? (int) $capital->monto_asignado : 0;

                // Ganancia proyectada (intereses) y “real” cobrada proporcionalmente
                $gananciaProyectada = $totalRecaudado - $totalPrestado;
                $gananciaReal = 0;
                if ($totalRecaudado > 0 && $totalCobrado > 0) {
                    // Parte capital ya “recuperada” = (cobrado / recaudado) * capital
                    $capitalRecuperadoProporcional = ($totalCobrado / $totalRecaudado) * $totalPrestado;
                    $gananciaReal = $totalCobrado - $capitalRecuperadoProporcional;
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

        // Totales generales para el pie
        $totalCapitalAsignado   = (int) $prestamistas->sum('capital_asignado');
        $totalPrestado          = (int) $prestamistas->sum('prestado');
        $totalCobrado           = (int) $prestamistas->sum('cobrado');
        $totalRecaudado         = (int) $prestamistas->sum('recaudado');
        $totalGanancia          = (int) $prestamistas->sum('ganancia');
        $totalGananciaCobrada   = (int) $prestamistas->sum('ganancia_real');
        $totalClientes          = (int) $prestamistas->sum('clientes');

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

    /**
     * Resetea indicadores (marca todo como "reportado" y pone capital asignado en 0).
     * No crea registros de devolución.
     */
    public function reset()
    {
        DB::table('prestamos')->update(['reportado' => now()]);
        DB::table('pagos')->update(['reportado' => now()]);
        DB::table('capital_prestamistas')->update(['monto_asignado' => 0, 'monto_disponible' => 0]);

        return redirect()
            ->route('admin.prestamistas.index')
            ->with('mensaje', 'Los totales fueron reiniciados correctamente.')
            ->with('icono', 'success');
    }

    /**
     * Elimina TODO el capital del prestamista y lo devuelve al capital de empresa.
     * Registra el movimiento en registros_capital con tipo:
     * "Capital devuelto por prestamista: NOMBRE"
     * Esto es lo que usa Auditorías como valor NEGATIVO en "Asignado (del día)".
     */
    public function eliminarCapital($id)
    {
        $usuario = User::findOrFail($id);

        if (
            $usuario->hasAnyRole(['ADMINISTRADOR', 'SUPERVISOR', 'PRESTAMISTA']) &&
            !$usuario->hasRole('DEV')
        ) {
            DB::beginTransaction();
            try {
                // Bloqueamos el registro del prestamista
                $capital = CapitalPrestamista::where('user_id', $usuario->id)
                    ->lockForUpdate()
                    ->first();

                if ($capital && (int)$capital->monto_asignado > 0) {
                    $montoDevolver = (int) $capital->monto_asignado;

                    // 1) Actualizar capital de empresa (último registro)
                    $empresa = DB::table('empresa_capital')->latest('id')->lockForUpdate()->first();
                    if ($empresa) {
                        DB::table('empresa_capital')->where('id', $empresa->id)->update([
                            'capital_anterior'   => $empresa->capital_disponible,
                            'capital_disponible' => $empresa->capital_disponible + $montoDevolver,
                        ]);
                    }

                    // 2) Registrar devolución para que Auditorías lo refleje
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
                return back()->with('success', 'Capital eliminado y devuelto al capital disponible de la empresa.');
            } catch (\Throwable $e) {
                DB::rollBack();
                return back()->with('error', 'No se pudo eliminar el capital: ' . $e->getMessage());
            }
        }

        return back()->with('error', 'Usuario inválido para esta operación.');
    }
}
