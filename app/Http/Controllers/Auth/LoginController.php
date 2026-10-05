<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $apiUrl = config('services.backend.url');

        try {
            $response = Http::acceptJson()->timeout(10)->post("{$apiUrl}/login", [
                'email' => $credentials['email'],
                'password' => $credentials['password'],
            ]);

            if ($response->status() === 429) {
                throw ValidationException::withMessages([
                    'email' => ['Demasiados intentos de acceso. Espera un minuto antes de reintentar.'],
                ]);
            }

            if ($response->failed()) {
                $error = $response->json('message') ?? 'Las credenciales ingresadas son incorrectas o el usuario está inactivo.';
                throw ValidationException::withMessages([
                    'email' => [$error],
                ]);
            }

            $data = $response->json();

            // Extraer el token de Sanctum (según cómo lo retorne AuthController: token o access_token)
            $token = $data['token'] ?? $data['access_token'] ?? null;
            $user = $data['user'] ?? null;

            if (! $token) {
                throw ValidationException::withMessages([
                    'email' => ['Respuesta de autenticación inválida del servidor.'],
                ]);
            }

            // Si el backend no devolvió el usuario en el login, lo consultamos con /api/me
            if (! $user) {
                $meResponse = Http::withToken($token)->acceptJson()->timeout(10)->get("{$apiUrl}/me");

                if ($meResponse->successful()) {
                    // El backend responde { user: {...} }; se guarda solo el usuario
                    $user = $meResponse->json('user') ?? $meResponse->json();
                }
            }

            // Guardar en la sesión del cliente web
            session([
                'auth_token' => $token,
                'user' => $user,
            ]);

            $request->session()->regenerate();

            return redirect()->intended('/usuarios');
        } catch (ConnectionException $e) {
            throw ValidationException::withMessages([
                'email' => ['No se pudo establecer conexión con el servicio backend.'],
            ]);
        }
    }

    public function logout(Request $request)
    {
        $token = session('auth_token');

        if ($token) {
            try {
                Http::withToken($token)
                    ->acceptJson()
                    ->timeout(10)
                    ->post(config('services.backend.url').'/logout');
            } catch (ConnectionException|\Exception $e) {
                // Si el backend no responde, se limpia la sesión local igualmente
            }
        }

        session()->forget(['auth_token', 'user']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
