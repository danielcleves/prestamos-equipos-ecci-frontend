<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * La sanción por mora se ve en la interfaz: aviso al encargado al devolver,
 * aviso al solicitante en Inicio y marca en la gestión de usuarios.
 */
class SancionVisibleTest extends TestCase
{
    private function sancion(int $restantes = 15, int $retraso = 3, int $dias = 15): array
    {
        return [
            'id' => 1, 'user_id' => 7, 'prestamo_id' => 5, 'motivo' => 'Mora de 3 día(s) en la devolución',
            'dias_retraso' => $retraso, 'dias_sancion' => $dias, 'dias_restantes' => $restantes,
            'fecha_inicio' => '2026-11-25T10:00:00-05:00', 'fecha_fin' => '2026-12-10T10:00:00-05:00',
            'estado' => 'activa', 'vigente' => true, 'created_at' => '2026-11-25T10:00:00-05:00',
        ];
    }

    private function sesion(string $rol): array
    {
        return ['auth_token' => 't', 'user' => ['name' => 'Persona', 'roles' => [$rol]]];
    }

    private function fakeInicio(?array $sancion, int $statusMe = 200): void
    {
        Http::fake([
            '*/me' => Http::response(['user' => ['id' => 7, 'sancion_activa' => $sancion]], $statusMe),
            '*' => Http::response(['data' => [], 'meta' => ['total' => 0]]),
        ]);
    }

    // ── Inicio del solicitante ─────────────────────────────────────────

    public function test_inicio_avisa_la_sancion_activa_del_solicitante(): void
    {
        $this->fakeInicio($this->sancion());

        $this->withSession($this->sesion('estudiante'))->get('/inicio')
            ->assertOk()
            ->assertSee('data-aviso="sancion"', false)
            ->assertSeeText('Tienes una sanción activa:')
            ->assertSeeText('quedan 15 días, hasta el 10/12/2026.')
            ->assertSeeText('No podrás solicitar equipos mientras dure.');
    }

    public function test_inicio_sin_sancion_no_muestra_aviso(): void
    {
        $this->fakeInicio(null);

        $this->withSession($this->sesion('estudiante'))->get('/inicio')
            ->assertOk()
            ->assertDontSee('data-aviso="sancion"', false)
            ->assertDontSeeText('sanción activa');
    }

    public function test_inicio_borde_vence_hoy_usa_singular(): void
    {
        $this->fakeInicio($this->sancion(restantes: 1));

        $this->withSession($this->sesion('estudiante'))->get('/inicio')
            ->assertOk()
            ->assertSeeText('queda 1 día, hasta el 10/12/2026.');
    }

    public function test_inicio_no_avisa_una_sancion_no_vigente_ni_si_me_falla(): void
    {
        $vencida = $this->sancion() + [];
        $vencida['vigente'] = false;
        $this->fakeInicio($vencida);
        $this->withSession($this->sesion('estudiante'))->get('/inicio')
            ->assertOk()->assertDontSee('data-aviso="sancion"', false);

        $this->fakeInicio($this->sancion(), 500);
        $this->withSession($this->sesion('estudiante'))->get('/inicio')
            ->assertOk()->assertDontSee('data-aviso="sancion"', false);
    }

    public function test_inicio_del_personal_no_consulta_la_sancion(): void
    {
        $this->fakeInicio($this->sancion());

        $this->withSession($this->sesion('encargado'))->get('/inicio')
            ->assertOk()->assertDontSee('data-aviso="sancion"', false);
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/me'));
    }

    // ── Devolución ─────────────────────────────────────────────────────

    public function test_devolucion_con_mora_avisa_la_sancion_al_encargado(): void
    {
        Http::fake(['*/prestamos/5/devolucion' => Http::response([
            'data' => ['id' => 5, 'estado' => 'devuelto'],
            'sancion' => $this->sancion(),
        ], 200)]);

        $this->withSession($this->sesion('encargado'))
            ->post('/prestamos/5/devolucion', ['condicion_devolucion' => 'bueno'])
            ->assertRedirect(route('prestamos.devoluciones'))
            ->assertSessionHas('success', '¡Devolución registrada con éxito! El equipo ha regresado al estado "Disponible". Se registró una sanción de 15 días por 3 días de retraso; vence el 10/12/2026.');
    }

