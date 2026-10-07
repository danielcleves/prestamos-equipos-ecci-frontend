<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

class LoginController extends Controller
{
    /**
     * Muestra el formulario de inicio de sesión.
     */
    public function showLoginForm()
    {
        if (session()->has('api_token')) {
            return $this->redirectByRole(session('user'));
        }

        return view('auth.login');
    }

    /**
     * Procesa la autenticación contra la API del backend.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $apiUrl = config('services.backend.url');

        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->post("{$apiUrl}/login", [
                    'email' => $credentials['email'],
                    'password' => $credentials['password'],
                ]);
        } catch (\Exception $e) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'No fue posible comunicarse con el servicio de autenticación.']);
        }

        if ($response->successful()) {
            $data = $response->json();

            // Validar que la respuesta contenga el token
            if (empty($data['token'])) {
                return back()->withInput($request->only('email'))
                    ->withErrors(['email' => 'Respuesta de autenticación inválida del servidor.']);
            }

            // Extraer el usuario limpio
            $user = $data['user'] ?? $data;

            // Guardar en la sesión de Laravel
            session([
                'auth_token' => $data['token'],
                'api_token' => $data['token'],
                'user' => $user,
            ]);

            $request->session()->regenerate();

            return $this->redirectByRole($user);
        }

        // Manejo de errores de credenciales devueltos por el backend (401, 422)
        $errorMessage = $response->json('message') ?? 'Las credenciales ingresadas son incorrectas.';

        return back()->withInput($request->only('email'))
            ->withErrors(['email' => $errorMessage]);
    }

    /**
     * Cierra la sesión revocando el token en el backend.
     */
    public function logout(Request $request)
    {
        $token = session('auth_token') ?? session('api_token');
        $apiUrl = config('services.backend.url');

        if ($token) {
            try {
                Http::timeout(5)
                    ->withToken($token)
                    ->acceptJson()
                    ->post("{$apiUrl}/logout");
            } catch (\Exception $e) {
                // Registro silencioso o log; continúa con el logout local
            }
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Sesión cerrada correctamente.');
    }

    /**
     * Redirige al usuario según su rol de manera segura.
     */
    private function redirectByRole(?array $user)
    {
        // El backend devuelve los roles como array en $user['roles']
        $roles = $user['roles'] ?? [];
        $rol = is_array($roles) ? ($roles[0] ?? null) : ($user['role'] ?? $user['rol'] ?? null);

        // Administrador: Gestión de usuarios
        if ($rol === 'admin' && Route::has('usuarios.index')) {
            return redirect()->route('usuarios.index');
        }

        // Encargado: Gestión de préstamos
        if ($rol === 'encargado' && Route::has('prestamos.gestion')) {
            return redirect()->route('prestamos.gestion');
        }

        // Estudiante / Solicitante: Catálogo de equipos
        if (Route::has('equipos.index')) {
            return redirect()->route('equipos.index');
        }

        if (Route::has('prestamos.catalogo')) {
            return redirect()->route('prestamos.catalogo');
        }

        // Fallback por defecto si no existen las otras rutas aún
        return redirect()->route('usuarios.index');
    }
}
