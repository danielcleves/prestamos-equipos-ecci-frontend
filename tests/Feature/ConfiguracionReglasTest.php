<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\Support\ErroresDeSesion;
use Tests\TestCase;

/**
 * Iteración 2 · parámetros de las reglas de negocio.
 *
 * Cubre la pantalla /configuracion (solo administrador), el guardado de los
 * parámetros agrupados (RN-02, RN-04, RN-06, RN-08) y el cambio del nivel de
 * restricción de una categoría (RN-03).
 */
class ConfiguracionReglasTest extends TestCase
{
    use ErroresDeSesion;

    /** @var array<string, mixed> */
    private array $sesionAdmin = [
        'auth_token' => 'token-admin-test',
        'user' => ['id' => 1, 'name' => 'Admin ECCI', 'roles' => ['admin']],
    ];

    /** @return array<string, mixed> */
    private function respuestaConfiguracion(): array
    {
        return [
            'data' => [
                'parametros' => [
                    'cupos.roles' => ['usuario' => 4, 'encargado' => 6, 'admin' => 12],
                    'duracion.categorias' => ['1' => 8, '2' => 4],
                    'duracion.roles_extra' => ['usuario' => 1, 'encargado' => 2, 'admin' => 3],
                    'mora.escala' => [
                        ['desde' => 1, 'hasta' => 2, 'dias_sancion' => 4],
                        ['desde' => 6, 'hasta' => null, 'dias_sancion' => 20],
                    ],
                    'mora.dias_bloqueo' => 2,
                    'danos.dias_restriccion' => 9,
                    'elegibilidad.requiere_capacitacion' => true,
                ],
                'categorias' => [
                    ['id' => 1, 'nombre' => 'Portátil', 'nivel_restriccion' => 1],
                    ['id' => 2, 'nombre' => 'Tablet', 'nivel_restriccion' => 2],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function formularioValido(array $sobrescribir = []): array
    {
        return array_merge([
            'cupos' => ['usuario' => 5, 'encargado' => 6, 'admin' => 7],
            'duracion_categorias' => [1 => 10, 2 => 6, 3 => 4],
            'duracion_extra' => ['usuario' => 0, 'encargado' => 2, 'admin' => 3],
            'mora_escala' => [
                ['desde' => 1, 'hasta' => 2, 'dias_sancion' => 3],
                ['desde' => 3, 'hasta' => null, 'dias_sancion' => 10],
            ],
            'mora_dias_bloqueo' => 2,
            'danos_dias_restriccion' => 5,
            'requiere_capacitacion' => '1',
        ], $sobrescribir);
    }

    public function test_admin_ve_los_parametros_que_devuelve_el_backend(): void
    {
        Http::fake(['*/configuracion' => Http::response($this->respuestaConfiguracion(), 200)]);

        $response = $this->withSession($this->sesionAdmin)->get('/configuracion');

        $response->assertOk();
        $response->assertSee('Configuración de reglas de negocio');
        // Cupos por rol (RN-02)
        $response->assertSee('name="cupos[usuario]"', false);
        $response->assertSee('value="4"', false);
        // Duración por categoría (RN-06)
        $response->assertSee('name="duracion_categorias[1]"', false);
        $response->assertSee('name="duracion_extra[encargado]"', false);
        // Mora (RN-04)
        $response->assertSee('x-model="tramo.desde"', false);
        $response->assertSee('name="mora_dias_bloqueo"', false);
        // Daños (RN-08)
        $response->assertSee('name="danos_dias_restriccion"', false);
        // Elegibilidad (RN-01)
        $response->assertSee('name="requiere_capacitacion"', false);
        // Restricción por categoría (RN-03)
        $response->assertSee('1 · Requiere autorización adicional');
        $response->assertSee('2 · Solo personal administrativo');
        $response->assertViewHas('categorias', fn ($categorias) => count($categorias) === 2);

        Http::assertSent(fn ($request) => $request->method() === 'GET' &&
            str_ends_with($request->url(), '/configuracion')
        );
    }

    public function test_configuracion_usa_los_valores_por_defecto_si_el_backend_falla(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Configuración no disponible.'], 500)]);

        $response = $this->withSession($this->sesionAdmin)->get('/configuracion');

        $response->assertOk();
        $response->assertSee('Configuración no disponible.');
        $response->assertSee('value="3"', false);
        $response->assertSee('value="10"', false);
    }

    public function test_configuracion_avisa_si_no_hay_conexion(): void
    {
        Http::fake(fn () => throw new ConnectionException('backend caído'));

        $this->withSession($this->sesionAdmin)
            ->get('/configuracion')
            ->assertOk()
            ->assertSee('No fue posible conectar con el servicio de configuración.');
    }

    public function test_configuracion_es_solo_para_administradores(): void
    {
        $this->withSession([
            'auth_token' => 'token-encargado',
            'user' => ['id' => 2, 'name' => 'Encargado', 'roles' => ['encargado']],
        ])
            ->get('/configuracion')
            ->assertRedirect(route('inicio'))
            ->assertSessionHasErrors('inicio');

        Http::assertNothingSent();
    }

    public function test_configuracion_sin_sesion_redirige_al_login(): void
    {
        $this->get('/configuracion')->assertRedirect(route('login'));

        Http::assertNothingSent();
    }

    public function test_configuracion_con_sesion_expirada_redirige_al_login(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

        $this->withSession($this->sesionAdmin)
            ->get('/configuracion')
            ->assertRedirect(route('login'))
            ->assertSessionMissing('auth_token');
    }

    public function test_el_menu_del_admin_incluye_configuracion_y_el_del_encargado_no(): void
    {
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $this->withSession($this->sesionAdmin)
            ->get('/equipos')
            ->assertOk()
            ->assertSee('Configuración');

        $this->withSession([
            'auth_token' => 'token-encargado',
            'user' => ['id' => 2, 'name' => 'Encargado', 'roles' => ['encargado']],
        ])
            ->get('/equipos')
            ->assertOk()
            ->assertDontSee('Configuración');
    }

    public function test_admin_guarda_los_parametros_y_envia_el_mapa_clave_valor(): void
    {
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $response = $this->withSession($this->sesionAdmin)
            ->put('/configuracion', $this->formularioValido());

        $response->assertRedirect(route('configuracion.index'));
        $response->assertSessionHas('success', 'Parámetros de configuración actualizados correctamente.');

        Http::assertSent(function ($request) {
            if ($request->method() !== 'PUT' || ! str_ends_with($request->url(), '/configuracion')) {
                return false;
            }

            return $request['cupos.roles']['usuario'] === 5
                && $request['duracion.categorias'][1] === 10
                && $request['mora.escala'][1]['hasta'] === null
                && $request['mora.dias_bloqueo'] === 2
                && $request['danos.dias_restriccion'] === 5
                && $request['elegibilidad.requiere_capacitacion'] === true;
        });
    }

    public function test_el_cupo_negativo_no_se_envia_al_backend(): void
    {
        $this->withSession($this->sesionAdmin)
            ->put('/configuracion', $this->formularioValido([
                'cupos' => ['usuario' => -1, 'encargado' => 6, 'admin' => 7],
            ]))
            ->assertSessionHasErrors('cupos.usuario');

        Http::assertNothingSent();
    }

    public function test_la_escala_de_mora_no_acepta_un_hasta_menor_que_desde(): void
    {
        $this->withSession($this->sesionAdmin)
            ->put('/configuracion', $this->formularioValido([
                'mora_escala' => [
                    ['desde' => 5, 'hasta' => 2, 'dias_sancion' => 3],
                ],
            ]))
            ->assertSessionHasErrors('mora_escala');

        Http::assertNothingSent();
    }

    public function test_configuracion_muestra_el_error_de_validacion_del_backend(): void
    {
        Http::fake([
            '*' => Http::response([
                'message' => 'No se pudo guardar.',
                'errors' => ['cupos.roles' => ['El cupo del administrador es obligatorio.']],
            ], 422),
        ]);

        $response = $this->withSession($this->sesionAdmin)
            ->from('/configuracion')
            ->put('/configuracion', $this->formularioValido());

        $response->assertRedirect('/configuracion');
        $response->assertSessionHasErrors('configuracion_error');
        $this->assertContains('El cupo del administrador es obligatorio.', $this->erroresDeSesion());
    }

    public function test_configuracion_con_error_de_conexion_conserva_la_pantalla(): void
    {
        Http::fake(fn () => throw new ConnectionException('backend caído'));

        $this->withSession($this->sesionAdmin)
            ->from('/configuracion')
            ->put('/configuracion', $this->formularioValido())
            ->assertRedirect('/configuracion')
            ->assertSessionHasErrors('configuracion_error');
    }

    public function test_admin_cambia_el_nivel_de_restriccion_de_una_categoria(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 2]], 200)]);

