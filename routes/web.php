<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// HU-01: Rutas de autenticación
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Rutas protegidas por sesión
Route::middleware(['web'])->group(function () {
    // HU-02: Gestión de usuarios (vista del dashboard)
    Route::get('/usuarios', function () {
        if (! session()->has('auth_token')) {
            return redirect()->route('login');
        }

        return view('usuarios.index');
    })->name('usuarios.index');
});
