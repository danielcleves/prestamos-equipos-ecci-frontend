<?php

namespace App\Http\Controllers;

use App\Support\FechaNegocio;
use App\Support\SancionVista;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Panel de inicio (D-2): cifras reales consultadas a la API en cada visita.
 *
 * Cada consulta falla por separado: si un endpoint no responde, esa tarjeta
 * muestra «No disponible» y el resto del panel se pinta igual (aviso no
 * bloqueante). Solo un 401 saca al usuario de la sesión.
 */
class InicioController extends Controller
{
    private const MAX_URGENTES = 5;

    private bool $sesionExpirada = false;

    public function __invoke()
    {
        $token = session('api_token') ?? session('auth_token');

        if (! $token) {
            return redirect()->route('login');
        }

        $roles = $this->nombresRoles();
        $esAdmin = in_array('admin', $roles, true);
        $esPersonal = $esAdmin || in_array('encargado', $roles, true);

        $panel = $esPersonal
            ? $this->panelPersonal($token, $esAdmin)
            : $this->panelSolicitante($token);

        if ($this->sesionExpirada) {
            session()->forget(['api_token', 'auth_token', 'user']);

            return redirect()->route('login')->withErrors(['email' => 'Tu sesión expiró. Inicia sesión nuevamente.']);
        }

        return view('inicio', ['esAdmin' => $esAdmin, 'esPersonal' => $esPersonal] + $panel);
    }

    /**
     * @return list<string>
     */
    private function nombresRoles(): array
    {
        $user = session('user') ?? [];
        $roles = $user['roles'] ?? [];

        if (is_array($roles[0] ?? null)) {
            return array_column($roles, 'name');
        }

        return is_array($roles) ? array_values($roles) : array_filter([$user['role'] ?? $user['rol'] ?? null]);
    }

    /**
     * GET a la API. Devuelve null si falla (red o error HTTP).
     *
     * @param  array<string, mixed>  $query
     * @return array{items: list<array<string, mixed>>, total: int}|null
     */
    private function consultar(string $token, string $ruta, array $query = []): ?array
    {
        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->get(config('services.backend.url').$ruta, $query + ['per_page' => 100]);
        } catch (ConnectionException $e) {
            return null;
        }

        if ($response->status() === 401) {
            $this->sesionExpirada = true;

            return null;
        }

        if ($response->failed()) {
            return null;
        }

        return $this->normalizar($response);
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    private function normalizar(Response $response): array
    {
        $items = $response->json('data');
        $items = is_array($items) ? array_values($items) : [];

        return ['items' => $items, 'total' => (int) ($response->json('meta.total') ?? count($items))];
    }

