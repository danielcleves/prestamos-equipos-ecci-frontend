<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * RN-01 · capacitación del usuario.
 *
 * El administrador marca «Capacitado» al crear o editar un usuario y el valor
 * viaja al backend (POST /api/usuarios y PUT /api/usuarios/{usuario}).
 */
class UsuariosCapacitacionTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $sesionAdmin = [
        'auth_token' => 'token-admin-test',
        'user' => ['id' => 1, 'name' => 'Admin ECCI', 'roles' => ['admin']],
    ];

    /** @return array<string, mixed> */
    private function payloadAlta(array $sobrescribir = []): array
    {
        return array_merge([
            'name' => 'Nuevo Docente',
            'email' => 'docente@ecci.edu.co',
            'password' => 'secreta123',
            'role' => 'estudiante',
        ], $sobrescribir);
    }

    /** @return array<string, mixed> */
    private function payloadEdicion(array $sobrescribir = []): array
    {
        return array_merge([
            'name' => 'Docente Editado',
            'email' => 'editado@ecci.edu.co',
            'role' => 'estudiante',
        ], $sobrescribir);
    }

    public function test_el_formulario_de_alta_incluye_el_checkbox_capacitado_marcado(): void
    {
        Http::fake(['*' => Http::response(['data' => [], 'meta' => ['total' => 0]], 200)]);

        $this->withSession($this->sesionAdmin)
            ->get('/usuarios')
            ->assertOk()
            ->assertSee('name="capacitado" value="1" checked', false)
            ->assertSee('Capacitado para usar los equipos');
    }

    public function test_el_alta_envia_capacitado_por_defecto(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 9]], 201)]);

        $this->withSession($this->sesionAdmin)
            ->post('/usuarios', $this->payloadAlta())
            ->assertRedirect(route('usuarios.index'));

        Http::assertSent(fn ($request) => $request->method() === 'POST' &&
            str_ends_with($request->url(), '/usuarios') &&
            $request['capacitado'] === true
        );
    }

    public function test_el_alta_envia_capacitado_falso_cuando_se_desmarca(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 9]], 201)]);

        $this->withSession($this->sesionAdmin)
            ->post('/usuarios', $this->payloadAlta(['capacitado' => '0']))
            ->assertRedirect(route('usuarios.index'));

        Http::assertSent(fn ($request) => $request['capacitado'] === false);
    }

    public function test_la_edicion_envia_capacitado_verdadero(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 7]], 200)]);

        $this->withSession($this->sesionAdmin)
            ->put('/usuarios/7', $this->payloadEdicion(['capacitado' => '1']))
            ->assertRedirect(route('usuarios.index'));

        Http::assertSent(fn ($request) => $request->method() === 'PUT' &&
            str_ends_with($request->url(), '/usuarios/7') &&
            $request['capacitado'] === true
        );
    }

    public function test_la_edicion_envia_capacitado_falso_si_no_se_marca(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 7]], 200)]);

        $this->withSession($this->sesionAdmin)
            ->put('/usuarios/7', $this->payloadEdicion())
            ->assertRedirect(route('usuarios.index'));

        Http::assertSent(fn ($request) => $request['capacitado'] === false);
    }

    public function test_la_tabla_distingue_usuarios_capacitados_y_sin_capacitacion(): void
    {
        Http::fake(['*' => Http::response([
            'data' => [
                ['id' => 3, 'name' => 'Ana Capacitada', 'email' => 'ana@ecci.edu.co', 'roles' => ['estudiante'], 'is_active' => true, 'capacitado' => true],
                ['id' => 4, 'name' => 'Beto Sin Curso', 'email' => 'beto@ecci.edu.co', 'roles' => ['estudiante'], 'is_active' => true, 'capacitado' => false],
            ],
            'meta' => ['total' => 2, 'last_page' => 1],
        ], 200)]);

        $this->withSession($this->sesionAdmin)
            ->get('/usuarios')
            ->assertOk()
            ->assertSee('Capacitado')
            ->assertSee('Sin capacitación');
    }
}
