<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Hallazgos de la auditoría UX/UI (EVD-033): H-13 … H-26.
 *
 * Cubre lo verificable en el HTML servido, agrupado por hallazgo. Lo que depende
 * de JavaScript o de una captura (foco real, Escape, franja del velo) se valida
 * por sus atributos y no por comportamiento en navegador.
 */
class UxAuditoriaEvd033Test extends TestCase
{
    /** @var array<string, mixed> */
    private array $sesionAdmin = [
        'auth_token' => 'token-admin',
        'user' => ['id' => 1, 'name' => 'Admin ECCI', 'roles' => ['admin']],
    ];

    /** @var array<string, mixed> */
    private array $sesionEncargado = [
        'auth_token' => 'token-encargado',
        'user' => ['id' => 2, 'name' => 'Encargado', 'roles' => ['encargado']],
    ];

    /** @var array<string, mixed> */
    private array $sesionEstudiante = [
        'auth_token' => 'token-estudiante',
        'user' => ['id' => 5, 'name' => 'Estudiante', 'roles' => ['estudiante']],
    ];

    private function fakeVacio(): void
    {
        Http::fake(['*' => Http::response(['data' => []], 200)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function equipo(int $id, string $estado = 'disponible', string $nombre = 'Portátil HP', string $codigo = 'LPT-001'): array
    {
        return [
            'id' => $id,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'estado' => $estado,
            'categoria' => ['nombre' => 'Portátil'],
        ];
    }

    // ---------------------------------------------------------------------
    // H-13: el botón de alta usa icono + texto, nunca el signo en el texto
    // ---------------------------------------------------------------------

    public function test_h13_el_boton_de_nuevo_equipo_no_repite_el_signo_mas(): void
    {
        Http::fake(['*' => Http::response(['data' => [$this->equipo(1)]], 200)]);

        $this->withSession($this->sesionAdmin)
            ->get('/equipos')
            ->assertOk()
            ->assertSee('Nuevo equipo')
            ->assertDontSee('+ Nuevo equipo');
    }

    public function test_h13_anadir_tramo_usa_el_mismo_patron_que_nuevo_usuario(): void
    {
        Http::fake([
            '*/configuracion*' => Http::response([
                'data' => [
                    'parametros' => [
                        'cupos.roles' => ['estudiante' => 3],
                        'duracion.categorias' => ['1' => 7],
                        'duracion.roles_extra' => ['estudiante' => 1],
                        'mora.escala' => [['desde' => 1, 'hasta' => null, 'dias_sancion' => 3]],
                        'mora.dias_bloqueo' => 2,
                        'danos.dias_restriccion' => 5,
                        'registro.correos_permitidos' => ['@ecci.edu.co'],
                    ],
                    'categorias' => [['id' => 1, 'nombre' => 'Portátil', 'nivel_restriccion' => 0, 'requiere_capacitacion' => false]],
                ],
            ], 200),
            '*/roles*' => Http::response(['data' => [[
                'id' => 1, 'nombre' => 'estudiante', 'etiqueta' => 'Estudiante',
                'cupo_prestamos' => 3, 'es_sistema' => false, 'es_predeterminado' => true,
                'descripcion' => '', 'usuarios_count' => 0,
            ]]], 200),
            '*' => Http::response(['data' => []], 200),
        ]);

        $this->withSession($this->sesionAdmin)
            ->get('/configuracion')
            ->assertOk()
            ->assertSee('Añadir tramo')
            ->assertDontSee('+ Añadir tramo');
    }

    // ---------------------------------------------------------------------
    // H-17: concordancia del contador del catálogo (0, 1 y N)
    // ---------------------------------------------------------------------

    private function catalogoConDisponibles(int $disponibles): string
    {
        $equipos = [];
        for ($i = 1; $i <= $disponibles; $i++) {
            $equipos[] = $this->equipo($i, 'disponible', 'Equipo '.$i, 'EQ-00'.$i);
        }

        Http::fake(['*' => Http::response(['data' => $equipos], 200)]);

        return $this->withSession($this->sesionEstudiante)
            ->get('/equipos')
            ->assertOk()
            ->getContent();
    }

    public function test_h17_el_catalogo_sin_equipos_usa_el_plural(): void
    {
        $this->assertStringContainsString('0 equipos disponibles para solicitar', $this->catalogoConDisponibles(0));
    }

    public function test_h17_el_catalogo_con_un_equipo_usa_el_singular(): void
    {
        $html = $this->catalogoConDisponibles(1);

        $this->assertStringContainsString('1 equipo disponible para solicitar', $html);
        $this->assertStringNotContainsString('1 equipos disponibles', $html);
    }

    public function test_h17_el_catalogo_con_varios_equipos_usa_el_plural(): void
    {
        $this->assertStringContainsString('3 equipos disponibles para solicitar', $this->catalogoConDisponibles(3));
    }

    // ---------------------------------------------------------------------
    // H-19: una sola miga de pan y título correcto en Solicitar préstamo
    // ---------------------------------------------------------------------

    private function solicitarConEquipo(): string
    {
        Http::fake(['*/equipos/1' => Http::response(['data' => $this->equipo(1)], 200)]);

        return $this->withSession($this->sesionEstudiante)
            ->get('/prestamos/solicitar/1')
            ->assertOk()
            ->getContent();
    }

    public function test_h19_la_pantalla_de_solicitar_envia_el_titulo_correcto(): void
    {
        $html = $this->solicitarConEquipo();

        $this->assertStringContainsString('<title>Solicitar préstamo</title>', $html);
        $this->assertStringNotContainsString('Gestión de equipos', $html);
    }

    public function test_h19_solo_queda_la_miga_del_encabezado(): void
    {
        // «Sistema» es el primer nivel de la miga del layout: debe aparecer una vez.
        $this->assertSame(1, substr_count($this->solicitarConEquipo(), '>Sistema<'));

        $this->fakeVacio();
        $entregas = $this->withSession($this->sesionEncargado)->get('/entregas-pendientes')->assertOk()->getContent();
        $this->assertStringNotContainsString('Personal de préstamo', $entregas);

        $usuarios = $this->withSession($this->sesionAdmin)->get('/usuarios')->assertOk()->getContent();
        $this->assertStringNotContainsString('>Administración<', $usuarios);
    }

    // ---------------------------------------------------------------------
    // H-20: un solo formato de fecha, d/m/Y (con hora solo en la devolución)
    // ---------------------------------------------------------------------

    public function test_h20_entregas_muestra_las_fechas_en_d_m_y(): void
    {
        Http::fake(['*' => Http::response(['data' => [[
            'id' => 4,
            'estado' => 'aprobado',
            'solicitante' => ['name' => 'Laura Gómez', 'email' => 'laura@ecci.edu.co'],
            'equipo' => ['nombre' => 'Osciloscopio', 'codigo' => 'OSC-01'],
            'fecha_inicio' => '2026-11-20 08:00:00',
            'fecha_devolucion_estimada' => '2026-11-27 18:00:00',
        ]]], 200)]);

        $html = $this->withSession($this->sesionEncargado)->get('/entregas-pendientes')->assertOk()->getContent();

        $this->assertStringContainsString('20/11/2026', $html);
        $this->assertStringContainsString('27/11/2026', $html);
        $this->assertStringNotContainsString('2026-11-20', $html);
    }

    public function test_h20_mis_prestamos_conserva_la_hora_solo_en_la_devolucion(): void
    {
        Http::fake(['*' => Http::response(['data' => [[
            'id' => 9,
            'estado' => 'entregado',
            'equipo' => ['nombre' => 'Portátil HP', 'codigo' => 'LPT-001', 'categoria' => ['nombre' => 'Portátil']],
            'fecha_entrega_real' => '2026-11-20 08:00:00',
            'fecha_devolucion_estimada' => '2026-11-22 18:00:00',
        ]]], 200)]);

        $html = $this->withSession($this->sesionEstudiante)->get('/mis-prestamos')->assertOk()->getContent();

        // Entrega sin hora; devolución con hora (zona de negocio).
        $this->assertStringContainsString('20/11/2026', $html);
        $this->assertStringContainsString('22/11/2026 13:00', $html);
    }

    public function test_h20_el_modal_de_solicitud_no_imprime_el_iso(): void
    {
        Http::fake(['*' => Http::response(['data' => [[
            'id' => 7,
            'estado' => 'solicitado',
            'solicitante' => ['name' => 'Laura Gómez', 'email' => 'laura@ecci.edu.co'],
            'equipo' => ['nombre' => 'Portátil HP', 'codigo' => 'LPT-001', 'estado' => 'disponible', 'categoria' => ['nombre' => 'Portátil']],
            'fecha_solicitud' => '2026-11-19T22:30:00-05:00',
            'fecha_inicio' => '2026-11-25T08:00:00-05:00',
            'fecha_devolucion_estimada' => '2026-11-28T18:00:00-05:00',
        ]]], 200)]);

        $html = $this->withSession($this->sesionEncargado)->get('/solicitudes')->assertOk()->getContent();

        $this->assertStringContainsString('19/11/2026', $html);
        // La fecha que recibe el modal ya no es ISO: se busca el patrón d/m/Y del JSON.
        $this->assertStringContainsString('fecha_solicitud', $html);
        $this->assertStringNotContainsString('fecha_solicitud":"2026-11-19', $html);
    }

    // ---------------------------------------------------------------------
    // H-21: los modales son diálogos accesibles
    // ---------------------------------------------------------------------

    public function test_h21_los_modales_tienen_semantica_de_dialogo_y_se_cierran_con_escape(): void
    {
        $this->fakeVacio();

        $paginas = ['/usuarios', '/equipos', '/entregas-pendientes', '/devoluciones', '/solicitudes', '/equipos?vista=catalogo'];

        foreach ($paginas as $url) {
            $html = $this->withSession($this->sesionAdmin)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('role="dialog"', $html, "Falta role=dialog en {$url}");
            $this->assertStringContainsString('aria-modal="true"', $html, "Falta aria-modal en {$url}");
            $this->assertStringContainsString('aria-labelledby="modal-', $html, "Falta aria-labelledby en {$url}");
            $this->assertStringContainsString('@keydown.escape.window', $html, "Falta Escape en {$url}");
            $this->assertStringContainsString('aria-label="Cerrar"', $html, "Falta nombre en la «×» de {$url}");
        }
    }

    public function test_h21_el_cancelar_del_rechazo_solo_existe_dentro_del_rechazo(): void
    {
        $this->fakeVacio();

        $html = $this->withSession($this->sesionEncargado)->get('/solicitudes')->assertOk()->getContent();

        $this->assertStringContainsString('Cancelar rechazo', $html);
        // Depende de openRechazo: no se muestra junto a «Cerrar» hasta pulsar «Rechazar».
        $this->assertMatchesRegularExpression('/x-show="openRechazo"[^>]*>\s*Cancelar rechazo/s', $html);
    }

    // ---------------------------------------------------------------------
    // H-22: sin filas no se pinta el encabezado de la tabla
    // ---------------------------------------------------------------------

    public function test_h22_los_listados_vacios_no_muestran_columnas(): void
    {
        $this->fakeVacio();

        $entregas = $this->withSession($this->sesionEncargado)->get('/entregas-pendientes')->assertOk();
        $entregas->assertSee('No hay entregas pendientes registradas en este momento.');
        $this->assertStringNotContainsString('<th', $entregas->getContent());

        $devoluciones = $this->withSession($this->sesionEncargado)->get('/devoluciones')->assertOk();
        $devoluciones->assertSee('No hay préstamos activos pendientes de devolución.');
        $this->assertStringNotContainsString('<th', $devoluciones->getContent());

        $incidencias = $this->withSession($this->sesionEncargado)->get('/incidencias')->assertOk();
        $incidencias->assertSee('No hay incidencias registradas.');
        $incidencias->assertDontSee('Restringido hasta');
    }

    public function test_h22_con_filas_el_encabezado_vuelve_a_mostrarse(): void
    {
        Http::fake(['*' => Http::response(['data' => [[
            'id' => 4,
            'estado' => 'aprobado',
            'solicitante' => ['name' => 'Laura Gómez', 'email' => 'laura@ecci.edu.co'],
            'equipo' => ['nombre' => 'Osciloscopio', 'codigo' => 'OSC-01'],
            'fecha_inicio' => '2026-11-20 08:00:00',
            'fecha_devolucion_estimada' => '2026-11-27 18:00:00',
        ]]], 200)]);

        $this->withSession($this->sesionEncargado)
            ->get('/entregas-pendientes')
            ->assertOk()
            ->assertSee('F. Préstamo')
            ->assertSee('Registrar entrega');
    }

    // ---------------------------------------------------------------------
    // H-23: el resumen muestra el equipo por nombre, no por serial
    // ---------------------------------------------------------------------

    public function test_h23_el_resumen_de_la_solicitud_identifica_el_equipo_por_nombre(): void
    {
        $html = $this->solicitarConEquipo();

        $this->assertStringContainsString('Portátil HP', $html);
        $this->assertStringContainsString('LPT-001', $html);
        $this->assertStringNotContainsString('Usuario autenticado', $html);
    }

    // ---------------------------------------------------------------------
    // H-25: mayúsculas iniciales y rótulo «Correo»
    // ---------------------------------------------------------------------

    public function test_h25_los_rotulos_van_con_mayuscula_inicial(): void
    {
        $this->fakeVacio();

        $usuarios = $this->withSession($this->sesionAdmin)->get('/usuarios')->assertOk();
        foreach (['Registrar nuevo usuario', 'Editar usuario', 'Nombre completo', 'Correo electrónico', 'Contraseña inicial', 'Guardar usuario'] as $rotulo) {
            $usuarios->assertSee($rotulo);
        }
        $usuarios->assertDontSee('Registrar Nuevo Usuario');

        $this->withSession($this->sesionAdmin)->get('/equipos')->assertOk()->assertSee('Actualizar estado del equipo');
        $this->withSession($this->sesionEncargado)->get('/entregas-pendientes')->assertOk()->assertSee('Confirmar entrega de equipo');
        $this->withSession($this->sesionEncargado)->get('/devoluciones')->assertOk()->assertSee('Registrar devolución de equipo');
    }

    public function test_h25_el_modal_de_solicitud_rotula_correo_y_no_documento(): void
    {
        $this->fakeVacio();

        $this->withSession($this->sesionEncargado)
            ->get('/solicitudes')
            ->assertOk()
            ->assertSee('CORREO')
            ->assertDontSee('DOCUMENTO / EMAIL');
    }

    // ---------------------------------------------------------------------
    // H-26: salto al contenido y halo de foco
    // ---------------------------------------------------------------------

    public function test_h26_el_layout_ofrece_saltar_al_contenido(): void
    {
        $this->fakeVacio();

        $this->withSession($this->sesionEstudiante)
            ->get('/inicio')
            ->assertOk()
            ->assertSee('Saltar al contenido')
            ->assertSee('href="#contenido-principal"', false)
            ->assertSee('id="contenido-principal"', false)
            ->assertSee('build/assets/app-', false);

        // El halo de foco dejó de estar incrustado en el layout: vive en
        // app.css, el bundle compartido con el layout guest que carga esta
        // página. UxAcabadoTest comprueba las dos reglas (halo y modalidad).
        $this->assertStringContainsString(
            ':focus-visible',
            file_get_contents(resource_path('css/app.css')),
            'app.css debe seguir definiendo el halo de foco de 2 px'
        );
    }

    // ---------------------------------------------------------------------
    // H-15: Inicio sin destinos que reboten
    // ---------------------------------------------------------------------

    public function test_h15_el_solicitante_no_ve_la_tarjeta_de_usuarios(): void
    {
        // Inicio consulta la API en cada visita (D-2): sin fake saldría a la red.
        $this->fakeVacio();

        $this->withSession($this->sesionEstudiante)
            ->get('/inicio')
            ->assertOk()
            ->assertSee('Catálogo de equipos')
            ->assertDontSee('Gestión de usuarios');
    }

    public function test_h15_el_admin_si_ve_la_tarjeta_de_usuarios(): void
    {
        // Inicio consulta la API en cada visita (D-2): sin fake saldría a la red.
        $this->fakeVacio();

        $this->withSession($this->sesionAdmin)
            ->get('/inicio')
            ->assertOk()
            ->assertSee('Gestión de usuarios');
    }

    // ---------------------------------------------------------------------
    // H-16: el login no muestra cifras inventadas
    // ---------------------------------------------------------------------

    public function test_h16_el_login_no_muestra_datos_que_no_son_del_producto(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Iniciar sesión')
            ->assertDontSee('240+')
            ->assertDontSee('Soporte técnico')
            ->assertDontSee('Roles de usuario')
            ->assertDontSee('v2.4.1')
            ->assertDontSee('SISTEMA ACTIVO');
    }

    // ---------------------------------------------------------------------
    // H-18: chip del sidebar y un único texto para volver
    // ---------------------------------------------------------------------

    public function test_h18_el_admin_en_la_vista_de_solicitante_se_identifica_como_admin(): void
    {
        $this->fakeVacio();

        $this->withSession($this->sesionAdmin)
            ->get('/equipos?vista=catalogo')
            ->assertOk()
            ->assertSee('Administrador · vista de solicitante')
            ->assertSee('Volver a gestión')
            ->assertDontSee('Ver como administrador');
    }

    public function test_h18_un_solicitante_no_se_anuncia_como_administrador(): void
    {
        $this->fakeVacio();

        $this->withSession($this->sesionEstudiante)
            ->get('/equipos')
            ->assertOk()
            ->assertSee('Solicitante')
            ->assertDontSee('vista de solicitante');
    }

    // ---------------------------------------------------------------------
    // H-24: el menú del personal llama «Entregas» a la pantalla de entregas
    // ---------------------------------------------------------------------

    public function test_h24_el_menu_del_personal_usa_el_rotulo_entregas(): void
    {
        $this->fakeVacio();

        $this->withSession($this->sesionEncargado)
            ->get('/equipos')
            ->assertOk()
            ->assertSee('<span>Entregas</span>', false)
            ->assertDontSee('<span>Préstamos</span>', false);
    }
}
