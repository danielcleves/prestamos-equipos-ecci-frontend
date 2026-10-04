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

    public function index(Request $request)
    {
        $token = session('api_token');
        if (! $token) {
            return redirect()->route('login');
        }

        $apiUrl = $this->getApiUrl();

        // Consultar listado
        $response = Http::withToken($token)
            ->acceptJson()
            ->get("{$apiUrl}/usuarios?per_page=100");

        if ($response->status() === 401 || $response->status() === 403) {
            session()->forget(['api_token', 'user']);

            return redirect()->route('login')->withErrors(['email' => 'Sesión expirada o permisos insuficientes.']);
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
        $token = session('api_token');

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => 'required|min:8',
            'role' => 'required|in:admin,encargado,usuario',
        ]);

        $apiUrl = $this->getApiUrl();

        $response = Http::withToken($token)
            ->acceptJson()
            ->post("{$apiUrl}/usuarios", $request->only('name', 'email', 'password', 'role'));

        if ($response->failed()) {
            $errorMsg = $response->json('message') ?? 'Error al crear el usuario.';

            return back()->withErrors(['store_error' => $errorMsg])->withInput();
        }

        return redirect()->route('usuarios.index')->with('success', 'Usuario registrado exitosamente.');
    }

    public function update(Request $request)
    {
        $token = session('api_token');

        $request->validate([
            'user_id' => 'required|integer',
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'role' => 'required|in:admin,encargado,usuario',
            'password' => 'nullable|min:8',
        ]);

        $id = $request->input('user_id');

        $payload = [
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'role' => $request->input('role'),
        ];

        if ($request->filled('password')) {
            $payload['password'] = $request->input('password');
        }

        $apiUrl = $this->getApiUrl();

        $response = Http::withToken($token)
            ->acceptJson()
            ->put("{$apiUrl}/usuarios/{$id}", $payload);

        if ($response->failed()) {
            return back()->withErrors(['update_error' => $response->json('message') ?? 'Error al actualizar usuario.']);
        }

        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado correctamente.');
    }

    public function toggleStatus($id, $accion)
    {
        $token = session('api_token');

        $endpoint = $accion === 'activar' ? 'activar' : 'desactivar';
        $apiUrl = $this->getApiUrl();

        $response = Http::withToken($token)
            ->acceptJson()
            ->patch("{$apiUrl}/usuarios/{$id}/{$endpoint}");

        if ($response->failed()) {
            return back()->withErrors(['status_error' => $response->json('message') ?? 'No se pudo cambiar el estado.']);
        }

        return redirect()->route('usuarios.index')->with('success', 'Estado actualizado.');
    }
}
