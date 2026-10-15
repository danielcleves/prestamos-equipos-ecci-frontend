<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PermisosPrestamosTest extends TestCase
{
    /** Vistas de personal autorizado (HU-08, HU-09, HU-11). */
    private const VISTAS = ['/solicitudes', '/entregas-pendientes', '/devoluciones'];

    /** @return array<string, mixed> */
    private function sesion(string $rol): array
    {
        return [
            'auth_token' => 'fake-token-123',
            'user' => ['id' => 1, 'name' => 'Persona Test', 'roles' => [$rol]],
        ];
    }

    public function test_invitado_es_redirigido_al_login(): void
    {
        foreach (self::VISTAS as $ruta) {
            $this->get($ruta)->assertRedirect(route('login'));
        }
    }

    public function test_rol_usuario_no_puede_ver_las_vistas_de_personal(): void
    {
        foreach (self::VISTAS as $ruta) {
            $this->withSession($this->sesion('usuario'))
                ->get($ruta)
                ->assertRedirect(route('inicio'))
                ->assertSessionHasErrors('inicio');
        }

        Http::assertNothingSent();
    }

    public function test_rol_usuario_no_puede_ejecutar_las_acciones_de_personal(): void
    {
        $this->withSession($this->sesion('usuario'))
            ->patch(route('prestamos.aprobar', 1))
            ->assertRedirect(route('inicio'));

        $this->withSession($this->sesion('usuario'))
            ->patch(route('prestamos.rechazar', 1), ['motivo' => 'No cumple los requisitos.'])
            ->assertRedirect(route('inicio'));

        $this->withSession($this->sesion('usuario'))
            ->post(route('prestamos.registrarEntrega', 1), ['condicion_entrega' => 'bueno'])
            ->assertRedirect(route('inicio'));

        $this->withSession($this->sesion('usuario'))
            ->post(route('prestamos.registrarDevolucion', 1), ['condicion_devolucion' => 'bueno'])
            ->assertRedirect(route('inicio'));

        Http::assertNothingSent();
    }

    public function test_rol_encargado_puede_ver_las_vistas_de_personal(): void
    {
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        foreach (self::VISTAS as $ruta) {
            $this->withSession($this->sesion('encargado'))
                ->get($ruta)
                ->assertOk();
        }
    }

    public function test_rol_admin_puede_ver_las_vistas_de_personal(): void
    {
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        foreach (self::VISTAS as $ruta) {
            $this->withSession($this->sesion('admin'))
                ->get($ruta)
                ->assertOk();
        }
    }

    public function test_rol_encargado_puede_aprobar_una_solicitud(): void
    {
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $this->withSession($this->sesion('encargado'))
            ->from(route('prestamos.solicitudes'))
            ->patch(route('prestamos.aprobar', 1))
            ->assertRedirect(route('prestamos.solicitudes'));
    }
}
