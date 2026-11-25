<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Fechas de negocio del préstamo (KAN-121, BUG-0006).
 *
 * La app corre en UTC y la institución en Bogotá: un retraso se cuenta en días
 * calendario de America/Bogota, nunca en horas UTC (un préstamo que vence a las
 * 23:30 no está en mora a las 00:30 UTC del día siguiente).
 */
final class FechaNegocio
{
    public const ZONA = 'America/Bogota';

    /**
     * Días calendario de retraso de un préstamo entregado.
     *
     * Solo un préstamo ya entregado puede estar en mora: una solicitud o un
     * préstamo aprobado sin entregar tiene la fecha estimada en el pasado por
     * antigüedad, no por retraso. Devuelve 0 si está en plazo (o no aplica) y
     * al menos 1 cuando la fecha estimada ya venció, aunque hayan pasado solo
     * unas horas del mismo día.
     */
    public static function diasDeRetraso(string $estado, ?string $fechaEstimada, ?CarbonInterface $ahora = null): int
    {
        if (mb_strtolower(trim($estado)) !== 'entregado' || blank($fechaEstimada)) {
            return 0;
        }

        $ahora = ($ahora !== null ? CarbonImmutable::instance($ahora) : CarbonImmutable::now())->setTimezone(self::ZONA);
        $limite = CarbonImmutable::parse($fechaEstimada)->setTimezone(self::ZONA);

        if (! $limite->lessThan($ahora)) {
            return 0;
        }

        $dias = (int) $limite->startOfDay()->diffInDays($ahora->startOfDay(), false);

        return max(1, $dias);
    }

    /**
     * Fecha del contrato pintada como la ve el negocio, d/m/Y por defecto.
     */
    public static function fecha(?string $fecha, string $formato = 'd/m/Y'): string
    {
        return blank($fecha) ? '—' : CarbonImmutable::parse($fecha)->setTimezone(self::ZONA)->format($formato);
    }
}
