<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\Support\ErroresDeSesion;
use Tests\TestCase;

/**
 * HU-08: gestión de solicitudes (aprobar y rechazar) desde la bandeja web.
 *
 * PermisosPrestamosTest cubre el acceso por rol y un caso feliz de aprobación;
 * aquí se completa el rechazo y el manejo de errores del backend (KAN-115).
 */
class SolicitudesGestionTest extends TestCase
{
    use ErroresDeSesion;

    /** @var array<string, mixed> */
    private array $sesionEncargado = [
        'auth_token' => 'token-encargado-test',
        'user' => ['id' => 2, 'name' => 'Encargado Laboratorio', 'roles' => ['encargado']],
    ];

    public function test_encargado_puede_rechazar_con_motivo_y_envia_el_payload(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 9]], 200)]);

        $response = $this->withSession($this->sesionEncargado)
            ->patch('/prestamos/9/rechazar', ['motivo' => 'El equipo está reservado para otra práctica.']);

        $response->assertRedirect(route('prestamos.solicitudes'));
        $response->assertSessionHas('success', fn ($valor) => str_contains($valor, 'ha sido rechazada'));

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/prestamos/9/rechazo') &&
            $request['motivo'] === 'El equipo está reservado para otra práctica.'
        );
    }

    public function test_rechazo_exige_el_motivo(): void
    {
        $this->withSession($this->sesionEncargado)
            ->patch('/prestamos/9/rechazar', [])
            ->assertSessionHasErrors('motivo');

        Http::assertNothingSent();
    }

    public function test_rechazo_limita_el_motivo_a_mil_caracteres(): void
    {
        $this->withSession($this->sesionEncargado)
            ->patch('/prestamos/9/rechazar', ['motivo' => str_repeat('a', 1001)])
            ->assertSessionHasErrors('motivo');

        Http::assertNothingSent();
    }

    public function test_rechazo_muestra_los_errores_del_backend_aplanados(): void
    {
        Http::fake([
            '*' => Http::response([
                'message' => 'No se pudo rechazar.',
                'errors' => [
                    'motivo' => ['El motivo es demasiado largo.'],
                    'estado' => ['El préstamo ya fue gestionado.'],
                ],
            ], 422),
        ]);

        $response = $this->withSession($this->sesionEncargado)
            ->from('/solicitudes')
            ->patch('/prestamos/9/rechazar', ['motivo' => 'No cumple.']);

        $response->assertRedirect('/solicitudes');
        $errores = $this->erroresDeSesion();
        $this->assertContains('El motivo es demasiado largo.', $errores);
        $this->assertContains('El préstamo ya fue gestionado.', $errores);
    }

    public function test_rechazo_sin_permisos_en_el_backend_muestra_el_mensaje(): void
    {
        Http::fake(['*' => Http::response(['message' => 'No autorizado.'], 403)]);

        $this->withSession($this->sesionEncargado)
            ->from('/solicitudes')
            ->patch('/prestamos/9/rechazar', ['motivo' => 'No cumple.'])
            ->assertRedirect('/solicitudes')
            ->assertSessionHasErrors('error');
    }

    public function test_rechazo_con_sesion_expirada_redirige_al_login(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

        $this->withSession($this->sesionEncargado)
            ->patch('/prestamos/9/rechazar', ['motivo' => 'No cumple.'])
            ->assertRedirect(route('login'))
            ->assertSessionMissing('auth_token');
    }

    public function test_rechazo_con_error_de_conexion_conserva_la_pantalla(): void
    {
        Http::fake(fn () => throw new ConnectionException('backend caído'));

        $this->withSession($this->sesionEncargado)
            ->from('/solicitudes')
            ->patch('/prestamos/9/rechazar', ['motivo' => 'No cumple.'])
            ->assertRedirect('/solicitudes')
            ->assertSessionHasErrors('error');
    }

    public function test_aprobar_muestra_el_mensaje_de_error_del_backend(): void
    {
        Http::fake([
            '*' => Http::response([
                'message' => 'El equipo ya no está disponible.',
                'errors' => ['estado' => ['El equipo está en mantenimiento.']],
            ], 422),
        ]);

        $this->withSession($this->sesionEncargado)
            ->from('/solicitudes')
            ->patch('/prestamos/9/aprobar')
            ->assertRedirect('/solicitudes')
            ->assertSessionHas('errors');
    }

    public function test_aprobar_sin_permisos_en_el_backend_muestra_el_mensaje(): void
    {
        Http::fake(['*' => Http::response(['message' => 'No autorizado.'], 403)]);

        $this->withSession($this->sesionEncargado)
            ->from('/solicitudes')
            ->patch('/prestamos/9/aprobar')
            ->assertRedirect('/solicitudes')
            ->assertSessionHasErrors('error');
    }

    public function test_aprobar_con_sesion_expirada_redirige_al_login(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

        $this->withSession($this->sesionEncargado)
            ->patch('/prestamos/9/aprobar')
            ->assertRedirect(route('login'))
            ->assertSessionMissing('auth_token');
    }

    public function test_solicitudes_muestra_el_mensaje_de_error_del_backend(): void
    {
        Http::fake([
            '*' => Http::response(['message' => 'Error al cargar las solicitudes.'], 500),
        ]);

        $this->withSession($this->sesionEncargado)
            ->get('/solicitudes')
            ->assertOk()
            ->assertSee('Error al cargar las solicitudes.');
    }

    public function test_solicitudes_muestra_el_aviso_si_no_hay_conexion(): void
    {
        Http::fake(fn () => throw new ConnectionException('backend caído'));

        $this->withSession($this->sesionEncargado)
            ->get('/solicitudes')
            ->assertOk()
            ->assertSee('No fue posible conectar con el servicio de préstamos.');
    }

    public function test_solicitudes_muestra_el_estado_vacio(): void
    {
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $this->withSession($this->sesionEncargado)
            ->get('/solicitudes')
            ->assertOk()
            ->assertSee('No hay solicitudes pendientes.');
    }

    public function test_solicitudes_incluye_los_filtros_de_texto_y_de_fechas_kan_114(): void
    {
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $this->withSession($this->sesionEncargado)
            ->get('/solicitudes')
            ->assertOk()
            ->assertSee('Buscar por solicitante o equipo...')
            ->assertSee('x-model="fechaDesde"', false)
            ->assertSee('x-model="fechaHasta"', false);
    }

    /**
     * Regresión: un comentario JS con comillas dobles dentro de x-data cerraba el
     * atributo antes de tiempo y el navegador mostraba el código como texto.
     */
    public function test_el_atributo_x_data_de_solicitudes_queda_completo(): void
    {
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $response = $this->withSession($this->sesionEncargado)->get('/solicitudes');
        $response->assertOk();

        $dom = new \DOMDocument();
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);

        $completo = false;
        foreach ($xpath->query('//*[@x-data]') as $nodo) {
            if (str_contains($nodo->getAttribute('x-data'), 'filasVisibles')) {
                $completo = true;
                break;
            }
        }

        $this->assertTrue(
            $completo,
            'El atributo x-data de /solicitudes está truncado: revisa comillas dobles en los comentarios JS.'
        );
    }
}
