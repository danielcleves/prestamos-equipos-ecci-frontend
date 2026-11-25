<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * BUG-0013 · la fecha «hoy» del formulario de solicitud se calculaba en UTC.
 *
 * El frontend tenía la zona del servidor (UTC) fija. Entre las 19:00 y las 23:59 de
 * Bogotá eso hacía que el formulario propusiera el día siguiente como «hoy» y que la
 * hora de inicio de una solicitud del mismo día quedara corrida cinco horas. Ahora
 * «hoy» y «ahora» se calculan en `America/Bogota`.
 */
class FechaHoyBogotaTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $sesionUsuario = [
        'auth_token' => 'token-usuario-test',
        'user' => ['id' => 5, 'name' => 'Estudiante ECCI', 'roles' => ['estudiante']],
    ];

    /**
     * Las 22:04 del 25 de noviembre en Bogotá ya son las 03:04 del 26 en UTC: es
     * justo el rango en el que el bug se veía.
     */
    private function congelarEnLaNoche(): void
    {
        $this->travelTo(Carbon::parse('2026-11-25 22:04:00', 'America/Bogota'));
    }

    public function test_el_formulario_propone_hoy_en_la_zona_de_bogota_despues_de_las_siete(): void
    {
        $this->congelarEnLaNoche();

        Http::fake([
            '*/equipos/1' => Http::response([
                'data' => ['id' => 1, 'nombre' => 'Portátil Pro', 'codigo' => 'POR-01', 'estado' => 'disponible'],
            ], 200),
        ]);

        $this->withSession($this->sesionUsuario)
            ->get('/prestamos/solicitar/1')
            ->assertOk()
            ->assertSee("fechaInicio: '2026-11-25'", false)
            ->assertSee("hoy: '2026-11-25'", false)
            ->assertSee('min="2026-11-25"', false)
            ->assertDontSee('2026-11-26', false);
    }

    public function test_la_hora_de_inicio_de_hoy_se_envia_en_la_zona_de_bogota(): void
    {
        $this->congelarEnLaNoche();

        Http::fake(['*/prestamos' => Http::response(['data' => ['id' => 1]], 201)]);

        $this->withSession($this->sesionUsuario)
            ->post('/prestamos/solicitar', [
                'equipo_id' => 1,
                'fecha_inicio' => '2026-11-25',
                'fecha_devolucion' => '2026-11-27',
                'motivo' => 'Práctica de laboratorio.',
            ])
            ->assertRedirect(route('equipos.index', ['vista' => 'catalogo']));

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/prestamos')
            && $request['fecha_inicio'] === '2026-11-25 22:04:00'
            && $request['fecha_devolucion_estimada'] === '2026-11-27 18:00:00');
    }

    public function test_una_fecha_futura_conserva_el_inicio_de_la_jornada(): void
    {
        $this->congelarEnLaNoche();

        Http::fake(['*/prestamos' => Http::response(['data' => ['id' => 2]], 201)]);

        $this->withSession($this->sesionUsuario)
            ->post('/prestamos/solicitar', [
                'equipo_id' => 1,
                'fecha_inicio' => '2026-11-30',
                'fecha_devolucion' => '2026-12-02',
                'motivo' => 'Clase de redes.',
            ])
            ->assertRedirect(route('equipos.index', ['vista' => 'catalogo']));

        Http::assertSent(fn ($request) => $request['fecha_inicio'] === '2026-11-30 08:00:00');
    }

    public function test_durante_el_dia_el_formulario_sigue_proponiendo_hoy(): void
    {
        $this->travelTo(Carbon::parse('2026-11-25 10:15:00', 'America/Bogota'));

        Http::fake([
            '*/equipos/1' => Http::response([
                'data' => ['id' => 1, 'nombre' => 'Portátil Pro', 'codigo' => 'POR-01', 'estado' => 'disponible'],
            ], 200),
        ]);

        $this->withSession($this->sesionUsuario)
            ->get('/prestamos/solicitar/1')
            ->assertOk()
            ->assertSee("fechaInicio: '2026-11-25'", false)
            ->assertSee('min="2026-11-25"', false);
    }
}
