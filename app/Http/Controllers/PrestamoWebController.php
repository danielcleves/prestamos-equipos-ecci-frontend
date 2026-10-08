<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PrestamoWebController extends Controller
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
     * Muestra el formulario para solicitar préstamo de un equipo (HU-06).
     */
    public function create(Request $request, string $equipoId)
    {
        $token = $this->getToken();
        if (! $token) {
            return redirect()->route('login');
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->get($this->apiUrl() . "/equipos/{$equipoId}");
        } catch (ConnectionException $e) {
            return redirect()->route('equipos.index', ['vista' => 'catalogo'])
                ->withErrors(['index_error' => 'No fue posible conectar con el servidor para consultar el equipo.']);
        }

        if ($response->status() === 401) {
            session()->forget(['api_token', 'auth_token', 'user']);
            return redirect()->route('login')->withErrors(['email' => 'Tu sesión expiró. Inicia sesión nuevamente.']);
        }

        if ($response->status() === 404) {
            return redirect()->route('equipos.index', ['vista' => 'catalogo'])
                ->withErrors(['error' => 'El equipo seleccionado no existe o no se encuentra disponible.']);
        }

        $equipo = $response->json('data') ?? [];

        // Validar regla de negocio: Solo equipos disponibles pueden solicitarse (HU-06)
        $estado = strtolower($equipo['estado'] ?? 'disponible');
        if ($estado !== 'disponible') {
            return redirect()->route('equipos.index', ['vista' => 'catalogo'])
                ->withErrors(['error' => 'No se puede solicitar un equipo que se encuentre en mantenimiento, prestado o dado de baja.']);
        }

        $usuario = session('user') ?? [];

        return view('prestamos.create', compact('equipo', 'usuario'));
    }

    /**
     * Procesa y registra la solicitud de préstamo (HU-06 / KAN-76).
     */
