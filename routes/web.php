<?php
use App\Http\Controllers\PagoParcialController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Auth::routes(['register' => false]);

Route::get('/home', [App\Http\Controllers\AdminController::class, 'index'])->name('admin.index.home')->middleware('auth');
Route::get('/admin', [App\Http\Controllers\AdminController::class, 'index'])->name('admin.index')->middleware('auth');

// Rutas protegidas por auth y con prefijo admin
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    // Configuraciones
    Route::resource('configuraciones', App\Http\Controllers\ConfiguracionController::class)->except(['edit', 'update', 'destroy']);
    Route::get('configuraciones/{id}/edit', [App\Http\Controllers\ConfiguracionController::class, 'edit'])->name('configuraciones.edit')->middleware('can:admin.configuracion.edit');
    Route::put('configuraciones/{id}', [App\Http\Controllers\ConfiguracionController::class, 'update'])->name('configuraciones.update')->middleware('can:admin.configuracion.update');
    Route::delete('configuraciones/{id}', [App\Http\Controllers\ConfiguracionController::class, 'destroy'])->name('configuraciones.destroy')->middleware('can:admin.configuracion.destroy');

    // Roles
    Route::resource('roles', App\Http\Controllers\RoleController::class);
    Route::get('roles/{id}/asignar', [App\Http\Controllers\RoleController::class, 'asignar_roles'])->name('roles.asignar_roles');
    Route::put('roles/asignar/{id}', [App\Http\Controllers\RoleController::class, 'update_asignar'])->name('roles.update_asignar');

    // Usuarios
    Route::resource('usuarios', App\Http\Controllers\UsuarioController::class);

    // Clientes
    Route::resource('clientes', App\Http\Controllers\ClienteController::class);

    // Prestamos
    Route::get('prestamos/cliente/{id}', [App\Http\Controllers\PrestamoController::class, 'obtenerCliente'])->name('prestamos.cliente.obtenerCliente');
    Route::get('prestamos/contratos/{id}', [App\Http\Controllers\PrestamoController::class, 'contratos'])->name('prestamos.contratos');
    Route::resource('prestamos', App\Http\Controllers\PrestamoController::class);

    // Pagos
    Route::get('pagos/prestamos/cliente/{id}', [App\Http\Controllers\PagoController::class, 'cargar_prestamos_cliente'])->name('pagos.cargar_prestamos_cliente');
    Route::get('pagos/prestamos/create/{id}', [App\Http\Controllers\PagoController::class, 'create'])->name('pagos.create');
    Route::post('pagos/create/{id}', [App\Http\Controllers\PagoController::class, 'store'])->name('pagos.store');
    Route::get('pagos/comprobantedepago/{id}', [App\Http\Controllers\PagoController::class, 'comprobantedepago'])->name('pagos.comprobantedepago');
    Route::resource('pagos', App\Http\Controllers\PagoController::class)->only(['index', 'show', 'destroy']);

    // Notificaciones
    Route::get('notificaciones/notificar/{id}', [App\Http\Controllers\NotificacionController::class, 'notificar'])->name('notificaciones.notificar');
    Route::resource('notificaciones', App\Http\Controllers\NotificacionController::class)->only(['index']);

    // Prestamistas
    Route::get('prestamistas', [App\Http\Controllers\PrestamistaController::class, 'index'])->name('prestamistas.index');
    Route::get('prestamistas/{id}', [App\Http\Controllers\PrestamistaController::class, 'detalle'])->name('prestamistas.detalle');
    Route::post('prestamistas/reset', [App\Http\Controllers\PrestamistaController::class, 'reset'])->name('prestamistas.reset');
    Route::patch('/prestamistas/{id}/eliminar-capital', [App\Http\Controllers\PrestamistaController::class, 'eliminarCapital'])->name('prestamistas.eliminarCapital');


    // Backups
    Route::get('backups/descargar/{nombreArchivo}', [App\Http\Controllers\BackupController::class, 'descargar'])->name('backups.descargar');
    Route::resource('backups', App\Http\Controllers\BackupController::class)->only(['index', 'create']);

    // Pagos parciales
    Route::resource('pagos_parciales', PagoParcialController::class)->only(['index', 'create', 'store', 'destroy']);
// Capital Empresa (control de acceso interno en el controlador)
Route::get('capital', [App\Http\Controllers\CapitalEmpresaController::class, 'index'])->name('capital.index');
Route::post('capital', [App\Http\Controllers\CapitalEmpresaController::class, 'store'])->name('capital.store');
Route::post('capital/asignar', [App\Http\Controllers\CapitalEmpresaController::class, 'asignarCapital'])->name('capital.asignar');

});
