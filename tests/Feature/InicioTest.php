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

    public function test_usuario_autenticado_ve_el_saludo_y_los_accesos(): void
    {
        $this->withSession([
            'auth_token' => 'token-vigente',
            'user' => ['name' => 'Ana Pérez', 'roles' => ['estudiante']],
        ])
            ->get('/inicio')
            ->assertOk()
            ->assertSee('Hola, Ana Pérez')
            ->assertSee('Catálogo de equipos')
            ->assertSee('Gestión de usuarios')
            ->assertSee('Cerrar sesión');
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
