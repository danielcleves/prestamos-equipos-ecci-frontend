<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Landing de inicio: exige sesión y saluda al usuario autenticado.
 */
class InicioTest extends TestCase
{
    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get('/inicio')->assertRedirect(route('login'));
    }

    /**
     * El layout conserva la acción de cerrar sesión del sidebar (title="Cerrar sesión"),
     * así que se cuenta el texto en vez de prohibirlo en toda la página.
     */
    private function contarCerrarSesion(string $html): int
    {
        return substr_count($html, 'Cerrar sesión');
    }

    public function test_usuario_autenticado_ve_el_saludo_y_los_accesos(): void
    {
        $response = $this->withSession([
            'auth_token' => 'token-vigente',
            'user' => ['name' => 'Ana Pérez', 'roles' => ['estudiante']],
        ])
            ->get('/inicio')
            ->assertOk()
            ->assertSee('Hola, Ana Pérez')
            ->assertSee('Catálogo de equipos')
            // H-15 (EVD-033): el solicitante no ve accesos que lo devuelvan a Inicio.
            ->assertDontSee('Gestión de usuarios');

        $this->assertSame(
            1,
            $this->contarCerrarSesion($response->getContent()),
            'Inicio ya no ofrece «Cerrar sesión» como tarjeta: solo queda la acción del sidebar.'
        );
    }

    public function test_el_admin_si_ve_el_acceso_a_la_gestion_de_usuarios(): void
    {
        $response = $this->withSession([
            'auth_token' => 'token-admin',
            'user' => ['name' => 'Admin ECCI', 'roles' => ['admin']],
        ])
            ->get('/inicio')
            ->assertOk()
            ->assertSee('Gestión de usuarios');

        $this->assertSame(1, $this->contarCerrarSesion($response->getContent()));
    }

    public function test_la_raiz_redirige_al_login_sin_sesion(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_la_raiz_redirige_a_inicio_con_sesion(): void
    {
        $this->withSession([
            'auth_token' => 'token-vigente',
            'user' => ['name' => 'Ana Pérez', 'roles' => ['estudiante']],
        ])
            ->get('/')
            ->assertRedirect(route('inicio'));
    }
}
