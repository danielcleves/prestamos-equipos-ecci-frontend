<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\UsuarioWebController;
use Illuminate\Support\Facades\Route;

// Ruta raíz
Route::get('/', function () {
    if (session()->has('api_token')) {
        return redirect()->route('usuarios.index');
    }

    return redirect()->route('login');
});

// HU-01: Autenticación
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Rutas protegidas
    // HU-02: Gestión de Usuarios (Exclusivo Administrador)
    Route::get('/usuarios', [UsuarioWebController::class, 'index'])->name('usuarios.index');
    Route::post('/usuarios', [UsuarioWebController::class, 'store'])->name('usuarios.store');
    Route::put('/usuarios/{usuario}', [UsuarioWebController::class, 'update'])->name('usuarios.update');
    Route::patch('/usuarios/{id}/{accion}', [UsuarioWebController::class, 'toggleStatus'])->name('usuarios.toggle');

    // HU-03: Catálogo de Equipos
    Route::get('/equipos', function () {
        if (! session()->has('api_token')) {
            return redirect()->route('login');
        }

        return view('equipos.index');
    })->name('equipos.index');

