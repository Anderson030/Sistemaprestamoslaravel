<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return redirect('/admin');
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\AdminController::class, 'index'])->name('admin.index.home')->middleware('auth');


Route::get('/admin', [App\Http\Controllers\AdminController::class, 'index'])->name('admin.index')->middleware('auth');


//rutas para configuraciones
Route::get('/admin/configuraciones', [App\Http\Controllers\ConfiguracionController::class, 'index'])->name('admin.configuracion.index')->middleware('auth','can:admin.configuracion.index');
Route::get('/admin/configuraciones/create', [App\Http\Controllers\ConfiguracionController::class, 'create'])->name('admin.configuracion.create')->middleware('auth','can:admin.configuracion.create');
Route::post('/admin/configuraciones/create', [App\Http\Controllers\ConfiguracionController::class, 'store'])->name('admin.configuracion.store')->middleware('auth','can:admin.configuracion.store');
Route::get('/admin/configuraciones/{id}', [App\Http\Controllers\ConfiguracionController::class, 'show'])->name('admin.configuracion.show')->middleware('auth','can:admin.configuracion.show');
Route::get('/admin/configuraciones/{id}/edit', [App\Http\Controllers\ConfiguracionController::class, 'edit'])->name('admin.configuracion.edit')->middleware('auth','can:admin.configuracion.edit');
Route::put('/admin/configuraciones/{id}', [App\Http\Controllers\ConfiguracionController::class, 'update'])->name('admin.configuracion.update')->middleware('auth','can:admin.configuracion.update');
Route::delete('/admin/configuraciones/{id}', [App\Http\Controllers\ConfiguracionController::class, 'destroy'])->name('admin.configuracion.destroy')->middleware('auth','can:admin.configuracion.destroy');

//rutas para roles
Route::get('/admin/roles', [App\Http\Controllers\RoleController::class, 'index'])->name('admin.roles.index')->middleware('auth','can:admin.roles.index');
Route::get('/admin/roles/create', [App\Http\Controllers\RoleController::class, 'create'])->name('admin.roles.create')->middleware('auth','can:admin.roles.create');
Route::post('/admin/roles/create', [App\Http\Controllers\RoleController::class, 'store'])->name('admin.roles.store')->middleware('auth','can:admin.roles.store');
Route::get('/admin/roles/{id}', [App\Http\Controllers\RoleController::class, 'show'])->name('admin.roles.show')->middleware('auth','can:admin.roles.show');
Route::get('/admin/roles/{id}/asignar', [App\Http\Controllers\RoleController::class, 'asignar_roles'])->name('admin.roles.asignar_roles')->middleware('auth','can:admin.roles.asignar_roles');
Route::put('/admin/roles/asignar/{id}', [App\Http\Controllers\RoleController::class, 'update_asignar'])->name('admin.roles.update_asignar')->middleware('auth','can:admin.roles.update_asignar');
Route::get('/admin/roles/{id}/edit', [App\Http\Controllers\RoleController::class, 'edit'])->name('admin.roles.edit')->middleware('auth','can:admin.roles.edit');
Route::put('/admin/roles/{id}', [App\Http\Controllers\RoleController::class, 'update'])->name('admin.roles.update')->middleware('auth','can:admin.roles.update');
Route::delete('/admin/roles/{id}', [App\Http\Controllers\RoleController::class, 'destroy'])->name('admin.roles.destroy')->middleware('auth','can:admin.roles.destroy');

//rutas para usuarios
Route::get('/admin/usuarios', [App\Http\Controllers\UsuarioController::class, 'index'])->name('admin.usuarios.index')->middleware('auth','can:admin.usuarios.index');
Route::get('/admin/usuarios/create', [App\Http\Controllers\UsuarioController::class, 'create'])->name('admin.usuarios.create')->middleware('auth','can:admin.usuarios.create');
Route::post('/admin/usuarios/create', [App\Http\Controllers\UsuarioController::class, 'store'])->name('admin.usuarios.store')->middleware('auth','can:admin.usuarios.store');
Route::get('/admin/usuarios/{id}', [App\Http\Controllers\UsuarioController::class, 'show'])->name('admin.usuarios.show')->middleware('auth','can:admin.usuarios.show');
Route::get('/admin/usuarios/{id}/edit', [App\Http\Controllers\UsuarioController::class, 'edit'])->name('admin.usuarios.edit')->middleware('auth','can:admin.usuarios.edit');
Route::put('/admin/usuarios/{id}', [App\Http\Controllers\UsuarioController::class, 'update'])->name('admin.usuarios.update')->middleware('auth','can:admin.usuarios.update');
Route::delete('/admin/usuarios/{id}', [App\Http\Controllers\UsuarioController::class, 'destroy'])->name('admin.usuarios.destroy')->middleware('auth','can:admin.usuarios.destroy');

