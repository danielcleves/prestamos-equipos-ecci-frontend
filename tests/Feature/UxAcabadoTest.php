<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Acabado UX/UI de los hallazgos H-02…H-10 de EVD-030.
 *
 * Cubre lo verificable en el HTML servido: nombres accesibles, títulos,
 * etiquetas de filtros y las pistas de desplazamiento de las tablas móviles.
 */
class UxAcabadoTest extends TestCase
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
    private array $sesionUsuario = [
        'auth_token' => 'token-usuario',
        'user' => ['id' => 5, 'name' => 'Estudiante', 'roles' => ['estudiante']],
    ];

    private function fakeVacio(): void
    {
        Http::fake(['*' => Http::response(['data' => []], 200)]);
    }

    public function test_el_login_no_muestra_credenciales_demo_que_no_existen(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('Credenciales demo')
            ->assertDontSee('admin123');
    }

    public function test_los_botones_del_encabezado_tienen_nombre_accesible(): void
    {
        $this->fakeVacio();

        $this->withSession($this->sesionEncargado)
            ->get('/equipos')
            ->assertOk()
            ->assertSee('aria-label="Abrir menú"', false)
            ->assertSee('aria-label="Notificaciones"', false);
    }

    public function test_la_miga_superior_refleja_la_pantalla_de_solicitudes(): void
    {
        $this->fakeVacio();

        $this->withSession($this->sesionEncargado)
            ->get('/solicitudes')
            ->assertOk()
            ->assertSee('Solicitudes pendientes')
            ->assertDontSee('Gestión de equipos');
    }

    public function test_usuarios_muestra_titulo_de_pagina(): void
    {
        $this->fakeVacio();

        $this->withSession($this->sesionAdmin)
            ->get('/usuarios')
            ->assertOk()
            ->assertSee('Gestión de usuarios');
    }

    public function test_los_filtros_de_usuarios_tienen_etiqueta_accesible(): void
    {
        $this->fakeVacio();

        $this->withSession($this->sesionAdmin)
            ->get('/usuarios')
            ->assertOk()
            ->assertSee('aria-label="Filtrar por rol"', false)
            ->assertSee('aria-label="Filtrar por estado"', false);
    }

    public function test_las_fechas_de_solicitudes_tienen_label_asociado(): void
    {
        $this->fakeVacio();

        $this->withSession($this->sesionEncargado)
            ->get('/solicitudes')
            ->assertOk()
            ->assertSee('id="fecha-desde"', false)
            ->assertSee('id="fecha-hasta"', false)
            ->assertSee('for="fecha-desde"', false)
            ->assertSee('for="fecha-hasta"', false);
    }

    public function test_solicitudes_avisa_del_desplazamiento_de_la_tabla_en_movil(): void
    {
        $this->fakeVacio();

        $this->withSession($this->sesionEncargado)
            ->get('/solicitudes')
            ->assertOk()
            ->assertSee('Desliza la tabla para ver todas las columnas.');
    }

    public function test_la_miga_del_catalogo_del_estudiante_dice_catalogo_de_equipos(): void
    {
        $this->fakeVacio();

        $response = $this->withSession($this->sesionUsuario)
            ->get('/equipos')
            ->assertOk();

        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $nodos = $xpath->query('//header//span[contains(@class, "text-slate-700")]');

        $this->assertGreaterThan(0, $nodos->length);
        $this->assertStringContainsString('Catálogo de equipos', $nodos->item(0)->textContent);
    }

    public function test_configuracion_avisa_del_desplazamiento_de_la_tabla_de_restriccion(): void
    {
        $this->fakeVacio();

        $this->withSession($this->sesionAdmin)
            ->get('/configuracion')
            ->assertOk()
            ->assertSee('Desliza la tabla para cambiar el nivel de restricción.');
    }

    public function test_incidencias_avisa_del_desplazamiento_y_muestra_estado_en_movil(): void
    {
        Http::fake([
            '*' => Http::response(['data' => [[
                'id' => 5,
                'estado' => 'abierta',
                'descripcion' => 'Golpe en la carcasa',
                'restringe_hasta' => '2026-12-01T18:00:00-05:00',
                'solicitante' => ['name' => 'Laura Gómez', 'email' => 'laura@ecci.edu.co'],
                'equipo' => ['nombre' => 'Osciloscopio', 'codigo' => 'OSC-01'],
            ]]], 200),
        ]);

        $this->withSession($this->sesionEncargado)
            ->get('/incidencias')
            ->assertOk()
            ->assertSee('Desliza la tabla para ver estado y acciones.')
            ->assertSee('Restringido hasta');
    }

    /**
     * El velo de un modal es hijo del contenedor `space-y-*` de la página, que
     * reparte 24 px de margen entre hermanos; en un elemento `fixed` ese margen
     * encoge la caja y deja una franja del viewport sin oscurecer. Todas las
     * raíces `fixed inset-0` llevan `m-0` para que el velo mida el viewport.
     */
    public function test_los_velos_de_los_modales_ocupan_todo_el_viewport(): void
    {
        $vistas = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($archivo) => str_ends_with($archivo->getFilename(), '.blade.php'))
            ->reject(fn ($archivo) => str_starts_with($archivo->getRelativePathname(), 'layouts'));

        $velos = 0;

        foreach ($vistas as $vista) {
            preg_match_all('/class="([^"]*)"/', $vista->getContents(), $coincidencias);

            foreach ($coincidencias[1] as $clases) {
                if (! preg_match('/\bfixed\b/', $clases) || ! preg_match('/\binset-0\b/', $clases)) {
                    continue;
                }

                $velos++;

                $this->assertMatchesRegularExpression(
                    '/\bm-0\b/',
                    $clases,
                    $vista->getRelativePathname().' tiene un velo fijo sin «m-0»: el margen del contenedor «space-y-*» lo encoge y deja una franja del viewport sin oscurecer'
                );
            }
        }

        // 9 velos: usuarios (2), equipos (2), catálogo, entregas, devoluciones,
        // solicitudes y roles.
        $this->assertSame(9, $velos, 'Cambió el número de velos de modal: revisa que cada uno lleve «m-0»');
    }

    /**
     * El halo de foco (§13) es de 2 px y vive en app.css, compartido por los
     * layouts app y guest. Al ir fuera de todo @layer gana a las utilidades
     * `outline-none`/`focus:outline-none` de Tailwind sin `!important`; pero
     * en Chrome los campos de texto coinciden con :focus-visible también con
     * el ratón, así que app.js marca la modalidad y el halo solo se pinta con
     * teclado.
     */
    public function test_el_halo_de_foco_es_de_2_px_y_solo_con_teclado(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/a:focus-visible,\s*button:focus-visible,\s*input:focus-visible,\s*select:focus-visible,\s*textarea:focus-visible,\s*\[tabindex\]:focus-visible\s*\{\s*outline:\s*2px solid var\(--color-marca-700\);\s*outline-offset:\s*2px;/',
            $css,
            'app.css debe definir el halo de 2 px con el azul de la marca para enlaces, botones y campos'
        );

        $this->assertMatchesRegularExpression(
            "/html\[data-entrada='puntero'\]\s*:focus-visible\s*\{\s*outline:\s*0;/",
            $css,
            'app.css debe apagar el halo cuando el último gesto fue el ratón'
        );

        $js = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString("setAttribute('data-entrada', 'puntero')", $js);
        $this->assertStringContainsString("removeAttribute('data-entrada')", $js);

        // Una sola fuente: el layout no repite la regla ni el layout guest se queda sin ella.
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $guest = file_get_contents(resource_path('views/layouts/guest.blade.php'));

        $this->assertStringNotContainsString(':focus-visible', $layout);
        $this->assertStringContainsString("@vite(['resources/css/app.css', 'resources/js/app.js'])", $guest);
    }

    /**
     * El documento declara su idioma, que es lo que usan los navegadores para
     * las pistas de los campos nativos (p. ej. la fecha de /solicitudes).
     */
    public function test_los_layouts_declaran_el_idioma_es(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('<html lang="es">', false);

        $this->fakeVacio();

        $this->withSession($this->sesionAdmin)
            ->get('/solicitudes')
            ->assertOk()
            ->assertSee('<html lang="es"', false);
    }
}
