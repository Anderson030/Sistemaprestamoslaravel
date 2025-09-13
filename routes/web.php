<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CapitalEmpresaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PagoParcialController;
use App\Http\Controllers\PrestamistaController;
use App\Http\Controllers\PrestamoController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\AbonoController;

Route::get('/', fn () => redirect('/admin'));

Auth::routes(['register' => false]);

Route::get('/home', [AdminController::class, 'index'])
    ->name('admin.index.home')
    ->middleware('auth');

Route::get('/admin', [AdminController::class, 'index'])
    ->name('admin.index')
    ->middleware('auth');

/*
|--------------------------------------------------------------------------
| Rutas /admin (protegidas)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {

    /* ───────── Configuraciones ───────── */
    Route::resource('configuraciones', ConfiguracionController::class)
        ->except(['edit', 'update', 'destroy'])
        ->middleware('role:ADMINISTRADOR|SUPERVISOR|DESARROLLADOR');

    Route::get('configuraciones/{id}/edit', [ConfiguracionController::class, 'edit'])
        ->name('configuraciones.edit')
        ->middleware(['can:admin.configuracion.edit', 'role:ADMINISTRADOR|SUPERVISOR|DESARROLLADOR']);

    Route::put('configuraciones/{id}', [ConfiguracionController::class, 'update'])
        ->name('configuraciones.update')
        ->middleware(['can:admin.configuracion.update', 'role:ADMINISTRADOR|SUPERVISOR|DESARROLLADOR']);

    Route::delete('configuraciones/{id}', [ConfiguracionController::class, 'destroy'])
        ->name('configuraciones.destroy')
        ->middleware(['can:admin.configuracion.destroy', 'role:ADMINISTRADOR|SUPERVISOR|DESARROLLADOR']);

    /* ───────── Roles ───────── */
    Route::resource('roles', RoleController::class)
        ->middleware('role:ADMINISTRADOR|SUPERVISOR|DESARROLLADOR');

    Route::get('roles/{id}/asignar', [RoleController::class, 'asignar_roles'])
        ->name('roles.asignar_roles')
        ->middleware('role:ADMINISTRADOR|SUPERVISOR|DESARROLLADOR');

    Route::put('roles/asignar/{id}', [RoleController::class, 'update_asignar'])
        ->name('roles.update_asignar')
        ->middleware('role:ADMINISTRADOR|SUPERVISOR|DESARROLLADOR');

    /* ───────── Usuarios ───────── */
    Route::resource('usuarios', UsuarioController::class)
        ->middleware('role:ADMINISTRADOR|SUPERVISOR|DESARROLLADOR');

    /* ───────── Clientes ───────── */
    Route::resource('clientes', ClienteController::class);

    /* ───────── Préstamos ───────── */
    Route::get('prestamos/cliente/{id}', [PrestamoController::class, 'obtenerCliente'])
        ->name('prestamos.cliente.obtenerCliente');
    Route::get('prestamos/contratos/{id}', [PrestamoController::class, 'contratos'])
        ->name('prestamos.contratos');
    Route::resource('prestamos', PrestamoController::class);

    /* ───────── Pagos ───────── */
    Route::get('pagos/prestamos/cliente/{id}', [PagoController::class, 'cargar_prestamos_cliente'])
        ->name('pagos.cargar_prestamos_cliente');
    Route::get('pagos/prestamos/create/{id}', [PagoController::class, 'create'])
        ->name('pagos.create');
    Route::post('pagos/create/{id}', [PagoController::class, 'store'])
        ->name('pagos.store');
    Route::get('pagos/comprobantedepago/{id}', [PagoController::class, 'comprobantedepago'])
        ->name('pagos.comprobantedepago');
    Route::resource('pagos', PagoController::class)->only(['index', 'show', 'destroy']);

    /* ───────── Notificaciones ───────── */
    Route::get('notificaciones/notificar/{id}', [NotificacionController::class, 'notificar'])
        ->name('notificaciones.notificar');
    Route::resource('notificaciones', NotificacionController::class)->only(['index']);

    /* ───────── Prestamistas ───────── */
    Route::get('prestamistas', [PrestamistaController::class, 'index'])
        ->name('prestamistas.index')
        ->middleware('role:ADMINISTRADOR|SUPERVISOR|DESARROLLADOR');

    Route::get('prestamistas/{id}', [PrestamistaController::class, 'detalle'])
        ->name('prestamistas.detalle')
        ->middleware('role:ADMINISTRADOR|SUPERVISOR|DESARROLLADOR');

    Route::post('prestamistas/reset', [PrestamistaController::class, 'reset'])
        ->name('prestamistas.reset')
        ->middleware('role:ADMINISTRADOR|SUPERVISOR|DESARROLLADOR');

    Route::patch('prestamistas/{id}/eliminar-capital', [PrestamistaController::class, 'eliminarCapital'])
        ->name('prestamistas.eliminarCapital')
        ->middleware('role:ADMINISTRADOR|SUPERVISOR|DESARROLLADOR');

    /* ───────── Backups ───────── */
    Route::get('backups/descargar/{nombreArchivo}', [BackupController::class, 'descargar'])
        ->name('backups.descargar')
        ->middleware('role:ADMINISTRADOR|SUPERVISOR|DESARROLLADOR');

    Route::resource('backups', BackupController::class)->only(['index', 'create'])
        ->middleware('role:ADMINISTRADOR|SUPERVISOR|DESARROLLADOR');

    /* ───────── Pagos parciales (módulo viejo) ───────── */
    Route::resource('pagos_parciales', PagoParcialController::class)
        ->only(['index', 'create', 'store', 'destroy']);

    /* ───────── Capital Empresa ───────── */
    Route::middleware('role:ADMINISTRADOR|SUPERVISOR|DESARROLLADOR')->group(function () {
        Route::get('capital', [CapitalEmpresaController::class, 'index'])->name('capital.index');
        Route::post('capital', [CapitalEmpresaController::class, 'store'])->name('capital.store');
        Route::post('capital/asignar', [CapitalEmpresaController::class, 'asignar'])->name('capital.asignar');
        Route::post('capital/agregar', [CapitalEmpresaController::class, 'agregar'])->name('capital.agregar');

        // Endpoint AJAX para refrescar tarjetas del tablero
        Route::get('capital/resumen', [CapitalEmpresaController::class, 'resumenJson'])
            ->name('capital.resumen');

        // Pasar TODO el bucket de abonos a Caja
        Route::post('capital/pasar-abonos-a-caja', [CapitalEmpresaController::class, 'pasarAbonosACaja'])
            ->name('capital.pasar_abonos_a_caja');

        // Devolver asignaciones por prestamista a Caja
        Route::post('capital/pasar-a-caja', [CapitalEmpresaController::class, 'pasarAsignadoACaja'])
            ->name('capital.pasar_a_caja');

        // Pasar un MONTO específico del bucket de abonos a Caja
        Route::post('capital/pasar-a-caja-monto', [CapitalEmpresaController::class, 'pasarACaja'])
            ->name('capital.pasar_a_caja_monto');
    });

    /* ───────── Auditorías ───────── */
    Route::get('auditorias', [AuditoriaController::class, 'index'])
        ->name('auditorias.index')
        ->middleware('role:ADMINISTRADOR|SUPERVISOR|DESARROLLADOR');

    Route::post('auditorias/gastos', [AuditoriaController::class, 'storeGasto'])
        ->name('auditorias.gastos.store')
        ->middleware('role:ADMINISTRADOR|SUPERVISOR|DESARROLLADOR');

    Route::post('auditorias/pago-parcial', [AuditoriaController::class, 'storePagoParcial'])
        ->name('auditorias.pagosparciales.store')
        ->middleware('role:ADMINISTRADOR|SUPERVISOR|DESARROLLADOR');

    /* ───────── Abonos (pagos parciales por cuota) ───────── */
    // GET (por si navegan directo): redirige a la pantalla del préstamo
    Route::get('prestamos/{prestamo}/abonos', function ($prestamo) {
        return redirect()->route('admin.pagos.create', $prestamo);
    })->name('abonos.index');

    // POST real: registrar abono
    Route::post('prestamos/{prestamo}/abonos', [AbonoController::class, 'store'])
        ->name('abonos.store');

    // Retanqueo
    Route::post('prestamos/{prestamo}/retanqueo', [PrestamoController::class, 'retanqueo'])
        ->name('prestamos.retanqueo');
});
