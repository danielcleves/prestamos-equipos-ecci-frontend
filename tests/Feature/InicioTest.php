<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Landing de inicio: exige sesión y saluda al usuario autenticado.
 */
class InicioTest extends TestCase
{
    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get('/inicio')->assertRedirect(route('login'));
    }

    /**
     * El layout conserva la acción de cerrar sesión del sidebar (title="Cerrar sesión"),
     * así que se cuenta el texto en vez de prohibirlo en toda la página.
     */
    private function contarCerrarSesion(string $html): int
    {
        return substr_count($html, 'Cerrar sesión');
    }

    public function test_usuario_autenticado_ve_el_saludo_y_los_accesos(): void
    {
        $this->fakeApi(['prestamos*' => $this->lista([])]);

        $response = $this->withSession([
            'auth_token' => 'token-vigente',
            'user' => ['name' => 'Ana Pérez', 'roles' => ['estudiante']],
        ])
            ->get('/inicio')
            ->assertOk()
            ->assertSee('Hola, Ana Pérez')
            ->assertSee('Catálogo de equipos')
            // H-15 (EVD-033): el solicitante no ve accesos que lo devuelvan a Inicio.
            ->assertDontSee('Gestión de usuarios');

        $this->assertSame(
            1,
            $this->contarCerrarSesion($response->getContent()),
            'Inicio ya no ofrece «Cerrar sesión» como tarjeta: solo queda la acción del sidebar.'
        );
    }

    public function test_el_admin_si_ve_el_acceso_a_la_gestion_de_usuarios(): void
    {
        $this->fakeApi([]);

        $response = $this->withSession([
            'auth_token' => 'token-admin',
            'user' => ['name' => 'Admin ECCI', 'roles' => ['admin']],
        ])
            ->get('/inicio')
            ->assertOk()
            ->assertSee('Gestión de usuarios');

        $this->assertSame(1, $this->contarCerrarSesion($response->getContent()));
    }

    private function lista(array $items): array
    {
        return ['data' => $items, 'meta' => ['total' => count($items)]];
    }

    private function prestamo(int $id, string $estado, string $fecha, string $equipo = 'Portátil HP'): array
    {
        return [
            'id' => $id,
            'estado' => $estado,
            'fecha_devolucion_estimada' => $fecha,
            'solicitante' => ['name' => 'Solicitante '.$id],
            'equipo' => ['codigo' => 'EQ-'.$id, 'nombre' => $equipo],
        ];
    }

    /** Simula la API; lo no indicado responde vacío. */
    private function fakeApi(array $respuestas): void
    {
        $vacio = Http::response($this->lista([]));
        $mapa = [];
        foreach ($respuestas as $patron => $respuesta) {
            $mapa['*/'.$patron] = is_array($respuesta) ? Http::response($respuesta) : $respuesta;
        }
        $mapa['*'] = $vacio;
        Http::fake($mapa);
    }

    private function personal(string $rol): static
    {
        return $this->withSession(['auth_token' => 't', 'user' => ['name' => 'Rosa Recepción', 'roles' => [$rol]]]);
    }

    public function test_el_encargado_ve_las_cifras_y_los_retrasados_primero(): void
    {
        $ayer = now()->subDays(3)->toIso8601String();
        $manana = now()->addDay()->toIso8601String();

        $this->fakeApi([
            'prestamos/pendientes*' => $this->lista([$this->prestamo(1, 'solicitado', $manana)]),
            'prestamos?estado=aprobado*' => $this->lista([$this->prestamo(2, 'aprobado', $manana), $this->prestamo(3, 'aprobado', $manana)]),
            'prestamos/activos*' => $this->lista([
                $this->prestamo(4, 'entregado', $manana, 'Cámara'),
                $this->prestamo(5, 'entregado', $ayer, 'Proyector'),
            ]),
        ]);

        $html = $this->personal('encargado')->get('/inicio')->assertOk()
            ->assertSee('Solicitudes por aprobar')
            ->assertSee('Entregas pendientes')
            ->assertSee('Devoluciones pendientes')
            ->assertSee('Préstamos retrasados')
            ->assertSee('3 días de retraso')
            ->assertSee('Retrasado')
            ->assertDontSee('Gestión de usuarios')
            ->assertDontSee('Equipos disponibles')
            ->assertDontSee('en construcción')
            ->getContent();

        $this->assertMatchesRegularExpression('/>\s*1\s*<\/p>\s*<p[^>]*>\s*Ver lista/', $html);
        $this->assertMatchesRegularExpression('/>\s*2\s*<\/p>\s*<p[^>]*>\s*Ver lista/', $html);
        $this->assertLessThan(strpos($html, 'Cámara'), strpos($html, 'Proyector'), 'El retrasado va antes que el préstamo en plazo.');
    }

    public function test_la_lista_urgente_se_limita_a_cinco(): void
    {
        $manana = now()->addDay()->toIso8601String();
        $this->fakeApi([
            'prestamos/pendientes*' => $this->lista(array_map(fn ($i) => $this->prestamo($i, 'solicitado', $manana, 'Equipo '.$i), range(1, 8))),
        ]);

        $html = $this->personal('encargado')->get('/inicio')->assertOk()->getContent();

        $this->assertSame(5, preg_match_all('/Revisar solicitud/', $html));
    }

    public function test_personal_sin_trabajo_ve_estados_vacios(): void
    {
        $this->fakeApi([]);

        $this->personal('encargado')->get('/inicio')->assertOk()
            ->assertSee('Sin solicitudes por aprobar')
            ->assertSee('Sin entregas pendientes')
            ->assertSee('Sin devoluciones pendientes')
            ->assertSee('Ningún préstamo retrasado')
            ->assertSee('Nada urgente por ahora')
            ->assertDontSee('No pudimos consultar');
    }

    public function test_el_admin_ve_equipos_incidencias_y_accesos(): void
    {
        $this->fakeApi([
            'equipos*' => ['data' => [['estado' => 'disponible'], ['estado' => 'en_prestamo'], ['estado' => 'disponible']], 'meta' => ['total' => 3]],
            'incidencias*' => $this->lista([['estado' => 'abierta'], ['estado' => 'cerrada']]),
        ]);

        $html = $this->personal('admin')->get('/inicio')->assertOk()
            ->assertSee('Equipos disponibles')
            ->assertSee('Incidencias abiertas')
            ->assertSee('Gestión de usuarios')
            ->assertSee(route('configuracion.index'))
            ->getContent();

        $this->assertMatchesRegularExpression('/2<span[^>]*>\s*\/ 3/', $html);
        $this->assertMatchesRegularExpression('/>\s*1\s*<\/p>\s*<p[^>]*>\s*Ver incidencias/', $html);
    }

    public function test_si_la_api_cae_el_panel_avisa_sin_romperse(): void
    {
        Http::fake(fn () => throw new ConnectionException('sin conexión'));

        $this->personal('admin')->get('/inicio')->assertOk()
            ->assertSee('Hola, Rosa Recepción')
            ->assertSee('No pudimos consultar parte de la información')
            ->assertSee('No disponible')
            ->assertDontSee('Sin solicitudes por aprobar');
    }

    public function test_un_error_500_de_un_endpoint_solo_marca_esa_tarjeta(): void
    {
        $this->fakeApi(['prestamos/activos*' => Http::response(['message' => 'fallo'], 500)]);

        $this->personal('encargado')->get('/inicio')->assertOk()
            ->assertSee('No pudimos consultar parte de la información')
            ->assertSee('Sin solicitudes por aprobar')
            ->assertSee('No disponible');
    }

    public function test_un_401_de_la_api_cierra_la_sesion(): void
    {
        $this->fakeApi(['prestamos/pendientes*' => Http::response([], 401)]);

        $this->personal('encargado')->get('/inicio')->assertRedirect(route('login'));
        $this->assertNull(session('auth_token'));
    }

    public function test_el_solicitante_ve_sus_prestamos_vigentes_con_el_retraso_resaltado(): void
    {
        $this->fakeApi([
            'prestamos*' => $this->lista([
                $this->prestamo(1, 'aprobado', now()->addDays(2)->toIso8601String(), 'Cámara'),
                $this->prestamo(2, 'entregado', now()->subDays(2)->toIso8601String(), 'Proyector'),
            ]),
        ]);

        $html = $this->withSession(['auth_token' => 't', 'user' => ['name' => 'Ana', 'roles' => ['estudiante']]])
            ->get('/inicio')->assertOk()
            ->assertSee('Mis préstamos vigentes')
            ->assertSee('2 días de retraso')
            ->assertSee('Tienes 1 préstamo retrasado')
            ->assertSee('Catálogo de equipos')
            ->assertDontSee('Solicitudes por aprobar')
            ->getContent();

        $this->assertLessThan(strpos($html, 'Cámara'), strpos($html, 'Proyector'));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'activos=1'));
    }

    public function test_el_solicitante_sin_prestamos_ve_el_estado_vacio(): void
    {
        $this->fakeApi([]);

        $this->withSession(['auth_token' => 't', 'user' => ['name' => 'Ana', 'roles' => ['estudiante']]])
            ->get('/inicio')->assertOk()
            ->assertSee('No tienes préstamos vigentes')
            ->assertSee('Catálogo de equipos');
    }

    public function test_el_solicitante_con_la_api_caida_ve_aviso_y_no_un_500(): void
    {
        Http::fake(fn () => throw new ConnectionException('sin conexión'));

        $this->withSession(['auth_token' => 't', 'user' => ['name' => 'Ana', 'roles' => ['estudiante']]])
            ->get('/inicio')->assertOk()
            ->assertSee('No pudimos consultar parte de la información')
            ->assertSee('No pudimos cargar tus préstamos')
            ->assertDontSee('No tienes préstamos vigentes');
    }

    public function test_la_raiz_redirige_al_login_sin_sesion(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_la_raiz_redirige_a_inicio_con_sesion(): void
    {
        $this->withSession([
            'auth_token' => 'token-vigente',
            'user' => ['name' => 'Ana Pérez', 'roles' => ['estudiante']],
        ])
            ->get('/')
            ->assertRedirect(route('inicio'));
    }
}
