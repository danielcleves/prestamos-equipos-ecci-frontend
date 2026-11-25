<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
     * Guardia de las secciones de personal (HU-08, HU-09, HU-11): requiere sesión
     * activa y rol autorizado (administrador o encargado), igual que autoriza el backend.
     * Devuelve el redirect correspondiente o null si el acceso está permitido.
     */
    private function guardiaPersonalAutorizado(): ?RedirectResponse
    {
        if (! $this->getToken()) {
            return redirect()->route('login');
        }

        if (! $this->esPersonalAutorizado()) {
            return redirect()->route('inicio')
                ->withErrors(['inicio' => 'Esta sección es exclusiva para personal autorizado (administradores y encargados).']);
        }

        return null;
    }

    private function esPersonalAutorizado(): bool
    {
        $user = session('user') ?? [];
        $roles = $user['roles'] ?? [];

        if (is_array($roles[0] ?? null)) {
            $nombresRoles = array_column($roles, 'name');
        } else {
            $nombresRoles = is_array($roles) ? $roles : array_filter([$user['role'] ?? $user['rol'] ?? null]);
        }

        return in_array('admin', $nombresRoles, true) || in_array('encargado', $nombresRoles, true);
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
                ->get($this->apiUrl()."/equipos/{$equipoId}");
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

        $estado = strtolower($equipo['estado'] ?? 'disponible');
        if ($estado !== 'disponible') {
            return redirect()->route('equipos.index', ['vista' => 'catalogo'])
                ->withErrors(['error' => 'No se puede solicitar un equipo que se encuentre en mantenimiento, prestado o dado de baja.']);
        }

        $usuario = session('user') ?? [];

        // RN-06: el detalle del equipo expone la duración máxima ya calculada
        // (días por categoría + días extra del rol). Si no viene, se mantiene 7.
        $duracionMaxima = (int) ($equipo['duracion_maxima_dias'] ?? 7);
        if ($duracionMaxima < 1) {
            $duracionMaxima = 7;
        }

        return view('prestamos.create', [
            'equipo' => $equipo,
            'usuario' => $usuario,
            'duracionMaxima' => $duracionMaxima,
            // H-19: la miga del encabezado debe nombrar la pantalla, no «Gestión de equipos».
            'title' => 'Solicitar préstamo',
        ]);
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

        // RF-06/RN-06: el formulario envía la duración máxima del equipo
        // (`duracion_maxima_dias`); si no llega, se conserva el límite de 7 días.
        // El backend valida de nuevo contra la categoría y el rol del usuario.
        $maxDias = (int) $request->input('duracion_maxima_dias', 7);
        if ($maxDias < 1 || $maxDias > 365) {
            $maxDias = 7;
        }

        $request->validate([
            'equipo_id' => 'required|integer',
            'fecha_inicio' => 'required|date',
            'fecha_devolucion' => [
                'required',
                'date',
                'after:fecha_inicio',
                function (string $attribute, mixed $value, \Closure $fail) use ($maxDias, $request) {
                    $inicio = Carbon::parse($request->input('fecha_inicio'));
                    $fin = Carbon::parse($value);

                    // Mismo criterio que StorePrestamoRequest del backend: el límite se
                    // cuenta hasta el fin del día (23:59:59) del día máximo permitido.
                    if ($fin->greaterThan($inicio->copy()->addDays($maxDias)->endOfDay())) {
                        $fail("La fecha de devolución no puede superar los {$maxDias} días posteriores a la fecha de inicio.");
                    }
                },
            ],
            'motivo' => 'required|string|max:1000',
        ], [
            'equipo_id.required' => 'Debes seleccionar un equipo.',
            'fecha_inicio.required' => 'La fecha de inicio es requerida.',
            'fecha_devolucion.required' => 'La fecha de devolución es requerida.',
            'fecha_devolucion.after' => 'La fecha de devolución debe ser posterior a la fecha de inicio.',
            'motivo.required' => 'El motivo del préstamo es obligatorio.',
            'motivo.max' => 'El motivo no puede superar los 1000 caracteres.',
        ]);

        $fechaInicioRaw = $request->input('fecha_inicio');
        $fechaDevolucionRaw = $request->input('fecha_devolucion');

        // El backend exige Y-m-d H:i:s y rechaza fechas pasadas. Si el input date solo
        // trae 'Y-m-d': para hoy usamos la hora actual (nunca en el pasado); para fechas
        // futuras usamos el inicio del horario laboral (08:00).
        if (str_contains($fechaInicioRaw, ':')) {
            $fechaInicio = date('Y-m-d H:i:s', strtotime($fechaInicioRaw));
        } else {
            $fechaInicio = $fechaInicioRaw === date('Y-m-d')
                ? date('Y-m-d H:i:s')
                : date('Y-m-d 08:00:00', strtotime($fechaInicioRaw));
        }

        $fechaDevolucion = str_contains($fechaDevolucionRaw, ':')
            ? date('Y-m-d H:i:s', strtotime($fechaDevolucionRaw))
            : date('Y-m-d 18:00:00', strtotime($fechaDevolucionRaw));

        // Payload según el contrato de StorePrestamoRequest del backend
        $payload = [
            'equipo_id' => (int) $request->input('equipo_id'),
            'fecha_inicio' => $fechaInicio,
            'fecha_devolucion_estimada' => $fechaDevolucion,
            'motivo' => $request->input('motivo'),
        ];

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->post($this->apiUrl().'/prestamos', $payload);
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

            // KAN-195: Mapear errores por campo para mostrarlos junto al campo correspondiente.
            // El backend puede usar fecha_inicio, fecha_devolucion_estimada, equipo_id, motivo, etc.
            $erroresMapeados = [];
            $campoPresentacion = [
                'fecha_inicio' => 'fecha_inicio',
                'fecha_devolucion_estimada' => 'fecha_devolucion',
                'fecha_devolucion' => 'fecha_devolucion',
                'equipo_id' => 'equipo_id',
                'motivo' => 'motivo',
            ];

            foreach ($errores as $campo => $msgs) {
                // RN-01/RN-02/RN-04: el backend identifica los rechazos por
                // elegibilidad bajo `usuario_id` (sanción activa, cupo, préstamo
                // vencido, capacitación, restricción de categoría).
                if (in_array($campo, ['usuario_id', 'user_id'], true)) {
                    $erroresMapeados['elegibilidad'] = array_values(array_map(
                        fn ($m) => is_array($m) ? ($m[0] ?? '') : (string) $m,
                        (array) $msgs
                    ));

                    continue;
                }

                $destino = $campoPresentacion[$campo] ?? 'prestamo_error';
                $primerMensaje = is_array($msgs) ? ($msgs[0] ?? '') : $msgs;
                $erroresMapeados[$destino] = $primerMensaje;
            }

            // Si no hay errores mapeados por campo, usar el mensaje general
            if (empty($erroresMapeados)) {
                $erroresMapeados['prestamo_error'] = $mensaje;
            }

            return back()->withInput()->withErrors($erroresMapeados);
        }

        return redirect()->route('equipos.index', ['vista' => 'catalogo'])
            ->with('success', '¡Solicitud de préstamo registrada con éxito! El estado inicial de la solicitud es "Solicitado".');
    }

    /**
     * Bandeja de solicitudes aprobadas listas para entrega física (HU-09 / KAN-82).
     */
    public function entregasIndex(Request $request)
    {
        if ($redirigido = $this->guardiaPersonalAutorizado()) {
            return $redirigido;
        }

        $token = $this->getToken();

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->get($this->apiUrl().'/prestamos?per_page=100');
        } catch (ConnectionException $e) {
            return view('prestamos.entregas', [
                'prestamos' => [],
                'totalPendientes' => 0,
                'errorConexion' => 'No fue posible conectar con el servicio de préstamos.',
            ]);
        }

        if ($response->status() === 401) {
            session()->forget(['api_token', 'auth_token', 'user']);

            return redirect()->route('login')->withErrors(['email' => 'Tu sesión expiró. Inicia sesión nuevamente.']);
        }

        // Un 5xx no puede confundirse con "no hay entregas": se avisa del fallo.
        if ($response->failed()) {
            return view('prestamos.entregas', [
                'prestamos' => [],
                'totalPendientes' => 0,
                'errorConexion' => $response->json('message') ?? 'No fue posible cargar las entregas pendientes.',
            ]);
        }

        $todosPrestamos = $response->json('data') ?? [];

        // Solo los préstamos aprobados están listos para entrega física:
        // el backend (EntregaPrestamoService) rechaza cualquier otra transición de estado.
        $entregasPendientes = array_values(array_filter($todosPrestamos, function ($p) {
            return strtolower($p['estado'] ?? '') === 'aprobado';
        }));

        return view('prestamos.entregas', [
            'prestamos' => $entregasPendientes,
            'totalPendientes' => count($entregasPendientes),
        ]);
    }

    /**
     * Registra la entrega física del equipo al solicitante (HU-09).
     */
    public function registrarEntrega(Request $request, string $id): RedirectResponse
    {
        if ($redirigido = $this->guardiaPersonalAutorizado()) {
            return $redirigido;
        }

        $token = $this->getToken();

        $request->validate([
            'condicion_entrega' => 'required|string|in:bueno,con_danos,requiere_mantenimiento',
            'observaciones' => 'nullable|string|max:2000',
        ], [
            'condicion_entrega.required' => 'Debes indicar la condición en que se entrega el equipo.',
            'condicion_entrega.in' => 'La condición de entrega no es válida.',
        ]);

        $payload = [
            'condicion_entrega' => $request->input('condicion_entrega', 'bueno'),
            'observaciones' => $request->input('observaciones'),
        ];

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->post($this->apiUrl()."/prestamos/{$id}/entrega", $payload);
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
            $errores = $response->json('errors') ?? [];

            // Aplanar los errores de validación del backend (422) para mostrarlos al usuario
            $mensajesAplanados = [];
            foreach ($errores as $msgs) {
                foreach ((array) $msgs as $m) {
                    $mensajesAplanados[] = $m;
                }
            }

            return back()->withErrors($mensajesAplanados ?: ['error' => $mensaje]);
        }

        return redirect()->route('prestamos.entregas')
            ->with('success', '¡Entrega registrada exitosamente! El préstamo pasó a estado "Entregado" y el equipo ahora está "En préstamo".');
    }

    /**
     * Muestra el módulo de devoluciones (HU-11 / KAN-88).
     */
    public function devolucionesIndex(Request $request)
    {
        if ($redirigido = $this->guardiaPersonalAutorizado()) {
            return $redirigido;
        }

        $token = $this->getToken();

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->get($this->apiUrl().'/prestamos?per_page=100');
        } catch (ConnectionException $e) {
            return view('prestamos.devoluciones', [
                'pendientes' => [],
                'historial' => [],
                'totalPendientes' => 0,
                'totalDevueltos' => 0,
                'errorConexion' => 'No fue posible conectar con el servicio de préstamos.',
            ]);
        }

        if ($response->status() === 401) {
            session()->forget(['api_token', 'auth_token', 'user']);

            return redirect()->route('login')->withErrors(['email' => 'Tu sesión expiró. Inicia sesión nuevamente.']);
        }

        // Un 5xx no puede confundirse con "no hay devoluciones": se avisa del fallo.
        if ($response->failed()) {
            return view('prestamos.devoluciones', [
                'pendientes' => [],
                'historial' => [],
                'totalPendientes' => 0,
                'totalDevueltos' => 0,
                'errorConexion' => $response->json('message') ?? 'No fue posible cargar las devoluciones.',
            ]);
        }

        $todos = $response->json('data') ?? [];

        // Préstamos con equipo entregado pendientes de ser devueltos
        $pendientesDevolucion = array_values(array_filter($todos, function ($p) {
            return strtolower($p['estado'] ?? '') === 'entregado';
        }));

        // Historial de devoluciones cerradas
        $historialDevueltos = array_values(array_filter($todos, function ($p) {
            return strtolower($p['estado'] ?? '') === 'devuelto';
        }));

        return view('prestamos.devoluciones', [
            'pendientes' => $pendientesDevolucion,
            'historial' => $historialDevueltos,
            'totalPendientes' => count($pendientesDevolucion),
            'totalDevueltos' => count($historialDevueltos),
        ]);
    }

    /**
     * Registra la devolución física del equipo (HU-11).
     */
    public function registrarDevolucion(Request $request, string $id): RedirectResponse
    {
        if ($redirigido = $this->guardiaPersonalAutorizado()) {
            return $redirigido;
        }

        $token = $this->getToken();

        $condicion = $request->input('condicion_devolucion');

        $reglas = [
            'condicion_devolucion' => 'required|string|in:bueno,con_danos,requiere_mantenimiento',
            'observaciones' => ($condicion !== 'bueno') ? 'required|string|min:5|max:2000' : 'nullable|string|max:2000',
        ];

        $mensajes = [
            'condicion_devolucion.required' => 'Debes indicar la condición en que se recibe el equipo.',
            'observaciones.required' => 'Las observaciones son obligatorias cuando el equipo presenta daños o requiere mantenimiento.',
            'observaciones.min' => 'Describe con mayor detalle el daño o mantenimiento requerido.',
        ];

        $request->validate($reglas, $mensajes);

        $payload = [
            'condicion_devolucion' => $condicion,
            'observaciones' => $request->input('observaciones'),
        ];

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->post($this->apiUrl()."/prestamos/{$id}/devolucion", $payload);
        } catch (ConnectionException $e) {
            return back()->withErrors(['error' => 'No fue posible conectar con el servidor backend.']);
        }

        if ($response->status() === 401) {
            session()->forget(['api_token', 'auth_token', 'user']);

            return redirect()->route('login')->withErrors(['email' => 'Tu sesión expiró. Inicia sesión nuevamente.']);
        }

        if ($response->status() === 403) {
            return back()->withErrors(['error' => 'No tienes permisos para registrar la devolución de equipos.']);
        }

        if ($response->failed()) {
            $errorMsg = $response->json('message') ?? 'Error al procesar la devolución del equipo.';
            $errores = $response->json('errors') ?? [];

            // Aplanar los errores de validación del backend (422) para mostrarlos al usuario
            $mensajesAplanados = [];
            foreach ($errores as $msgs) {
                foreach ((array) $msgs as $m) {
                    $mensajesAplanados[] = $m;
                }
            }

            return back()->withErrors($mensajesAplanados ?: ['error' => $errorMsg]);
        }

        // RN-08: si la condición no es «bueno», el backend abre una incidencia y
        // restringe el equipo. El aviso lo confirma explícitamente al encargado.
        if ($condicion === 'bueno') {
            $mensajeExito = '¡Devolución registrada con éxito! El equipo ha regresado al estado "Disponible".';
        } else {
            $incidencia = $response->json('data.incidencia') ?? $response->json('incidencia');
            $restriccion = is_array($incidencia) ? ($incidencia['restringe_hasta'] ?? null) : null;

            $mensajeExito = '¡Devolución registrada con éxito! Debido a la condición reportada, el equipo ha pasado a "En mantenimiento" y se abrió una incidencia que restringe su uso.';

            if (! empty($restriccion)) {
                $mensajeExito .= ' Restringido hasta el '.Carbon::parse($restriccion)->timezone('America/Bogota')->format('d/m/Y H:i').'.';
            }
        }

        return redirect()->route('prestamos.devoluciones')->with('success', $mensajeExito);
    }

    /**
     * Muestra la bandeja de solicitudes pendientes (HU-07 / KAN-113).
     * Consume GET /api/prestamos/pendientes (endpoint dedicado).
     */
    public function solicitudesIndex(Request $request)
    {
        if ($redirigido = $this->guardiaPersonalAutorizado()) {
            return $redirigido;
        }

        $token = $this->getToken();

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                // El backend pagina a 15 por defecto y la vista no tiene paginación:
                // se pide el máximo de una página para no ocultar solicitudes.
                ->get($this->apiUrl().'/prestamos/pendientes', ['per_page' => 100]);
        } catch (ConnectionException $e) {
            return view('prestamos.solicitudes', [
                'solicitudes' => [],
                'errorConexion' => 'No fue posible conectar con el servicio de préstamos.',
            ]);
        }

        if ($response->status() === 401) {
            session()->forget(['api_token', 'auth_token', 'user']);

            return redirect()->route('login')->withErrors(['email' => 'Tu sesión expiró. Inicia sesión nuevamente.']);
        }

        if ($response->failed()) {
            $msg = $response->json('message') ?? 'Error al cargar las solicitudes pendientes.';

            return view('prestamos.solicitudes', [
                'solicitudes' => [],
                'errorConexion' => $msg,
            ]);
        }

        // Soporta respuesta directa o envuelta en data/meta
        $pendientes = $response->json('data') ?? $response->json() ?? [];
        if (isset($pendientes['data'])) {
            $pendientes = $pendientes['data'];
        }

        return view('prestamos.solicitudes', [
            'solicitudes' => is_array($pendientes) ? $pendientes : [],
        ]);
    }

    /**
     * Aprueba una solicitud de préstamo (HU-08).
     * Contrato del backend: POST /api/prestamos/{prestamo}/aprobacion (sin body).
     */
    public function aprobarSolicitud(Request $request, string $id): RedirectResponse
    {
        if ($redirigido = $this->guardiaPersonalAutorizado()) {
            return $redirigido;
        }

        // RN-03: una solicitud restringida no se aprueba mientras no esté autorizada.
        if ($request->boolean('requiere_autorizacion') && ! $request->boolean('autorizado')) {
            return back()->withErrors([
                'autorizacion' => 'La solicitud #'.$id.' requiere autorización especial. Usa el botón «Autorizar» antes de aprobarla.',
            ]);
        }

        $token = $this->getToken();

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->post($this->apiUrl()."/prestamos/{$id}/aprobacion");
        } catch (ConnectionException $e) {
            return back()->withErrors(['error' => 'No fue posible conectar con el backend.']);
        }

        if ($response->status() === 401) {
            session()->forget(['api_token', 'auth_token', 'user']);

            return redirect()->route('login')->withErrors(['email' => 'Tu sesión expiró. Inicia sesión nuevamente.']);
        }

        if ($response->status() === 403) {
            return back()->withErrors(['error' => 'No tienes permisos para aprobar solicitudes.']);
        }

        if ($response->failed()) {
            $msg = $response->json('message') ?? 'No se pudo aprobar la solicitud.';
            $errores = $response->json('errors') ?? [];

            $mensajesAplanados = [];
            foreach ($errores as $msgs) {
                foreach ((array) $msgs as $m) {
                    $mensajesAplanados[] = $m;
                }
            }

            return back()->withErrors($mensajesAplanados ?: ['error' => $msg]);
        }

        return redirect()->route('prestamos.solicitudes')
            ->with('success', '¡Solicitud #'.$id.' aprobada exitosamente! Ahora se encuentra lista para entrega física.');
    }

    /**
     * Autoriza una solicitud restringida (RN-03).
     * Contrato del backend: POST /api/prestamos/{prestamo}/autorizacion (sin body).
     */
    public function autorizarSolicitud(Request $request, string $id): RedirectResponse
    {
        if ($redirigido = $this->guardiaPersonalAutorizado()) {
            return $redirigido;
        }

        $token = $this->getToken();

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->post($this->apiUrl()."/prestamos/{$id}/autorizacion");
        } catch (ConnectionException $e) {
            return back()->withErrors(['error' => 'No fue posible conectar con el backend.']);
        }

        if ($response->status() === 401) {
            session()->forget(['api_token', 'auth_token', 'user']);

            return redirect()->route('login')->withErrors(['email' => 'Tu sesión expiró. Inicia sesión nuevamente.']);
        }

        if ($response->status() === 403) {
            return back()->withErrors(['error' => 'No tienes permisos para autorizar solicitudes.']);
        }

        if ($response->failed()) {
            $msg = $response->json('message') ?? 'No se pudo autorizar la solicitud.';
            $errores = $response->json('errors') ?? [];

            $mensajesAplanados = [];
            foreach ($errores as $msgs) {
                foreach ((array) $msgs as $m) {
                    $mensajesAplanados[] = $m;
                }
            }

            return back()->withErrors($mensajesAplanados ?: ['error' => $msg]);
        }

        return redirect()->route('prestamos.solicitudes')
            ->with('success', 'La solicitud #'.$id.' fue autorizada. Ya puede aprobarse.');
    }

    /**
     * Rechaza una solicitud de préstamo (HU-08).
     * Contrato del backend: POST /api/prestamos/{prestamo}/rechazo con {motivo} required (max:1000).
     */
    public function rechazarSolicitud(Request $request, string $id): RedirectResponse
    {
        if ($redirigido = $this->guardiaPersonalAutorizado()) {
            return $redirigido;
        }

        $token = $this->getToken();

        $request->validate([
            'motivo' => 'required|string|max:1000',
        ], [
            'motivo.required' => 'Debes indicar el motivo del rechazo.',
            'motivo.max' => 'El motivo no puede superar los 1000 caracteres.',
        ]);

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->post($this->apiUrl()."/prestamos/{$id}/rechazo", [
                    'motivo' => $request->input('motivo'),
                ]);
        } catch (ConnectionException $e) {
            return back()->withErrors(['error' => 'No fue posible conectar con el backend.']);
        }

        if ($response->status() === 401) {
            session()->forget(['api_token', 'auth_token', 'user']);

            return redirect()->route('login')->withErrors(['email' => 'Tu sesión expiró. Inicia sesión nuevamente.']);
        }

        if ($response->status() === 403) {
            return back()->withErrors(['error' => 'No tienes permisos para rechazar solicitudes.']);
        }

        if ($response->failed()) {
            $msg = $response->json('message') ?? 'No se pudo rechazar la solicitud.';
            $errores = $response->json('errors') ?? [];

            $mensajesAplanados = [];
            foreach ($errores as $msgs) {
                foreach ((array) $msgs as $m) {
                    $mensajesAplanados[] = $m;
                }
            }

            return back()->withErrors($mensajesAplanados ?: ['error' => $msg]);
        }

        return redirect()->route('prestamos.solicitudes')
            ->with('success', 'La solicitud #'.$id.' ha sido rechazada.');
    }

    /**
     * Muestra los préstamos activos del solicitante autenticado (HU-10 / KAN-120).
     * Consume GET /api/prestamos?activos=1 (filtro del solicitante, NO el endpoint administrativo).
     */
    public function misPrestamosActivos(Request $request)
    {
        $token = $this->getToken();
        if (! $token) {
            return redirect()->route('login');
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                // per_page máximo: la vista no pagina y el backend pagina a 15 por defecto.
                ->get($this->apiUrl().'/prestamos', ['activos' => 1, 'per_page' => 100]);
        } catch (ConnectionException $e) {
            return view('prestamos.mis-activos', [
                'prestamos' => [],
                'errorConexion' => 'No fue posible conectar con el servicio de préstamos.',
            ]);
        }

        if ($response->status() === 401) {
            session()->forget(['api_token', 'auth_token', 'user']);

            return redirect()->route('login')->withErrors(['email' => 'Tu sesión expiró. Inicia sesión nuevamente.']);
        }

        if ($response->failed()) {
            $msg = $response->json('message') ?? 'Error al cargar tus préstamos activos.';

            return view('prestamos.mis-activos', [
                'prestamos' => [],
                'errorConexion' => $msg,
            ]);
        }

        $datos = $response->json('data') ?? $response->json() ?? [];
        if (isset($datos['data'])) {
            $datos = $datos['data'];
        }

        return view('prestamos.mis-activos', [
            'prestamos' => is_array($datos) ? $datos : [],
        ]);
    }
}
