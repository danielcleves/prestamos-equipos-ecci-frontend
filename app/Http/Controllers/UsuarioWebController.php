<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class UsuarioWebController extends Controller
{
    /** Tope de páginas de 100 usuarios que se recorren en la consulta (1000 usuarios). */
    private const MAX_PAGINAS = 10;

    private function apiUrl(): string
    {
        return config('services.backend.url');
    }

    private function getToken(): ?string
    {
        return session('api_token') ?? session('auth_token');
    }

    /**
     * Guardia de la sección: requiere sesión activa y rol administrador.
     * Devuelve el redirect correspondiente o null si el acceso está permitido.
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
     * Traduce 401 (sesión expirada) y 403 (sin permisos) a los redirects del flujo web.
     * Devuelve null cuando la respuesta no corresponde a esos casos.
     */
    private function redirigirPorStatus(int $status): ?RedirectResponse
    {
        if ($status === 401) {
            session()->forget(['api_token', 'auth_token', 'user']);

            return redirect()->route('login')
                ->withErrors(['email' => 'Tu sesión expiró, vuelve a iniciar sesión.']);
        }

        if ($status === 403) {
            return redirect()->route('inicio')
                ->withErrors(['inicio' => 'No tienes permisos para realizar esta acción.']);
        }

        return null;
    }

    public function index()
    {
        if ($redirigido = $this->guardia()) {
            return $redirigido;
        }

        $token = $this->getToken();

        // Paginación: se recorren todas las páginas para no truncar métricas
        $usuarios = [];
        $pagina = 1;
        $total = 0;

        do {
            try {
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->timeout(10)
                    ->get($this->apiUrl().'/usuarios', ['per_page' => 100, 'page' => $pagina]);
            } catch (ConnectionException $e) {
                return back()->withErrors(['index_error' => 'No se pudo conectar con el servicio backend.']);
            }

            if ($redirigido = $this->redirigirPorStatus($response->status())) {
                return $redirigido;
            }

            if ($response->failed()) {
                return back()->withErrors([
                    'index_error' => $response->json('message') ?? 'No fue posible cargar los usuarios.',
                ]);
            }

            $usuarios = array_merge($usuarios, $response->json('data') ?? []);
            $total = (int) ($response->json('meta.total') ?? count($usuarios));
            $ultimaPagina = (int) ($response->json('meta.last_page') ?? $pagina);
            $pagina++;
        } while ($pagina <= $ultimaPagina && $pagina <= self::MAX_PAGINAS);

        $activos = collect($usuarios)->where('is_active', true)->count();
        $inactivos = count($usuarios) - $activos;

        return view('usuarios.index', compact('usuarios', 'total', 'activos', 'inactivos'));
    }

    public function store(Request $request)
    {
        if ($redirigido = $this->guardia()) {
            return $redirigido;
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => 'required|min:8',
            'role' => 'required|in:admin,encargado,usuario',
        ]);

        $token = $this->getToken();

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->post($this->apiUrl().'/usuarios', $request->only('name', 'email', 'password', 'role'));
        } catch (ConnectionException $e) {
            return back()->withErrors(['store_error' => 'No se pudo conectar con el servicio backend.'])->withInput();
        }

        if ($redirigido = $this->redirigirPorStatus($response->status())) {
            return $redirigido;
        }

        if ($response->failed()) {
            return back()
                ->withErrors(['store_error' => $response->json('message') ?? 'Error al crear el usuario.'])
                ->withInput();
        }

        return redirect()->route('usuarios.index')->with('success', 'Usuario registrado exitosamente.');
    }

    public function update(Request $request, string $user)
    {
        if ($redirigido = $this->guardia()) {
            return $redirigido;
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'role' => 'required|in:admin,encargado,usuario',
            'password' => 'nullable|min:8',
        ]);

        $id = (int) $user;

        $payload = [
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'role' => $request->input('role'),
        ];

        if ($request->filled('password')) {
            $payload['password'] = $request->input('password');
        }

        $token = $this->getToken();

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->put($this->apiUrl()."/usuarios/{$id}", $payload);
        } catch (ConnectionException $e) {
            return back()->withErrors(['update_error' => 'No se pudo conectar con el servicio backend.']);
        }

        if ($redirigido = $this->redirigirPorStatus($response->status())) {
            return $redirigido;
        }

        if ($response->failed()) {
            return back()->withErrors(['update_error' => $response->json('message') ?? 'Error al actualizar usuario.']);
        }

        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado correctamente.');
    }

    public function toggleStatus($id, $accion)
    {
        if ($redirigido = $this->guardia()) {
            return $redirigido;
        }

        $endpoint = $accion === 'activar' ? 'activar' : 'desactivar';
        $token = $this->getToken();

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->patch($this->apiUrl()."/usuarios/{$id}/{$endpoint}");
        } catch (ConnectionException $e) {
            return back()->withErrors(['status_error' => 'No se pudo conectar con el servicio backend.']);
        }

        if ($redirigido = $this->redirigirPorStatus($response->status())) {
            return $redirigido;
        }

        if ($response->failed()) {
            return back()->withErrors(['status_error' => $response->json('message') ?? 'No se pudo cambiar el estado.']);
        }

        return redirect()->route('usuarios.index')->with('success', 'Estado actualizado.');
    }
}
