<?php

namespace Tests\Feature;

use App\Support\EstadoPildora;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Protege la marca de la guía UI/UX (secciones §3.5, §3.6, §15 y §16):
 * los tokens viven en app.css, el layout ya no usa el CDN de Tailwind, los
 * pares de color cumplen el contraste medido con WCAG 2.1 y un estado se pinta
 * igual en todas las pantallas.
 */
class MarcaTokensTest extends TestCase
{
    /** @return array<string, mixed> */
    private function sesion(string $rol): array
    {
        return [
            'auth_token' => 'token-'.$rol,
            'user' => ['id' => 1, 'name' => 'Persona '.$rol, 'roles' => [$rol]],
        ];
    }

    /** @return array<string, mixed> */
    private function prestamo(int $id, string $estado): array
    {
        return [
            'id' => $id,
            'estado' => $estado,
            'fecha_solicitud' => '2026-11-20T10:00:00-05:00',
            'fecha_inicio' => '2026-11-21',
            'fecha_devolucion_estimada' => '2026-11-28T18:00:00-05:00',
            'solicitante' => ['name' => 'Laura Gómez', 'email' => 'laura@ecci.edu.co'],
            'equipo' => ['nombre' => 'Osciloscopio', 'codigo' => 'OSC-01', 'estado' => 'disponible'],
        ];
    }

    /* =========================================================================
     * §16: tokens centralizados en resources/css/app.css
     * ========================================================================= */

    public function test_app_css_define_los_tokens_de_la_guia(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $tokens = [
            '--color-marca-700' => '#0b3d91',
            '--color-marca-500' => '#1d5fd0',
            '--color-marca-50' => '#e8effb',
            '--color-fondo' => '#f5f7fa',
            '--color-borde' => '#d9dee7',
            '--color-borde-campo' => '#8190a5',
            '--color-texto' => '#1f2937',
            '--color-texto-suave' => '#6b7280',
            '--color-exito' => '#22c55e',
            '--color-exito-fuerte' => '#15803d',
            '--color-exito-suave' => '#dcfce7',
            '--color-exito-texto' => '#166534',
            '--color-aviso' => '#f59e0b',
            '--color-aviso-suave' => '#fef3c7',
            '--color-aviso-texto' => '#92400e',
            '--color-error' => '#ef4444',
            '--color-error-fuerte' => '#b91c1c',
            '--color-error-suave' => '#fee2e2',
            '--color-error-texto' => '#991b1b',
            '--radius-control' => '0.75rem',
            '--radius-tarjeta' => '1rem',
        ];

        foreach ($tokens as $token => $valor) {
            $this->assertMatchesRegularExpression(
                '/'.preg_quote($token, '/').'\s*:\s*'.preg_quote($valor, '/').'\s*;/i',
                $css,
                "app.css no define {$token}: {$valor}"
            );
        }

        $this->assertMatchesRegularExpression('/--shadow-tarjeta\s*:\s*0 2px 10px/i', $css);
    }

    public function test_el_layout_usa_vite_y_ya_no_el_cdn_de_tailwind(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringNotContainsString('cdn.tailwindcss.com', $layout);
        $this->assertStringNotContainsString('tailwind.config', $layout);
        $this->assertStringContainsString("@vite(['resources/css/app.css', 'resources/js/app.js'])", $layout);

        // Inicio consulta la API en cada visita (D-2): se simula vacía.
        Http::fake(['*' => Http::response(['data' => [], 'meta' => ['total' => 0]])]);

        $html = $this->withSession($this->sesion('admin'))->get('/inicio')->assertOk()->getContent();

        $this->assertStringNotContainsString('cdn.tailwindcss.com', $html);
        $this->assertStringNotContainsString('tailwind.config', $html);
        $this->assertStringContainsString('build/assets/app-', $html);
    }

    /* =========================================================================
     * §3.5 y §3.6: contraste medido con la fórmula de WCAG 2.1
     * ========================================================================= */

    public function test_los_pares_de_color_cumplen_el_contraste_de_la_guia(): void
    {
        // [fondo/relleno, texto, mínimo, razón documentada en la guía]
        $pares = [
            ['#0B3D91', '#FFFFFF', 4.5, 10.04],
            ['#1D5FD0', '#FFFFFF', 4.5, 5.83],
            ['#F5F7FA', '#1F2937', 4.5, 13.68],
            ['#FFFFFF', '#6B7280', 4.5, 4.83],
            ['#DCFCE7', '#166534', 4.5, 6.49],
            ['#FEF3C7', '#92400E', 4.5, 6.37],
            ['#FEE2E2', '#991B1B', 4.5, 6.80],
            ['#15803D', '#FFFFFF', 4.5, 5.02],
            ['#B91C1C', '#FFFFFF', 4.5, 6.47],
        ];

        foreach ($pares as [$fondo, $texto, $minimo, $documentada]) {
            $razon = $this->razonContraste($fondo, $texto);

            $this->assertGreaterThanOrEqual(
                $minimo,
                $razon,
                "{$texto} sobre {$fondo} da {$razon}:1, por debajo de {$minimo}:1"
            );
            $this->assertEqualsWithDelta(
                $documentada,
                $razon,
                0.05,
                "{$texto} sobre {$fondo} ya no coincide con la razón documentada ({$documentada}:1)"
            );
        }

        // El borde de campo es un componente no textual: umbral 3:1 (WCAG 1.4.11).
        $this->assertGreaterThanOrEqual(
            3.0,
            $this->razonContraste('#FFFFFF', '#8190A5'),
            'El borde de campo #8190A5 no alcanza el 3:1 de componentes'
        );
    }

