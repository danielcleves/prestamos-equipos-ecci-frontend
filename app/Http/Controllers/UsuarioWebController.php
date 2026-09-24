<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class UsuarioWebController extends Controller
{
    private string $apiUrl = 'http://backend:8000/api';

    public function index(Request $request)
    {
        $token = session('auth_token');
        if (! $token) {
            return redirect()->route('login');
        }

        // Consultar listado completo
        $response = Http::withToken($token)
            ->acceptJson()
            ->get("{$this->apiUrl}/usuarios?per_page=100");

        if ($response->status() === 401 || $response->status() === 403) {
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
        $token = session('auth_token');

        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email',
            'password' => 'required|min:6',
            'role'     => 'required|in:admin,encargado,usuario',
        ]);

        $response = Http::withToken($token)
            ->acceptJson()
            ->post("{$this->apiUrl}/usuarios", $request->only('name', 'email', 'password', 'role'));

        if ($response->failed()) {
            $errorMsg = $response->json('message') ?? 'Error al crear el usuario.';
            return back()->withErrors(['store_error' => $errorMsg])->withInput();
        }

        return redirect()->route('usuarios.index')->with('success', 'Usuario registrado exitosamente.');
    }

    public function update(Request $request)
    {
        $token = session('auth_token');

        $request->validate([
            'user_id'  => 'required|integer',
            'name'     => 'required|string|max:255',
            'email'    => 'required|email',
            'role'     => 'required|in:admin,encargado,usuario',
            'password' => 'nullable|min:6',
        ]);

        $id = $request->input('user_id');

        $payload = [
            'name'  => $request->input('name'),
            'email' => $request->input('email'),
            'role'  => $request->input('role'),
        ];

        if ($request->filled('password')) {
            $payload['password'] = $request->input('password');
        }

        
        $response = Http::withToken($token)
            ->acceptJson()
            ->put("{$this->apiUrl}/usuarios/{$id}", $payload);

        if ($response->failed()) {
            return back()->withErrors(['update_error' => $response->json('message') ?? 'Error al actualizar usuario.']);
        }

        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado correctamente.');
    }

    public function toggleStatus($id, $accion)
    {
        $token = session('auth_token');

        $endpoint = $accion === 'activar' ? 'activar' : 'desactivar';

        $response = Http::withToken($token)
            ->acceptJson()
            ->patch("{$this->apiUrl}/usuarios/{$id}/{$endpoint}");

        if ($response->failed()) {
            return back()->withErrors(['status_error' => $response->json('message') ?? 'No se pudo cambiar el estado.']);
        }

        return redirect()->route('usuarios.index')->with('success', 'Estado actualizado.');
    }
}