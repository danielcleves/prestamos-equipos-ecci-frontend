<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
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
            '*/prestamos?activos=1*' => Http::response([
                'data' => [
                    [
                        'id' => 10,
                        'equipo' => [
                            'nombre' => 'Osciloscopio Digital',
                            'codigo' => 'OSC-001',
                            'categoria' => ['nombre' => 'Electrónica'],
                        ],
                        'fecha_inicio' => now()->subDays(2)->format('Y-m-d H:i:s'),
                        'fecha_entrega_real' => now()->subDays(2)->addHour()->format('Y-m-d H:i:s'),
                        'fecha_devolucion_estimada' => now()->addDays(5)->format('Y-m-d H:i:s'),
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
        $response->assertDontSee('Devolución vencida');

        // Verifica que se llamó exactamente a /prestamos?activos=1 y NO a /prestamos/activos
        Http::assertSent(fn ($request) => str_contains($request->url(), '/prestamos') &&
            ($request['activos'] ?? null) == 1 &&
            !str_contains($request->url(), '/prestamos/activos')
        );
    }

    public function test_mis_prestamos_destaca_prestamos_retrasados(): void
    {
        Http::fake([
            '*/prestamos?activos=1*' => Http::response([
                'data' => [
                    [
                        'id' => 11,
                        'equipo' => [
                            'nombre' => 'Multímetro Fluke',
                            'codigo' => 'MUL-002',
                            'categoria' => ['nombre' => 'Electrónica'],
                        ],
                        'fecha_inicio' => now()->subDays(10)->format('Y-m-d H:i:s'),
                        'fecha_entrega_real' => now()->subDays(10)->addHour()->format('Y-m-d H:i:s'),
                        'fecha_devolucion_estimada' => now()->subDays(3)->format('Y-m-d H:i:s'), // Pasada
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

    public function test_mis_prestamos_no_marca_retrasado_si_aun_no_se_entrega(): void
    {
        // KAN-121: una solicitud con fecha estimada vencida no es una mora: nunca
        // se entregó, así que no debe aparecer como "Retrasado".
        Http::fake([
            '*/prestamos?activos=1*' => Http::response([
                'data' => [
                    [
                        'id' => 12,
                        'equipo' => [
                            'nombre' => 'Fuente de Poder',
                            'codigo' => 'FUE-003',
                            'categoria' => ['nombre' => 'Electrónica'],
                        ],
                        'fecha_inicio' => now()->subDays(10)->format('Y-m-d H:i:s'),
                        'fecha_entrega_real' => null,
                        'fecha_devolucion_estimada' => now()->subDays(3)->format('Y-m-d H:i:s'),
                        'estado' => 'solicitado',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->withSession($this->sesionUsuario)
            ->get('/mis-prestamos');

        $response->assertOk();
        $response->assertSee('Fuente de Poder');
        $response->assertSee('Solicitado');
        $response->assertDontSee('Devolución vencida');
    }

    public function test_mis_prestamos_muestra_estado_vacio(): void
    {
        Http::fake([
            '*/prestamos?activos=1*' => Http::response(['data' => []], 200),
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
            '*/prestamos/pendientes*' => Http::response([
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

        Http::assertSent(fn ($request) => str_ends_with(strtok($request->url(), '?'), '/prestamos/pendientes') &&
            ($request['per_page'] ?? null) == 100
        );
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

    public function test_store_muestra_aviso_de_indisponibilidad_del_equipo(): void
    {
        // KAN-188: el backend reporta la indisponibilidad bajo `equipo_id`; el
        // formulario no tiene ese input, así que se pinta en el aviso ámbar.
        $mensaje = 'El equipo seleccionado se encuentra en mantenimiento y no está disponible para préstamo.';

        Http::fake([
            '*/prestamos' => Http::response([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => ['equipo_id' => [$mensaje]],
            ], 422),
        ]);

        $this->withSession($this->sesionUsuario)
            ->from('/prestamos/solicitar/1')
            ->post('/prestamos/solicitar', [
                'equipo_id' => 1,
                'fecha_inicio' => now()->format('Y-m-d'),
                'fecha_devolucion' => now()->addDays(2)->format('Y-m-d'),
                'motivo' => 'Prueba',
            ])
            ->assertRedirect('/prestamos/solicitar/1')
            ->assertSessionHasErrors(['equipo_id']);

        // La vista pinta ese error en el aviso de indisponibilidad.
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['equipo_id' => [$mensaje]]));

        $this->view('prestamos.create', [
            'equipo' => [
                'id' => 1,
                'nombre' => 'Osciloscopio Digital',
                'codigo' => 'OSC-001',
                'estado' => 'disponible',
            ],
            'errors' => $errors,
            'usuario' => [],
        ])
            ->assertSee('El equipo no puede ser solicitado')
            ->assertSee($mensaje);
    }

    public function test_create_no_confunde_un_error_de_motivo_con_indisponibilidad(): void
    {
        // Un mensaje con la palabra "préstamo" no debe activar el aviso de
        // indisponibilidad del equipo (KAN-188): solo el error de `equipo_id`.
        $errors = (new ViewErrorBag)->put('default', new MessageBag([
            'motivo' => ['El motivo del préstamo es obligatorio.'],
        ]));

        $this->view('prestamos.create', [
            'equipo' => [
                'id' => 1,
                'nombre' => 'Osciloscopio Digital',
                'codigo' => 'OSC-001',
                'estado' => 'disponible',
            ],
            'errors' => $errors,
            'usuario' => [],
        ])
            ->assertSee('El motivo del préstamo es obligatorio.')
            ->assertDontSee('El equipo no puede ser solicitado');
    }

    public function test_solicitudes_muestra_fechas_en_zona_horaria_de_bogota(): void
    {
        // El backend entrega ISO 8601 con desplazamiento; con APP_TIMEZONE=UTC una
        // solicitud de las 22:30 en Bogotá debe seguir mostrando el mismo día.
        Http::fake([
            '*/prestamos/pendientes*' => Http::response([
                'data' => [
                    [
                        'id' => 30,
                        'solicitante' => ['name' => 'Laura Gómez', 'email' => 'laura@ecci.edu.co'],
                        'equipo' => [
                            'nombre' => 'Fuente de Poder',
                            'codigo' => 'FUE-01',
                            'estado' => 'disponible',
                            'categoria' => ['nombre' => 'Electrónica'],
                        ],
                        'fecha_solicitud' => '2026-11-19T22:30:00-05:00',
                        'fecha_inicio' => '2026-11-25T08:00:00-05:00',
                        'fecha_devolucion_estimada' => '2026-11-28T18:00:00-05:00',
                        'estado' => 'solicitado',
                    ],
                ],
            ], 200),
        ]);

        $this->withSession($this->sesionEncargado)
            ->get('/solicitudes')
            ->assertOk()
            ->assertSee('19/11/2026')
            ->assertSee('25/11/2026')
            ->assertSee('28/11/2026');
    }
}
