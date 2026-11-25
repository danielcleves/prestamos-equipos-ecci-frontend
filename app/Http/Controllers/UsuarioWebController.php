<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CatalogoRoles;
use App\Support\CapacitacionesVista;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class UsuarioWebController extends Controller
{
    use CatalogoRoles;

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

        // RN-02: roles disponibles (incluye los personalizados que cree el admin).
        $roles = $this->consultarRoles($token);

        // RN-01: categorías en las que se puede capacitar a un usuario.
        [$categorias, $errorCategorias] = $this->consultarCategorias($token, $usuarios);

        return view('usuarios.index', compact('usuarios', 'total', 'activos', 'inactivos', 'roles', 'categorias', 'errorCategorias'));
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
            'role' => ['required', 'string', 'max:125', 'regex:/^[a-z0-9_]+$/'],
            ...$this->reglasCapacitaciones(),
        ]);

        $payload = $request->only('name', 'email', 'password', 'role');

        // RN-01: nadie arranca capacitado; solo viajan las categorías marcadas.
        if ($request->boolean('capacitaciones_enviadas')) {
            $payload['capacitaciones'] = $this->idsCapacitaciones($request);
        }

        $token = $this->getToken();

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->post($this->apiUrl().'/usuarios', $payload);
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
            'role' => ['required', 'string', 'max:125', 'regex:/^[a-z0-9_]+$/'],
            'password' => 'nullable|min:8',
            ...$this->reglasCapacitaciones(),
        ]);

        $id = (int) $user;

        $payload = [
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'role' => $request->input('role'),
        ];

        // RN-01: con el grupo de categorías en pantalla se envía siempre la lista
        // completa (el backend la sincroniza); [] quita todas las capacitaciones.
        if ($request->boolean('capacitaciones_enviadas')) {
            $payload['capacitaciones'] = $this->idsCapacitaciones($request);
        }

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

    /**
     * Reglas del grupo de capacitaciones (RN-01): ids de categoría.
     * `capacitaciones_enviadas` indica que el formulario mostró el grupo, para
     * distinguir «no marcó ninguna» (se envía []) de «no se tocó».
     *
     * @return array<string, mixed>
     */
    private function reglasCapacitaciones(): array
    {
        return [
            'capacitaciones_enviadas' => 'nullable|boolean',
            'capacitaciones' => 'nullable|array',
            'capacitaciones.*' => 'integer|min:1|distinct',
        ];
    }

    /**
     * @return list<int>
     */
    private function idsCapacitaciones(Request $request): array
    {
        return array_values(array_unique(array_map('intval', (array) $request->input('capacitaciones', []))));
    }

    /**
     * Categorías del inventario para el grupo de capacitaciones (RN-01).
     *
     * El front no tiene un listado propio de categorías: se reutiliza
     * GET /api/configuracion (solo admin, como esta pantalla), que trae
     * `categorias[]` con `requiere_capacitacion`. Se añaden las categorías que
     * ya figuran en las capacitaciones de los usuarios, para que editar a alguien
     * nunca le quite una capacitación por no verla en la lista.
     *
     * @param  list<array<string, mixed>>  $usuarios
     * @return array{0: list<array{id: int, nombre: string, requiere_capacitacion: bool}>, 1: string|null}
     */
    private function consultarCategorias(?string $token, array $usuarios): array
    {
        $categorias = [];
        $error = null;

        try {
            $response = Http::withToken((string) $token)
                ->acceptJson()
                ->timeout(10)
                ->get($this->apiUrl().'/configuracion');

            if ($response->failed()) {
                $error = 'No fue posible cargar todas las categorías; solo se muestran las que ya tienen usuarios capacitados.';
            } else {
                $lista = $response->json('data.categorias') ?? $response->json('categorias') ?? [];

                foreach (is_array($lista) ? $lista : [] as $categoria) {
                    if (is_array($categoria) && isset($categoria['id'])) {
                        $id = (int) $categoria['id'];
                        $categorias[$id] = [
                            'id' => $id,
                            'nombre' => (string) ($categoria['nombre'] ?? 'Categoría '.$id),
                            'requiere_capacitacion' => filter_var($categoria['requiere_capacitacion'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        ];
                    }
                }
            }
        } catch (ConnectionException $e) {
            $error = 'No fue posible cargar todas las categorías; solo se muestran las que ya tienen usuarios capacitados.';
        }

        foreach ($usuarios as $usuario) {
            foreach (CapacitacionesVista::desde($usuario) as $capacitacion) {
                $categorias[$capacitacion['categoria_id']] ??= [
                    'id' => $capacitacion['categoria_id'],
                    'nombre' => $capacitacion['nombre'],
                    'requiere_capacitacion' => false,
                ];
            }
        }

        ksort($categorias);

        return [array_values($categorias), $error];
    }
}
