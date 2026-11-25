<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Auto-registro de solicitantes.
 *
 * El backend valida que el correo pertenezca a un dominio o dirección
 * autorizada y asigna el rol `usuario`; el administrador puede cambiarlo
 * después desde Gestión de usuarios.
 */
class RegistroController extends Controller
{
    public function show()
    {
        if (session()->has('api_token')) {
            return redirect()->route('inicio');
        }

        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required' => 'Escribe tu nombre completo.',
            'email.required' => 'Escribe tu correo institucional.',
            'email.email' => 'Escribe un correo válido.',
            'password.required' => 'Escribe una contraseña.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        try {
            $response = Http::acceptJson()
                ->timeout(10)
                // BUG-0004: el backend también valida 'confirmed', así que la
                // confirmación debe viajar (validate() no la devuelve).
                ->post(config('services.backend.url').'/register', [
                    ...$datos,
                    'password_confirmation' => $request->input('password_confirmation'),
                ]);
        } catch (ConnectionException $e) {
            return back()->withInput($request->only('name', 'email'))
                ->withErrors(['email' => 'No fue posible conectar con el servicio de registro.']);
        }

        if ($response->status() === 422) {
            $errores = $response->json('errors') ?? [];
            $mapeados = [];

            foreach ($errores as $campo => $mensajes) {
                $mapeados[$campo] = is_array($mensajes) ? ($mensajes[0] ?? '') : (string) $mensajes;
            }

            return back()->withInput($request->only('name', 'email'))
                ->withErrors($mapeados ?: [
                    'email' => $response->json('message') ?? 'No se pudo crear la cuenta.',
                ]);
        }

        if ($response->status() === 429) {
            return back()->withInput($request->only('name', 'email'))
                ->withErrors(['email' => 'Demasiados intentos de registro. Espera un minuto y vuelve a intentarlo.']);
        }

        if ($response->failed()) {
            return back()->withInput($request->only('name', 'email'))
                ->withErrors(['email' => $response->json('message') ?? 'No se pudo crear la cuenta.']);
        }

        return redirect()->route('login')
            ->with('success', 'Cuenta creada correctamente. Ya puedes iniciar sesión.');
    }
}
