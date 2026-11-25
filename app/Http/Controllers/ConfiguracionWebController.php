<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CatalogoRoles;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Pantalla de parámetros de las reglas de negocio (RN-01…RN-08, iteración 2).
 *
 * Consume el contrato del backend:
 *  - GET   /api/configuracion                    → parámetros agrupados
 *  - PUT   /api/configuracion                    → mapa clave → valor
 *  - PATCH /api/categorias/{categoria}/restriccion → nivel 0/1/2 (RN-03) y/o
 *    si la categoría exige capacitación (RN-01, por categoría)
 *
 * Los cupos por rol (RN-02) se gestionan en /roles; aquí solo se muestran.
 *
 * Solo el rol administrador puede entrar; el backend lo vuelve a validar.
 */
class ConfiguracionWebController extends Controller
{
    use CatalogoRoles;

    /** Valores iniciales acordados con el PO (Diseño - Reglas de negocio iteración 2). */
    private const VALORES_POR_DEFECTO = [
        'cupos.roles' => ['estudiante' => 3, 'encargado' => 5, 'admin' => 10],
        'duracion.categorias' => [1 => 7, 2 => 5, 3 => 3],
        'duracion.roles_extra' => ['estudiante' => 0, 'encargado' => 2, 'admin' => 3],
        'mora.escala' => [
            ['desde' => 1, 'hasta' => 2, 'dias_sancion' => 3],
            ['desde' => 3, 'hasta' => 5, 'dias_sancion' => 7],
            ['desde' => 6, 'hasta' => null, 'dias_sancion' => 15],
        ],
        'mora.dias_bloqueo' => 1,
        'danos.dias_restriccion' => 7,
        'registro.correos_permitidos' => ['@ecci.edu.co'],
    ];

    /** Nombres conocidos de las categorías del inventario (las crea el admin en Equipos). */
    private const NOMBRES_CATEGORIA = [1 => 'Portátil', 2 => 'Tablet', 3 => 'De mesa'];

    private function apiUrl(): string
    {
        return config('services.backend.url');
    }

    private function getToken(): ?string
    {
        return session('api_token') ?? session('auth_token');
    }

    /**
     * Guardia de la sección: sesión activa y rol administrador.
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
            $nombresRoles = array_column($roles, 'name');
        } else {
            $nombresRoles = is_array($roles) ? $roles : [];
        }

        if (empty($nombresRoles)) {
            $nombresRoles = array_filter([$user['role'] ?? $user['rol'] ?? null]);
        }

        return in_array('admin', $nombresRoles, true);
    }

    /**
     * Muestra los parámetros de las reglas de negocio.
     */
    public function index()
    {
        if ($redirigido = $this->guardia()) {
            return $redirigido;
        }

        $token = $this->getToken();

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->get($this->apiUrl().'/configuracion');
        } catch (ConnectionException $e) {
            return $this->render(null, 'No fue posible conectar con el servicio de configuración.');
        }

        if ($response->status() === 401) {
            session()->forget(['api_token', 'auth_token', 'user']);

            return redirect()->route('login')->withErrors(['email' => 'Tu sesión expiró. Inicia sesión nuevamente.']);
        }

        if ($response->status() === 403) {
            return redirect()->route('inicio')
                ->withErrors(['inicio' => 'No tienes permisos para ver la configuración del sistema.']);
        }

        if ($response->failed()) {
            return $this->render(null, $response->json('message') ?? 'No fue posible cargar la configuración.');
        }

