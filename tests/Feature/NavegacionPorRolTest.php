<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Navegación lateral por rol (§ layouts/app.blade.php).
 *
 * Deja fijado qué menú ve cada rol. El caso del encargado documenta el
 * comportamiento actual y el defecto conocido: el backend lo autoriza para
 * entregas, devoluciones y solicitudes, pero la barra lateral le muestra el menú
 * de solicitante.
 */
class NavegacionPorRolTest extends TestCase
{
    /** @return array<string, mixed> */
    private function sesion(string $rol): array
    {
        return [
            'auth_token' => 'token-'.$rol,
            'user' => ['id' => 1, 'name' => 'Persona '.$rol, 'roles' => [$rol]],
        ];
    }

    private function cargarEquipos(): void
    {
        Http::fake(['*' => Http::response(['data' => []], 200)]);
    }

    public function test_admin_ve_el_menu_administrativo(): void
    {
        $this->cargarEquipos();

        $this->withSession($this->sesion('admin'))
            ->get('/equipos')
            ->assertOk()
            ->assertSee('Administrador')
            ->assertSee('Usuarios')
            ->assertSee('Solicitudes')
            ->assertSee('Devoluciones')
            ->assertSee('Préstamos');
    }

    public function test_usuario_ve_solo_el_menu_de_solicitante(): void
    {
        $this->cargarEquipos();

        $this->withSession($this->sesion('estudiante'))
            ->get('/equipos')
            ->assertOk()
            ->assertSee('Solicitante')
            ->assertSee('Catálogo de equipos')
            ->assertSee('Mis préstamos')
            ->assertDontSee('Mis solicitudes')
            ->assertDontSee('Usuarios');
    }

    public function test_admin_en_modo_catalogo_puede_volver_a_la_vista_de_gestion(): void
    {
        $this->cargarEquipos();

        $this->withSession($this->sesion('admin'))
            ->get('/equipos?vista=catalogo')
            ->assertOk()
            ->assertSee('Ver como administrador')
            ->assertSee('Catálogo de equipos');
    }

    public function test_encargado_ve_el_menu_de_personal(): void
    {
        $this->cargarEquipos();

        $this->withSession($this->sesion('encargado'))
            ->get('/equipos')
            ->assertOk()
            ->assertSee('Encargado')
            ->assertSee('Catálogo de equipos')
            ->assertSee('Préstamos')
            ->assertSee('Devoluciones')
            ->assertSee('Solicitudes')
            ->assertDontSee('Usuarios');
    }

    public function test_encargado_conserva_el_menu_de_personal_en_el_catalogo(): void
    {
        $this->cargarEquipos();

        $this->withSession($this->sesion('encargado'))
            ->get('/equipos?vista=catalogo')
            ->assertOk()
            ->assertSee('Encargado')
            ->assertSee('Solicitudes')
            ->assertDontSee('Usuarios');
    }
}
