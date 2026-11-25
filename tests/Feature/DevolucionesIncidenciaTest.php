<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * RN-08 · incidencia al devolver un equipo en mal estado.
 *
 * Cuando la condición no es «bueno» el backend abre una incidencia; la pantalla
 * de devoluciones debe avisarlo (con la fecha de restricción si viene).
 */
class DevolucionesIncidenciaTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $sesionEncargado = [
        'auth_token' => 'token-encargado-test',
        'user' => ['id' => 2, 'name' => 'Encargado Laboratorio', 'roles' => ['encargado']],
    ];

    public function test_devolucion_con_danos_avisa_de_la_incidencia_y_la_restriccion(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'id' => 5,
                    'incidencia' => ['id' => 3, 'estado' => 'abierta', 'restringe_hasta' => '2026-12-01T18:00:00-05:00'],
                ],
            ], 200),
        ]);

        $response = $this->withSession($this->sesionEncargado)
            ->post('/prestamos/5/devolucion', [
                'condicion_devolucion' => 'con_danos',
                'observaciones' => 'Pantalla rayada y bisagra floja.',
            ]);

        $response->assertRedirect(route('prestamos.devoluciones'));
        $response->assertSessionHas('success', function ($valor) {
            return str_contains($valor, 'ha pasado a "En mantenimiento"')
                && str_contains($valor, 'se abrió una incidencia')
                && str_contains($valor, 'Restringido hasta el 01/12/2026');
        });
    }

    public function test_devolucion_que_requiere_mantenimiento_avisa_de_la_incidencia_sin_fecha(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 5]], 200)]);

        $response = $this->withSession($this->sesionEncargado)
            ->post('/prestamos/5/devolucion', [
                'condicion_devolucion' => 'requiere_mantenimiento',
                'observaciones' => 'No enciende y huele a quemado.',
            ]);

        $response->assertRedirect(route('prestamos.devoluciones'));
        $response->assertSessionHas('success', fn ($valor) => str_contains($valor, 'se abrió una incidencia')
            && ! str_contains($valor, 'Restringido hasta el')
        );
    }

    public function test_devolucion_en_buen_estado_no_avisa_de_incidencia(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 5]], 200)]);

        $response = $this->withSession($this->sesionEncargado)
            ->post('/prestamos/5/devolucion', ['condicion_devolucion' => 'bueno']);

        $response->assertRedirect(route('prestamos.devoluciones'));
        $response->assertSessionHas('success', fn ($valor) => str_contains($valor, 'ha regresado al estado "Disponible"')
            && ! str_contains($valor, 'incidencia')
        );
    }

    public function test_el_modal_avisa_que_se_abrira_una_incidencia(): void
    {
        Http::fake(['*' => Http::response([
            'data' => [
                [
                    'id' => 1,
                    'estado' => 'entregado',
                    'usuario' => ['name' => 'Laura Gómez', 'email' => 'laura@ecci.edu.co'],
                    'equipo' => ['nombre' => 'Osciloscopio', 'codigo' => 'OSC-01'],
                    'fecha_devolucion_estimada' => '2026-11-25 18:00:00',
                ],
            ],
        ], 200)]);

        $this->withSession($this->sesionEncargado)
            ->get('/devoluciones')
            ->assertOk()
            ->assertSee('Además se abrirá una')
            ->assertSee('<strong>incidencia</strong>', false)
            // El código de la regla no se muestra al encargado.
            ->assertDontSee('RN-0');
    }
}
