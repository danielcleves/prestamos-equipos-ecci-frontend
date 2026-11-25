<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * RN-01 / RN-02 / RN-04 · rechazo por elegibilidad al registrar la solicitud.
 *
 * El backend devuelve los motivos bajo `usuario_id` (sanción activa, cupo
 * superado, préstamo vencido, capacitación o categoría restringida) o en el
 * mensaje general. La pantalla debe mostrar cualquiera de los dos.
 */
class ElegibilidadSolicitudTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $sesionUsuario = [
        'auth_token' => 'token-usuario-test',
        'user' => ['id' => 5, 'name' => 'Estudiante ECCI', 'roles' => ['estudiante']],
    ];

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'equipo_id' => 1,
            'fecha_inicio' => now()->format('Y-m-d'),
            'fecha_devolucion' => now()->addDays(3)->format('Y-m-d'),
            'motivo' => 'Práctica de laboratorio.',
        ];
    }

    private function fakeEquipo(): void
    {
        Http::fake([
            '*/equipos/1' => Http::response([
                'data' => ['id' => 1, 'nombre' => 'Osciloscopio', 'codigo' => 'OSC-01', 'estado' => 'disponible'],
            ], 200),
            '*/prestamos' => Http::response([
                'message' => 'No cumples los requisitos para solicitar este equipo.',
                'errors' => [
                    'usuario_id' => [
                        'Tienes una sanción activa por mora hasta el 30/11/2026.',
                        'Tienes un préstamo entregado y vencido sin devolver.',
                    ],
                ],
            ], 422),
        ]);
    }

    public function test_los_motivos_de_elegibilidad_del_campo_usuario_id_se_muestran(): void
    {
        $this->fakeEquipo();

        $this->withSession($this->sesionUsuario)
            ->from('/prestamos/solicitar/1')
            ->followingRedirects()
            ->post('/prestamos/solicitar', $this->payload())
            ->assertOk()
            ->assertSee('No puedes solicitar este equipo todavía:')
            ->assertSee('Tienes una sanción activa por mora hasta el 30/11/2026.')
            ->assertSee('Tienes un préstamo entregado y vencido sin devolver.');
    }

    public function test_el_aviso_de_cupo_superado_tambien_se_muestra(): void
    {
        Http::fake([
            '*/equipos/1' => Http::response([
                'data' => ['id' => 1, 'nombre' => 'Osciloscopio', 'codigo' => 'OSC-01', 'estado' => 'disponible'],
            ], 200),
            '*/prestamos' => Http::response([
                'message' => 'Cupo de préstamos superado.',
                'errors' => ['usuario_id' => ['Ya tienes 3 préstamos vivos (cupo del rol: 3).']],
            ], 422),
        ]);

        $this->withSession($this->sesionUsuario)
            ->from('/prestamos/solicitar/1')
            ->followingRedirects()
            ->post('/prestamos/solicitar', $this->payload())
            ->assertOk()
            ->assertSee('Ya tienes 3 préstamos vivos (cupo del rol: 3).');
    }

    public function test_el_mensaje_general_del_backend_se_muestra_si_no_hay_errores_por_campo(): void
    {
        Http::fake([
            '*/equipos/1' => Http::response([
                'data' => ['id' => 1, 'nombre' => 'Osciloscopio', 'codigo' => 'OSC-01', 'estado' => 'disponible'],
            ], 200),
            '*/prestamos' => Http::response([
                'message' => 'El equipo no admite más solicitudes en este momento.',
            ], 422),
        ]);

        $this->withSession($this->sesionUsuario)
            ->from('/prestamos/solicitar/1')
            ->followingRedirects()
            ->post('/prestamos/solicitar', $this->payload())
            ->assertOk()
            ->assertSee('El equipo no admite más solicitudes en este momento.');
    }

    public function test_la_pantalla_limpia_no_muestra_avisos_de_elegibilidad(): void
    {
        Http::fake([
            '*/equipos/1' => Http::response([
                'data' => ['id' => 1, 'nombre' => 'Osciloscopio', 'codigo' => 'OSC-01', 'estado' => 'disponible'],
            ], 200),
        ]);

        $this->withSession($this->sesionUsuario)
            ->get('/prestamos/solicitar/1')
            ->assertOk()
            ->assertDontSee('No puedes solicitar este equipo todavía:');
    }
}
