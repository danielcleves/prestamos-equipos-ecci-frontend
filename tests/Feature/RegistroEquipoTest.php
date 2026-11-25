<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RegistroEquipoTest extends TestCase
{
    private function authHeaders(string $role = 'admin'): array
    {
        return [
            'auth_token' => 'fake-token-123',
            'api_token' => 'fake-token-123',
            'user' => [
                'id' => 1,
                'name' => 'Administrador Test',
                'roles' => [$role],
            ],
        ];
    }

    public function test_invitado_es_redirigido_al_login_al_intentar_ver_catalogo(): void
    {
        $this->get(route('equipos.index'))
            ->assertRedirect(route('login'));
    }

    public function test_invitado_es_redirigido_al_login_al_intentar_crear_equipo(): void
    {
        $this->get(route('equipos.create'))
            ->assertRedirect(route('login'));
    }

    public function test_usuario_no_admin_es_bloqueado_de_la_creacion_de_equipos(): void
    {
        $this->withSession($this->authHeaders('estudiante'))
            ->get(route('equipos.create'))
            ->assertRedirect(route('inicio'));
    }

    public function test_admin_puede_ver_el_formulario_de_creacion(): void
    {
        $this->withSession($this->authHeaders('admin'))
            ->get(route('equipos.create'))
            ->assertOk()
            ->assertSee('Registrar equipo')
            ->assertSee('Código o serial');
    }

    public function test_admin_puede_registrar_equipo_exitosamente(): void
    {
        $apiUrl = config('services.backend.url');

        Http::fake([
            "{$apiUrl}/equipos" => Http::response([
                'data' => [
                    'id' => 10,
                    'codigo' => 'CP-TEST-01',
                    'nombre' => 'Portátil de Prueba',
                    'categoria_id' => 1,
                    'estado' => 'disponible',
                    'descripcion' => 'Prueba unitaria',
                    'observaciones' => null,
                ],
            ], 201),
        ]);

        $this->withSession($this->authHeaders('admin'))
            ->post(route('equipos.store'), [
                'codigo' => 'CP-TEST-01',
                'nombre' => 'Portátil de Prueba',
                'categoria_id' => 1,
                'descripcion' => 'Prueba unitaria',
                'observaciones' => null,
            ])
            ->assertRedirect(route('equipos.index'))
            ->assertSessionHas('success');
    }

    public function test_falla_validacion_si_faltan_campos_obligatorios(): void
    {
        $this->withSession($this->authHeaders('admin'))
            ->post(route('equipos.store'), [])
            ->assertSessionHasErrors(['codigo', 'nombre', 'categoria_id']);
    }

    public function test_invitado_es_redirigido_al_login_al_intentar_actualizar_estado(): void
    {
        $this->patch(route('equipos.updateEstado', 1), ['estado' => 'mantenimiento'])
            ->assertRedirect(route('login'));
    }

    public function test_usuario_no_admin_es_bloqueado_al_actualizar_estado(): void
    {
        $this->withSession($this->authHeaders('estudiante'))
            ->patch(route('equipos.updateEstado', 1), ['estado' => 'mantenimiento'])
            ->assertRedirect(route('inicio'));
    }

    public function test_admin_puede_actualizar_estado_exitosamente(): void
    {
        $apiUrl = config('services.backend.url');

        Http::fake([
            "{$apiUrl}/equipos/1/estado" => Http::response([
                'data' => ['id' => 1, 'codigo' => 'CP-011', 'estado' => 'mantenimiento'],
            ], 200),
        ]);

        $this->withSession($this->authHeaders('admin'))
            ->patch(route('equipos.updateEstado', 1), ['estado' => 'mantenimiento'])
            ->assertRedirect(route('equipos.index'))
            ->assertSessionHas('success');
    }

    public function test_falla_validacion_si_estado_no_es_valido(): void
    {
        $this->withSession($this->authHeaders('admin'))
            ->patch(route('equipos.updateEstado', 1), ['estado' => 'inventado'])
            ->assertSessionHasErrors(['estado']);
    }

    public function test_admin_puede_registrar_equipo_con_ubicacion_opcional(): void
    {
        $apiUrl = config('services.backend.url');

        Http::fake([
            "{$apiUrl}/equipos" => Http::response([
                'data' => [
                    'id' => 11,
                    'codigo' => 'CP-UBI-01',
                    'nombre' => 'Portátil con ubicación',
                    'categoria_id' => 1,
                    'ubicacion' => 'Bodega principal',
                    'estado' => 'disponible',
                ],
            ], 201),
        ]);

        $this->withSession($this->authHeaders('admin'))
            ->post(route('equipos.store'), [
                'codigo' => 'CP-UBI-01',
                'nombre' => 'Portátil con ubicación',
                'categoria_id' => 1,
                'ubicacion' => 'Bodega principal',
            ])
            ->assertRedirect(route('equipos.index'))
            ->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request->method() === 'POST' &&
            str_ends_with($request->url(), '/equipos') &&
            $request['ubicacion'] === 'Bodega principal');
    }
}
