<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PrestamosHUTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $sesionUsuario = [
        'auth_token' => 'token-usuario-test',
        'user' => ['id' => 5, 'name' => 'Estudiante ECCI', 'roles' => ['usuario']],
    ];

    /** @var array<string, mixed> */
    private array $sesionEncargado = [
        'auth_token' => 'token-encargado-test',
        'user' => ['id' => 2, 'name' => 'Encargado Laboratorio', 'roles' => ['encargado']],
    ];

    /* =========================================================================
     * KAN-120 & KAN-121: Mis Préstamos Vigentes (Solicitante)
     * ========================================================================= */

    public function test_mis_prestamos_requiere_autenticacion(): void
    {
        $this->get('/mis-prestamos')->assertRedirect(route('login'));
    }

    public function test_mis_prestamos_consume_endpoint_activos_con_parametro(): void
    {
        Http::fake([
            '*/prestamos?activos=1' => Http::response([
                'data' => [
                    [
                        'id' => 10,
                        'equipo' => [
                            'nombre' => 'Osciloscopio Digital',
                            'codigo' => 'OSC-001',
                            'categoria' => ['nombre' => 'Electrónica'],
                        ],
                        'fecha_inicio' => '2026-11-19 08:00:00',
                        'fecha_entrega_real' => '2026-11-19 09:30:00',
                        'fecha_devolucion_estimada' => '2026-11-25 18:00:00',
                        'estado' => 'entregado',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->withSession($this->sesionUsuario)
            ->get('/mis-prestamos');

        $response->assertOk();
        $response->assertSee('Osciloscopio Digital');
        $response->assertSee('OSC-001');
        $response->assertSee('Vigente');

        // Verifica que se llamó exactamente a /prestamos?activos=1 y NO a /prestamos/activos
        Http::assertSent(fn ($request) => str_contains($request->url(), '/prestamos') &&
            ($request['activos'] ?? null) == 1 &&
            !str_contains($request->url(), '/prestamos/activos')
        );
    }

    public function test_mis_prestamos_destaca_prestamos_retrasados(): void
    {
        Http::fake([
            '*/prestamos?activos=1' => Http::response([
                'data' => [
                    [
                        'id' => 11,
                        'equipo' => [
                            'nombre' => 'Multímetro Fluke',
                            'codigo' => 'MUL-002',
                            'categoria' => ['nombre' => 'Electrónica'],
                        ],
                        'fecha_inicio' => '2026-11-01 08:00:00',
                        'fecha_entrega_real' => '2026-11-01 09:00:00',
                        'fecha_devolucion_estimada' => '2026-11-05 18:00:00', // Pasada
                        'estado' => 'entregado',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->withSession($this->sesionUsuario)
            ->get('/mis-prestamos');

        $response->assertOk();
        $response->assertSee('Multímetro Fluke');
        $response->assertSee('Retrasado');
        $response->assertSee('Devolución vencida');
    }

    public function test_mis_prestamos_muestra_estado_vacio(): void
    {
        Http::fake([
            '*/prestamos?activos=1' => Http::response(['data' => []], 200),
        ]);

        $response = $this->withSession($this->sesionUsuario)
            ->get('/mis-prestamos');

        $response->assertOk();
        $response->assertSee('No tienes préstamos vigentes');
    }

    /* =========================================================================
     * KAN-113: Solicitudes Pendientes (Personal Autorizado)
     * ========================================================================= */

    public function test_solicitudes_consume_endpoint_pendientes(): void
    {
        Http::fake([
            '*/prestamos/pendientes' => Http::response([
                'data' => [
                    [
                        'id' => 20,
                        'usuario' => ['name' => 'Juan Pérez', 'email' => 'juan@ecci.edu.co'],
                        'equipo' => ['nombre' => 'Generador de Señales', 'codigo' => 'GEN-01'],
                        'fecha_inicio' => '2026-11-20 08:00:00',
                        'fecha_devolucion_estimada' => '2026-11-22 18:00:00',
                        'motivo' => 'Laboratorio de Telecomunicaciones',
                        'estado' => 'solicitado',
                        'created_at' => '2026-11-19 10:00:00',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->withSession($this->sesionEncargado)
            ->get('/solicitudes');

        $response->assertOk();
        $response->assertSee('Juan Pérez');
        $response->assertSee('Generador de Señales');

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/prestamos/pendientes'));
    }

    /* =========================================================================
     * KAN-188 & KAN-195: Errores de Disponibilidad y Mapeo por Campo
     * ========================================================================= */

    public function test_store_mapea_errores_por_campo_desde_backend(): void
    {
        Http::fake([
            '*/prestamos' => Http::response([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => [
                    'fecha_inicio' => ['La fecha de inicio no puede ser anterior a la actual.'],
                    'fecha_devolucion_estimada' => ['El equipo no se encuentra disponible para las fechas seleccionadas.'],
                    'motivo' => ['El motivo es obligatorio.'],
                ],
            ], 422),
        ]);

        $response = $this->withSession($this->sesionUsuario)
            ->from('/prestamos/solicitar/1')
            ->post('/prestamos/solicitar', [
                'equipo_id' => 1,
                'fecha_inicio' => now()->format('Y-m-d'),
                'fecha_devolucion' => now()->addDays(2)->format('Y-m-d'),
                'motivo' => 'Prueba',
            ]);

        $response->assertRedirect('/prestamos/solicitar/1');
        $response->assertSessionHasErrors(['fecha_inicio', 'fecha_devolucion', 'motivo']);
    }

    public function test_store_mapea_motivo_indisponibilidad_general(): void
    {
        Http::fake([
            '*/prestamos' => Http::response([
                'message' => 'El equipo se encuentra en mantenimiento y no puede ser prestado.',
            ], 422),
        ]);

        $response = $this->withSession($this->sesionUsuario)
            ->from('/prestamos/solicitar/1')
            ->post('/prestamos/solicitar', [
                'equipo_id' => 1,
                'fecha_inicio' => now()->format('Y-m-d'),
                'fecha_devolucion' => now()->addDays(2)->format('Y-m-d'),
                'motivo' => 'Prueba',
            ]);

        $response->assertRedirect('/prestamos/solicitar/1');
        $response->assertSessionHasErrors(['prestamo_error']);
    }
}
