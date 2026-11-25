<?php

namespace App\Support;

/**
 * Píldora de estado del préstamo y del equipo (guía UI/UX §15).
 *
 * Única fuente de la variante de color de cada estado: la usan el componente
 * `components/estado.blade.php` y las pantallas que pintan el estado desde
 * JavaScript (modal de solicitud), para que «Solicitado», «Aprobado», etc. se
 * vean igual en todas las pantallas.
 */
final class EstadoPildora
{
    /** Estado => variante de §3.6. */
    private const VARIANTES = [
        'prestamo' => [
            'solicitado' => 'aviso',
            'aprobado' => 'informativo',
            'entregado' => 'informativo',
            'retrasado' => 'error',
            'devuelto' => 'exito',
            'rechazado' => 'error',
            'cancelado' => 'neutro',
        ],
        'equipo' => [
            'disponible' => 'exito',
            'en_prestamo' => 'informativo',
            'prestado' => 'informativo',
            'mantenimiento' => 'aviso',
            'dado_de_baja' => 'neutro',
            'no_disponible' => 'neutro',
        ],
    ];

    /** Rótulo por defecto; las pantallas pueden sobrescribirlo. */
    private const ETIQUETAS = [
        'prestamo' => [
            'solicitado' => 'Solicitado',
            'aprobado' => 'Aprobado',
            'entregado' => 'Entregado',
            'retrasado' => 'Retrasado',
            'devuelto' => 'Devuelto',
            'rechazado' => 'Rechazado',
            'cancelado' => 'Cancelado',
        ],
        'equipo' => [
            'disponible' => 'Disponible',
            'en_prestamo' => 'En préstamo',
            'prestado' => 'En préstamo',
            'mantenimiento' => 'En mantenimiento',
            'dado_de_baja' => 'Dado de baja',
            'no_disponible' => 'No disponible',
        ],
    ];

    /** Clases de la píldora y del punto, ya con los tokens de app.css. */
    private const CLASES = [
        'exito' => ['pildora' => 'bg-exito-suave text-exito-texto border-emerald-200', 'punto' => 'bg-exito'],
        'aviso' => ['pildora' => 'bg-aviso-suave text-aviso-texto border-amber-200', 'punto' => 'bg-aviso'],
        'error' => ['pildora' => 'bg-error-suave text-error-texto border-rose-200', 'punto' => 'bg-error'],
        'informativo' => ['pildora' => 'bg-marca-50 text-marca-700 border-blue-200', 'punto' => 'bg-marca-500'],
        'neutro' => ['pildora' => 'bg-slate-100 text-slate-600 border-slate-200', 'punto' => 'bg-slate-400'],
    ];

    public static function normalizar(string $estado): string
    {
        return str_replace([' ', '-'], '_', mb_strtolower(trim($estado)));
    }

    public static function variante(string $tipo, string $estado): string
    {
        $tipo = array_key_exists($tipo, self::VARIANTES) ? $tipo : 'prestamo';

        return self::VARIANTES[$tipo][self::normalizar($estado)] ?? 'neutro';
    }

    /**
     * @return array{pildora: string, punto: string}
     */
    public static function clases(string $tipo, string $estado): array
    {
        return self::CLASES[self::variante($tipo, $estado)];
    }

    public static function etiqueta(string $tipo, string $estado): string
    {
        $normalizado = self::normalizar($estado);

        return self::ETIQUETAS[$tipo][$normalizado] ?? ucfirst(str_replace('_', ' ', $normalizado));
    }
}
