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
 * parámetros agrupados (RN-02, RN-04, RN-06, RN-08) y la restricción de una
 * categoría: su nivel (RN-03) y si exige capacitación (RN-01, por categoría).
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
                    'cupos.roles' => ['estudiante' => 4, 'encargado' => 6, 'admin' => 12],
                    'duracion.categorias' => ['1' => 8, '2' => 4],
                    'duracion.roles_extra' => ['estudiante' => 1, 'encargado' => 2, 'admin' => 3],
                    'mora.escala' => [
                        ['desde' => 1, 'hasta' => 2, 'dias_sancion' => 4],
                        ['desde' => 6, 'hasta' => null, 'dias_sancion' => 20],
                    ],
                    'mora.dias_bloqueo' => 2,
                    'danos.dias_restriccion' => 9,
                ],
                'categorias' => [
                    ['id' => 1, 'nombre' => 'Portátil', 'nivel_restriccion' => 1, 'requiere_capacitacion' => true],
                    ['id' => 2, 'nombre' => 'Tablet', 'nivel_restriccion' => 2, 'requiere_capacitacion' => false],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function formularioValido(array $sobrescribir = []): array
    {
        return array_merge([
            'duracion_categorias' => [1 => 10, 2 => 6, 3 => 4],
            'duracion_extra' => ['estudiante' => 0, 'encargado' => 2, 'admin' => 3],
            'mora_escala' => [
                ['desde' => 1, 'hasta' => 2, 'dias_sancion' => 3],
                ['desde' => 3, 'hasta' => null, 'dias_sancion' => 10],
            ],
            'mora_dias_bloqueo' => 2,
            'danos_dias_restriccion' => 5,
            'correos_permitidos' => "@ecci.edu.co\ndocente@otro.edu.co",
        ], $sobrescribir);
    }

    public function test_admin_ve_los_parametros_que_devuelve_el_backend(): void
    {
        Http::fake(['*/configuracion' => Http::response($this->respuestaConfiguracion(), 200)]);

        $response = $this->withSession($this->sesionAdmin)->get('/configuracion');

        $response->assertOk();
        $response->assertSee('Configuración de reglas de negocio');
        // Cupos por rol (RN-02): solo lectura, se gestionan en /roles
        $response->assertDontSee('name="cupos[', false);
        $response->assertSee('Gestionar roles y cupos');
        $response->assertSee(route('roles.index'), false);
        // Duración por categoría (RN-06)
        $response->assertSee('name="duracion_categorias[1]"', false);
        $response->assertSee('name="duracion_extra[encargado]"', false);
        // Mora (RN-04)
        $response->assertSee('x-model="tramo.desde"', false);
        $response->assertSee('name="mora_dias_bloqueo"', false);
        // Daños (RN-08)
        $response->assertSee('name="danos_dias_restriccion"', false);
        // Capacitación por categoría (RN-01): un interruptor por fila, sin parámetro global.
        $response->assertSee('data-capacitacion-categoria="1"', false);
        $response->assertSee('data-capacitacion-categoria="2"', false);
        $response->assertSee('quien no esté capacitado en esa categoría no podrá solicitar sus equipos', false);
        $response->assertDontSee('Exigir capacitación registrada para solicitar equipos');
        // Registro de solicitantes
        $response->assertSee('name="correos_permitidos"', false);
        $response->assertSee('@ecci.edu.co');
        // Restricción por categoría (RN-03)
        $response->assertSee('1 · Requiere autorización adicional');
        $response->assertSee('2 · Solo personal administrativo');
        $response->assertViewHas('categorias', fn ($categorias) => count($categorias) === 2
            && $categorias[0]['requiere_capacitacion'] === true
            && $categorias[1]['requiere_capacitacion'] === false);

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
        // Días extra por rol con los roles iniciales y sus valores por defecto.
        $response->assertSee('name="duracion_extra[estudiante]"', false);
        $response->assertSee('value="3"', false);
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

            // RN-02: el cupo ya no viaja desde esta pantalla.
            return ! isset($request['cupos.roles'])
                && $request['duracion.roles_extra'] === ['estudiante' => 0, 'encargado' => 2, 'admin' => 3]
                && $request['duracion.categorias'][1] === 10
                && $request['mora.escala'][1]['hasta'] === null
                && $request['mora.dias_bloqueo'] === 2
                && $request['danos.dias_restriccion'] === 5
                // RN-01: la capacitación es por categoría; el parámetro global ya no existe.
                && ! isset($request['elegibilidad.requiere_capacitacion'])
                && $request['registro.correos_permitidos'] === ['@ecci.edu.co', 'docente@otro.edu.co'];
        });
    }

    public function test_los_dias_extra_negativos_no_se_envian_al_backend(): void
    {
        $this->withSession($this->sesionAdmin)
            ->put('/configuracion', $this->formularioValido([
                'duracion_extra' => ['estudiante' => -1, 'encargado' => 2, 'admin' => 3],
            ]))
            ->assertSessionHasErrors('duracion_extra.estudiante');

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
        $response->assertSessionHas('success', 'Restricción de la categoría actualizada correctamente.');

        Http::assertSent(fn ($request) => $request->method() === 'PATCH' &&
            str_ends_with($request->url(), '/categorias/2/restriccion') &&
            $request->data() === ['nivel_restriccion' => 2]
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

    public function test_el_interruptor_exige_la_capacitacion_de_la_categoria(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 2]], 200)]);

        // El formulario de la fila envía el oculto «0» y el checkbox marcado «1».
        $this->withSession($this->sesionAdmin)
            ->patch('/categorias/2/restriccion', ['nivel_restriccion' => 0, 'requiere_capacitacion' => '1'])
            ->assertRedirect(route('configuracion.index'))
            ->assertSessionHas('success', 'Restricción de la categoría actualizada correctamente.');

        Http::assertSent(fn ($request) => $request->method() === 'PATCH' &&
            str_ends_with($request->url(), '/categorias/2/restriccion') &&
            $request['requiere_capacitacion'] === true &&
            $request['nivel_restriccion'] === 0
        );
    }

    public function test_el_interruptor_apagado_quita_el_requisito_de_capacitacion(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 1]], 200)]);

        // Desmarcado solo viaja el oculto «0»: se envía false, no se omite.
        $this->withSession($this->sesionAdmin)
            ->patch('/categorias/1/restriccion', ['nivel_restriccion' => 1, 'requiere_capacitacion' => '0'])
            ->assertRedirect(route('configuracion.index'));

        Http::assertSent(fn ($request) => $request->method() === 'PATCH' &&
            str_ends_with($request->url(), '/categorias/1/restriccion') &&
            $request['requiere_capacitacion'] === false &&
            $request['nivel_restriccion'] === 1
        );
    }

    public function test_la_capacitacion_se_puede_cambiar_sin_enviar_el_nivel(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 3]], 200)]);

        $this->withSession($this->sesionAdmin)
            ->patch('/categorias/3/restriccion', ['requiere_capacitacion' => '1'])
            ->assertRedirect(route('configuracion.index'));

        Http::assertSent(fn ($request) => $request->data() === ['requiere_capacitacion' => true]);
    }

    public function test_la_restriccion_sin_nivel_ni_capacitacion_no_llega_al_backend(): void
    {
        $this->withSession($this->sesionAdmin)
            ->patch('/categorias/2/restriccion', [])
            ->assertSessionHasErrors(['nivel_restriccion', 'requiere_capacitacion']);

        Http::assertNothingSent();
    }

    public function test_un_valor_de_capacitacion_no_booleano_no_llega_al_backend(): void
    {
        $this->withSession($this->sesionAdmin)
            ->patch('/categorias/2/restriccion', ['nivel_restriccion' => 0, 'requiere_capacitacion' => 'tal-vez'])
            ->assertSessionHasErrors('requiere_capacitacion');

        Http::assertNothingSent();
    }

    public function test_el_interruptor_refleja_el_estado_de_cada_categoria(): void
    {
        Http::fake(['*/configuracion' => Http::response($this->respuestaConfiguracion(), 200)]);

        $html = $this->withSession($this->sesionAdmin)->get('/configuracion')->assertOk()->getContent();

        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $portatil = $xpath->query('//input[@data-capacitacion-categoria="1"]')->item(0);
        $tablet = $xpath->query('//input[@data-capacitacion-categoria="2"]')->item(0);

        $this->assertTrue($portatil->hasAttribute('checked'));
        $this->assertFalse($tablet->hasAttribute('checked'));
        $this->assertSame('switch', $portatil->getAttribute('role'));
        $this->assertSame('restriccion-1', $portatil->getAttribute('form'));
        // El oculto que envía «0» va antes del checkbox y en el mismo formulario.
        $this->assertSame(1, $xpath->query('//input[@type="hidden"][@name="requiere_capacitacion"][@value="0"][@form="restriccion-1"]')->length);
    }

    public function test_si_el_backend_aun_trae_el_parametro_global_no_se_pinta_ni_se_reenvia(): void
    {
        $respuesta = $this->respuestaConfiguracion();
        $respuesta['data']['parametros']['elegibilidad.requiere_capacitacion'] = true;

        Http::fake(['*/configuracion' => Http::response($respuesta, 200)]);

        $this->withSession($this->sesionAdmin)
            ->get('/configuracion')
            ->assertOk()
            ->assertDontSee('Exigir capacitación registrada para solicitar equipos')
            ->assertViewHas('parametros', fn ($parametros) => ! array_key_exists('elegibilidad.requiere_capacitacion', $parametros));
    }

    public function test_una_categoria_sin_el_campo_arranca_sin_requisito(): void
    {
        $respuesta = $this->respuestaConfiguracion();
        unset($respuesta['data']['categorias'][0]['requiere_capacitacion']);

        Http::fake(['*/configuracion' => Http::response($respuesta, 200)]);

        $this->withSession($this->sesionAdmin)
            ->get('/configuracion')
            ->assertOk()
            ->assertViewHas('categorias', fn ($categorias) => $categorias[0]['requiere_capacitacion'] === false);
    }
}
