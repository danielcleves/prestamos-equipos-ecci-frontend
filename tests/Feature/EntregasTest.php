<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\Support\ErroresDeSesion;
use Tests\TestCase;

/**
 * HU-09 / KAN-82: bandeja de entregas pendientes y registro de la entrega.
 *
 * Complementa PermisosPrestamosTest (que solo cubre el control de acceso) con
 * el comportamiento funcional: filtrado por estado, validaciones y manejo de
 * errores del backend.
 */
class EntregasTest extends TestCase
{
    use ErroresDeSesion;

    /** @var array<string, mixed> */
    private array $sesionEncargado = [
        'auth_token' => 'token-encargado-test',
        'user' => ['id' => 2, 'name' => 'Encargado Laboratorio', 'roles' => ['encargado']],
    ];

    public function test_entregas_consume_el_listado_general_y_solo_muestra_aprobados(): void
    {
        Http::fake([
            '*/prestamos?per_page=100*' => Http::response([
                'data' => [
                    ['id' => 1, 'estado' => 'solicitado', 'equipo' => ['nombre' => 'Portátil HP', 'codigo' => 'POR-01']],
                    ['id' => 2, 'estado' => 'aprobado', 'equipo' => ['nombre' => 'Osciloscopio Digital', 'codigo' => 'OSC-01']],
                    ['id' => 3, 'estado' => 'entregado', 'equipo' => ['nombre' => 'Multímetro Fluke', 'codigo' => 'MUL-01']],
                    ['id' => 4, 'estado' => 'rechazado', 'equipo' => ['nombre' => 'Tablet Lenovo', 'codigo' => 'TAB-01']],
                ],
            ], 200),
        ]);

        $response = $this->withSession($this->sesionEncargado)->get('/entregas-pendientes');

        $response->assertOk();
        $response->assertSee('Osciloscopio Digital');
        $response->assertDontSee('Portátil HP');
        $response->assertDontSee('Multímetro Fluke');
        $response->assertDontSee('Tablet Lenovo');
        $response->assertViewHas('totalPendientes', 1);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/prestamos') &&
            ($request['per_page'] ?? null) == 100
        );
    }

    public function test_entregas_muestra_el_estado_vacio_cuando_no_hay_aprobados(): void
    {
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $this->withSession($this->sesionEncargado)
            ->get('/entregas-pendientes')
            ->assertOk()
            ->assertSee('No hay entregas pendientes registradas en este momento.');
    }

    public function test_entregas_avisa_del_error_del_backend_en_vez_de_mostrar_una_lista_vacia(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Servicio de préstamos no disponible.'], 500)]);

        $this->withSession($this->sesionEncargado)
            ->get('/entregas-pendientes')
            ->assertOk()
            ->assertSee('Servicio de préstamos no disponible.')
            ->assertViewHas('totalPendientes', 0);
    }

    public function test_entregas_avisa_del_error_de_conexion(): void
    {
        Http::fake(fn () => throw new ConnectionException('backend caído'));

        $this->withSession($this->sesionEncargado)
            ->get('/entregas-pendientes')
            ->assertOk()
            ->assertSee('No fue posible conectar con el servicio de préstamos.');
    }

    public function test_registrar_entrega_exitosa_redirige_con_mensaje_y_envia_el_payload(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 1]], 200)]);

        $response = $this->withSession($this->sesionEncargado)
            ->post('/prestamos/7/entrega', [
                'condicion_entrega' => 'bueno',
                'observaciones' => 'Se entrega con cargador y mouse.',
            ]);

        $response->assertRedirect(route('prestamos.entregas'));
        $response->assertSessionHas('success', fn ($valor) => str_contains($valor, 'Entrega registrada exitosamente'));

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/prestamos/7/entrega') &&
            $request['condicion_entrega'] === 'bueno' &&
            $request['observaciones'] === 'Se entrega con cargador y mouse.'
        );
    }

    public function test_registrar_entrega_exige_la_condicion(): void
    {
        $this->withSession($this->sesionEncargado)
            ->post('/prestamos/7/entrega', [])
            ->assertSessionHasErrors('condicion_entrega');

        Http::assertNothingSent();
    }

    public function test_registrar_entrega_rechaza_una_condicion_invalida(): void
    {
        $this->withSession($this->sesionEncargado)
            ->post('/prestamos/7/entrega', ['condicion_entrega' => 'perfecto'])
            ->assertSessionHasErrors('condicion_entrega');

        Http::assertNothingSent();
    }

    public function test_registrar_entrega_muestra_los_errores_de_validacion_del_backend(): void
    {
        Http::fake([
            '*' => Http::response([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => [
                    'condicion_entrega' => ['La condición de entrega no es válida.'],
                    'observaciones' => ['Las observaciones no pueden superar los 2000 caracteres.'],
                ],
            ], 422),
        ]);

        $response = $this->withSession($this->sesionEncargado)
            ->from('/entregas-pendientes')
            ->post('/prestamos/7/entrega', ['condicion_entrega' => 'bueno']);

        $response->assertRedirect('/entregas-pendientes');
        $errores = $this->erroresDeSesion();
        $this->assertContains('La condición de entrega no es válida.', $errores);
        $this->assertContains('Las observaciones no pueden superar los 2000 caracteres.', $errores);
    }

    public function test_registrar_entrega_sin_permisos_en_el_backend_muestra_el_mensaje(): void
    {
        Http::fake(['*' => Http::response(['message' => 'No autorizado.'], 403)]);

        $this->withSession($this->sesionEncargado)
            ->from('/entregas-pendientes')
            ->post('/prestamos/7/entrega', ['condicion_entrega' => 'bueno'])
            ->assertRedirect('/entregas-pendientes')
            ->assertSessionHasErrors('error');
    }

    public function test_registrar_entrega_con_sesion_expirada_redirige_al_login_y_limpia_la_sesion(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

        $this->withSession($this->sesionEncargado)
            ->post('/prestamos/7/entrega', ['condicion_entrega' => 'bueno'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('auth_token')
            ->assertSessionMissing('user');
    }

    public function test_registrar_entrega_con_error_de_conexion_conserva_la_pantalla(): void
    {
        Http::fake(fn () => throw new ConnectionException('backend caído'));

        $this->withSession($this->sesionEncargado)
            ->from('/entregas-pendientes')
            ->post('/prestamos/7/entrega', ['condicion_entrega' => 'bueno'])
            ->assertRedirect('/entregas-pendientes')
            ->assertSessionHasErrors('error');
    }
}