    public function test_devolucion_borde_un_dia_usa_singular(): void
    {
        Http::fake(['*/prestamos/5/devolucion' => Http::response([
            'data' => ['id' => 5], 'sancion' => $this->sancion(1, 1, 1),
        ], 200)]);

        $this->withSession($this->sesion('encargado'))
            ->post('/prestamos/5/devolucion', ['condicion_devolucion' => 'bueno'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'sanción de 1 día por 1 día de retraso'));
    }

    public function test_devolucion_sin_sancion_o_sin_la_clave_conserva_el_mensaje(): void
    {
        $mensaje = '¡Devolución registrada con éxito! El equipo ha regresado al estado "Disponible".';

        foreach ([['data' => ['id' => 5], 'sancion' => null], ['data' => ['id' => 5]]] as $cuerpo) {
            Http::fake(['*/prestamos/5/devolucion' => Http::response($cuerpo, 200)]);

            $this->withSession($this->sesion('encargado'))
                ->post('/prestamos/5/devolucion', ['condicion_devolucion' => 'bueno'])
                ->assertSessionHas('success', $mensaje);
        }
    }

    // ── Usuarios ───────────────────────────────────────────────────────

    private function fakeUsuarios(): void
    {
        Http::fake(['*' => Http::response(['data' => [
            ['id' => 7, 'name' => 'Profesor Sarabino', 'email' => 'profe@ecci.edu.co', 'roles' => ['estudiante'], 'is_active' => true, 'sancion_activa' => $this->sancion()],
            ['id' => 8, 'name' => 'Beto Limpio', 'email' => 'beto@ecci.edu.co', 'roles' => ['estudiante'], 'is_active' => true, 'sancion_activa' => null],
        ], 'meta' => ['total' => 2, 'last_page' => 1]])]);
    }

    public function test_usuarios_marca_al_sancionado_en_tabla_y_tarjeta_con_su_resumen(): void
    {
        $this->fakeUsuarios();

        $html = $this->withSession($this->sesion('admin'))->get('/usuarios')->assertOk()->getContent();

        // Tabla y tarjeta móvil: dos píldoras, solo del usuario 7.
        $this->assertSame(2, substr_count($html, 'data-sancion="7"'));
        $this->assertStringNotContainsString('data-sancion="8"', $html);
        $this->assertSame(2, substr_count($html, 'Sancionado · 15 días'));
        $this->assertStringContainsString('bg-error-suave', $html);
        // Resumen para el modal: motivo, días de retraso y fecha fin.
        $this->assertStringContainsString('data-resumen-sancion', $html);
        $this->assertStringContainsString('Mora de 3 día(s) en la devolución', $html);
        $this->assertStringContainsString('\u0022diasRetraso\u0022:3', $html);
        $this->assertStringContainsString('\u0022fin\u0022:\u002210', $html);
    }

    public function test_usuarios_sin_sanciones_no_muestra_marca(): void
    {
        Http::fake(['*' => Http::response(['data' => [
            ['id' => 8, 'name' => 'Beto Limpio', 'email' => 'beto@ecci.edu.co', 'roles' => ['estudiante'], 'is_active' => true],
        ], 'meta' => ['total' => 1, 'last_page' => 1]])]);
        $this->withSession($this->sesion('admin'))->get('/usuarios')->assertOk()->assertDontSee('Sancionado');
    }

    public function test_usuarios_borde_un_dia_va_en_singular(): void
    {
        Http::fake(['*' => Http::response(['data' => [
            ['id' => 7, 'name' => 'Profesor Sarabino', 'email' => 'profe@ecci.edu.co', 'roles' => ['estudiante'], 'is_active' => true, 'sancion_activa' => $this->sancion(1)],
        ], 'meta' => ['total' => 1, 'last_page' => 1]])]);
        $this->withSession($this->sesion('admin'))->get('/usuarios')->assertOk()->assertSee('Sancionado · 1 día', false);
    }
}
