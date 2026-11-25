<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Support\ErroresDeSesion;
use Tests\TestCase;

/**
 * Re-ejecución automatizada de los casos del QA funcional de Alejandro (24 nov 2026)
 * que quedaron en rojo. Cada método corresponde a un `TC-*` de su matriz; tres estaban
 * bloqueados por `DEF-001` (crítica, `/solicitudes`), `DEF-002` (aviso del catálogo,
 * `BUG-0012`) y `DEF-003` (fecha «hoy» en UTC, `BUG-0013`), todos ya corregidos.
 *
 *   TC-PEND-001/003/004/005 · TC-GEST-001/002/003/004
 *   TC-DISP-006/007 · TC-FEC-001/007
 *
 * Lo que depende de Alpine (filtros, modal) se comprueba sobre el HTML que recibe el
 * navegador: las filas y los datos están en el documento y el atributo `x-data` llega
 * completo. El movimiento real de JS se validó con Playwright (`capturar-guion.js`).
 */
class ReejecucionCasosQATest extends TestCase
{
    use ErroresDeSesion;

    /** @var array<string, mixed> */
    private array $sesionSolicitante = [
        'auth_token' => 'token-sol',
        'user' => ['id' => 5, 'name' => 'Diana Ortiz', 'roles' => ['estudiante']],
    ];

    /** @var array<string, mixed> */
    private array $sesionEncargado = [
        'auth_token' => 'token-enc',
        'user' => ['id' => 2, 'name' => 'Encargado', 'roles' => ['encargado']],
    ];

    /**
     * Una solicitud pendiente como la entrega `/api/prestamos/pendientes`.
     *
     * @return array<string, mixed>
     */
    private function solicitud(int $id = 7, string $equipo = 'LPT-004', string $nombre = 'Laura Gómez'): array
    {
        return [
            'id' => $id,
            'estado' => 'solicitado',
            'solicitante' => ['name' => $nombre, 'email' => 'laura@ecci.edu.co'],
            'equipo' => [
                'codigo' => $equipo,
                'nombre' => 'Portátil Acer Aspire 5',
                'estado' => 'disponible',
                'categoria' => ['nombre' => 'Portátil'],
            ],
            'fecha_solicitud' => '2026-11-24T17:00:00-05:00',
            'fecha_inicio' => '2026-11-26T08:00:00-05:00',
            'fecha_devolucion_estimada' => '2026-11-28T18:00:00-05:00',
            'requiere_autorizacion' => false,
            'autorizado' => false,
        ];
    }

    // ── TC-PEND: consultar solicitudes pendientes (HU-07 · KAN-116) ──────────

    /** TC-PEND-001 — el encargado consulta las solicitudes con todos sus datos. */
    public function test_tc_pend_001_el_encargado_ve_las_solicitudes_con_sus_datos(): void
    {
        Http::fake(['*' => Http::response(['data' => [$this->solicitud()], 'meta' => ['total' => 1]], 200)]);

        $this->withSession($this->sesionEncargado)
            ->get('/solicitudes')
            ->assertOk()
            ->assertSee('#7')
            ->assertSee('Laura Gómez')
            ->assertSee('laura@ecci.edu.co')
            ->assertSee('Portátil Acer Aspire 5')
            ->assertSee('LPT-004')
            ->assertDontSee('No hay solicitudes pendientes.');
    }

    /** TC-PEND-003 — acceso al detalle (modal) de una solicitud. */
    public function test_tc_pend_003_el_detalle_de_la_solicitud_tiene_los_datos_del_modal(): void
    {
        Http::fake(['*' => Http::response(['data' => [$this->solicitud()]], 200)]);

        $html = $this->withSession($this->sesionEncargado)->get('/solicitudes')->assertOk()->getContent();

        // El botón abre el modal con el objeto de la fila: si no trae id y nombre, el
        // modal sale vacío (lo que fallaba con DEF-001).
        $this->assertStringContainsString('abrirModal(JSON.parse(', $html);
        $this->assertStringContainsString('id\u0022:7', $html);
        $this->assertStringContainsString('selected.solicitante', $html);
        $this->assertStringContainsString('Detalle de solicitud', $html);
    }

