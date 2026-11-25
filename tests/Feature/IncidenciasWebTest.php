<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * RN-08: pantalla de incidencias por daños (admin y encargado).
 *
 * El backend expone GET /api/incidencias y PATCH /api/incidencias/{id}/cerrar;
 * esta pantalla los consume y permite levantar la restricción del solicitante.
 */
class IncidenciasWebTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $sesionEncargado = [
        'auth_token' => 'token-encargado-test',
        'user' => ['id' => 2, 'name' => 'Encargado Laboratorio', 'roles' => ['encargado']],
    ];

    /** @var array<string, mixed> */
    private array $sesionUsuario = [
        'auth_token' => 'token-usuario-test',
        'user' => ['id' => 5, 'name' => 'Estudiante ECCI', 'roles' => ['estudiante']],
    ];

    private function incidencia(string $estado = 'abierta'): array
    {
        return [
            'id' => 5,
            'prestamo_id' => 9,
            'estado' => $estado,
            'descripcion' => 'Golpe en la carcasa',
            'restringe_hasta' => '2026-12-01T18:00:00-05:00',
            'solicitante' => ['id' => 5, 'name' => 'Laura Gómez', 'email' => 'laura@ecci.edu.co'],
            'equipo' => ['id' => 3, 'codigo' => 'OSC-01', 'nombre' => 'Osciloscopio Digital'],
        ];
    }

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get('/incidencias')->assertRedirect(route('login'));
        Http::assertNothingSent();
    }

    public function test_usuario_no_personal_no_entra(): void
    {
        $this->withSession($this->sesionUsuario)
            ->get('/incidencias')
            ->assertRedirect(route('inicio'))
            ->assertSessionHasErrors('inicio');

        Http::assertNothingSent();
    }

    public function test_encargado_ve_las_incidencias(): void
    {
        Http::fake(['*' => Http::response(['data' => [$this->incidencia()]], 200)]);

        $this->withSession($this->sesionEncargado)
            ->get('/incidencias')
            ->assertOk()
            ->assertSee('Laura Gómez')
            ->assertSee('Osciloscopio Digital')
            ->assertSee('Golpe en la carcasa')
            ->assertSee('Cerrar incidencia');
    }

    public function test_admin_ve_las_incidencias(): void
    {
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $this->withSession([
            'auth_token' => 'token-admin',
            'user' => ['id' => 1, 'name' => 'Admin', 'roles' => ['admin']],
        ])
            ->get('/incidencias')
            ->assertOk()
            ->assertSee('No hay incidencias registradas.');
    }

    public function test_incidencia_cerrada_no_muestra_boton_de_cierre(): void
    {
        Http::fake(['*' => Http::response(['data' => [$this->incidencia('cerrada')]], 200)]);

        $this->withSession($this->sesionEncargado)
            ->get('/incidencias')
            ->assertOk()
            ->assertDontSee('Cerrar incidencia');
    }

    public function test_cerrar_incidencia_redirige_con_mensaje(): void
    {
        Http::fake(['*' => Http::response(['data' => $this->incidencia('cerrada')], 200)]);

        $this->withSession($this->sesionEncargado)
            ->patch('/incidencias/5/cerrar')
            ->assertRedirect(route('incidencias.index'))
            ->assertSessionHas('success', fn ($valor) => str_contains($valor, 'Incidencia cerrada'));

        Http::assertSent(fn ($request) => $request->method() === 'PATCH' &&
            str_ends_with($request->url(), '/incidencias/5/cerrar'));
    }

    public function test_cerrar_sin_permisos_en_el_backend_muestra_error(): void
    {
        Http::fake(['*' => Http::response(['message' => 'No autorizado.'], 403)]);

        $this->withSession($this->sesionEncargado)
            ->from('/incidencias')
            ->patch('/incidencias/5/cerrar')
            ->assertRedirect('/incidencias')
            ->assertSessionHasErrors('error');
    }

    public function test_cerrar_con_sesion_expirada_redirige_al_login(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

        $this->withSession($this->sesionEncargado)
            ->patch('/incidencias/5/cerrar')
            ->assertRedirect(route('login'))
            ->assertSessionMissing('auth_token');
    }

    public function test_cerrar_con_error_de_conexion_conserva_la_pantalla(): void
    {
        Http::fake(fn () => throw new ConnectionException('backend caído'));

        $this->withSession($this->sesionEncargado)
            ->from('/incidencias')
            ->patch('/incidencias/5/cerrar')
            ->assertRedirect('/incidencias')
            ->assertSessionHasErrors('error');
    }

    public function test_listado_avisa_del_error_del_backend(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Servicio de incidencias no disponible.'], 500)]);

        $this->withSession($this->sesionEncargado)
            ->get('/incidencias')
            ->assertOk()
            ->assertSee('Servicio de incidencias no disponible.');
    }

    public function test_listado_avisa_del_error_de_conexion(): void
    {
        Http::fake(fn () => throw new ConnectionException('backend caído'));

        $this->withSession($this->sesionEncargado)
            ->get('/incidencias')
            ->assertOk()
            ->assertSee('No fue posible conectar con el servicio de incidencias.');
    }
}
