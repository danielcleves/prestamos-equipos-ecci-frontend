<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class UsuarioWebController extends Controller
{
    private function getApiUrl(): string
    {
        return config('services.backend.url');
    }

    /**
     * Valida que exista sesión activa y que el usuario tenga rol de administrador.
     */
    private function checkAdminAuth()
    {
        $token = session('api_token');
        if (! $token) {
            return redirect()->route('login');
        }

        $user = session('user', []);
        $roles = $user['roles'] ?? [];
        $rol = is_array($roles) ? ($roles[0] ?? null) : ($user['role'] ?? $user['rol'] ?? null);

        if ($rol !== 'admin') {
            abort(403, 'Acceso denegado: Se requieren permisos de administrador.');
        }

        return null;
    }

    public function index(Request $request)
    {
        if ($redirect = $this->checkAdminAuth()) {
            return $redirect;
        }

        $token = session('api_token');
        $apiUrl = $this->getApiUrl();

        try {
            $response = Http::timeout(10)
                ->withToken($token)
                ->acceptJson()
                ->get("{$apiUrl}/usuarios?per_page=100");
        } catch (\Exception $e) {
            return back()->withErrors(['store_error' => 'No fue posible conectar con el servicio de usuarios.']);
        }

        // Si el token es inválido o expiró (401), se reinicia sesión
        if ($response->status() === 401) {
            session()->forget(['api_token', 'user']);

            return redirect()->route('login')->withErrors(['email' => 'Su sesión ha expirado. Por favor ingrese de nuevo.']);
        }

        // Si es 403, no se destruye la sesión: se devuelve error de autorización
        if ($response->status() === 403) {
            abort(403, 'No tiene permisos para consultar este recurso.');
        }

        $usuarios = $response->json('data') ?? [];

        // Cálculo de métricas
        $total = count($usuarios);
        $activos = collect($usuarios)->where('is_active', true)->count();
        $inactivos = collect($usuarios)->where('is_active', false)->count();

        return view('usuarios.index', compact('usuarios', 'total', 'activos', 'inactivos'));
    }

    public function store(Request $request)
    {
        if ($redirect = $this->checkAdminAuth()) {
            return $redirect;
        }

        $token = session('api_token');

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => 'required|min:8',
            'role' => 'required|in:admin,encargado,usuario',
        ]);

        $apiUrl = $this->getApiUrl();

        try {
            $response = Http::timeout(10)
                ->withToken($token)
                ->acceptJson()
                ->post("{$apiUrl}/usuarios", $request->only('name', 'email', 'password', 'role'));
        } catch (\Exception $e) {
            return back()->withErrors(['store_error' => 'Error de conexión al registrar usuario.'])->withInput();
        }

        if ($response->failed()) {
            $errorMsg = $response->json('message') ?? 'Error al crear el usuario.';

            return back()->withErrors(['store_error' => $errorMsg])->withInput();
        }

        return redirect()->route('usuarios.index')->with('success', 'Usuario registrado exitosamente.');
    }

    public function update($usuario, Request $request)
    {
        if ($redirect = $this->checkAdminAuth()) {
            return $redirect;
        }

        $token = session('api_token');

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'role' => 'required|in:admin,encargado,usuario',
            'password' => 'nullable|min:8',
        ]);

        $payload = [
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'role' => $request->input('role'),
        ];

        if ($request->filled('password')) {
            $payload['password'] = $request->input('password');
        }

        $apiUrl = $this->getApiUrl();

        try {
            $response = Http::timeout(10)
                ->withToken($token)
                ->acceptJson()
                ->put("{$apiUrl}/usuarios/{$usuario}", $payload);
        } catch (\Exception $e) {
            return back()->withErrors(['update_error' => 'Error de conexión al actualizar usuario.']);
        }

        if ($response->failed()) {
            return back()->withErrors(['update_error' => $response->json('message') ?? 'Error al actualizar usuario.']);
        }

        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado correctamente.');
    }

    public function toggleStatus($id, $accion)
    {
        if ($redirect = $this->checkAdminAuth()) {
            return $redirect;
        }

        $token = session('api_token');

        $endpoint = $accion === 'activar' ? 'activar' : 'desactivar';
        $apiUrl = $this->getApiUrl();

        try {
            $response = Http::timeout(10)
                ->withToken($token)
                ->acceptJson()
                ->patch("{$apiUrl}/usuarios/{$id}/{$endpoint}");
        } catch (\Exception $e) {
            return back()->withErrors(['status_error' => 'Error de conexión al cambiar el estado.']);
        }

        if ($response->failed()) {
            return back()->withErrors(['status_error' => $response->json('message') ?? 'No se pudo cambiar el estado.']);
        }

        return redirect()->route('usuarios.index')->with('success', 'Estado actualizado.');
    }
}