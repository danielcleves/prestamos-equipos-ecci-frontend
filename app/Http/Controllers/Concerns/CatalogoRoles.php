<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Catálogo de roles del backend (RN-02): estudiante, profesor, coordinador…
 * más los roles de sistema (admin, encargado).
 *
 * Si el backend no responde se usan los tres roles iniciales para que las
 * pantallas sigan funcionando.
 */
trait CatalogoRoles
{
    /**
     * @return list<array{id: int|null, nombre: string, etiqueta: string, descripcion: string|null, cupo_prestamos: int, es_sistema: bool, es_predeterminado: bool, usuarios_count: int|null}>
     */
    public static function rolesPorDefecto(): array
    {
        return [
            ['id' => null, 'nombre' => 'estudiante', 'etiqueta' => 'Estudiante', 'descripcion' => null, 'cupo_prestamos' => 3, 'es_sistema' => false, 'es_predeterminado' => true, 'usuarios_count' => null],
            ['id' => null, 'nombre' => 'encargado', 'etiqueta' => 'Personal de préstamo', 'descripcion' => null, 'cupo_prestamos' => 5, 'es_sistema' => true, 'es_predeterminado' => false, 'usuarios_count' => null],
            ['id' => null, 'nombre' => 'admin', 'etiqueta' => 'Administrador', 'descripcion' => null, 'cupo_prestamos' => 10, 'es_sistema' => true, 'es_predeterminado' => false, 'usuarios_count' => null],
        ];
    }

    /**
     * Normaliza la lista de roles que devuelve el backend. Los de solicitante
     * van primero (el predeterminado a la cabeza) y luego los de sistema.
     *
     * @param  mixed  $datos
     * @return list<array<string, mixed>>
     */
    protected function normalizarRoles(mixed $datos): array
    {
        if (! is_array($datos) || $datos === []) {
            return self::rolesPorDefecto();
        }

        $roles = [];

        foreach ($datos as $rol) {
            if (! is_array($rol)) {
                continue;
            }

            $nombre = $rol['nombre'] ?? $rol['name'] ?? null;
            if (! is_string($nombre) || $nombre === '') {
                continue;
            }

            $roles[] = [
                'id' => isset($rol['id']) ? (int) $rol['id'] : null,
                'nombre' => $nombre,
                'etiqueta' => (string) ($rol['etiqueta'] ?? ucfirst(str_replace('_', ' ', $nombre))),
                'descripcion' => $rol['descripcion'] ?? null,
                'cupo_prestamos' => max(0, (int) ($rol['cupo_prestamos'] ?? 0)),
                'es_sistema' => (bool) ($rol['es_sistema'] ?? in_array($nombre, ['admin', 'encargado'], true)),
                'es_predeterminado' => (bool) ($rol['es_predeterminado'] ?? false),
                'usuarios_count' => isset($rol['usuarios_count']) ? (int) $rol['usuarios_count'] : null,
            ];
        }

        if ($roles === []) {
            return self::rolesPorDefecto();
        }

        usort($roles, function (array $a, array $b) {
            return [$a['es_sistema'], ! $a['es_predeterminado'], $a['id'] ?? PHP_INT_MAX]
                <=> [$b['es_sistema'], ! $b['es_predeterminado'], $b['id'] ?? PHP_INT_MAX];
        });

        return $roles;
    }

    /**
     * Consulta GET /api/roles. Devuelve los roles por defecto si falla.
     *
     * @return list<array<string, mixed>>
     */
    protected function consultarRoles(?string $token): array
    {
        try {
            $response = Http::withToken((string) $token)
                ->acceptJson()
                ->timeout(10)
                ->get(config('services.backend.url').'/roles');
        } catch (ConnectionException $e) {
            return self::rolesPorDefecto();
        }

        if ($response->failed()) {
            return self::rolesPorDefecto();
        }

        return $this->normalizarRoles($response->json('data'));
    }
}
