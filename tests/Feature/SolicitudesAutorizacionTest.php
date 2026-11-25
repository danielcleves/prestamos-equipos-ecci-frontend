<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\Support\ErroresDeSesion;
use Tests\TestCase;

/**
 * RN-03 · autorización especial de solicitudes de categorías restringidas.
 *
 * La bandeja de solicitudes marca las que requieren autorización, ofrece el
 * botón «Autorizar» (POST /api/prestamos/{id}/autorizacion) y bloquea la
 * aprobación mientras el préstamo no esté autorizado.
 */
class SolicitudesAutorizacionTest extends TestCase
{
    use ErroresDeSesion;

    /** @var array<string, mixed> */
    private array $sesionEncargado = [
        'auth_token' => 'token-encargado-test',
        'user' => ['id' => 2, 'name' => 'Encargado Laboratorio', 'roles' => ['encargado']],
    ];

    /** @return array<string, mixed> */
    private function solicitud(bool $requiere, bool $autorizado): array
    {
        return [
            'id' => 9,
            'estado' => 'solicitado',
            'requiere_autorizacion' => $requiere,
            'autorizado' => $autorizado,
            'autorizado_por' => $autorizado ? ['name' => 'Coordinación Académica'] : null,
            'fecha_solicitud' => '2026-11-20 10:00:00',
            'fecha_inicio' => '2026-11-21 08:00:00',
            'fecha_devolucion_estimada' => '2026-11-26 18:00:00',
            'usuario' => ['name' => 'Laura Gómez', 'email' => 'laura@ecci.edu.co'],
            'equipo' => [
                'nombre' => 'Osciloscopio',
                'codigo' => 'OSC-01',
                'estado' => 'disponible',
                'categoria' => ['nombre' => 'Laboratorio'],
            ],
        ];
    }

    public function test_solicitud_restringida_muestra_el_badge_requiere_autorizacion(): void
    {
        Http::fake([
            '*/prestamos/pendientes*' => Http::response(['data' => [$this->solicitud(true, false)]], 200),
        ]);

        $this->withSession($this->sesionEncargado)
            ->get('/solicitudes')
            ->assertOk()
            ->assertSee('Laura Gómez')
            ->assertSee('Requiere autorización')
            ->assertSee('Autorizar');
    }

    public function test_solicitud_ya_autorizada_no_muestra_el_badge(): void
    {
        Http::fake([
            '*/prestamos/pendientes*' => Http::response(['data' => [$this->solicitud(true, true)]], 200),
        ]);

        $this->withSession($this->sesionEncargado)
            ->get('/solicitudes')
            ->assertOk()
            ->assertDontSee('Requiere autorización');
    }

    public function test_solicitud_sin_restriccion_no_muestra_el_badge(): void
    {
        Http::fake([
            '*/prestamos/pendientes*' => Http::response(['data' => [$this->solicitud(false, false)]], 200),
        ]);

        $this->withSession($this->sesionEncargado)
            ->get('/solicitudes')
            ->assertOk()
            ->assertDontSee('Requiere autorización');
    }

    public function test_encargado_autoriza_la_solicitud_contra_el_endpoint_del_contrato(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 9]], 200)]);

        $response = $this->withSession($this->sesionEncargado)
            ->patch('/prestamos/9/autorizar');

        $response->assertRedirect(route('prestamos.solicitudes'));
        $response->assertSessionHas('success', fn ($valor) => str_contains($valor, 'La solicitud #9 fue autorizada'));

        Http::assertSent(fn ($request) => $request->method() === 'POST' &&
            str_ends_with($request->url(), '/prestamos/9/autorizacion')
        );
    }

    public function test_autorizar_muestra_los_errores_del_backend(): void
    {
        Http::fake([
            '*' => Http::response([
                'message' => 'No se pudo autorizar.',
                'errors' => ['estado' => ['La solicitud ya no está pendiente.']],
            ], 422),
        ]);

        $this->withSession($this->sesionEncargado)
            ->from('/solicitudes')
            ->patch('/prestamos/9/autorizar')
            ->assertRedirect('/solicitudes');

        $this->assertContains('La solicitud ya no está pendiente.', $this->erroresDeSesion());
    }

    public function test_autorizar_sin_permisos_muestra_el_mensaje(): void
    {
        Http::fake(['*' => Http::response(['message' => 'No autorizado.'], 403)]);

        $this->withSession($this->sesionEncargado)
            ->from('/solicitudes')
            ->patch('/prestamos/9/autorizar')
            ->assertRedirect('/solicitudes')
            ->assertSessionHasErrors('error');
    }

    public function test_autorizar_con_sesion_expirada_redirige_al_login(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

        $this->withSession($this->sesionEncargado)
            ->patch('/prestamos/9/autorizar')
            ->assertRedirect(route('login'))
            ->assertSessionMissing('auth_token');
    }

    public function test_autorizar_con_error_de_conexion_conserva_la_pantalla(): void
    {
        Http::fake(fn () => throw new ConnectionException('backend caído'));

        $this->withSession($this->sesionEncargado)
            ->from('/solicitudes')
            ->patch('/prestamos/9/autorizar')
            ->assertRedirect('/solicitudes')
            ->assertSessionHasErrors('error');
    }

    public function test_no_se_puede_aprobar_una_solicitud_sin_autorizar(): void
    {
        $response = $this->withSession($this->sesionEncargado)
            ->from('/solicitudes')
            ->patch('/prestamos/9/aprobar', [
                'requiere_autorizacion' => 1,
                'autorizado' => 0,
            ]);

        $response->assertRedirect('/solicitudes');
        $this->assertContains(
            'La solicitud #9 requiere autorización especial. Usa el botón «Autorizar» antes de aprobarla.',
            $this->erroresDeSesion()
        );

        // No llega ninguna petición al backend: se corta en el frontend.
        Http::assertNothingSent();
    }

    public function test_una_solicitud_autorizada_si_se_aprueba(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 9]], 200)]);

        $this->withSession($this->sesionEncargado)
            ->patch('/prestamos/9/aprobar', [
                'requiere_autorizacion' => 1,
                'autorizado' => 1,
            ])
            ->assertRedirect(route('prestamos.solicitudes'));

        Http::assertSent(fn ($request) => $request->method() === 'POST' &&
            str_ends_with($request->url(), '/prestamos/9/aprobacion')
        );
    }
}
