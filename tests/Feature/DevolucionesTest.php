<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\Support\ErroresDeSesion;
use Tests\TestCase;

/**
 * HU-11 / KAN-88 y KAN-128: devoluciones (pendientes, historial y registro).
 *
 * El registro de la devolución es el punto donde se reporta la condición del
 * equipo; de ahí depende que el equipo vuelva a estar disponible o pase a
 * mantenimiento (RN-08).
 */
class DevolucionesTest extends TestCase
{
    use ErroresDeSesion;

    /** @var array<string, mixed> */
    private array $sesionEncargado = [
        'auth_token' => 'token-encargado-test',
        'user' => ['id' => 2, 'name' => 'Encargado Laboratorio', 'roles' => ['encargado']],
    ];

    public function test_devoluciones_separa_pendientes_e_historial(): void
    {
        Http::fake([
            '*/prestamos?per_page=100*' => Http::response([
                'data' => [
                    [
                        'id' => 1,
                        'estado' => 'entregado',
                        'usuario' => ['name' => 'Laura Gómez', 'email' => 'laura@ecci.edu.co'],
                        'equipo' => ['nombre' => 'Osciloscopio', 'codigo' => 'OSC-01'],
                        'fecha_entrega_real' => '2026-11-18 10:00:00',
                        'fecha_devolucion_estimada' => '2026-11-25 18:00:00',
                    ],
                    [
                        'id' => 2,
                        'estado' => 'entregado',
                        'usuario' => ['name' => 'Juan Pérez', 'email' => 'juan@ecci.edu.co'],
                        'equipo' => ['nombre' => 'Multímetro', 'codigo' => 'MUL-01'],
                        'fecha_entrega_real' => '2026-11-19 09:00:00',
                        'fecha_devolucion_estimada' => '2026-11-26 18:00:00',
                    ],
                    [
                        'id' => 3,
                        'estado' => 'devuelto',
                        'usuario' => ['name' => 'Ana Ruiz', 'email' => 'ana@ecci.edu.co'],
                        'equipo' => ['nombre' => 'Portátil HP', 'codigo' => 'POR-01'],
                        'fecha_devolucion_real' => '2026-11-20 15:30:00',
                        'condicion_devolucion_etiqueta' => 'Con daños',
                    ],
                    [
                        'id' => 4,
                        'estado' => 'aprobado',
                        'usuario' => ['name' => 'Pedro Gil', 'email' => 'pedro@ecci.edu.co'],
                        'equipo' => ['nombre' => 'Tablet', 'codigo' => 'TAB-01'],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->withSession($this->sesionEncargado)->get('/devoluciones');

        $response->assertOk();
        $response->assertViewHas('totalPendientes', 2);
        $response->assertViewHas('totalDevueltos', 1);
        $response->assertSee('Laura Gómez');
        $response->assertSee('Osciloscopio');
        $response->assertSee('Ana Ruiz');
        $response->assertSee('Con daños');
        $response->assertSee('2026-11-20 15:30');
        // Un préstamo aprobado todavía no se ha entregado: no aparece en ninguna lista.
        $response->assertDontSee('Pedro Gil');
    }

    public function test_devoluciones_muestra_los_estados_vacios(): void
    {
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $this->withSession($this->sesionEncargado)
            ->get('/devoluciones')
            ->assertOk()
            ->assertSee('No hay préstamos activos pendientes de devolución.')
            ->assertSee('Aún no hay devoluciones completadas registradas en el historial.');
    }

    public function test_devoluciones_avisa_del_error_del_backend_en_vez_de_mostrar_listas_vacias(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Servicio de préstamos no disponible.'], 500)]);

        $this->withSession($this->sesionEncargado)
            ->get('/devoluciones')
            ->assertOk()
            ->assertSee('Servicio de préstamos no disponible.')
            ->assertViewHas('totalPendientes', 0)
            ->assertViewHas('totalDevueltos', 0);
    }

    public function test_devoluciones_avisa_del_error_de_conexion(): void
    {
        Http::fake(fn () => throw new ConnectionException('backend caído'));

        $this->withSession($this->sesionEncargado)
            ->get('/devoluciones')
            ->assertOk()
            ->assertSee('No fue posible conectar con el servicio de préstamos.');
    }

    public function test_devolucion_en_buen_estado_no_exige_observaciones_y_redirige(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 1]], 200)]);

        $response = $this->withSession($this->sesionEncargado)
            ->post('/prestamos/5/devolucion', ['condicion_devolucion' => 'bueno']);

        $response->assertRedirect(route('prestamos.devoluciones'));
        $response->assertSessionHas('success', fn ($valor) => str_contains($valor, 'ha regresado al estado "Disponible"'));

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/prestamos/5/devolucion') &&
            $request['condicion_devolucion'] === 'bueno'
        );
    }

