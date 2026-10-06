<?php

use App\Http\Controllers\Auth\LoginController;
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

// HU-03: Catálogo de equipos
Route::get('/equipos', function () {
    if (! session()->has('auth_token')) {
        return redirect()->route('login');
    }

    return view('equipos.index');
})->name('equipos.index');
