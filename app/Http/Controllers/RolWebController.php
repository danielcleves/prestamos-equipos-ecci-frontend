<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CatalogoRoles;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * RN-02 — Roles personalizados y cupos de préstamo (exclusivo administrador).
 *
 * Consume:
 *  - GET    /api/roles
 *  - POST   /api/roles            → etiqueta, descripcion, cupo_prestamos, es_predeterminado
 *  - PATCH  /api/roles/{rol}
 *  - DELETE /api/roles/{rol}
 */
class RolWebController extends Controller
{
    use CatalogoRoles;

    private function apiUrl(): string
    {
        return config('services.backend.url');
    }

    private function getToken(): ?string
    {
        return session('api_token') ?? session('auth_token');
    }

    private function guardia(): ?RedirectResponse
    {
        if (! $this->getToken()) {
            return redirect()->route('login');
        }

        $user = session('user') ?? [];
        $roles = $user['roles'] ?? [];
        $nombres = is_array($roles[0] ?? null) ? array_column($roles, 'name') : (is_array($roles) ? $roles : []);

        if (empty($nombres)) {
            $nombres = array_filter([$user['role'] ?? $user['rol'] ?? null]);
        }

        if (! in_array('admin', $nombres, true)) {
            return redirect()->route('inicio')
                ->withErrors(['inicio' => 'Esta sección es exclusiva para administradores.']);
        }

        return null;
    }

    private function redirigirPorStatus(Response $response): ?RedirectResponse
    {
        if ($response->status() === 401) {
            session()->forget(['api_token', 'auth_token', 'user']);

            return redirect()->route('login')->withErrors(['email' => 'Tu sesión expiró. Inicia sesión nuevamente.']);
        }

        if ($response->status() === 403) {
            return redirect()->route('inicio')
                ->withErrors(['inicio' => 'No tienes permisos para gestionar los roles.']);
        }

        return null;
    }

    private function primerError(Response $response, string $porDefecto): string
    {
        foreach ((array) ($response->json('errors') ?? []) as $mensajes) {
            foreach ((array) $mensajes as $mensaje) {
                if (is_string($mensaje) && $mensaje !== '') {
                    return $mensaje;
                }
            }
        }

        return $response->json('message') ?? $porDefecto;
    }

    public function index()
    {
        if ($redirigido = $this->guardia()) {
            return $redirigido;
        }

        $errorConexion = null;
        $roles = [];

        try {
            $response = Http::withToken($this->getToken())
                ->acceptJson()
                ->timeout(10)
                ->get($this->apiUrl().'/roles');

            if ($redirigido = $this->redirigirPorStatus($response)) {
                return $redirigido;
            }

            if ($response->failed()) {
                $errorConexion = $response->json('message') ?? 'No fue posible cargar los roles.';
            } else {
                $roles = $this->normalizarRoles($response->json('data'));
            }
        } catch (ConnectionException $e) {
            $errorConexion = 'No fue posible conectar con el servicio de roles.';
        }

        return view('roles.index', [
            'roles' => $roles,
            'errorConexion' => $errorConexion,
            'title' => 'Roles y cupos',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirigido = $this->guardia()) {
            return $redirigido;
        }

        $datos = $request->validate($this->reglas(true), $this->mensajes());

        try {
            $response = Http::withToken($this->getToken())
                ->acceptJson()
                ->timeout(10)
                ->post($this->apiUrl().'/roles', [
                    'etiqueta' => $datos['etiqueta'],
                    'descripcion' => $datos['descripcion'] ?? null,
                    'cupo_prestamos' => (int) $datos['cupo_prestamos'],
                    'es_predeterminado' => $request->boolean('es_predeterminado'),
                ]);
        } catch (ConnectionException $e) {
            return back()->withInput()->withErrors(['rol_error' => 'No fue posible conectar con el servicio de roles.']);
        }

        if ($redirigido = $this->redirigirPorStatus($response)) {
            return $redirigido;
        }

        if ($response->failed()) {
            return back()->withInput()->withErrors(['rol_error' => $this->primerError($response, 'No fue posible crear el rol.')]);
        }

        return redirect()->route('roles.index')
            ->with('success', 'Rol «'.($response->json('data.etiqueta') ?? $datos['etiqueta']).'» creado correctamente.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        if ($redirigido = $this->guardia()) {
            return $redirigido;
        }

        $datos = $request->validate($this->reglas(false), $this->mensajes());

        $payload = [
            'etiqueta' => $datos['etiqueta'],
            'descripcion' => $datos['descripcion'] ?? null,
            'cupo_prestamos' => (int) $datos['cupo_prestamos'],
        ];

        // Solo se envía cuando se marca: desmarcar el predeterminado no está
        // permitido (se cambia marcando otro rol).
        if ($request->boolean('es_predeterminado')) {
            $payload['es_predeterminado'] = true;
        }

        try {
            $response = Http::withToken($this->getToken())
                ->acceptJson()
                ->timeout(10)
                ->patch($this->apiUrl().'/roles/'.(int) $id, $payload);
        } catch (ConnectionException $e) {
            return back()->withErrors(['rol_error' => 'No fue posible conectar con el servicio de roles.']);
        }

        if ($redirigido = $this->redirigirPorStatus($response)) {
            return $redirigido;
        }

        if ($response->failed()) {
            return back()->withErrors(['rol_error' => $this->primerError($response, 'No fue posible actualizar el rol.')]);
        }

        return redirect()->route('roles.index')->with('success', 'Rol actualizado correctamente.');
    }

    public function destroy(string $id): RedirectResponse
    {
        if ($redirigido = $this->guardia()) {
            return $redirigido;
        }

        try {
            $response = Http::withToken($this->getToken())
                ->acceptJson()
                ->timeout(10)
                ->delete($this->apiUrl().'/roles/'.(int) $id);
        } catch (ConnectionException $e) {
            return back()->withErrors(['rol_error' => 'No fue posible conectar con el servicio de roles.']);
        }

        if ($redirigido = $this->redirigirPorStatus($response)) {
            return $redirigido;
        }

        if ($response->failed()) {
            return back()->withErrors(['rol_error' => $this->primerError($response, 'No fue posible eliminar el rol.')]);
        }

        return redirect()->route('roles.index')->with('success', 'Rol eliminado correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function reglas(bool $alta): array
    {
        return [
            'etiqueta' => ['required', 'string', 'min:3', 'max:60'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'cupo_prestamos' => ['required', 'integer', 'min:0', 'max:1000'],
            'es_predeterminado' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mensajes(): array
    {
        return [
            'etiqueta.required' => 'El nombre del rol es obligatorio.',
            'etiqueta.min' => 'El nombre del rol debe tener al menos 3 caracteres.',
            'etiqueta.max' => 'El nombre del rol no puede superar 60 caracteres.',
            'cupo_prestamos.required' => 'El cupo de préstamos es obligatorio.',
            'cupo_prestamos.integer' => 'El cupo debe ser un número entero.',
            'cupo_prestamos.min' => 'El cupo no puede ser negativo.',
            'cupo_prestamos.max' => 'El cupo no puede superar 1000.',
        ];
    }
}