    public function test_devolucion_con_danos_exige_observaciones(): void
    {
        $this->withSession($this->sesionEncargado)
            ->post('/prestamos/5/devolucion', ['condicion_devolucion' => 'con_danos'])
            ->assertSessionHasErrors('observaciones');

        Http::assertNothingSent();
    }

    public function test_devolucion_con_danos_exige_observaciones_de_al_menos_cinco_caracteres(): void
    {
        $this->withSession($this->sesionEncargado)
            ->post('/prestamos/5/devolucion', [
                'condicion_devolucion' => 'con_danos',
                'observaciones' => 'roto',
            ])
            ->assertSessionHasErrors('observaciones');

        Http::assertNothingSent();
    }

    public function test_devolucion_con_danos_redirige_con_el_mensaje_de_mantenimiento(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 1]], 200)]);

        $response = $this->withSession($this->sesionEncargado)
            ->post('/prestamos/5/devolucion', [
                'condicion_devolucion' => 'con_danos',
                'observaciones' => 'Golpe en la esquina inferior derecha.',
            ]);

        $response->assertRedirect(route('prestamos.devoluciones'));
        $response->assertSessionHas('success', fn ($valor) => str_contains($valor, 'ha pasado a "En mantenimiento"'));
    }

    public function test_devolucion_rechaza_una_condicion_invalida(): void
    {
        $this->withSession($this->sesionEncargado)
            ->post('/prestamos/5/devolucion', [
                'condicion_devolucion' => 'perfecto',
                'observaciones' => 'Todo bien.',
            ])
            ->assertSessionHasErrors('condicion_devolucion');

        Http::assertNothingSent();
    }

    public function test_devolucion_muestra_los_errores_del_backend_aplanados(): void
    {
        Http::fake([
            '*' => Http::response([
                'message' => 'No se puede registrar la devolución.',
                'errors' => [
                    'condicion_devolucion' => ['El préstamo no está en estado entregado.'],
                    'fecha_devolucion_real' => ['La fecha no puede ser futura.'],
                ],
            ], 422),
        ]);

        $response = $this->withSession($this->sesionEncargado)
            ->from('/devoluciones')
            ->post('/prestamos/5/devolucion', ['condicion_devolucion' => 'bueno']);

        $response->assertRedirect('/devoluciones');
        $errores = $this->erroresDeSesion();
        $this->assertContains('El préstamo no está en estado entregado.', $errores);
        $this->assertContains('La fecha no puede ser futura.', $errores);
    }

    public function test_devolucion_sin_permisos_en_el_backend_muestra_el_mensaje(): void
    {
        Http::fake(['*' => Http::response(['message' => 'No autorizado.'], 403)]);

        $this->withSession($this->sesionEncargado)
            ->from('/devoluciones')
            ->post('/prestamos/5/devolucion', ['condicion_devolucion' => 'bueno'])
            ->assertRedirect('/devoluciones')
            ->assertSessionHasErrors('error');
    }

    public function test_devolucion_con_sesion_expirada_redirige_al_login(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

        $this->withSession($this->sesionEncargado)
            ->post('/prestamos/5/devolucion', ['condicion_devolucion' => 'bueno'])
            ->assertRedirect(route('login'))
            ->assertSessionMissing('auth_token');
    }

    public function test_devolucion_con_error_de_conexion_conserva_la_pantalla(): void
    {
        Http::fake(fn () => throw new ConnectionException('backend caído'));

        $this->withSession($this->sesionEncargado)
            ->from('/devoluciones')
            ->post('/prestamos/5/devolucion', ['condicion_devolucion' => 'bueno'])
            ->assertRedirect('/devoluciones')
            ->assertSessionHasErrors('error');
    }
}
