<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Auto-registro de solicitantes desde la web.
 */
class RegistroWebTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $payload = [
        'name' => 'Estudiante Nuevo',
        'email' => 'nuevo@ecci.edu.co',
        'password' => 'secreta123',
        'password_confirmation' => 'secreta123',
    ];

    public function test_el_invitado_puede_ver_el_formulario_de_registro(): void
    {
        $this->get('/registro')
            ->assertOk()
            ->assertSee('Crear cuenta')
            ->assertSee('Correo institucional')
            ->assertSee('Confirmar contraseña');
    }

    public function test_el_login_ofrece_crear_una_cuenta(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Crear cuenta')
            ->assertSee(route('registro'));
    }

    public function test_registro_exitoso_redirige_al_login_y_envia_los_datos(): void
    {
        Http::fake(['*/register' => Http::response(['message' => 'Cuenta creada.'], 201)]);

        $this->post('/registro', $this->payload)
            ->assertRedirect(route('login'))
            ->assertSessionHas('success', fn ($valor) => str_contains($valor, 'Cuenta creada'));

        Http::assertSent(fn ($request) => $request->method() === 'POST' &&
            str_ends_with($request->url(), '/register') &&
            $request['email'] === 'nuevo@ecci.edu.co' &&
            $request['name'] === 'Estudiante Nuevo' &&
            $request['password'] === 'secreta123' &&
            // BUG-0004: sin la confirmación el backend real responde 422.
            $request['password_confirmation'] === 'secreta123');
    }

    public function test_registro_muestra_los_errores_del_backend(): void
    {
        Http::fake([
            '*/register' => Http::response([
                'message' => 'Los datos no son válidos.',
                'errors' => [
                    'email' => ['Ese correo no está autorizado para registrarse.'],
                ],
            ], 422),
        ]);

        $this->from('/registro')
            ->post('/registro', $this->payload)
            ->assertRedirect('/registro')
            ->assertSessionHasErrors('email')
            ->assertSessionHasInput('email', 'nuevo@ecci.edu.co');
    }

    public function test_registro_con_error_de_conexion_muestra_un_mensaje(): void
    {
        Http::fake(fn () => throw new ConnectionException('backend caído'));

        $this->from('/registro')
            ->post('/registro', $this->payload)
            ->assertRedirect('/registro')
            ->assertSessionHasErrors('email');
    }

    public function test_registro_valida_localmente_antes_de_llamar_al_backend(): void
    {
        $this->post('/registro', [])
            ->assertSessionHasErrors(['name', 'email', 'password']);

        Http::assertNothingSent();
    }

    public function test_registro_exige_confirmar_la_contrasena(): void
    {
        $payload = $this->payload;
        unset($payload['password_confirmation']);

        $this->post('/registro', $payload)
            ->assertSessionHasErrors('password');

        Http::assertNothingSent();
    }
}