    private function razonContraste(string $hexA, string $hexB): float
    {
        $lA = $this->luminancia($hexA);
        $lB = $this->luminancia($hexB);
        [$claro, $oscuro] = $lA >= $lB ? [$lA, $lB] : [$lB, $lA];

        return round(($claro + 0.05) / ($oscuro + 0.05), 2);
    }

    private function luminancia(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $canales = array_map(
            fn (int $inicio) => hexdec(substr($hex, $inicio, 2)) / 255,
            [0, 2, 4]
        );
        [$r, $g, $b] = array_map(
            fn (float $canal) => $canal <= 0.03928
                ? $canal / 12.92
                : (($canal + 0.055) / 1.055) ** 2.4,
            $canales
        );

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /* =========================================================================
     * §15: un estado, una píldora, en todas las pantallas
     * ========================================================================= */

    public function test_cada_estado_tiene_una_unica_variante(): void
    {
        $prestamo = [
            'solicitado' => 'aviso',
            'aprobado' => 'informativo',
            'entregado' => 'informativo',
            'devuelto' => 'exito',
            'rechazado' => 'error',
            'cancelado' => 'neutro',
            'retrasado' => 'error',
        ];

        foreach ($prestamo as $estado => $variante) {
            $this->assertSame(
                $variante,
                EstadoPildora::variante('prestamo', $estado),
                "El estado de préstamo «{$estado}» cambió de variante"
            );
        }

        $equipo = [
            'disponible' => 'exito',
            'en_prestamo' => 'informativo',
            'mantenimiento' => 'aviso',
            'dado_de_baja' => 'neutro',
        ];

        foreach ($equipo as $estado => $variante) {
            $this->assertSame(
                $variante,
                EstadoPildora::variante('equipo', $estado),
                "El estado de equipo «{$estado}» cambió de variante"
            );
        }

        // El componente pinta exactamente las clases del helper.
        foreach (array_keys($prestamo) as $estado) {
            $clases = EstadoPildora::clases('prestamo', $estado);
            $html = view('components.estado', ['estado' => $estado, 'tipo' => 'prestamo'])->render();

            $this->assertStringContainsString($clases['pildora'], $html);
            $this->assertStringContainsString($clases['punto'], $html);
        }
    }

    public function test_aprobado_se_ve_igual_en_solicitudes_mis_prestamos_y_entregas(): void
    {
        $clases = EstadoPildora::clases('prestamo', 'aprobado');
        $this->assertStringContainsString('bg-marca-50', $clases['pildora'], '«Aprobado» debe ser informativo (azul claro)');

        // Solicitudes (personal): la vista pinta el estado de cada fila.
        Http::fake(['*/prestamos/pendientes*' => Http::response(['data' => [$this->prestamo(1, 'aprobado')]], 200)]);
        $this->withSession($this->sesion('encargado'))
            ->get('/solicitudes')
            ->assertOk()
            ->assertSee($clases['pildora'], false);

        // Entregas: la fila es un préstamo aprobado pendiente de entregar.
        Http::fake(['*' => Http::response(['data' => [$this->prestamo(1, 'aprobado')]], 200)]);
        $this->withSession($this->sesion('encargado'))
            ->get('/entregas-pendientes')
            ->assertOk()
            ->assertSee($clases['pildora'], false);

        // Mis préstamos (solicitante).
        Http::fake(['*' => Http::response(['data' => [$this->prestamo(1, 'aprobado')]], 200)]);
        $this->withSession($this->sesion('estudiante'))
            ->get('/mis-prestamos')
            ->assertOk()
            ->assertSee($clases['pildora'], false);
    }

    /* =========================================================================
     * La paleta no admite colores fuera de la guía
     * ========================================================================= */

    public function test_las_vistas_no_usan_purpura_ni_indigo(): void
    {
        $archivos = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($archivo) => str_ends_with($archivo->getFilename(), '.blade.php'))
            ->reject(fn ($archivo) => $archivo->getFilename() === 'welcome.blade.php')
            ->reject(fn ($archivo) => str_contains($archivo->getFilename(), '.sync-conflict-'));

        $this->assertTrue($archivos->isNotEmpty(), 'No se encontraron vistas que revisar');

        foreach ($archivos as $archivo) {
            $contenido = $archivo->getContents();

            foreach (['purple-', 'indigo-'] as $prohibida) {
                $this->assertStringNotContainsString(
                    $prohibida,
                    $contenido,
                    $archivo->getRelativePathname()." usa «{$prohibida}», que no existe en la paleta"
                );
            }
        }
    }
}