    /** TC-PEND-004 — búsqueda por solicitante/equipo y filtro por fecha. */
    public function test_tc_pend_004_los_filtros_reciben_las_filas(): void
    {
        Http::fake(['*' => Http::response(['data' => [$this->solicitud()]], 200)]);

        $this->withSession($this->sesionEncargado)
            ->get('/solicitudes')
            ->assertOk()
            ->assertSee('x-model="search"', false)
            ->assertSee('x-model="fechaDesde"', false)
            ->assertSee('x-model="fechaHasta"', false)
            ->assertSee('registrarFila(', false)
            ->assertSee('filaVisible(', false)
            ->assertSee("'laura gómez'", false);
    }

    /** TC-PEND-005 — la solicitud desaparece del listado tras aprobarla. */
    public function test_tc_pend_005_tras_aprobar_la_solicitud_sale_del_listado(): void
    {
        Http::fake([
            '*/prestamos/7/aprobacion' => Http::response(['data' => ['id' => 7, 'estado' => 'aprobado']], 200),
            '*/prestamos/pendientes*' => Http::response(['data' => []], 200),
        ]);

        $this->withSession($this->sesionEncargado)
            ->patch('/prestamos/7/aprobar')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->withSession($this->sesionEncargado)
            ->get('/solicitudes')
            ->assertOk()
            ->assertSee('No hay solicitudes pendientes.');
    }

    // ── TC-GEST: aprobar y rechazar (HU-08 · KAN-100) ────────────────────────

    /** TC-GEST-001 — aprobar una solicitud y que pase a la bandeja de entregas. */
    public function test_tc_gest_001_aprobar_la_envia_a_entregas_pendientes(): void
    {
        Http::fake([
            '*/prestamos/7/aprobacion' => Http::response(['data' => ['id' => 7, 'estado' => 'aprobado']], 200),
            '*/prestamos/activos*' => Http::response(['data' => []], 200),
        ]);

        $this->withSession($this->sesionEncargado)
            ->patch('/prestamos/7/aprobar')
            ->assertSessionHas('success')
            ->assertRedirect();

        Http::assertSent(fn ($r) => str_contains($r->url(), '/prestamos/7/aprobacion'));

        $this->withSession($this->sesionEncargado)->get('/entregas-pendientes')->assertOk();
    }

    /** TC-GEST-002 — rechazar con motivo y que el solicitante lo vea. */
    public function test_tc_gest_002_el_solicitante_ve_el_motivo_del_rechazo(): void
    {
        Http::fake([
            '*/prestamos/7/rechazo' => Http::response(['data' => ['id' => 7, 'estado' => 'rechazado']], 200),
        ]);

        $this->withSession($this->sesionEncargado)
            ->patch('/prestamos/7/rechazar', ['motivo' => 'El equipo está reservado para las prácticas.'])
            ->assertSessionHas('success');

        Http::fake([
            '*/prestamos*' => Http::response(['data' => [[
                'id' => 7,
                'estado' => 'rechazado',
                'motivo_rechazo' => 'El equipo está reservado para las prácticas.',
                'equipo' => ['codigo' => 'LPT-004', 'nombre' => 'Portátil Acer Aspire 5', 'categoria' => ['nombre' => 'Portátil']],
            ]]], 200),
        ]);

        $this->withSession($this->sesionSolicitante)
            ->get('/mis-prestamos')
            ->assertOk()
            ->assertSee('Motivo del rechazo: El equipo está reservado para las prácticas.');
    }

    /** TC-GEST-003 — no se puede rechazar sin motivo. */
    public function test_tc_gest_003_rechazar_exige_motivo(): void
    {
        $this->withSession($this->sesionEncargado)
            ->from('/solicitudes')
            ->patch('/prestamos/7/rechazar', ['motivo' => ''])
            ->assertRedirect('/solicitudes')
            ->assertSessionHasErrors('motivo');

        Http::assertNothingSent();
    }

    /** TC-GEST-004 — no se aprueba si el equipo ya no está disponible. */
    public function test_tc_gest_004_no_se_aprueba_si_el_equipo_no_esta_disponible(): void
    {
        Http::fake([
            '*/prestamos/7/aprobacion' => Http::response([
                'message' => 'El equipo no está disponible.',
                'errors' => ['equipo' => ['El equipo está en mantenimiento.']],
            ], 422),
        ]);

        $this->withSession($this->sesionEncargado)
            ->from('/solicitudes')
            ->patch('/prestamos/7/aprobar')
            ->assertRedirect('/solicitudes')
            ->assertSessionHasErrors();

        $this->assertContains('El equipo está en mantenimiento.', $this->erroresDeSesion());
    }

