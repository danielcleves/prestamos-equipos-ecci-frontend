<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Acabado UX/UI de los hallazgos H-02…H-10 de EVD-030.
 *
 * Cubre lo verificable en el HTML servido: nombres accesibles, títulos,
 * etiquetas de filtros y las pistas de desplazamiento de las tablas móviles.
 */
class UxAcabadoTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $sesionAdmin = [
        'auth_token' => 'token-admin',
        'user' => ['id' => 1, 'name' => 'Admin ECCI', 'roles' => ['admin']],
    ];

    /** @var array<string, mixed> */
    private array $sesionEncargado = [
        'auth_token' => 'token-encargado',
        'user' => ['id' => 2, 'name' => 'Encargado', 'roles' => ['encargado']],
    ];

    /** @var array<string, mixed> */
    private array $sesionUsuario = [
        'auth_token' => 'token-usuario',
        'user' => ['id' => 5, 'name' => 'Estudiante', 'roles' => ['usuario']],
    ];

    private function fakeVacio(): void
    {
        Http::fake(['*' => Http::response(['data' => []], 200)]);
    }

    public function test_el_login_no_muestra_credenciales_demo_que_no_existen(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('Credenciales demo')
            ->assertDontSee('admin123');
    }

    public function test_los_botones_del_encabezado_tienen_nombre_accesible(): void
    {
        $this->fakeVacio();

        $this->withSession($this->sesionEncargado)
            ->get('/equipos')
            ->assertOk()
            ->assertSee('aria-label="Abrir menú"', false)
            ->assertSee('aria-label="Notificaciones"', false);
    }

    public function test_la_miga_superior_refleja_la_pantalla_de_solicitudes(): void
    {
        $this->fakeVacio();

        $this->withSession($this->sesionEncargado)
            ->get('/solicitudes')
            ->assertOk()
            ->assertSee('Solicitudes pendientes')
            ->assertDontSee('Gestión de equipos');
    }

    public function test_usuarios_muestra_titulo_de_pagina(): void
    {
        $this->fakeVacio();

        $this->withSession($this->sesionAdmin)
            ->get('/usuarios')
            ->assertOk()
            ->assertSee('Gestión de usuarios');
    }

    public function test_los_filtros_de_usuarios_tienen_etiqueta_accesible(): void
    {
        $this->fakeVacio();

        $this->withSession($this->sesionAdmin)
            ->get('/usuarios')
            ->assertOk()
            ->assertSee('aria-label="Filtrar por rol"', false)
            ->assertSee('aria-label="Filtrar por estado"', false);
    }

    public function test_las_fechas_de_solicitudes_tienen_label_asociado(): void
    {
        $this->fakeVacio();

        $this->withSession($this->sesionEncargado)
            ->get('/solicitudes')
            ->assertOk()
            ->assertSee('id="fecha-desde"', false)
            ->assertSee('id="fecha-hasta"', false)
            ->assertSee('for="fecha-desde"', false)
            ->assertSee('for="fecha-hasta"', false);
    }

    public function test_solicitudes_avisa_del_desplazamiento_de_la_tabla_en_movil(): void
    {
        $this->fakeVacio();

        $this->withSession($this->sesionEncargado)
            ->get('/solicitudes')
            ->assertOk()
            ->assertSee('Desliza la tabla para ver todas las columnas.');
    }

    public function test_la_miga_del_catalogo_del_estudiante_dice_catalogo_de_equipos(): void
    {
        $this->fakeVacio();

        $response = $this->withSession($this->sesionUsuario)
            ->get('/equipos')
            ->assertOk();

        $dom = new \DOMDocument();
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $nodos = $xpath->query('//header//span[contains(@class, "text-slate-700")]');

        $this->assertGreaterThan(0, $nodos->length);
        $this->assertStringContainsString('Catálogo de equipos', $nodos->item(0)->textContent);
    }

    public function test_configuracion_avisa_del_desplazamiento_de_la_tabla_de_restriccion(): void
    {
        $this->fakeVacio();

        $this->withSession($this->sesionAdmin)
            ->get('/configuracion')
            ->assertOk()
            ->assertSee('Desliza la tabla para cambiar el nivel de restricción.');
    }

    public function test_incidencias_avisa_del_desplazamiento_y_muestra_estado_en_movil(): void
    {
        Http::fake([
            '*' => Http::response(['data' => [[
                'id' => 5,
                'estado' => 'abierta',
                'descripcion' => 'Golpe en la carcasa',
                'restringe_hasta' => '2026-12-01T18:00:00-05:00',
                'solicitante' => ['name' => 'Laura Gómez', 'email' => 'laura@ecci.edu.co'],
                'equipo' => ['nombre' => 'Osciloscopio', 'codigo' => 'OSC-01'],
            ]]], 200),
        ]);

        $this->withSession($this->sesionEncargado)
            ->get('/incidencias')
            ->assertOk()
            ->assertSee('Desliza la tabla para ver estado y acciones.')
            ->assertSee('Restringido hasta');
    }
}