//rutas para clientes
Route::get('/admin/clientes', [App\Http\Controllers\ClienteController::class, 'index'])->name('admin.clientes.index')->middleware('auth','can:admin.clientes.index');
Route::get('/admin/clientes/create', [App\Http\Controllers\ClienteController::class, 'create'])->name('admin.clientes.create')->middleware('auth','can:admin.clientes.create');
Route::post('/admin/clientes/create', [App\Http\Controllers\ClienteController::class, 'store'])->name('admin.clientes.store')->middleware('auth','can:admin.clientes.store');
Route::get('/admin/clientes/{id}', [App\Http\Controllers\ClienteController::class, 'show'])->name('admin.clientes.show')->middleware('auth','can:admin.clientes.show');
Route::get('/admin/clientes/{id}/edit', [App\Http\Controllers\ClienteController::class, 'edit'])->name('admin.clientes.edit')->middleware('auth','can:admin.clientes.edit');
Route::put('/admin/clientes/{id}', [App\Http\Controllers\ClienteController::class, 'update'])->name('admin.clientes.update')->middleware('auth','can:admin.clientes.update');
Route::delete('/admin/clientes/{id}', [App\Http\Controllers\ClienteController::class, 'destroy'])->name('admin.clientes.destroy')->middleware('auth','can:admin.clientes.destroy');

//rutas para prestamos
Route::get('/admin/prestamos', [App\Http\Controllers\PrestamoController::class, 'index'])->name('admin.prestamos.index')->middleware('auth','can:admin.prestamos.index');
Route::get('/admin/prestamos/create', [App\Http\Controllers\PrestamoController::class, 'create'])->name('admin.prestamos.create')->middleware('auth','can:admin.prestamos.create');
Route::get('/admin/prestamos/cliente/{id}', [App\Http\Controllers\PrestamoController::class, 'obtenerCliente'])->name('admin.prestamos.cliente.obtenerCliente')->middleware('auth','can:admin.prestamos.cliente.obtenerCliente');
Route::post('/admin/prestamos/create', [App\Http\Controllers\PrestamoController::class, 'store'])->name('admin.prestamos.store')->middleware('auth','can:admin.prestamos.store');
Route::get('/admin/prestamos/{id}', [App\Http\Controllers\PrestamoController::class, 'show'])->name('admin.prestamos.show')->middleware('auth','can:admin.prestamos.show');
Route::get('/admin/prestamos/contratos/{id}', [App\Http\Controllers\PrestamoController::class, 'contratos'])->name('admin.prestamos.contratos')->middleware('auth','can:admin.prestamos.contratos');
Route::get('/admin/prestamos/{id}/edit', [App\Http\Controllers\PrestamoController::class, 'edit'])->name('admin.prestamos.edit')->middleware('auth','can:admin.prestamos.edit');
Route::put('/admin/prestamos/{id}', [App\Http\Controllers\PrestamoController::class, 'update'])->name('admin.prestamos.update')->middleware('auth','can:admin.prestamos.update');
Route::delete('/admin/prestamos/{id}', [App\Http\Controllers\PrestamoController::class, 'destroy'])->name('admin.prestamos.destroy')->middleware('auth','can:admin.prestamos.destroy');


//rutas para pagos
Route::get('/admin/pagos', [App\Http\Controllers\PagoController::class, 'index'])->name('admin.pagos.index')->middleware('auth','can:admin.pagos.index');
Route::get('/admin/pagos/prestamos/cliente/{id}', [App\Http\Controllers\PagoController::class, 'cargar_prestamos_cliente'])->name('admin.pagos.cargar_prestamos_cliente')->middleware('auth','can:admin.pagos.cargar_prestamos_cliente');
Route::get('/admin/pagos/prestamos/create/{id}', [App\Http\Controllers\PagoController::class, 'create'])->name('admin.pagos.create')->middleware('auth','can:admin.pagos.create');
Route::post('/admin/pagos/create/{id}', [App\Http\Controllers\PagoController::class, 'store'])->name('admin.pagos.store')->middleware('auth','can:admin.pagos.store');
Route::get('/admin/pagos/comprobantedepago/{id}', [App\Http\Controllers\PagoController::class, 'comprobantedepago'])->name('admin.pagos.comprobantedepago')->middleware('auth','can:admin.pagos.comprobantedepago');
Route::get('/admin/pagos/{id}', [App\Http\Controllers\PagoController::class, 'show'])->name('admin.pagos.show')->middleware('auth','can:admin.pagos.show');
Route::post('/admin/pagos/{id}', [App\Http\Controllers\PagoController::class, 'destroy'])->name('admin.pagos.destroy')->middleware('auth','can:admin.pagos.destroy');

//rutas para notificaciones
Route::get('/admin/notificaciones', [App\Http\Controllers\NotificacionController::class, 'index'])->name('admin.notificaciones.index')->middleware('auth','can:admin.notificaciones.index');
Route::get('/admin/notificaciones/notificar/{id}', [App\Http\Controllers\NotificacionController::class, 'notificar'])->name('admin.notificaciones.notificar')->middleware('auth','can:admin.notificaciones.notificar');

//rutas para backups
Route::get('/admin/backups', [App\Http\Controllers\BackupController::class, 'index'])->name('admin.backups.index')->middleware('auth','can:admin.backups.index');
Route::get('/admin/backups/create', [App\Http\Controllers\BackupController::class, 'create'])->name('admin.backups.create')->middleware('auth','can:admin.backups.create');
Route::get('/admin/backups/descargar/{nombreArchivo}', [App\Http\Controllers\BackupController::class, 'descargar'])->name('admin.backups.descargar')->middleware('auth','can:admin.backups.descargar');

