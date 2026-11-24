<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;

/**
 * RN-08: consulta y cierre de las incidencias abiertas por daños.
 *
 * Consume GET /api/incidencias y PATCH /api/incidencias/{id}/cerrar. Solo el
 * personal administrativo (admin o encargado) puede entrar; el backend lo
 * vuelve a validar.
 */
class IncidenciaWebController extends Controller
{
    private function apiUrl(): string
    {
        return config('services.backend.url');
    }

    private function getToken(): ?string
    {
        return session('api_token') ?? session('auth_token');
    }

    private function guardiaPersonalAutorizado(): ?RedirectResponse
    {
        if (! $this->getToken()) {
            return redirect()->route('login');
        }

        $user = session('user') ?? [];
        $roles = $user['roles'] ?? [];

        if (is_array($roles[0] ?? null)) {
            $nombresRoles = array_column($roles, 'name');
        } else {
            $nombresRoles = is_array($roles) ? $roles : array_filter([$user['role'] ?? $user['rol'] ?? null]);
        }

        if (! in_array('admin', $nombresRoles, true) && ! in_array('encargado', $nombresRoles, true)) {
            return redirect()->route('inicio')
                ->withErrors(['inicio' => 'Esta sección es exclusiva para personal autorizado (administradores y encargados).']);
        }

        return null;
    }

    public function index()
    {
        if ($redirigido = $this->guardiaPersonalAutorizado()) {
            return $redirigido;
        }

        $token = $this->getToken();

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->get($this->apiUrl().'/incidencias', ['per_page' => 100]);
        } catch (ConnectionException $e) {
            return view('incidencias.index', [
                'incidencias' => [],
                'errorConexion' => 'No fue posible conectar con el servicio de incidencias.',
            ]);
        }

        if ($response->status() === 401) {
            session()->forget(['api_token', 'auth_token', 'user']);

            return redirect()->route('login')->withErrors(['email' => 'Tu sesión expiró. Inicia sesión nuevamente.']);
        }

        if ($response->status() === 403) {
            return redirect()->route('inicio')
                ->withErrors(['inicio' => 'No tienes permisos para consultar las incidencias.']);
        }

        if ($response->failed()) {
            return view('incidencias.index', [
                'incidencias' => [],
                'errorConexion' => $response->json('message') ?? 'No fue posible cargar las incidencias.',
            ]);
        }

        return view('incidencias.index', [
            'incidencias' => $response->json('data') ?? [],
        ]);
    }

    public function cerrar(string $id): RedirectResponse
    {
        if ($redirigido = $this->guardiaPersonalAutorizado()) {
            return $redirigido;
        }

        $token = $this->getToken();

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->patch($this->apiUrl()."/incidencias/{$id}/cerrar");
        } catch (ConnectionException $e) {
            return back()->withErrors(['error' => 'No fue posible conectar con el servicio de incidencias.']);
        }

        if ($response->status() === 401) {
            session()->forget(['api_token', 'auth_token', 'user']);

            return redirect()->route('login')->withErrors(['email' => 'Tu sesión expiró. Inicia sesión nuevamente.']);
        }

        if ($response->status() === 403) {
            return back()->withErrors(['error' => 'No tienes permisos para cerrar incidencias.']);
        }

        if ($response->failed()) {
            $mensajes = [];
            foreach (($response->json('errors') ?? []) as $msgs) {
                foreach ((array) $msgs as $mensaje) {
                    $mensajes[] = $mensaje;
                }
            }

            return back()->withErrors($mensajes ?: [
                'error' => $response->json('message') ?? 'No fue posible cerrar la incidencia.',
            ]);
        }

        return redirect()->route('incidencias.index')
            ->with('success', 'Incidencia cerrada. El usuario ya puede volver a solicitar equipos.');
    }
}
