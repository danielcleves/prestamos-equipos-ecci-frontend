<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * RN-01 · capacitación por categoría en el catálogo y en la solicitud.
 *
 * El `EquipoResource` trae `categoria.requiere_capacitacion` y, en la raíz,
 * `usuario_capacitado` (si el usuario autenticado puede pedirlo). Si la
 * categoría la exige y el usuario no la tiene, el catálogo avisa y deshabilita
 * «Solicitar préstamo»; el 422 del backend se sigue mostrando si llega.
 */
class CapacitacionCatalogoTest extends TestCase
{
    private string $html = '';

    /** @var array<string, mixed> */
    private array $sesionEstudiante = [
        'auth_token' => 'token-estudiante',
        'user' => ['id' => 5, 'name' => 'Estudiante ECCI', 'roles' => ['estudiante']],
    ];

    /** @return array<string, mixed> */
    private function equipo(int $id, string $categoria, bool $requiere, ?bool $capacitado, string $estado = 'disponible'): array
    {
        $equipo = [
            'id' => $id,
            'codigo' => 'EQ-0'.$id,
            'nombre' => 'Equipo '.$id,
            'estado' => $estado,
            'categoria' => ['id' => $id, 'nombre' => $categoria, 'requiere_capacitacion' => $requiere],
        ];

        if ($capacitado !== null) {
            $equipo['usuario_capacitado'] = $capacitado;
        }

        return $equipo;
    }

    private function catalogo(array ...$equipos): \DOMXPath
    {
        Http::fake(['*/equipos*' => Http::response(['data' => $equipos], 200)]);

        $html = $this->html = $this->withSession($this->sesionEstudiante)
            ->get('/equipos')
            ->assertOk()
            ->assertViewIs('equipos.catalogo')
            ->getContent();

        $dom = new \DOMDocument;
        @$dom->loadHTML($html);

        return new \DOMXPath($dom);
    }

    public function test_sin_capacitacion_se_avisa_y_el_boton_queda_deshabilitado(): void
    {
        $xpath = $this->catalogo($this->equipo(4, 'Servidor', true, false));

        $this->assertSame(1, $xpath->query('//*[@data-requiere-capacitacion="4"]')->length);
        $this->assertStringContainsString(
            'Necesitas capacitación en Servidor para solicitar este equipo. Pídela al encargado.',
            preg_replace('/\s+/u', ' ', $xpath->query('//*[@data-aviso-capacitacion="4"]')->item(0)->textContent)
        );

        // El botón del modal: aria-disabled y texto visible, no solo color.
        $boton = $xpath->query('//button[@data-solicitar-bloqueado]')->item(0);
        $this->assertSame('true', $boton->getAttribute('aria-disabled'));
        $this->assertSame('aviso-capacitacion-modal', $boton->getAttribute('aria-describedby'));
        $this->assertStringContainsString('Requiere capacitación', $boton->textContent);
        $this->assertSame(1, $xpath->query('//*[@id="aviso-capacitacion-modal"]')->length);

        // El JSON del modal marca el equipo como bloqueado.
        $this->assertStringContainsString('\u0022bloqueadoPorCapacitacion\u0022:true', $this->html);
    }

    public function test_capacitado_ve_la_etiqueta_pero_puede_solicitar(): void
    {
        $xpath = $this->catalogo($this->equipo(4, 'Servidor', true, true));

        $this->assertSame(1, $xpath->query('//*[@data-requiere-capacitacion="4"]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-aviso-capacitacion="4"]')->length);
        // @js() escribe las comillas como \u0022.
        $this->assertStringContainsString('\u0022bloqueadoPorCapacitacion\u0022:false', $this->html);
    }

    public function test_categoria_sin_requisito_no_muestra_etiqueta_ni_aviso_aunque_no_este_capacitado(): void
    {
        $xpath = $this->catalogo($this->equipo(1, 'Portátil', false, false));

        $this->assertSame(0, $xpath->query('//*[@data-requiere-capacitacion="1"]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-aviso-capacitacion="1"]')->length);
        // @js() escribe las comillas como \u0022.
        $this->assertStringContainsString('\u0022bloqueadoPorCapacitacion\u0022:false', $this->html);
    }

    public function test_sin_el_campo_usuario_capacitado_no_se_bloquea(): void
    {
        // Borde: un backend sin el campo no debe dejar a todos sin poder solicitar.
        $xpath = $this->catalogo($this->equipo(4, 'Servidor', true, null));

        $this->assertSame(1, $xpath->query('//*[@data-requiere-capacitacion="4"]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-aviso-capacitacion="4"]')->length);
    }

    public function test_solo_se_bloquea_el_equipo_de_la_categoria_sin_capacitacion(): void
    {
        $xpath = $this->catalogo(
            $this->equipo(1, 'Router', true, true),
            $this->equipo(2, 'Servidor', true, false),
        );

        $this->assertSame(0, $xpath->query('//*[@data-aviso-capacitacion="1"]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-aviso-capacitacion="2"]')->length);
    }

    public function test_el_formulario_de_solicitud_avisa_si_falta_la_capacitacion(): void
    {
        Http::fake(['*/equipos/4' => Http::response(['data' => $this->equipo(4, 'Servidor', true, false)], 200)]);

        $this->withSession($this->sesionEstudiante)
            ->get('/prestamos/solicitar/4')
            ->assertOk()
            ->assertSee('data-aviso-capacitacion', false)
            ->assertSee('Necesitas capacitación en Servidor para solicitar este equipo. Pídela al encargado.');
    }

    public function test_el_formulario_de_solicitud_no_avisa_si_esta_capacitado(): void
    {
        Http::fake(['*/equipos/4' => Http::response(['data' => $this->equipo(4, 'Servidor', true, true)], 200)]);

        $this->withSession($this->sesionEstudiante)
            ->get('/prestamos/solicitar/4')
            ->assertOk()
            ->assertDontSee('data-aviso-capacitacion', false);
    }

    public function test_el_422_de_capacitacion_del_backend_se_muestra(): void
    {
        Http::fake([
            '*/equipos/4' => Http::response(['data' => $this->equipo(4, 'Servidor', true, true)], 200),
            '*/prestamos' => Http::response([
                'message' => 'Debes estar capacitado en «Servidor» para solicitar este equipo.',
                'errors' => ['usuario_id' => ['Debes estar capacitado en «Servidor» para solicitar este equipo.']],
            ], 422),
        ]);

        $this->withSession($this->sesionEstudiante)
            ->from('/prestamos/solicitar/4')
            ->followingRedirects()
            ->post('/prestamos/solicitar', [
                'equipo_id' => 4,
                'fecha_inicio' => now()->format('Y-m-d'),
                'fecha_devolucion' => now()->addDays(2)->format('Y-m-d'),
                'motivo' => 'Práctica de redes.',
            ])
            ->assertOk()
            ->assertSee('No puedes solicitar este equipo todavía:')
            ->assertSee('Debes estar capacitado en «Servidor» para solicitar este equipo.');
    }
}