        return $this->render($response->json());
    }

    /**
     * Guarda los parámetros enviados desde la pantalla.
     */
    public function update(Request $request): RedirectResponse
    {
        if ($redirigido = $this->guardia()) {
            return $redirigido;
        }

        $datos = $request->validate([
            'duracion_categorias' => ['required', 'array', 'min:1'],
            'duracion_categorias.*' => ['required', 'integer', 'min:1', 'max:365'],
            // RN-06: un campo por rol, también los personalizados (profesor…).
            'duracion_extra' => ['required', 'array', 'min:1'],
            'duracion_extra.*' => ['required', 'integer', 'min:0', 'max:365'],
            'mora_escala' => ['nullable', 'array'],
            'mora_escala.*.desde' => ['required', 'integer', 'min:1', 'max:365'],
            'mora_escala.*.hasta' => ['nullable', 'integer', 'min:1', 'max:365'],
            'mora_escala.*.dias_sancion' => ['required', 'integer', 'min:1', 'max:365'],
            'mora_dias_bloqueo' => ['required', 'integer', 'min:0', 'max:365'],
            'danos_dias_restriccion' => ['required', 'integer', 'min:0', 'max:365'],
            'correos_permitidos' => ['nullable', 'string'],
        ], [
            'duracion_categorias.required' => 'Debes indicar la duración máxima por categoría.',
            'duracion_categorias.*.integer' => 'Las duraciones deben ser números enteros.',
            'duracion_categorias.*.min' => 'La duración máxima debe ser de al menos 1 día.',
            'duracion_extra.*.required' => 'Indica los días extra de cada rol (0 si no tiene).',
            'duracion_extra.*.integer' => 'Los días extra por rol deben ser números enteros.',
            'duracion_extra.*.min' => 'Los días extra por rol no pueden ser negativos.',
            'mora_escala.*.desde.required' => 'Cada tramo de mora necesita el día "desde".',
            'mora_escala.*.dias_sancion.required' => 'Cada tramo de mora necesita los días de sanción.',
            'mora_dias_bloqueo.required' => 'Debes indicar el umbral de bloqueo por mora.',
            'danos_dias_restriccion.required' => 'Debes indicar los días de restricción por daños.',
        ]);

        $escala = $this->escalaDesdeFormulario($datos['mora_escala'] ?? []);

        $parametros = [
            'duracion.categorias' => $this->mapaEnteros($datos['duracion_categorias']),
            'duracion.roles_extra' => $this->extraPorRol($datos['duracion_extra']),
            'mora.escala' => $escala,
            'mora.dias_bloqueo' => (int) $datos['mora_dias_bloqueo'],
            'danos.dias_restriccion' => (int) $datos['danos_dias_restriccion'],
        ];

        if ($request->filled('correos_permitidos')) {
            $correos = collect(preg_split('/[\r\n,]+/', (string) $request->input('correos_permitidos')))
                ->map(fn ($correo) => strtolower(trim($correo)))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($correos === []) {
                throw ValidationException::withMessages([
                    'correos_permitidos' => 'Debes autorizar al menos un correo o dominio para el registro.',
                ]);
            }

            $parametros['registro.correos_permitidos'] = $correos;
        }

        $token = $this->getToken();

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->put($this->apiUrl().'/configuracion', $parametros);
        } catch (ConnectionException $e) {
            return back()->withInput()
                ->withErrors(['configuracion_error' => 'No fue posible conectar con el servicio de configuración.']);
        }

        if ($response->status() === 401) {
            session()->forget(['api_token', 'auth_token', 'user']);

            return redirect()->route('login')->withErrors(['email' => 'Tu sesión expiró. Inicia sesión nuevamente.']);
        }

        if ($response->status() === 403) {
            return redirect()->route('inicio')
                ->withErrors(['inicio' => 'No tienes permisos para cambiar la configuración.']);
        }

        if ($response->failed()) {
            $mensajes = $this->aplanarErrores($response->json('errors') ?? []);
            $mensajeGeneral = $response->json('message') ?? 'No fue posible guardar la configuración.';

            return back()->withInput()
                ->withErrors(['configuracion_error' => $mensajes[0] ?? $mensajeGeneral]);
        }

        return redirect()->route('configuracion.index')
            ->with('success', 'Parámetros de configuración actualizados correctamente.');
    }

    /**
     * Cambia la restricción de una categoría: el nivel (RN-03) y si exige
     * capacitación para solicitar sus equipos (RN-01, por categoría).
     * 0 = libre, 1 = requiere autorización, 2 = solo personal administrativo.
     * Llega al menos uno de los dos campos; solo se envía al backend lo que llega.
     */
    public function actualizarRestriccion(Request $request, string $id): RedirectResponse
    {
        if ($redirigido = $this->guardia()) {
            return $redirigido;
        }

        $datos = $request->validate([
            'nivel_restriccion' => ['required_without:requiere_capacitacion', 'nullable', 'integer', 'in:0,1,2'],
            'requiere_capacitacion' => ['required_without:nivel_restriccion', 'nullable', 'boolean'],
        ], [
            'nivel_restriccion.required_without' => 'Debes indicar el nivel de restricción o si la categoría requiere capacitación.',
            'requiere_capacitacion.required_without' => 'Debes indicar el nivel de restricción o si la categoría requiere capacitación.',
            'nivel_restriccion.in' => 'El nivel de restricción debe ser 0, 1 o 2.',
            'requiere_capacitacion.boolean' => 'Indica si la categoría requiere capacitación (sí o no).',
        ]);

        $cambios = [];

        if (isset($datos['nivel_restriccion'])) {
            $cambios['nivel_restriccion'] = (int) $datos['nivel_restriccion'];
        }

        if (isset($datos['requiere_capacitacion'])) {
            $cambios['requiere_capacitacion'] = $request->boolean('requiere_capacitacion');
        }

        $token = $this->getToken();

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->patch($this->apiUrl()."/categorias/{$id}/restriccion", $cambios);
        } catch (ConnectionException $e) {
            return back()->withErrors(['restriccion_error' => 'No fue posible conectar con el servicio de configuración.']);
        }

        if ($response->status() === 401) {
            session()->forget(['api_token', 'auth_token', 'user']);

            return redirect()->route('login')->withErrors(['email' => 'Tu sesión expiró. Inicia sesión nuevamente.']);
        }

        if ($response->status() === 403) {
            return redirect()->route('inicio')
                ->withErrors(['inicio' => 'No tienes permisos para cambiar la restricción de las categorías.']);
        }

        if ($response->failed()) {
            $mensajes = $this->aplanarErrores($response->json('errors') ?? []);
            $mensajeGeneral = $response->json('message') ?? 'No fue posible actualizar la restricción de la categoría.';

            return back()->withErrors(['restriccion_error' => $mensajes[0] ?? $mensajeGeneral]);
        }

        return redirect()->route('configuracion.index')
            ->with('success', 'Restricción de la categoría actualizada correctamente.');
    }

    /**
     * Pinta la pantalla con los parámetros recibidos (o con los valores por defecto).
     *
     * @param  array<string, mixed>|null  $respuesta
     */
    private function render(?array $respuesta, ?string $errorConexion = null)
    {
        $datos = $this->cuerpoUtil($respuesta);

        $fuenteParametros = is_array($datos['parametros'] ?? null) ? $datos['parametros'] : $datos;
        $roles = $this->normalizarRoles($datos['roles'] ?? null);
        $parametros = $this->normalizarParametros($this->parametrosPlanos($fuenteParametros), $roles);
        $categorias = $this->categoriasDesdeRespuesta($datos, $parametros);

        return view('configuracion.index', [
            'parametros' => $parametros,
            'categorias' => $categorias,
            'roles' => $roles,
            'errorConexion' => $errorConexion,
            'title' => 'Configuración',
        ]);
    }

    /**
     * Extrae el objeto de datos del cuerpo JSON, sin la envoltura `data` si existe.
     *
     * @param  array<string, mixed>|null  $respuesta
     * @return array<string, mixed>
     */
    private function cuerpoUtil(?array $respuesta): array
    {
        $respuesta ??= [];

        $datos = $respuesta['data'] ?? $respuesta['configuracion'] ?? $respuesta;

        return is_array($datos) ? $datos : [];
    }

    /**
     * Convierte la respuesta del backend en un mapa plano `clave => valor`.
     * Acepta tres formas: registros con `clave`/`valor`, claves con punto y
     * grupos anidados (`cupos` → `roles`).
     *
     * @param  array<mixed>  $datos
     * @return array<string, mixed>
     */
    private function parametrosPlanos(array $datos): array
    {
        $planos = [];

        if (array_is_list($datos) && $datos !== [] && is_array($datos[0] ?? null)) {
            foreach ($datos as $registro) {
                if (! is_array($registro)) {
                    continue;
                }

                $clave = $registro['clave'] ?? $registro['key'] ?? null;
                if (is_string($clave)) {
                    $planos[$clave] = $registro['valor'] ?? $registro['value'] ?? null;
                }
            }
        } else {
            foreach ($datos as $clave => $valor) {
                if (! is_string($clave)) {
                    continue;
                }

                if (str_contains($clave, '.')) {
                    $planos[$clave] = $valor;

                    continue;
                }

                if (is_array($valor) && ! array_is_list($valor)) {
                    foreach ($valor as $subclave => $subvalor) {
                        if (is_string($subclave)) {
                            $planos[$clave.'.'.$subclave] = $subvalor;
                        }
                    }
                }
            }
        }

        // Solo interesan las claves del contrato.
        return array_intersect_key($planos, self::VALORES_POR_DEFECTO);
    }

    /**
     * @param  array<string, mixed>  $planos
     * @return array<string, mixed>
     */
    private function normalizarParametros(array $planos, array $roles): array
    {
        $parametros = self::VALORES_POR_DEFECTO;

        // RN-02: el cupo viene en el catálogo de roles; cupos.roles es respaldo.
        $cupos = is_array($planos['cupos.roles'] ?? null) ? $planos['cupos.roles'] : [];
        $parametros['cupos.roles'] = [];
        foreach ($roles as $rol) {
            $parametros['cupos.roles'][$rol['nombre']] = max(0, (int) ($cupos[$rol['nombre']] ?? $rol['cupo_prestamos']));
        }

        if (is_array($planos['duracion.categorias'] ?? null)) {
            $parametros['duracion.categorias'] = $this->mapaEnteros($planos['duracion.categorias']);
        }

        if (is_array($planos['duracion.roles_extra'] ?? null)) {
            $parametros['duracion.roles_extra'] = $planos['duracion.roles_extra'];
        }

        $parametros['duracion.roles_extra'] = $this->completarRoles(
            $roles,
            $parametros['duracion.roles_extra'],
            self::VALORES_POR_DEFECTO['duracion.roles_extra']
        );

        if (is_array($planos['mora.escala'] ?? null)) {
            $escala = $this->escalaDesdeRespuesta($planos['mora.escala']);
            if ($escala !== []) {
                $parametros['mora.escala'] = $escala;
            }
        }

        if (array_key_exists('mora.dias_bloqueo', $planos)) {
            $parametros['mora.dias_bloqueo'] = max(0, (int) $planos['mora.dias_bloqueo']);
        }

        if (array_key_exists('danos.dias_restriccion', $planos)) {
            $parametros['danos.dias_restriccion'] = max(0, (int) $planos['danos.dias_restriccion']);
        }

        if (is_array($planos['registro.correos_permitidos'] ?? null)) {
            $parametros['registro.correos_permitidos'] = array_values(array_filter(
                $planos['registro.correos_permitidos'],
                fn ($correo) => is_string($correo) && trim($correo) !== ''
            ));
        }

        return $parametros;
    }

    /**
     * Un valor por cada rol del catálogo: el guardado, el inicial o 0.
     *
     * @param  list<array<string, mixed>>  $roles
     * @param  array<mixed>  $valores
     * @param  array<string, int>  $porDefecto
     * @return array<string, int>
     */
    private function completarRoles(array $roles, array $valores, array $porDefecto): array
    {
        $resultado = [];

        foreach ($roles as $rol) {
            $nombre = $rol['nombre'];
            $resultado[$nombre] = max(0, (int) ($valores[$nombre] ?? $porDefecto[$nombre] ?? 0));
        }

        return $resultado;
    }

    /**
     * Mapa «rol => días extra» del formulario, solo con nombres de rol válidos.
     *
     * @param  array<mixed>  $valores
     * @return array<string, int>
     */
    private function extraPorRol(array $valores): array
    {
        $mapa = [];

        foreach ($valores as $rol => $dias) {
            if (is_string($rol) && preg_match('/^[a-z0-9_]{1,60}$/', $rol)) {
                $mapa[$rol] = max(0, (int) $dias);
            }
        }

        if ($mapa === []) {
            throw ValidationException::withMessages([
                'duracion_extra' => 'Indica los días extra de cada rol.',
            ]);
        }

        return $mapa;
    }

    /**
     * @param  array<mixed>  $valores
     * @return array<int, int>
     */
    private function mapaEnteros(array $valores): array
    {
        $mapa = [];

        foreach ($valores as $clave => $valor) {
            if (! is_numeric($clave) && ! is_int($clave)) {
                continue;
            }

            $mapa[(int) $clave] = (int) $valor;
        }

        ksort($mapa);

        return $mapa;
    }

    /**
     * @param  array<mixed>  $escala
     * @return list<array{desde: int, hasta: int|null, dias_sancion: int}>
     */
    private function escalaDesdeRespuesta(array $escala): array
    {
        $tramos = [];

        foreach ($escala as $tramo) {
            if (! is_array($tramo) || ! isset($tramo['desde'])) {
                continue;
            }

            $hasta = $tramo['hasta'] ?? null;

            $tramos[] = [
                'desde' => (int) $tramo['desde'],
                'hasta' => ($hasta === null || $hasta === '') ? null : (int) $hasta,
                'dias_sancion' => (int) ($tramo['dias_sancion'] ?? 0),
            ];
        }

        usort($tramos, fn ($a, $b) => $a['desde'] <=> $b['desde']);

        return $tramos;
    }

    /**
     * Normaliza los tramos que llegan del formulario y valida que no se crucen.
     *
     * @param  array<int|string, mixed>  $escala
     * @return list<array{desde: int, hasta: int|null, dias_sancion: int}>
     */
    private function escalaDesdeFormulario(array $escala): array
    {
        $tramos = $this->escalaDesdeRespuesta($escala);

        foreach ($tramos as $tramo) {
            if ($tramo['hasta'] !== null && $tramo['hasta'] < $tramo['desde']) {
                throw ValidationException::withMessages([
                    'mora_escala' => 'En la escala de mora, el día "hasta" no puede ser menor que el día "desde".',
                ]);
            }
        }

        return $tramos;
    }

    /**
     * @param  array<string, mixed>  $errores
     * @return list<string>
     */
    private function aplanarErrores(array $errores): array
    {
        $mensajes = [];

        foreach ($errores as $msgs) {
            foreach ((array) $msgs as $mensaje) {
                if (is_string($mensaje) && $mensaje !== '') {
                    $mensajes[] = $mensaje;
                }
            }
        }

        return $mensajes;
    }

    /**
     * Construye la lista de categorías con su nivel de restricción y si exigen
     * capacitación (RN-01, por categoría; arrancan sin requisito).
     *
     * @param  array<string, mixed>  $datos
     * @param  array<string, mixed>  $parametros
     * @return list<array{id: int, nombre: string, nivel_restriccion: int, requiere_capacitacion: bool}>
     */
    private function categoriasDesdeRespuesta(array $datos, array $parametros): array
    {
        // El backend puede exponer el nivel en un mapa aparte (id → nivel).
        $restricciones = [];

        $mapa = $datos['categorias_restriccion'] ?? $datos['restricciones'] ?? null;

        if (is_array($mapa)) {
            foreach ($mapa as $id => $nivel) {
                $restricciones[(int) $id] = $this->nivelValido($nivel);
            }
        }

        $categorias = [];

        $categoriasApi = is_array($datos['categorias'] ?? null) ? $datos['categorias'] : [];

        foreach ($categoriasApi as $categoria) {
            if (! is_array($categoria) || ! isset($categoria['id'])) {
                continue;
            }

            $id = (int) $categoria['id'];
            $categorias[$id] = [
                'id' => $id,
                'nombre' => (string) ($categoria['nombre'] ?? $this->nombreCategoria($id)),
                'nivel_restriccion' => $this->nivelValido($categoria['nivel_restriccion'] ?? ($restricciones[$id] ?? 0)),
                'requiere_capacitacion' => filter_var($categoria['requiere_capacitacion'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        // Si el backend no devuelve el catálogo, se deduce de las duraciones.
        foreach (array_keys($parametros['duracion.categorias']) as $id) {
            $id = (int) $id;
            if (! isset($categorias[$id])) {
                $categorias[$id] = [
                    'id' => $id,
                    'nombre' => $this->nombreCategoria($id),
                    'nivel_restriccion' => $restricciones[$id] ?? 0,
                    'requiere_capacitacion' => false,
                ];
            }
        }

        ksort($categorias);

        return array_values($categorias);
    }

    private function nivelValido(mixed $nivel): int
    {
        $nivel = (int) $nivel;

        return in_array($nivel, [0, 1, 2], true) ? $nivel : 0;
    }

    private function nombreCategoria(int $id): string
    {
        return self::NOMBRES_CATEGORIA[$id] ?? 'Categoría '.$id;
    }
}
