<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class EquipoWebController extends Controller
{
    private function apiUrl(): string
    {
        return config('services.backend.url');
    }

    private function getToken(): ?string
    {
        return session('api_token') ?? session('auth_token');
    }

    /**
     * Muestra el catálogo de equipos consultando la API del backend.
     */
    public function index(Request $request)
    {
        $token = $this->getToken();
        if (! $token) {
            return redirect()->route('login');
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->get($this->apiUrl().'/equipos?per_page=100');
        } catch (ConnectionException $e) {
            return back()->withErrors(['index_error' => 'No fue posible conectar con el catálogo de equipos.']);
        }

        if ($response->status() === 401) {
            session()->forget(['api_token', 'auth_token', 'user']);

            return redirect()->route('login')->withErrors(['email' => 'Tu sesión expiró, vuelve a iniciar sesión.']);
        }

        $equipos = $response->json('data') ?? [];
        $esAdmin = $this->esAdmin();

        return view('equipos.index', compact('equipos', 'esAdmin'));
    }

    /**
     * Guardia: requiere sesión activa y rol administrador.
     */
    private function guardia(): ?RedirectResponse
    {
        if (! $this->getToken()) {
            return redirect()->route('login');
        }

        if (! $this->esAdmin()) {
            return redirect()->route('inicio')
                ->withErrors(['inicio' => 'Esta sección es exclusiva para administradores.']);
        }

        return null;
    }

    private function esAdmin(): bool
    {
        $user = session('user') ?? [];
        $roles = $user['roles'] ?? [];

        if (is_array($roles[0] ?? null)) {
            $rol = $roles[0]['name'] ?? null;
        } else {
            $rol = $roles[0] ?? $user['role'] ?? $user['rol'] ?? null;
        }

        return $rol === 'admin';
    }

    /**
     * Muestra el formulario para registrar un nuevo equipo.
     */
    public function create()
    {
        if ($redirigido = $this->guardia()) {
            return $redirigido;
        }

        // Categorías base según CategoriaSeeder
        $categorias = [
            ['id' => 1, 'nombre' => 'Portátil'],
            ['id' => 2, 'nombre' => 'Tablet'],
            ['id' => 3, 'nombre' => 'De mesa'],
        ];

        return view('equipos.create', compact('categorias'));
    }

    /**
     * Envía la solicitud de creación al backend.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($redirigido = $this->guardia()) {
            return $redirigido;
        }

        $request->validate([
            'codigo' => 'required|string|max:255',
            'nombre' => 'required|string|max:255',
            'categoria_id' => 'required|integer',
            'descripcion' => 'nullable|string',
            'observaciones' => 'nullable|string',
        ]);

        $payload = [
            'codigo' => $request->input('codigo'),
            'nombre' => $request->input('nombre'),
            'categoria_id' => (int) $request->input('categoria_id'),
            'descripcion' => $request->input('descripcion'),
            'observaciones' => $request->input('observaciones'),
        ];

        $token = $this->getToken();

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->post($this->apiUrl().'/equipos', $payload);
        } catch (ConnectionException $e) {
            return back()->withInput()->withErrors(['store_error' => 'No fue posible conectar con el servicio backend.']);
        }

        if ($response->status() === 401) {
            session()->forget(['api_token', 'auth_token', 'user']);

            return redirect()->route('login')->withErrors(['email' => 'Tu sesión expiró, vuelve a iniciar sesión.']);
        }

        if ($response->status() === 403) {
            return redirect()->route('inicio')->withErrors(['inicio' => 'No tienes permisos para registrar equipos.']);
        }

        if ($response->failed()) {
            $errorMsg = $response->json('message') ?? 'Error al registrar el equipo.';
            $errors = $response->json('errors') ?? [];

            return back()->withInput()->withErrors($errors ?: ['store_error' => $errorMsg]);
        }

        return redirect()->route('equipos.index')->with('success', 'Equipo registrado exitosamente.');
    }
}
