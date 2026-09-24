<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\UsuarioWebController;

Route::get('/', function () {
    return redirect()->route('login');
});

// Hu-01: Rutas de Auth
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

//ruta luego de Logearse
Route::middleware(['web'])->group(function () {
    Route::get('/usuarios', function () {
        if (!session()->has('auth_token')) {
            return redirect()->route('login');
        }
        return view('usuarios.index');
    })->name('usuarios.index');
});



// Rutas protegidas por sesión
Route::middleware(['web'])->group(function () {
    // HU-02: Gestión de Usuarios (Exclusivo Administrador)
    Route::get('/usuarios', [UsuarioWebController::class, 'index'])->name('usuarios.index');
    Route::post('/usuarios', [UsuarioWebController::class, 'store'])->name('usuarios.store');
    Route::post('/usuarios/update', [UsuarioWebController::class, 'update'])->name('usuarios.update');
    Route::patch('/usuarios/{id}/{accion}', [UsuarioWebController::class, 'toggleStatus'])->name('usuarios.toggle');

    // HU-03: Catálogo de Equipos (Para solicitantes, docentes y administradores)
    Route::get('/equipos', function () {
        if (!session()->has('auth_token')) {
            return redirect()->route('login');
        }
        return view('equipos.index');
    })->name('equipos.index');
});