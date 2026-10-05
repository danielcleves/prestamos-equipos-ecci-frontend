<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GestionUsuariosTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $adminSession = [
        'auth_token' => 'token-de-prueba',
        'user' => ['name' => 'Admin ECCI', 'roles' => ['admin']],
    ];

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get('/usuarios')->assertRedirect('/login');
    }

    public function test_usuario_no_admin_no_entra_a_la_gestion_de_usuarios(): void
    {
        $this->withSession([
            'auth_token' => 'token-de-prueba',
            'user' => ['name' => 'Ana Pérez', 'roles' => ['usuario']],
        ])
            ->get('/usuarios')
            ->assertRedirect(route('inicio'));
    }

    public function test_admin_puede_ver_la_gestion_de_usuarios(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    [
                        'id' => 1,
                        'name' => 'Ana "La Jefa" O\'Neil',
                        'email' => 'ana@ecci.edu.co',
                        'roles' => ['admin'],
                        'is_active' => true,
                    ],
                ],
                'meta' => ['total' => 1, 'current_page' => 1, 'last_page' => 1],
            ]),
        ]);

        $this->withSession($this->adminSession)
            ->get('/usuarios')
            ->assertOk()
            ->assertSee('Ana "La Jefa" O\'Neil');
    }

    public function test_el_servidor_rechaza_contrasenas_de_menos_de_8_caracteres(): void
    {
        $response = $this->withSession($this->adminSession)
            ->from('/usuarios')
            ->post('/usuarios', [
                'name' => 'Ana Pérez',
                'email' => 'ana@ecci.edu.co',
                'password' => 'corta',
                'role' => 'usuario',
            ]);

        $response->assertSessionHasErrors('password');
        Http::assertNothingSent();
    }

    public function test_login_redirige_al_admin_a_la_gestion_de_usuarios(): void
    {
        Http::fake([
            '*/login' => Http::response([
                'token' => 'abc123',
                'user' => ['name' => 'Admin ECCI', 'roles' => ['admin']],
            ]),
        ]);

        $this->post('/login', ['email' => 'admin@ecci.edu.co', 'password' => 'secreta123'])
            ->assertRedirect(route('usuarios.index'));

        $this->assertSame('abc123', session('auth_token'));
    }

    public function test_login_sin_token_en_la_respuesta_es_rechazado(): void
    {
        Http::fake([
            '*/login' => Http::response(['user' => ['name' => 'Sin token']], 200),
        ]);

        $this->post('/login', ['email' => 'admin@ecci.edu.co', 'password' => 'secreta123'])
            ->assertSessionHasErrors('email');
    }
}
