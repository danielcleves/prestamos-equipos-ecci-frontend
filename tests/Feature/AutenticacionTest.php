<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * HU-01: inicio y cierre de sesión web.
 *
 * GestionUsuariosTest ya cubre el login de un admin y el rechazo de contraseñas
 * cortas; aquí se completan los demás roles, las credenciales inválidas, el
 * error de conexión y el logout (que es lo que revoca el token en el backend).
 */
class AutenticacionTest extends TestCase
{
    /** @return array<string, mixed> */
    private function respuestaLogin(string $rol, string|array|null $roles = null): array
    {
        return [
            'token' => 'token-'.$rol,
            'user' => [
                'id' => 10,
                'name' => 'Persona '.$rol,
                'roles' => $roles ?? [$rol],
            ],
        ];
    }

    /**
     * El panel del login conserva los consejos que rotan, pero el reloj se
     * retiró a pedido de Daniel (25 nov 2026).
     */
    public function test_el_login_muestra_los_consejos_y_no_el_reloj(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('aria-label="Consejos de uso"', false)
            ->assertSee('data-consejo', false)
            ->assertDontSee('role="timer"', false)
            ->assertDontSee('reloj-', false)
            ->assertDontSee('Hora de Bogotá');
    }

    public function test_login_de_encargado_redirige_a_las_entregas_pendientes(): void
    {
        Http::fake(['*/login' => Http::response($this->respuestaLogin('encargado'), 200)]);

        $this->post('/login', ['email' => 'enc@ecci.edu.co', 'password' => 'secreta123'])
            ->assertRedirect(route('prestamos.entregas'));

        $this->assertSame('token-encargado', session('auth_token'));
        $this->assertSame('token-encargado', session('api_token'));
    }

    public function test_login_de_usuario_redirige_al_catalogo(): void
    {
        Http::fake(['*/login' => Http::response($this->respuestaLogin('usuario'), 200)]);

        $this->post('/login', ['email' => 'usr@ecci.edu.co', 'password' => 'secreta123'])
            ->assertRedirect(route('equipos.index'));
    }

    public function test_login_acepta_roles_como_arreglo_de_objetos(): void
    {
        Http::fake([
            '*/login' => Http::response($this->respuestaLogin('admin', [['name' => 'admin']]), 200),
        ]);

        $this->post('/login', ['email' => 'admin@ecci.edu.co', 'password' => 'secreta123'])
            ->assertRedirect(route('usuarios.index'));
    }

    public function test_login_con_credenciales_invalidas_muestra_el_mensaje_del_backend(): void
    {
        Http::fake([
            '*/login' => Http::response(['message' => 'Las credenciales ingresadas son incorrectas.'], 401),
        ]);

        $this->from('/login')
            ->post('/login', ['email' => 'nadie@ecci.edu.co', 'password' => 'secreta123'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email')
            ->assertSessionHasInput('email', 'nadie@ecci.edu.co');
    }

    public function test_login_con_error_de_conexion_muestra_un_mensaje_claro(): void
    {
        Http::fake(fn () => throw new ConnectionException('backend caído'));

        $this->from('/login')
            ->post('/login', ['email' => 'admin@ecci.edu.co', 'password' => 'secreta123'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');
    }

    public function test_login_exige_email_y_password(): void
    {
        $this->post('/login', [])->assertSessionHasErrors(['email', 'password']);

        Http::assertNothingSent();
    }

    public function test_mostrar_login_redirige_si_ya_hay_sesion(): void
    {
        $this->withSession([
            'api_token' => 'token-vigente',
            'user' => ['name' => 'Admin ECCI', 'roles' => ['admin']],
        ])
            ->get('/login')
            ->assertRedirect(route('usuarios.index'));
    }

    public function test_logout_revoca_el_token_y_redirige_al_login(): void
    {
        Http::fake(['*/logout' => Http::response(['message' => 'Sesión cerrada.'], 200)]);

        $this->withSession([
            'auth_token' => 'token-a-revocar',
            'api_token' => 'token-a-revocar',
            'user' => ['name' => 'Admin ECCI', 'roles' => ['admin']],
        ])
            ->post('/logout')
            ->assertRedirect(route('login'))
            ->assertSessionHas('success', 'Sesión cerrada correctamente.')
            ->assertSessionMissing('auth_token')
            ->assertSessionMissing('user');

        Http::assertSent(fn ($request) => $request->method() === 'POST' &&
            str_ends_with($request->url(), '/logout') &&
            $request->hasHeader('Authorization', 'Bearer token-a-revocar')
        );
    }

    public function test_logout_sin_token_no_llama_al_backend(): void
    {
        $this->post('/logout')->assertRedirect(route('login'));

        Http::assertNothingSent();
    }
}
