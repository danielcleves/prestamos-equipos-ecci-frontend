<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * HU-05 / HU-04: catálogo de equipos y vista de gestión.
 *
 * RegistroEquipoTest ya cubre crear equipos y cambiar estado; aquí se prueba la
 * carga del inventario, la selección de vista por rol y las métricas.
 */
class CatalogoEquiposTest extends TestCase
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
    private function equipo(int $id, string $nombre, string $estado): array
    {
        return [
            'id' => $id,
            'codigo' => 'EQ-00'.$id,
            'nombre' => $nombre,
            'estado' => $estado,
            'categoria' => ['nombre' => 'Electrónica'],
        ];
    }

    public function test_admin_ve_la_vista_de_gestion(): void
    {
        Http::fake(['*' => Http::response(['data' => [$this->equipo(1, 'Osciloscopio', 'disponible')]], 200)]);

        $this->withSession($this->sesion('admin'))
            ->get('/equipos')
            ->assertOk()
            ->assertViewIs('equipos.index')
            ->assertSee('Osciloscopio');
    }

    public function test_admin_puede_entrar_al_catalogo_con_el_parametro_vista(): void
    {
        Http::fake(['*' => Http::response(['data' => [$this->equipo(1, 'Osciloscopio', 'disponible')]], 200)]);

        $this->withSession($this->sesion('admin'))
            ->get('/equipos?vista=catalogo')
            ->assertOk()
            ->assertViewIs('equipos.catalogo')
            ->assertSee('Disponible');
    }

    public function test_usuario_ve_el_catalogo_sin_parametro(): void
    {
        Http::fake(['*' => Http::response(['data' => [$this->equipo(1, 'Osciloscopio', 'disponible')]], 200)]);

        $this->withSession($this->sesion('estudiante'))
            ->get('/equipos')
            ->assertOk()
            ->assertViewIs('equipos.catalogo');
    }

    public function test_las_metricas_cuentan_disponibles_prestados_y_mantenimiento(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    $this->equipo(1, 'Disponible', 'disponible'),
                    $this->equipo(2, 'Prestado', 'en_prestamo'),
                    $this->equipo(3, 'Mantenimiento', 'mantenimiento'),
                ],
            ], 200),
        ]);

        $this->withSession($this->sesion('admin'))
            ->get('/equipos')
            ->assertOk()
            ->assertViewHas('metricas', [
                'disponibles' => 1,
                'prestados' => 1,
                'mantenimiento' => 1,
                'total' => 3,
            ]);
    }

    public function test_catalogo_muestra_el_estado_vacio(): void
    {
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $this->withSession($this->sesion('estudiante'))
            ->get('/equipos')
            ->assertOk()
            ->assertSee('No hay equipos registrados en el catálogo.');
    }

    public function test_gestion_muestra_el_estado_vacio(): void
    {
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $this->withSession($this->sesion('admin'))
            ->get('/equipos')
            ->assertOk()
            ->assertSee('No hay equipos registrados en el inventario.');
    }

    public function test_catalogo_distingue_disponible_de_no_disponible(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    $this->equipo(1, 'Equipo Libre', 'disponible'),
                    $this->equipo(2, 'Equipo Ocupado', 'en_prestamo'),
                ],
            ], 200),
        ]);

        $this->withSession($this->sesion('estudiante'))
            ->get('/equipos')
            ->assertOk()
            ->assertSee('Equipo Libre')
            ->assertSee('Equipo Ocupado')
            ->assertSee('Disponible')
            ->assertSee('No disponible');
    }

    public function test_sesion_expirada_limpia_la_sesion_y_redirige_al_login(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

        $this->withSession($this->sesion('estudiante'))
            ->get('/equipos')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('auth_token')
            ->assertSessionMissing('user');
    }

    public function test_gestion_avisa_del_error_del_backend_en_vez_de_un_inventario_vacio(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Servicio de equipos no disponible.'], 500)]);

        $this->withSession($this->sesion('admin'))
            ->get('/equipos')
            ->assertOk()
            ->assertViewIs('equipos.index')
            ->assertSee('Servicio de equipos no disponible.')
            ->assertViewHas('metricas', fn ($m) => $m['total'] === 0);
    }

    public function test_catalogo_avisa_del_error_del_backend_en_vez_de_un_catalogo_vacio(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Servicio de equipos no disponible.'], 500)]);

        $this->withSession($this->sesion('estudiante'))
            ->get('/equipos')
            ->assertOk()
            ->assertViewIs('equipos.catalogo')
            ->assertSee('Servicio de equipos no disponible.');
    }

    public function test_catalogo_avisa_del_error_de_conexion(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('backend caído'));

        $this->withSession($this->sesion('estudiante'))
            ->get('/equipos')
            ->assertOk()
            ->assertSee('No fue posible conectar con el inventario de equipos.');
    }
}