public function store(Request $request): RedirectResponse
    {
        $token = $this->getToken();
        if (! $token) {
            return redirect()->route('login');
        }

        $request->validate([
            'equipo_id'        => 'required',
            'fecha_inicio'     => 'required|date',
            'fecha_devolucion' => 'required|date|after_or_equal:fecha_inicio',
            'motivo'           => 'nullable|string|max:500',
        ], [
            'fecha_inicio.required'     => 'La fecha de inicio es requerida.',
            'fecha_devolucion.required' => 'La fecha de devolución es requerida.',
            'fecha_devolucion.after_or_equal' => 'La fecha de devolución debe ser igual o posterior a la fecha de inicio.',
        ]);

        $fechaInicioRaw = $request->input('fecha_inicio');
        $fechaDevolucionRaw = $request->input('fecha_devolucion');

        // Formatear a fecha y hora completa que exige el backend (Y-m-d H:i:s)
        // Si el input date solo trae 'Y-m-d', le asignamos una hora de inicio y fin laboral estándar
        $fechaInicio = str_contains($fechaInicioRaw, ':')
            ? date('Y-m-d H:i:s', strtotime($fechaInicioRaw))
            : date('Y-m-d 08:00:00', strtotime($fechaInicioRaw));

        $fechaDevolucion = str_contains($fechaDevolucionRaw, ':')
            ? date('Y-m-d H:i:s', strtotime($fechaDevolucionRaw))
            : date('Y-m-d 18:00:00', strtotime($fechaDevolucionRaw));

        $motivo = $request->input('motivo');

        // Payload exacto según el contrato de StorePrestamoRequest del backend
        $payload = [
            'equipo_id'                 => (int) $request->input('equipo_id'),
            'fecha_inicio'              => $fechaInicio,
            'fecha_devolucion_estimada' => $fechaDevolucion,
            'motivo'                    => $motivo,
            'observaciones'             => $motivo,
        ];

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->post($this->apiUrl() . '/prestamos', $payload);
        } catch (ConnectionException $e) {
            return back()->withInput()->withErrors(['prestamo_error' => 'No fue posible conectar con el servicio backend.']);
        }

        if ($response->status() === 401) {
            session()->forget(['api_token', 'auth_token', 'user']);
            return redirect()->route('login')->withErrors(['email' => 'Tu sesión expiró. Inicia sesión nuevamente.']);
        }

        if ($response->failed()) {
            $mensaje = $response->json('message') ?? 'Error al procesar la solicitud de préstamo.';
            $errores = $response->json('errors') ?? [];

            // Si el backend responde con mensajes en errores anidados, aplanarlos para mostrarlos al usuario
            $mensajesAplanados = [];
            foreach ($errores as $campo => $msgs) {
                if (is_array($msgs)) {
                    foreach ($msgs as $m) {
                        $mensajesAplanados[] = $m;
                    }
                } else {
                    $mensajesAplanados[] = $msgs;
                }
            }

            return back()->withInput()->withErrors($mensajesAplanados ?: ['prestamo_error' => $mensaje]);
        }

        return redirect()->route('equipos.index', ['vista' => 'catalogo'])
            ->with('success', '¡Solicitud de préstamo registrada con éxito! El estado inicial de la solicitud es "Solicitado".');
    }
    /**
     * bandeja de solicitudes listas para entrega
     */
    public function entregasIndex(Request $request)
    {
        $token = $this->getToken();
        if (! $token) {
            return redirect()->route('login');
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->get($this->apiUrl() . '/prestamos?per_page=100');
        } catch (ConnectionException $e) {
            return back()->withErrors(['error' => 'No fue posible conectar con el servicio de préstamos.']);
        }

        if ($response->status() === 401) {
            session()->forget(['api_token', 'auth_token', 'user']);
            return redirect()->route('login')->withErrors(['email' => 'Tu sesión expiró. Inicia sesión nuevamente.']);
        }

        $todosPrestamos = $response->json('data') ?? [];

        // Filtra los préstamos aprobados 
        $entregasPendientes = array_filter($todosPrestamos, function ($p) {
            $estado = strtolower($p['estado'] ?? '');
            return in_array($estado, ['aprobado', 'solicitado']);
        });

        return view('prestamos.entregas', [
            'prestamos' => array_values($entregasPendientes),
            'totalPendientes' => count($entregasPendientes),
        ]);
    }

    /**
     * Registra la entrega del equipo al solicitante (HU-09).
     */
    public function registrarEntrega(Request $request, string $id): RedirectResponse
    {
        $token = $this->getToken();
        if (! $token) {
            return redirect()->route('login');
        }

        $request->validate([
            'condicion_entrega' => 'required|string|in:bueno,con_danos,requiere_mantenimiento',
            'observaciones'     => 'nullable|string|max:2000',
        ]);

        $payload = [
            'condicion_entrega' => $request->input('condicion_entrega', 'bueno'),
            'observaciones'     => $request->input('observaciones'),
        ];

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->post($this->apiUrl() . "/prestamos/{$id}/entrega", $payload);
        } catch (ConnectionException $e) {
            return back()->withErrors(['error' => 'No fue posible conectar con el servidor backend.']);
        }

        if ($response->status() === 401) {
            session()->forget(['api_token', 'auth_token', 'user']);
            return redirect()->route('login')->withErrors(['email' => 'Tu sesión expiró. Inicia sesión nuevamente.']);
        }

        if ($response->status() === 403) {
            return back()->withErrors(['error' => 'No tienes permisos para registrar la entrega de equipos.']);
        }

        if ($response->failed()) {
            $mensaje = $response->json('message') ?? 'Error al procesar la entrega del equipo.';
            return back()->withErrors(['error' => $mensaje]);
        }

        return redirect()->route('prestamos.entregas')
            ->with('success', '¡Entrega registrada exitosamente! El préstamo pasó a estado "Entregado" y el equipo ahora está "En préstamo".');
    }
}