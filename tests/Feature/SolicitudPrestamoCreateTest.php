<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * HU-06 / KAN-76: formulario de solicitud de préstamo.
 *
 * SolicitudPrestamoTest ya cubre el límite de 7 días; aquí se completa la carga
 * del equipo, las guardias de sesión/estado, el formato de fechas que se envía
 * al backend y los errores de conexión.
 */
class SolicitudPrestamoCreateTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $sesionUsuario = [
        'auth_token' => 'token-usuario-test',
        'user' => ['id' => 5, 'name' => 'Estudiante ECCI', 'roles' => ['usuario']],
    ];

    public function test_create_muestra_el_formulario_con_un_equipo_disponible(): void
    {
        Http::fake([
            '*/equipos/1' => Http::response([
                'data' => [
                    'id' => 1,
                    'nombre' => 'Osciloscopio Digital',
                    'codigo' => 'OSC-001',
                    'estado' => 'disponible',
                ],
            ], 200),
        ]);

        $this->withSession($this->sesionUsuario)
            ->get('/prestamos/solicitar/1')
            ->assertOk()
            ->assertSee('Osciloscopio Digital')
            ->assertSee('OSC-001');
    }

    public function test_create_sin_sesion_redirige_al_login(): void
    {
        $this->get('/prestamos/solicitar/1')->assertRedirect(route('login'));
        Http::assertNothingSent();
    }

    public function test_create_con_sesion_expirada_limpia_la_sesion(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

        $this->withSession($this->sesionUsuario)
            ->get('/prestamos/solicitar/1')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('auth_token');
    }

    public function test_create_redirige_al_catalogo_si_el_equipo_no_existe(): void
    {
        Http::fake(['*' => Http::response(['message' => 'No encontrado.'], 404)]);

        $this->withSession($this->sesionUsuario)
            ->get('/prestamos/solicitar/99')
            ->assertRedirect(route('equipos.index', ['vista' => 'catalogo']))
            ->assertSessionHasErrors('error');
    }

    public function test_create_bloquea_un_equipo_que_no_esta_disponible(): void
    {
        Http::fake([
            '*/equipos/1' => Http::response([
                'data' => ['id' => 1, 'nombre' => 'Osciloscopio', 'codigo' => 'OSC-001', 'estado' => 'mantenimiento'],
            ], 200),
        ]);

        $this->withSession($this->sesionUsuario)
            ->get('/prestamos/solicitar/1')
            ->assertRedirect(route('equipos.index', ['vista' => 'catalogo']))
            ->assertSessionHasErrors('error');
    }

    public function test_create_maneja_el_error_de_conexion(): void
    {
        Http::fake(fn () => throw new ConnectionException('backend caído'));

        $this->withSession($this->sesionUsuario)
            ->get('/prestamos/solicitar/1')
            ->assertRedirect(route('equipos.index', ['vista' => 'catalogo']))
            ->assertSessionHasErrors('index_error');
    }

    public function test_store_envia_las_fechas_con_horas_por_defecto(): void
    {
        Http::fake(['*/prestamos' => Http::response(['data' => ['id' => 1]], 201)]);

        $inicio = now()->addDays(2)->format('Y-m-d');
        $devolucion = now()->addDays(5)->format('Y-m-d');

        $this->withSession($this->sesionUsuario)
            ->post('/prestamos/solicitar', [
                'equipo_id' => 1,
                'fecha_inicio' => $inicio,
                'fecha_devolucion' => $devolucion,
                'motivo' => 'Práctica de laboratorio.',
            ])
            ->assertRedirect(route('equipos.index', ['vista' => 'catalogo']));

        Http::assertSent(function ($request) use ($inicio, $devolucion) {
            $data = $request->data();

            return str_ends_with($request->url(), '/prestamos')
                && $data['fecha_inicio'] === $inicio.' 08:00:00'
                && $data['fecha_devolucion_estimada'] === $devolucion.' 18:00:00';
        });
    }

    public function test_store_envia_la_fecha_de_hoy_con_la_hora_actual(): void
    {
        Http::fake(['*/prestamos' => Http::response(['data' => ['id' => 1]], 201)]);

        $hoy = now()->format('Y-m-d');

        $this->withSession($this->sesionUsuario)
            ->post('/prestamos/solicitar', [
                'equipo_id' => 1,
                'fecha_inicio' => $hoy,
                'fecha_devolucion' => now()->addDays(3)->format('Y-m-d'),
                'motivo' => 'Práctica de laboratorio.',
            ])
            ->assertRedirect(route('equipos.index', ['vista' => 'catalogo']));

        Http::assertSent(function ($request) use ($hoy) {
            $data = $request->data();

            return str_starts_with($data['fecha_inicio'], $hoy)
                && str_contains($data['fecha_inicio'], ':');
        });
    }

    public function test_store_rechaza_una_fecha_de_devolucion_anterior_al_inicio(): void
    {
        $this->withSession($this->sesionUsuario)
            ->post('/prestamos/solicitar', [
                'equipo_id' => 1,
                'fecha_inicio' => now()->addDays(3)->format('Y-m-d'),
                'fecha_devolucion' => now()->addDays(2)->format('Y-m-d'),
                'motivo' => 'Práctica de laboratorio.',
            ])
            ->assertSessionHasErrors('fecha_devolucion');

        Http::assertNothingSent();
    }

    public function test_store_con_sesion_expirada_redirige_al_login(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

        $this->withSession($this->sesionUsuario)
            ->post('/prestamos/solicitar', [
                'equipo_id' => 1,
                'fecha_inicio' => now()->format('Y-m-d'),
                'fecha_devolucion' => now()->addDays(2)->format('Y-m-d'),
                'motivo' => 'Práctica de laboratorio.',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionMissing('auth_token');
    }

    public function test_store_con_error_de_conexion_conserva_la_entrada(): void
    {
        Http::fake(fn () => throw new ConnectionException('backend caído'));

        $this->withSession($this->sesionUsuario)
            ->from('/prestamos/solicitar/1')
            ->post('/prestamos/solicitar', [
                'equipo_id' => 1,
                'fecha_inicio' => now()->format('Y-m-d'),
                'fecha_devolucion' => now()->addDays(2)->format('Y-m-d'),
                'motivo' => 'Práctica de laboratorio.',
            ])
            ->assertRedirect('/prestamos/solicitar/1')
            ->assertSessionHasErrors('prestamo_error')
            ->assertSessionHasInput('motivo', 'Práctica de laboratorio.');
    }

    public function test_store_exitoso_muestra_el_mensaje_de_confirmacion(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 1]], 201)]);

        $this->withSession($this->sesionUsuario)
            ->post('/prestamos/solicitar', [
                'equipo_id' => 1,
                'fecha_inicio' => now()->format('Y-m-d'),
                'fecha_devolucion' => now()->addDays(2)->format('Y-m-d'),
                'motivo' => 'Práctica de laboratorio.',
            ])
            ->assertRedirect(route('equipos.index', ['vista' => 'catalogo']))
            ->assertSessionHas('success', fn ($valor) => str_contains($valor, 'Solicitud de préstamo registrada con éxito'));
    }
}
