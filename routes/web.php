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

Route::get('/', function () {
    return redirect('/admin');
});

Auth::routes(['register' => false]);

Route::get('/home', [AdminController::class, 'index'])
    ->name('admin.index.home')
    ->middleware('auth');

Route::get('/admin', [AdminController::class, 'index'])
    ->name('admin.index')
    ->middleware('auth');

/* ================================
 *  Rutas protegidas con prefijo /admin
 * ================================*/
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {

    /* ───────────── Configuraciones (bloqueado a PRESTAMISTA) ───────────── */
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

    /* ───────────── Roles (bloqueado a PRESTAMISTA) ───────────── */
    Route::resource('roles', RoleController::class)
        ->middleware('role:ADMINISTRADOR|SUPERVISOR|DESARROLLADOR');

    Route::get('roles/{id}/asignar', [RoleController::class, 'asignar_roles'])
        ->name('roles.asignar_roles')
        ->middleware('role:ADMINISTRADOR|SUPERVISOR|DESARROLLADOR');

    Route::put('roles/asignar/{id}', [RoleController::class, 'update_asignar'])
        ->name('roles.update_asignar')
        ->middleware('role:ADMINISTRADOR|SUPERVISOR|DESARROLLADOR');

    /* ───────────── Usuarios (bloqueado a PRESTAMISTA) ───────────── */
    Route::resource('usuarios', UsuarioController::class)
        ->middleware('role:ADMINISTRADOR|SUPERVISOR|DESARROLLADOR');

    /* ───────────── Clientes (acceso como lo tenías) ───────────── */
    Route::resource('clientes', ClienteController::class);

    /* ───────────── Préstamos (acceso como lo tenías) ───────────── */
    Route::get('prestamos/cliente/{id}', [PrestamoController::class, 'obtenerCliente'])
        ->name('prestamos.cliente.obtenerCliente');
    Route::get('prestamos/contratos/{id}', [PrestamoController::class, 'contratos'])
        ->name('prestamos.contratos');
    Route::resource('prestamos', PrestamoController::class);

    /* ───────────── Pagos (acceso como lo tenías) ───────────── */
    Route::get('pagos/prestamos/cliente/{id}', [PagoController::class, 'cargar_prestamos_cliente'])
        ->name('pagos.cargar_prestamos_cliente');
    Route::get('pagos/prestamos/create/{id}', [PagoController::class, 'create'])
        ->name('pagos.create');
    Route::post('pagos/create/{id}', [PagoController::class, 'store'])
        ->name('pagos.store');
    Route::get('pagos/comprobantedepago/{id}', [PagoController::class, 'comprobantedepago'])
        ->name('pagos.comprobantedepago');
    Route::resource('pagos', PagoController::class)->only(['index', 'show', 'destroy']);

    /* ───────────── Notificaciones (acceso como lo tenías) ───────────── */
    Route::get('notificaciones/notificar/{id}', [NotificacionController::class, 'notificar'])
        ->name('notificaciones.notificar');
    Route::resource('notificaciones', NotificacionController::class)->only(['index']);

    /* ───────────── Prestamistas (bloqueado a PRESTAMISTA) ───────────── */
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

    /* ───────────── Backups (bloqueado a PRESTAMISTA) ───────────── */
    Route::get('backups/descargar/{nombreArchivo}', [BackupController::class, 'descargar'])
        ->name('backups.descargar')
        ->middleware('role:ADMINISTRADOR|SUPERVISOR|DESARROLLADOR');

    Route::resource('backups', BackupController::class)->only(['index', 'create'])
        ->middleware('role:ADMINISTRADOR|SUPERVISOR|DESARROLLADOR');

    /* ───────────── Pagos parciales (módulo existente) ───────────── */
    Route::resource('pagos_parciales', PagoParcialController::class)
        ->only(['index', 'create', 'store', 'destroy']);

    /* ───────────── Capital Empresa (BLOQUEADO a PRESTAMISTA) ───────────── */
    Route::middleware('role:ADMINISTRADOR|SUPERVISOR|DESARROLLADOR')->group(function () {
        Route::get('capital', [CapitalEmpresaController::class, 'index'])
            ->name('capital.index');
        Route::post('capital', [CapitalEmpresaController::class, 'store'])
            ->name('capital.store');
        Route::post('capital/asignar', [CapitalEmpresaController::class, 'asignar'])
            ->name('capital.asignar');
        Route::post('capital/agregar', [CapitalEmpresaController::class, 'agregar'])
            ->name('capital.agregar');
    });

    /* ───────────── Auditorías (bloqueado a PRESTAMISTA) ───────────── */
    Route::get('auditorias', [AuditoriaController::class, 'index'])
        ->name('auditorias.index')
        ->middleware('role:ADMINISTRADOR|SUPERVISOR|DESARROLLADOR');

    Route::post('auditorias/gastos', [AuditoriaController::class, 'storeGasto'])
        ->name('auditorias.gastos.store')
        ->middleware('role:ADMINISTRADOR|SUPERVISOR|DESARROLLADOR');

    // NUEVO: registrar pago parcial del día (modal)
    Route::post('auditorias/pago-parcial', [AuditoriaController::class, 'storePagoParcial'])
        ->name('auditorias.pagosparciales.store')
        ->middleware('role:ADMINISTRADOR|SUPERVISOR|DESARROLLADOR');
});