    /**
     * @param  array<string, mixed>  $prestamo
     * @return array<string, mixed>
     */
    private function fila(array $prestamo, string $accion, string $ruta, ?int $dias = null): array
    {
        $estado = (string) ($prestamo['estado'] ?? '');
        $dias ??= FechaNegocio::diasDeRetraso($estado, $prestamo['fecha_devolucion_estimada'] ?? null);

        return [
            'equipo' => $prestamo['equipo']['nombre'] ?? 'Equipo',
            'codigo' => $prestamo['equipo']['codigo'] ?? null,
            'solicitante' => $prestamo['solicitante']['name'] ?? null,
            'estado' => $dias > 0 ? 'retrasado' : $estado,
            'dias' => $dias,
            'fecha' => FechaNegocio::fecha($prestamo['fecha_devolucion_estimada'] ?? null),
            'accion' => $accion,
            'ruta' => $ruta,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function panelPersonal(string $token, bool $esAdmin): array
    {
        $porAprobar = $this->consultar($token, '/prestamos/pendientes');
        $porEntregar = $this->consultar($token, '/prestamos', ['estado' => 'aprobado']);
        $porDevolver = $this->consultar($token, '/prestamos/activos');

        $retrasados = $porDevolver === null ? [] : array_values(array_filter(
            $porDevolver['items'],
            fn ($p) => FechaNegocio::diasDeRetraso((string) ($p['estado'] ?? ''), $p['fecha_devolucion_estimada'] ?? null) > 0
        ));
        usort($retrasados, fn ($a, $b) => strcmp((string) $a['fecha_devolucion_estimada'], (string) $b['fecha_devolucion_estimada']));

        $idsRetrasados = array_column($retrasados, 'id');
        $enPlazo = $porDevolver === null ? [] : array_values(array_filter(
            $porDevolver['items'],
            fn ($p) => ! in_array($p['id'] ?? null, $idsRetrasados, true)
        ));

        // Lo más urgente: retrasados, luego solicitudes por aprobar, entregas y devoluciones en plazo.
        $urgentes = array_merge(
            array_map(fn ($p) => $this->fila($p, 'Registrar devolución', route('prestamos.devoluciones')), $retrasados),
            array_map(fn ($p) => $this->fila($p, 'Revisar solicitud', route('prestamos.solicitudes')), $porAprobar['items'] ?? []),
            array_map(fn ($p) => $this->fila($p, 'Registrar entrega', route('prestamos.entregas')), $porEntregar['items'] ?? []),
            array_map(fn ($p) => $this->fila($p, 'Registrar devolución', route('prestamos.devoluciones')), $enPlazo),
        );

        $panel = [
            'porAprobar' => $porAprobar['total'] ?? null,
            'porEntregar' => $porEntregar['total'] ?? null,
            'porDevolver' => $porDevolver['total'] ?? null,
            'retrasados' => $porDevolver === null ? null : count($retrasados),
            'urgentes' => array_slice($urgentes, 0, self::MAX_URGENTES),
            'hayFallos' => $porAprobar === null || $porEntregar === null || $porDevolver === null,
        ];

        if ($esAdmin) {
            $equipos = $this->consultar($token, '/equipos');
            $incidencias = $this->consultar($token, '/incidencias');

            $panel['equiposTotal'] = $equipos['total'] ?? null;
            $panel['equiposDisponibles'] = $equipos === null ? null : count(array_filter(
                $equipos['items'],
                fn ($e) => ($e['estado'] ?? '') === 'disponible'
            ));
            $panel['incidenciasAbiertas'] = $incidencias === null ? null : count(array_filter(
                $incidencias['items'],
                fn ($i) => ($i['estado'] ?? 'abierta') !== 'cerrada'
            ));
            $panel['hayFallos'] = $panel['hayFallos'] || $equipos === null || $incidencias === null;
        }

        return $panel;
    }

    /**
     * @return array<string, mixed>
     */
    private function panelSolicitante(string $token): array
    {
        $activos = $this->consultar($token, '/prestamos', ['activos' => 1]);

        $misPrestamos = array_map(
            fn ($p) => $this->fila($p, 'Ver mis préstamos', route('prestamos.mis-activos')),
            $activos['items'] ?? []
        );
        // Retrasados primero, luego por fecha de devolución más próxima.
        usort($misPrestamos, fn ($a, $b) => [$b['dias'], $a['fecha']] <=> [$a['dias'], $b['fecha']]);

        return [
            'misPrestamos' => $misPrestamos,
            'misRetrasados' => count(array_filter($misPrestamos, fn ($p) => $p['dias'] > 0)),
            'hayFallos' => $activos === null,
            'sancionActiva' => $this->sancionActiva($token),
        ];
    }

    /**
     * Sanción vigente del usuario (GET /api/me). Si la consulta falla no se
     * inventa nada: simplemente no se muestra el aviso.
     *
     * @return array<string, mixed>|null
     */
    private function sancionActiva(string $token): ?array
    {
        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->get(config('services.backend.url').'/me');
        } catch (ConnectionException $e) {
            return null;
        }

        if ($response->status() === 401) {
            $this->sesionExpirada = true;

            return null;
        }

        return $response->successful()
            ? SancionVista::desde($response->json('user.sancion_activa') ?? $response->json('data.sancion_activa'))
            : null;
    }
}
