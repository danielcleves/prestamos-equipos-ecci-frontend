<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegistroController;
use App\Http\Controllers\ConfiguracionWebController;
use App\Http\Controllers\EquipoWebController;
use App\Http\Controllers\IncidenciaWebController;
use App\Http\Controllers\PrestamoWebController;
use App\Http\Controllers\RolWebController;
use App\Http\Controllers\UsuarioWebController;
use Illuminate\Support\Facades\Route;

// Raíz: según sesión/rol
Route::get('/', function () {
    if (! session()->has('auth_token')) {
        return redirect()->route('login');
    }

    return redirect()->route('inicio');
});

// HU-01: Rutas de autenticación
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Auto-registro de solicitantes (correo autorizado en configuración)
Route::get('/registro', [RegistroController::class, 'show'])->name('registro');
Route::post('/registro', [RegistroController::class, 'store'])->name('registro.store');

// Rutas autenticadas (el grupo `web` ya viene aplicado por defecto a routes/web.php)
// Landing por rol (inicio de cada usuario autenticado)
Route::get('/inicio', function () {
    if (! session()->has('auth_token')) {
        return redirect()->route('login');
    }

    return view('inicio');
})->name('inicio');

// HU-02: Gestión de usuarios (exclusivo administrador)
Route::get('/usuarios', [UsuarioWebController::class, 'index'])->name('usuarios.index');
Route::post('/usuarios', [UsuarioWebController::class, 'store'])->name('usuarios.store');
Route::put('/usuarios/{user}', [UsuarioWebController::class, 'update'])->name('usuarios.update');
Route::patch('/usuarios/{id}/{accion}', [UsuarioWebController::class, 'toggleStatus'])
    ->whereIn('accion', ['activar', 'desactivar'])
    ->name('usuarios.toggle');

// Iteración 2: parámetros de las reglas de negocio (exclusivo administrador)
Route::get('/configuracion', [ConfiguracionWebController::class, 'index'])->name('configuracion.index');
Route::put('/configuracion', [ConfiguracionWebController::class, 'update'])->name('configuracion.update');
Route::patch('/categorias/{id}/restriccion', [ConfiguracionWebController::class, 'actualizarRestriccion'])
    ->name('categorias.restriccion');

// RN-02: roles personalizados y cupos de préstamo (exclusivo administrador)
Route::get('/roles', [RolWebController::class, 'index'])->name('roles.index');
Route::post('/roles', [RolWebController::class, 'store'])->name('roles.store');
Route::put('/roles/{id}', [RolWebController::class, 'update'])->whereNumber('id')->name('roles.update');
Route::delete('/roles/{id}', [RolWebController::class, 'destroy'])->whereNumber('id')->name('roles.destroy');

// HU-03/HU-05: Catálogo y gestión de equipos
Route::get('/equipos', [EquipoWebController::class, 'index'])->name('equipos.index');
Route::get('/equipos/crear', [EquipoWebController::class, 'create'])->name('equipos.create');
Route::post('/equipos', [EquipoWebController::class, 'store'])->name('equipos.store');
Route::patch('/equipos/{id}/estado', [EquipoWebController::class, 'updateEstado'])->name('equipos.updateEstado');

// Solicitud de préstamo de equipos (HU-06)
Route::get('/prestamos/solicitar/{equipoId}', [PrestamoWebController::class, 'create'])->name('prestamos.solicitar');
Route::post('/prestamos/solicitar', [PrestamoWebController::class, 'store'])->name('prestamos.store');

// Entregas de equipos (HU-09 / personal autorizado)
Route::get('/entregas-pendientes', [PrestamoWebController::class, 'entregasIndex'])->name('prestamos.entregas');
Route::post('/prestamos/{id}/entrega', [PrestamoWebController::class, 'registrarEntrega'])->name('prestamos.registrarEntrega');

// Devolución de equipos (HU-11 / personal autorizado)
Route::get('/devoluciones', [PrestamoWebController::class, 'devolucionesIndex'])->name('prestamos.devoluciones');
Route::post('/prestamos/{id}/devolucion', [PrestamoWebController::class, 'registrarDevolucion'])->name('prestamos.registrarDevolucion');

// Incidencias por daños (RN-08 / personal autorizado)
Route::get('/incidencias', [IncidenciaWebController::class, 'index'])->name('incidencias.index');
Route::patch('/incidencias/{id}/cerrar', [IncidenciaWebController::class, 'cerrar'])->name('incidencias.cerrar');

// Gestión y detalle de solicitudes (HU-08 / personal autorizado)
Route::get('/solicitudes', [PrestamoWebController::class, 'solicitudesIndex'])->name('prestamos.solicitudes');
Route::patch('/prestamos/{id}/aprobar', [PrestamoWebController::class, 'aprobarSolicitud'])->name('prestamos.aprobar');
Route::patch('/prestamos/{id}/autorizar', [PrestamoWebController::class, 'autorizarSolicitud'])->name('prestamos.autorizar');
Route::patch('/prestamos/{id}/rechazar', [PrestamoWebController::class, 'rechazarSolicitud'])->name('prestamos.rechazar');

// Mis préstamos vigentes - vista del solicitante (HU-10 / KAN-120)
Route::get('/mis-prestamos', [PrestamoWebController::class, 'misPrestamosActivos'])->name('prestamos.mis-activos');