        $response = $this->withSession($this->sesionAdmin)
            ->patch('/categorias/2/restriccion', ['nivel_restriccion' => 2]);

        $response->assertRedirect(route('configuracion.index'));
        $response->assertSessionHas('success', 'Nivel de restricción de la categoría actualizado correctamente.');

        Http::assertSent(fn ($request) => $request->method() === 'PATCH' &&
            str_ends_with($request->url(), '/categorias/2/restriccion') &&
            $request['nivel_restriccion'] === 2
        );
    }

    public function test_un_nivel_de_restriccion_invalido_no_llega_al_backend(): void
    {
        $this->withSession($this->sesionAdmin)
            ->patch('/categorias/2/restriccion', ['nivel_restriccion' => 3])
            ->assertSessionHasErrors('nivel_restriccion');

        Http::assertNothingSent();
    }

    public function test_cambiar_la_restriccion_sin_permisos_en_el_backend_redirige_al_inicio(): void
    {
        Http::fake(['*' => Http::response(['message' => 'No autorizado.'], 403)]);

        $this->withSession($this->sesionAdmin)
            ->patch('/categorias/2/restriccion', ['nivel_restriccion' => 1])
            ->assertRedirect(route('inicio'))
            ->assertSessionHasErrors('inicio');
    }
}
