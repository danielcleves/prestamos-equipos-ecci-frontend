<?php

namespace App\Support;

/**
 * Capacitaciones de un usuario tal como las pintan las pantallas.
 *
 * La capacitación es por categoría de equipo: el `UserResource` del backend trae
 * `capacitaciones` como lista de `{ categoria_id, nombre, otorgada_en }`. Si estás
 * capacitado para un router no lo estás para un servidor.
 */
final class CapacitacionesVista
{
    /**
     * @param  array<string, mixed>  $usuario  Usuario del contrato de la API.
     * @return list<array{categoria_id: int, nombre: string}>
     */
    public static function desde(array $usuario): array
    {
        $capacitaciones = [];

        foreach ((array) ($usuario['capacitaciones'] ?? []) as $capacitacion) {
            if (! is_array($capacitacion) || ! isset($capacitacion['categoria_id'])) {
                continue;
            }

            $id = (int) $capacitacion['categoria_id'];
            $capacitaciones[$id] = [
                'categoria_id' => $id,
                'nombre' => (string) ($capacitacion['nombre'] ?? 'Categoría '.$id),
            ];
        }

        return array_values($capacitaciones);
    }

    /**
     * Ids de categoría en que está capacitado el usuario (para el modal de edición).
     *
     * @param  array<string, mixed>  $usuario
     * @return list<int>
     */
    public static function ids(array $usuario): array
    {
        return array_column(self::desde($usuario), 'categoria_id');
    }

    /**
     * Texto corto para la tabla: «Portátil, Router» o «Sin capacitaciones».
     *
     * @param  array<string, mixed>  $usuario
     */
    public static function texto(array $usuario): string
    {
        $nombres = array_column(self::desde($usuario), 'nombre');

        return $nombres === [] ? 'Sin capacitaciones' : implode(', ', $nombres);
    }
}
