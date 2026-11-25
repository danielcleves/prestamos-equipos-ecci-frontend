<?php

namespace App\Support;

/**
 * Texto de una sanción por mora tal como la ve el usuario (sin siglas de reglas).
 *
 * Recibe el objeto `sancion_activa` / `sancion` del contrato de la API y devuelve
 * solo lo que las pantallas pintan, con las fechas en d/m/Y de Bogotá.
 */
final class SancionVista
{
    /**
     * @param  mixed  $sancion  Objeto de la API o null.
     * @return array{dias: int, diasTexto: string, fin: string, motivo: ?string, diasRetraso: ?int, diasSancion: ?int}|null
     */
    public static function desde(mixed $sancion): ?array
    {
        if (! is_array($sancion) || $sancion === []) {
            return null;
        }

        // Una sanción ya cumplida o vencida no se avisa.
        if (array_key_exists('vigente', $sancion) && $sancion['vigente'] === false) {
            return null;
        }

        $dias = max(1, (int) ($sancion['dias_restantes'] ?? 1));

        return [
            'dias' => $dias,
            'diasTexto' => self::dias($dias),
            'fin' => FechaNegocio::fecha($sancion['fecha_fin'] ?? null),
            'motivo' => $sancion['motivo'] ?? null,
            'diasRetraso' => isset($sancion['dias_retraso']) ? (int) $sancion['dias_retraso'] : null,
            'diasSancion' => isset($sancion['dias_sancion']) ? (int) $sancion['dias_sancion'] : null,
        ];
    }

    public static function dias(int $n): string
    {
        return $n === 1 ? '1 día' : $n.' días';
    }

    /**
     * Aviso al encargado al registrar una devolución con mora.
     */
    public static function mensajeDevolucion(mixed $sancion): ?string
    {
        if (! is_array($sancion) || $sancion === []) {
            return null;
        }

        $dias = (int) ($sancion['dias_sancion'] ?? 0);
        $retraso = (int) ($sancion['dias_retraso'] ?? 0);

        if ($dias < 1) {
            return null;
        }

        return 'Se registró una sanción de '.self::dias($dias).' por '.self::dias($retraso).' de retraso; vence el '
            .FechaNegocio::fecha($sancion['fecha_fin'] ?? null).'.';
    }
}