    // ── TC-DISP y TC-FEC: solicitar desde la interfaz (HU-20 y HU-21) ───────

    /** TC-DISP-006 — un equipo disponible se puede solicitar y se ve la confirmación. */
    public function test_tc_disp_006_solicitar_muestra_la_confirmacion_en_el_catalogo(): void
    {
        Http::fake(['*/prestamos' => Http::response(['data' => ['id' => 31]], 201)]);

        $this->withSession($this->sesionSolicitante)
            ->post('/prestamos/solicitar', [
                'equipo_id' => 4,
                'fecha_inicio' => now('America/Bogota')->toDateString(),
                'fecha_devolucion' => now('America/Bogota')->addDays(2)->toDateString(),
                'motivo' => 'Práctica de laboratorio.',
            ])
            ->assertRedirect(route('equipos.index', ['vista' => 'catalogo']))
            ->assertSessionHas('success');

        // DEF-002: el catálogo tiene que pintar el aviso de la sesión.
        $this->withSession($this->sesionSolicitante + ['success' => 'Solicitud registrada.'])
            ->get('/equipos?vista=catalogo')
            ->assertOk()
            ->assertSee('Solicitud registrada.');
    }

    /** TC-DISP-007 — si el equipo dejó de estar disponible, el catálogo explica por qué. */
    public function test_tc_disp_007_el_catalogo_muestra_el_motivo_cuando_falla_la_solicitud(): void
    {
        Http::fake(['*/prestamos' => Http::response([
            'message' => 'No disponible.',
            'errors' => ['equipo_id' => ['El equipo acaba de ser prestado a otro usuario.']],
        ], 422)]);

        $this->withSession($this->sesionSolicitante)
            ->from('/prestamos/solicitar/4')
            ->post('/prestamos/solicitar', [
                'equipo_id' => 4,
                'fecha_inicio' => now('America/Bogota')->toDateString(),
                'fecha_devolucion' => now('America/Bogota')->addDays(2)->toDateString(),
                'motivo' => 'Práctica de laboratorio.',
            ])
            ->assertRedirect('/prestamos/solicitar/4')
            ->assertSessionHasErrors();

        $this->assertContains('El equipo acaba de ser prestado a otro usuario.', $this->erroresDeSesion());
    }

    /** TC-FEC-001 — con fechas válidas la solicitud se registra. */
    public function test_tc_fec_001_fechas_validas_registran_la_solicitud(): void
    {
        Http::fake(['*/prestamos' => Http::response(['data' => ['id' => 32]], 201)]);

        $this->withSession($this->sesionSolicitante)
            ->post('/prestamos/solicitar', [
                'equipo_id' => 1,
                'fecha_inicio' => '2026-11-26',
                'fecha_devolucion' => '2026-11-28',
                'motivo' => 'Clase de redes.',
            ])
            ->assertRedirect(route('equipos.index', ['vista' => 'catalogo']));

        Http::assertSent(fn ($r) => $r['fecha_inicio'] === '2026-11-26 08:00:00'
            && $r['fecha_devolucion_estimada'] === '2026-11-28 18:00:00');
    }

    /** TC-FEC-007 — «hoy» es el día de Bogotá, también después de las 19:00. */
    public function test_tc_fec_007_la_fecha_de_hoy_es_la_de_bogota(): void
    {
        $this->travelTo(Carbon::parse('2026-11-25 22:04:00', 'America/Bogota'));

        Http::fake([
            '*/equipos/1' => Http::response([
                'data' => ['id' => 1, 'nombre' => 'Portátil Pro', 'codigo' => 'POR-01', 'estado' => 'disponible'],
            ], 200),
        ]);

        $this->withSession($this->sesionSolicitante)
            ->get('/prestamos/solicitar/1')
            ->assertOk()
            ->assertSee("fechaInicio: '2026-11-25'", false)
            ->assertSee('min="2026-11-25"', false)
            ->assertDontSee('2026-11-26', false);
    }
}
