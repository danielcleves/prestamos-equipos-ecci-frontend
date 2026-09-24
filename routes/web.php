<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;

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


// Route::get('/login', function () {
//     return view('auth.login');
// })->name('login');