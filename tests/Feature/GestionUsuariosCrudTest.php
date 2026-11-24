<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * HU-02: gestión de usuarios desde la interfaz web (listar, crear, editar y
 * activar/desactivar).
 *
 * GestionUsuariosTest solo cubre el acceso y que se pinte la tabla; aquí se
 * prueban las cuatro acciones contra el backend y su manejo de errores.
 */
class GestionUsuariosCrudTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $sesionAdmin = [
        'auth_token' => 'token-admin-test',
        'user' => ['id' => 1, 'name' => 'Admin ECCI', 'roles' => ['admin']],
    ];

    public function test_index_recorre_todas_las_paginas_y_calcula_las_metricas(): void
    {
        Http::fake(function ($request) {
            $pagina = (int) ($request['page'] ?? 1);
            $datos = $pagina === 1
                ? [
                    ['id' => 1, 'name' => 'Ana Activa', 'email' => 'ana@ecci.edu.co', 'roles' => ['admin'], 'is_active' => true],
                    ['id' => 2, 'name' => 'Beto Activo', 'email' => 'beto@ecci.edu.co', 'roles' => ['usuario'], 'is_active' => true],
                ]
                : [
                    ['id' => 3, 'name' => 'Carla Inactiva', 'email' => 'carla@ecci.edu.co', 'roles' => ['encargado'], 'is_active' => false],
                ];

            return Http::response([
                'data' => $datos,
                'meta' => ['total' => 3, 'current_page' => $pagina, 'last_page' => 2],
            ], 200);
        });

        $response = $this->withSession($this->sesionAdmin)->get('/usuarios');

        $response->assertOk();
        $response->assertSee('Ana Activa');
        $response->assertSee('Beto Activo');
        $response->assertSee('Carla Inactiva');
        $response->assertViewHas('total', 3);
        $response->assertViewHas('activos', 2);
        $response->assertViewHas('inactivos', 1);
    }

    public function test_index_muestra_el_estado_vacio(): void
    {
        Http::fake(['*' => Http::response(['data' => [], 'meta' => ['total' => 0, 'last_page' => 1]], 200)]);

        $this->withSession($this->sesionAdmin)
            ->get('/usuarios')
            ->assertOk()
            ->assertSee('No hay usuarios registrados en el sistema.');
    }

    public function test_index_con_sesion_expirada_redirige_al_login(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

        $this->withSession($this->sesionAdmin)
            ->get('/usuarios')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('auth_token');
    }

    public function test_index_sin_permisos_en_el_backend_redirige_al_inicio(): void
    {
        Http::fake(['*' => Http::response(['message' => 'No autorizado.'], 403)]);

        $this->withSession($this->sesionAdmin)
            ->get('/usuarios')
            ->assertRedirect(route('inicio'))
            ->assertSessionHasErrors('inicio');
    }

    public function test_admin_puede_crear_un_usuario(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 9]], 201)]);

        $response = $this->withSession($this->sesionAdmin)
            ->post('/usuarios', [
                'name' => 'Nuevo Usuario',
                'email' => 'nuevo@ecci.edu.co',
                'password' => 'secreta123',
                'role' => 'encargado',
            ]);

        $response->assertRedirect(route('usuarios.index'));
        $response->assertSessionHas('success', 'Usuario registrado exitosamente.');

        Http::assertSent(fn ($request) => $request->method() === 'POST' &&
            str_ends_with($request->url(), '/usuarios') &&
            $request['name'] === 'Nuevo Usuario' &&
            $request['email'] === 'nuevo@ecci.edu.co' &&
            $request['role'] === 'encargado'
        );
    }

    public function test_crear_usuario_muestra_el_error_del_backend(): void
    {
        Http::fake(['*' => Http::response(['message' => 'El correo ya está registrado.'], 422)]);

        $this->withSession($this->sesionAdmin)
            ->from('/usuarios')
            ->post('/usuarios', [
                'name' => 'Nuevo Usuario',
                'email' => 'nuevo@ecci.edu.co',
                'password' => 'secreta123',
                'role' => 'usuario',
            ])
            ->assertRedirect('/usuarios')
            ->assertSessionHasErrors('store_error');
    }

    public function test_crear_usuario_con_error_de_conexion_conserva_la_entrada(): void
    {
        Http::fake(fn () => throw new ConnectionException('backend caído'));

        $this->withSession($this->sesionAdmin)
            ->from('/usuarios')
            ->post('/usuarios', [
                'name' => 'Nuevo Usuario',
                'email' => 'nuevo@ecci.edu.co',
                'password' => 'secreta123',
                'role' => 'usuario',
            ])
            ->assertRedirect('/usuarios')
            ->assertSessionHasErrors('store_error')
            ->assertSessionHasInput('email', 'nuevo@ecci.edu.co');
    }

    public function test_actualizar_usuario_sin_password_no_envia_el_campo(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 7]], 200)]);

        $this->withSession($this->sesionAdmin)
            ->put('/usuarios/7', [
                'name' => 'Usuario Editado',
                'email' => 'editado@ecci.edu.co',
                'role' => 'encargado',
            ])
            ->assertRedirect(route('usuarios.index'))
            ->assertSessionHas('success', 'Usuario actualizado correctamente.');

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->method() === 'PUT'
                && str_ends_with($request->url(), '/usuarios/7')
                && $data['name'] === 'Usuario Editado'
                && $data['role'] === 'encargado'
                && ! array_key_exists('password', $data);
        });
    }

    public function test_actualizar_usuario_con_password_lo_envia(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 7]], 200)]);

        $this->withSession($this->sesionAdmin)
            ->put('/usuarios/7', [
                'name' => 'Usuario Editado',
                'email' => 'editado@ecci.edu.co',
                'role' => 'usuario',
                'password' => 'nuevaClave123',
            ])
            ->assertRedirect(route('usuarios.index'));

        Http::assertSent(fn ($request) => $request->method() === 'PUT' &&
            $request['password'] === 'nuevaClave123'
        );
    }

    public function test_actualizar_usuario_muestra_el_error_del_backend(): void
    {
        Http::fake(['*' => Http::response(['message' => 'El correo ya está en uso.'], 422)]);

        $this->withSession($this->sesionAdmin)
            ->from('/usuarios')
            ->put('/usuarios/7', [
                'name' => 'Usuario Editado',
                'email' => 'editado@ecci.edu.co',
                'role' => 'usuario',
            ])
            ->assertRedirect('/usuarios')
            ->assertSessionHasErrors('update_error');
    }

    public function test_admin_puede_desactivar_un_usuario(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 7]], 200)]);

        $this->withSession($this->sesionAdmin)
            ->patch('/usuarios/7/desactivar')
            ->assertRedirect(route('usuarios.index'))
            ->assertSessionHas('success', 'Estado actualizado.');

        Http::assertSent(fn ($request) => $request->method() === 'PATCH' &&
            str_ends_with($request->url(), '/usuarios/7/desactivar')
        );
    }

    public function test_admin_puede_activar_un_usuario(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 7]], 200)]);

        $this->withSession($this->sesionAdmin)
            ->patch('/usuarios/7/activar')
            ->assertRedirect(route('usuarios.index'));

        Http::assertSent(fn ($request) => $request->method() === 'PATCH' &&
            str_ends_with($request->url(), '/usuarios/7/activar')
        );
    }

    public function test_cambiar_estado_sin_permisos_en_el_backend_redirige_al_inicio(): void
    {
        Http::fake(['*' => Http::response(['message' => 'No autorizado.'], 403)]);

        $this->withSession($this->sesionAdmin)
            ->patch('/usuarios/7/desactivar')
            ->assertRedirect(route('inicio'))
            ->assertSessionHasErrors('inicio');
    }
}
