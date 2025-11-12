<?php

namespace App\Http\Controllers;

use App\Models\CapitalPrestamista;
use App\Models\User;
use App\Models\Prestamo;
use App\Models\Pago;
use App\Models\RegistroCapital;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrestamistaController extends Controller
{
    /**
     * Listado de prestamistas con métricas agregadas
     * (solo ADMIN/SUPERVISOR/DEV ven todo; un PRESTAMISTA no entra aquí con restricciones
     *  porque es un tablero general).
     */
    public function index()
    {
        // Usuarios válidos (excluye DEV explícitamente)
        $usuarios = User::with('roles')
            ->whereHas('roles', function ($q) {
                $q->whereIn('name', ['ADMINISTRADOR', 'SUPERVISOR', 'PRESTAMISTA']);
            })
            ->whereDoesntHave('roles', function ($q) {
                $q->where('name', 'DEV');
            })
            ->get();

        $prestamistas = $usuarios->map(function ($usuario) {
            // Préstamos NO reportados del usuario
            $prestamos = Prestamo::query()
                ->where('idusuario', $usuario->id)
                ->whereNull('reportado')
                ->get(['id','cliente_id','monto_prestado','monto_total']);

            $ids = $prestamos->pluck('id');

            // Sumas (usa float por DECIMAL)
            $totalPrestado  = (float) $prestamos->sum('monto_prestado');
            $totalRecaudado = (float) $prestamos->sum('monto_total');

            // Pagos confirmados NO reportados de esos préstamos
            $totalCobrado = 0.0;
            if ($ids->isNotEmpty()) {
                $totalCobrado = (float) Pago::whereIn('prestamo_id', $ids)
                    ->where('estado', 'Confirmado')
                    ->whereNull('reportado')
                    ->sum('monto_pagado');
            }

            // # clientes distintos
            $clientesAtendidos = $prestamos->unique('cliente_id')->count();

            // Capital asignado actual a ese prestamista (afuera)
            $capital = CapitalPrestamista::where('user_id', $usuario->id)->first();
            $capitalAsignado = (float) ($capital->monto_asignado ?? 0);

            // Ganancia proyectada = totalRecaudado - totalPrestado
            $gananciaProyectada = max(0.0, $totalRecaudado - $totalPrestado);

            // Ganancia “real” aproximada: de lo cobrado, descuenta capital proporcional
            $gananciaReal = 0.0;
            if ($totalRecaudado > 0 && $totalCobrado > 0) {
                $capitalRecuperadoProporcional = ($totalCobrado / $totalRecaudado) * $totalPrestado;
                $gananciaReal = max(0.0, $totalCobrado - $capitalRecuperadoProporcional);
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

        // Totales del pie
        $totalCapitalAsignado   = (float) $prestamistas->sum('capital_asignado');
        $totalPrestado          = (float) $prestamistas->sum('prestado');
        $totalCobrado           = (float) $prestamistas->sum('cobrado');
        $totalRecaudado         = (float) $prestamistas->sum('recaudado');
        $totalGanancia          = (float) $prestamistas->sum('ganancia');
        $totalGananciaCobrada   = (float) $prestamistas->sum('ganancia_real');
        $totalClientes          = (int)   $prestamistas->sum('clientes');

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

    /**
     * Detalle de un prestamista.
     * - Si el usuario autenticado es PRESTAMISTA, solo puede ver su propio detalle.
     */
    public function detalle($id)
    {
        $usuario = User::with('roles')->findOrFail($id);

        if (!$usuario->hasAnyRole(['ADMINISTRADOR', 'SUPERVISOR', 'PRESTAMISTA'])) {
            abort(403, 'Este usuario no es un prestamista válido.');
        }

        // Regla de visibilidad: un PRESTAMISTA solo puede ver su propio detalle
        if (auth()->user()->hasRole('PRESTAMISTA') && (int) $usuario->id !== (int) auth()->id()) {
            abort(403, 'No tienes permiso para ver este detalle.');
        }

        $prestamos = Prestamo::where('idusuario', $usuario->id)
            ->whereNull('reportado')
            ->with('cliente')
            ->get(['id','cliente_id','monto_prestado','monto_total','estado']);

        $idsPrestamos = $prestamos->pluck('id');

        $pagos = collect();
        if ($idsPrestamos->isNotEmpty()) {
            $pagos = Pago::whereIn('prestamo_id', $idsPrestamos)
                ->where('estado', 'Confirmado')
                ->whereNull('reportado')
                ->with(['prestamo.cliente'])
                ->orderBy('fecha_cancelado')
                ->get();
        }

        return view('admin.prestamistas.show', compact('usuario', 'prestamos', 'pagos'));
    }

    /**
     * Resetea indicadores globales (marca TODO como reportado y pone capital prestamista en 0).
     * Recomendado SOLO para ADMIN/SUPERVISOR. No crea registros de devolución.
     */
    public function reset()
    {
        if (!auth()->user()->hasAnyRole(['ADMINISTRADOR', 'SUPERVISOR'])) {
            abort(403, 'No tienes permiso para esta operación.');
        }

        DB::transaction(function () {
            // Marca todo como reportado con timestamp en TZ de la app
            DB::table('prestamos')->update(['reportado' => now()]);
            DB::table('pagos')->update(['reportado' => now()]);

            // Capital de prestamistas a cero (asignado y disponible)
            DB::table('capital_prestamistas')->update([
                'monto_asignado'   => 0,
                'monto_disponible' => 0,
            ]);
        });

        return redirect()
            ->route('admin.prestamistas.index')
            ->with('mensaje', 'Los totales fueron reiniciados correctamente.')
            ->with('icono', 'success');
    }

    /**
     * Devuelve TODO el capital asignado de un prestamista a la caja de empresa
     * y deja trazabilidad en registros_capital.
     */
    public function eliminarCapital($id)
    {
        if (!auth()->user()->hasAnyRole(['ADMINISTRADOR', 'SUPERVISOR'])) {
            abort(403, 'No tienes permiso para esta operación.');
        }

        $usuario = User::findOrFail($id);

        if (
            $usuario->hasAnyRole(['ADMINISTRADOR', 'SUPERVISOR', 'PRESTAMISTA']) &&
            !$usuario->hasRole('DEV')
        ) {
            DB::beginTransaction();
            try {
                // Bloquear el registro del prestamista
                $capital = CapitalPrestamista::where('user_id', $usuario->id)
                    ->lockForUpdate()
                    ->first();

                if ($capital && (float) $capital->monto_asignado > 0) {
                    $montoDevolver = (float) $capital->monto_asignado;

                    // 1) Actualizar capital de empresa (último registro)
                    $empresa = DB::table('empresa_capital')->latest('id')->lockForUpdate()->first();
                    if ($empresa) {
                        DB::table('empresa_capital')->where('id', $empresa->id)->update([
                            'capital_anterior'   => (int) $empresa->capital_disponible,
                            'capital_disponible' => (int) $empresa->capital_disponible + (int) round($montoDevolver, 0),
                        ]);
                    }

                    // 2) Registrar devolución (Auditorías lo toma como negativo en "Asignado del día")
                    RegistroCapital::create([
                        'monto'       => (int) round($montoDevolver, 0),
                        'user_id'     => auth()->id(),
                        'tipo_accion' => 'Capital devuelto por prestamista: ' . $usuario->name,
                    ]);

                    // 3) Poner en cero el capital del prestamista
                    $capital->monto_disponible = max(0, (int) $capital->monto_disponible - (int) round($montoDevolver, 0));
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
