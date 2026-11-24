<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\Support\ErroresDeSesion;
use Tests\TestCase;

/**
 * RN-06 · duración máxima del préstamo por categoría y rol.
 *
 * El formulario de solicitud usa `duracion_maxima_dias` del detalle del equipo
 * para el límite del calendario, el resumen y los mensajes; si el backend no lo
 * envía, se conserva el límite de 7 días.
 */
class SolicitudDuracionMaximaTest extends TestCase
{
    use ErroresDeSesion;

    /** @var array<string, mixed> */
    private array $sesionUsuario = [
        'auth_token' => 'token-usuario-test',
        'user' => ['id' => 5, 'name' => 'Estudiante ECCI', 'roles' => ['usuario']],
    ];

    /** @return array<string, mixed> */
    private function payload(array $sobrescribir = []): array
    {
        return array_merge([
            'equipo_id' => 1,
            'fecha_inicio' => now()->format('Y-m-d'),
            'fecha_devolucion' => now()->addDays(3)->format('Y-m-d'),
            'motivo' => 'Práctica de laboratorio.',
        ], $sobrescribir);
    }

    public function test_el_formulario_usa_la_duracion_maxima_del_equipo(): void
    {
        Http::fake([
            '*/equipos/1' => Http::response([
                'data' => [
                    'id' => 1,
                    'nombre' => 'Portátil Pro',
                    'codigo' => 'POR-01',
                    'estado' => 'disponible',
                    'duracion_maxima_dias' => 10,
                ],
            ], 200),
        ]);

        $this->withSession($this->sesionUsuario)
            ->get('/prestamos/solicitar/1')
            ->assertOk()
            ->assertSee('maxDias: 10', false)
            ->assertSee('name="duracion_maxima_dias" value="10"', false)
            ->assertSee('Máximo permitido');
    }

    public function test_el_formulario_mantiene_siete_dias_si_el_equipo_no_trae_el_limite(): void
    {
        Http::fake([
            '*/equipos/1' => Http::response([
                'data' => ['id' => 1, 'nombre' => 'Osciloscopio', 'codigo' => 'OSC-01', 'estado' => 'disponible'],
            ], 200),
        ]);

        $this->withSession($this->sesionUsuario)
            ->get('/prestamos/solicitar/1')
            ->assertOk()
            ->assertSee('maxDias: 7', false)
            ->assertSee('name="duracion_maxima_dias" value="7"', false);
    }

    public function test_una_devolucion_dentro_del_limite_del_equipo_se_acepta(): void
    {
        Http::fake(['*/prestamos' => Http::response(['data' => ['id' => 1]], 201)]);

        $this->withSession($this->sesionUsuario)
            ->post('/prestamos/solicitar', $this->payload([
                'duracion_maxima_dias' => 10,
                'fecha_devolucion' => now()->addDays(10)->format('Y-m-d'),
            ]))
            ->assertRedirect(route('equipos.index', ['vista' => 'catalogo']));

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/prestamos')
            && $request['equipo_id'] === 1
        );
    }

    public function test_un_dia_mas_del_limite_del_equipo_se_rechaza_con_el_numero_correcto(): void
    {
        $this->withSession($this->sesionUsuario)
            ->from('/prestamos/solicitar/1')
            ->post('/prestamos/solicitar', $this->payload([
                'duracion_maxima_dias' => 10,
                'fecha_devolucion' => now()->addDays(11)->format('Y-m-d'),
            ]))
            ->assertRedirect('/prestamos/solicitar/1')
            ->assertSessionHasErrors('fecha_devolucion');

        $this->assertContains(
            'La fecha de devolución no puede superar los 10 días posteriores a la fecha de inicio.',
            $this->erroresDeSesion()
        );

        Http::assertNothingSent();
    }

    public function test_un_limite_fuera_de_rango_vuelve_al_valor_de_siete_dias(): void
    {
        $this->withSession($this->sesionUsuario)
            ->from('/prestamos/solicitar/1')
            ->post('/prestamos/solicitar', $this->payload([
                'duracion_maxima_dias' => 9999,
                'fecha_devolucion' => now()->addDays(10)->format('Y-m-d'),
            ]))
            ->assertSessionHasErrors('fecha_devolucion');

        $this->assertContains(
            'La fecha de devolución no puede superar los 7 días posteriores a la fecha de inicio.',
            $this->erroresDeSesion()
        );

        Http::assertNothingSent();
    }

    public function test_sin_duracion_maxima_se_mantiene_el_limite_de_siete_dias(): void
    {
        Http::fake(['*/prestamos' => Http::response(['data' => ['id' => 1]], 201)]);

        $this->withSession($this->sesionUsuario)
            ->post('/prestamos/solicitar', $this->payload([
                'fecha_devolucion' => now()->addDays(7)->format('Y-m-d'),
            ]))
            ->assertRedirect(route('equipos.index', ['vista' => 'catalogo']));
    }
}
