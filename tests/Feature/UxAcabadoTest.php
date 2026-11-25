<?php

namespace Tests\Feature;

use App\Support\EstadoPildora;
use App\Support\FechaNegocio;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
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

    /**
     * A 390 px la tabla de solicitudes mide más de 1000 px y la píldora de estado
     * quedaba fuera del viewport; el aviso «Desliza la tabla…» no lo resolvía. En
     * móvil se pintan tarjetas con el estado y la tabla queda solo para escritorio.
     */
    public function test_solicitudes_muestra_tarjetas_en_movil_en_lugar_de_la_tabla(): void
    {
        Http::fake([
            '*/prestamos/pendientes*' => Http::response([
                'data' => [
                    [
                        'id' => 30,
                        'solicitante' => ['name' => 'Laura Gómez', 'email' => 'laura@ecci.edu.co'],
                        'equipo' => [
                            'nombre' => 'Fuente de Poder',
                            'codigo' => 'FUE-01',
                            'estado' => 'disponible',
                            'categoria' => ['nombre' => 'Electrónica'],
                        ],
                        'fecha_solicitud' => '2026-11-19T22:30:00-05:00',
                        'fecha_inicio' => '2026-11-25T08:00:00-05:00',
                        'fecha_devolucion_estimada' => '2026-11-28T18:00:00-05:00',
                        'estado' => 'solicitado',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->withSession($this->sesionEncargado)
            ->get('/solicitudes')
            ->assertOk();

        // El aviso de deslizar ya no aplica: en móvil no hay tabla que deslizar.
        $response->assertDontSee('Desliza la tabla para ver todas las columnas.');

        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $limpiar = fn (string $texto) => trim(preg_replace('/\s+/', ' ', $texto));

        // La tabla de escritorio está oculta en móvil y la tarjeta es solo móvil.
        $this->assertSame(
            1,
            $xpath->query('//div[contains(@class, "hidden md:block")]//table')->length,
            'La tabla debe quedar dentro de un contenedor «hidden md:block»'
        );

        $contenedor = $xpath->query('//div[contains(@class, "md:hidden divide-y")]');
        $this->assertSame(1, $contenedor->length, 'Debe existir una vista de tarjetas «md:hidden»');

        $tarjeta = $limpiar($contenedor->item(0)->textContent);

        foreach ([
            'Laura Gómez',
            'laura@ecci.edu.co',
            'Fuente de Poder',
            'FUE-01',
            'Solicitado',
            '19/11/2026',
            '25/11/2026',
            '28/11/2026',
            'Ver detalle',
        ] as $dato) {
            $this->assertStringContainsString($dato, $tarjeta, "La tarjeta móvil no muestra «{$dato}»");
        }

        // Orden: solicitante, equipo con su código, estado, fechas y acción.
        $orden = ['Laura Gómez', 'FUE-01', 'Solicitado', '19/11/2026', 'Ver detalle'];
        $posiciones = array_map(fn (string $dato) => mb_strpos($tarjeta, $dato), $orden);
        $this->assertSame(
            $posiciones,
            collect($posiciones)->sort()->values()->all(),
            'La tarjeta debe mostrar solicitante, equipo, estado, fechas y acción en ese orden'
        );

        // El estado usa el componente x-estado (misma píldora que la tabla).
        $pildora = EstadoPildora::clases('prestamo', 'solicitado')['pildora'];
        $this->assertSame(
            1,
            $xpath->query('.//span[contains(@class, "'.$pildora.'")]', $contenedor->item(0))->length,
            'La tarjeta debe pintar el estado con <x-estado>'
        );

        // La acción de la tarjeta es la misma de la fila: abre el modal de detalle.
        $botones = $xpath->query('.//button[contains(@class, "w-full")]', $contenedor->item(0));
        $this->assertSame(1, $botones->length, 'La tarjeta debe tener una acción a ancho completo');
        $this->assertStringContainsString('Ver detalle', $limpiar($botones->item(0)->textContent));

        // Las dos acciones (fila y tarjeta) llaman a abrirModal con los mismos
        // datos, así que el modal, Escape y el foco son los mismos.
        preg_match_all("/abrirModal\(JSON\.parse\('(\{.*?\})'\)\)/", $response->getContent(), $acciones);
        $this->assertCount(2, $acciones[1], 'Fila y tarjeta deben ofrecer «Ver detalle»');
        $this->assertSame($acciones[1][0], $acciones[1][1], 'Tarjeta y fila deben abrir el mismo detalle');

        // Un solo modal: con Escape y el foco en el diálogo.
        $response->assertSee('@keydown.escape.window="openModalDetalle = false"', false);
        $response->assertSee('x-ref="dialogoDetalleSolicitud"', false);
        $response->assertSee('$refs.dialogoDetalleSolicitud.focus()', false);
    }

    /**
     * El estado es el dato que se mira primero: su columna va justo después del
     * equipo y antes de las tres fechas. Al final de la tabla, en pantallas
     * medianas, quedaba tapada por la columna fija de acciones.
     */
    public function test_solicitudes_pone_el_estado_antes_de_las_fechas(): void
    {
        Http::fake([
            '*/prestamos/pendientes*' => Http::response([
                'data' => [
                    [
                        'id' => 30,
                        'solicitante' => ['name' => 'Laura Gómez', 'email' => 'laura@ecci.edu.co'],
                        'equipo' => [
                            'nombre' => 'Fuente de Poder',
                            'codigo' => 'FUE-01',
                            'estado' => 'disponible',
                            'categoria' => ['nombre' => 'Electrónica'],
                        ],
                        'fecha_solicitud' => '2026-11-19T22:30:00-05:00',
                        'fecha_inicio' => '2026-11-25T08:00:00-05:00',
                        'fecha_devolucion_estimada' => '2026-11-28T18:00:00-05:00',
                        'estado' => 'solicitado',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->withSession($this->sesionEncargado)
            ->get('/solicitudes')
            ->assertOk();

        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $limpiar = fn (string $texto) => trim(preg_replace('/\s+/', ' ', $texto));

        $encabezados = [];
        foreach ($xpath->query('//table/thead/tr/th') as $th) {
            $encabezados[] = $limpiar($th->textContent);
        }

        $this->assertSame(
            ['#', 'Solicitante', 'Equipo', 'Estado', 'F. Solicitud', 'F. Inicio', 'F. Devolución', 'Acciones'],
            $encabezados,
            '«Estado» debe ir después del equipo y antes de las fechas'
        );

        $celdas = [];
        foreach ($xpath->query('//table/tbody/tr[1]/td') as $td) {
            $celdas[] = $limpiar($td->textContent);
        }

        $this->assertCount(8, $celdas, 'La fila debe tener las mismas celdas que encabezados');
        $this->assertStringContainsString('Solicitado', $celdas[3], 'La cuarta celda es la píldora de estado');
        $this->assertSame('19/11/2026', $celdas[4]);
        $this->assertSame('25/11/2026', $celdas[5]);
        $this->assertSame('28/11/2026', $celdas[6]);
    }

    /**
     * A 1024 px la tabla de entregas mide 939 px y la píldora de estado quedaba
     * pegada a la columna fija «Acciones», que recortaba la última letra. Se
     * mueve «Estado» antes de las fechas y, como la tabla sigue sin caber en los
     * 702 px del contenedor, debajo de 1280 px se pintan las tarjetas.
     */
    public function test_entregas_pone_el_estado_antes_de_las_fechas(): void
    {
        $this->unaEntregaPendiente();

        $response = $this->withSession($this->sesionEncargado)
            ->get('/entregas-pendientes')
            ->assertOk();

        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $limpiar = fn (string $texto) => trim(preg_replace('/\s+/', ' ', $texto));

        $encabezados = [];
        foreach ($xpath->query('//table/thead/tr/th') as $th) {
            $encabezados[] = $limpiar($th->textContent);
        }

        $this->assertSame(
            ['Solicitante', 'Equipo', 'Estado', 'F. Préstamo', 'F. Devolución', 'Acciones'],
            $encabezados,
            '«Estado» debe ir después del equipo y antes de las fechas'
        );

        $celdas = [];
        foreach ($xpath->query('//table/tbody/tr[1]/td') as $td) {
            $celdas[] = $limpiar($td->textContent);
        }

        $this->assertCount(6, $celdas, 'La fila debe tener las mismas celdas que encabezados');
        $this->assertStringContainsString('Pendiente de entrega', $celdas[2], 'La tercera celda es la píldora de estado');
        $this->assertSame('20/11/2026', $celdas[3]);
        $this->assertSame('27/11/2026', $celdas[4]);
    }

    public function test_entregas_usa_tarjetas_por_debajo_de_1280(): void
    {
        $this->unaEntregaPendiente();

        $response = $this->withSession($this->sesionEncargado)
            ->get('/entregas-pendientes')
            ->assertOk();

        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $limpiar = fn (string $texto) => trim(preg_replace('/\s+/', ' ', $texto));

        // La tabla queda solo para escritorio ancho; las tarjetas cubren el resto.
        $this->assertSame(
            1,
            $xpath->query('//div[contains(@class, "hidden xl:block")]//table')->length,
            'La tabla debe quedar dentro de un contenedor «hidden xl:block»'
        );

        $contenedor = $xpath->query('//div[contains(@class, "xl:hidden")]');
        $this->assertSame(1, $contenedor->length, 'Debe existir una vista de tarjetas «xl:hidden»');

        $tarjeta = $limpiar($contenedor->item(0)->textContent);

        foreach (['Laura Gómez', 'Osciloscopio', 'Pendiente de entrega', '20/11/2026', 'Registrar entrega'] as $dato) {
            $this->assertStringContainsString($dato, $tarjeta, "La tarjeta no muestra «{$dato}»");
        }

        // El estado va en la primera línea de la tarjeta, junto al solicitante.
        $this->assertLessThan(
            mb_strpos($tarjeta, 'Osciloscopio'),
            mb_strpos($tarjeta, 'Pendiente'),
            'El estado debe verse antes que el equipo'
        );
    }

    /**
     * Una entrega pendiente (aprobada) con fechas, como la que sirve el backend.
     */
    private function unaEntregaPendiente(): void
    {
        Http::fake(['*' => Http::response(['data' => [[
            'id' => 4,
            'estado' => 'aprobado',
            'solicitante' => ['name' => 'Laura Gómez', 'email' => 'laura@ecci.edu.co'],
            'equipo' => ['nombre' => 'Osciloscopio', 'codigo' => 'OSC-01'],
            'fecha_inicio' => '2026-11-20 08:00:00',
            'fecha_devolucion_estimada' => '2026-11-27 18:00:00',
        ]]], 200)]);
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

    /**
     * Las siglas RN-0x son la numeración interna del backlog y quien configura
     * los parámetros no las conoce: los rótulos perdieron el «(RN-0x)». La
     * prueba falla si alguna sigla vuelve al texto que se pinta (los comentarios
     * Blade no cuentan).
     */
    public function test_la_configuracion_no_muestra_las_siglas_internas_de_las_reglas(): void
    {
        Http::fake([
            '*/configuracion' => Http::response(['data' => [
                'parametros' => [
                    'cupos.roles' => ['estudiante' => 3, 'encargado' => 5, 'admin' => 10],
                    'duracion.categorias' => ['1' => 7, '2' => 5],
                    'duracion.roles_extra' => ['estudiante' => 0, 'encargado' => 2, 'admin' => 3],
                    'mora.escala' => [['desde' => 1, 'hasta' => 2, 'dias_sancion' => 3]],
                    'mora.dias_bloqueo' => 1,
                    'danos.dias_restriccion' => 7,
                    'elegibilidad.requiere_capacitacion' => false,
                    'registro.correos_permitidos' => ['@ecci.edu.co'],
                ],
                'categorias' => [['id' => 1, 'nombre' => 'Portátil', 'nivel_restriccion' => 1]],
            ]], 200),
            '*/roles' => Http::response(['data' => [
                ['id' => 1, 'nombre' => 'estudiante', 'etiqueta' => 'Estudiante', 'cupo_prestamos' => 3, 'es_predeterminado' => true],
            ]], 200),
        ]);

        $configuracion = $this->withSession($this->sesionAdmin)
            ->get('/configuracion')
            ->assertOk()
            // Los rótulos siguen ahí, ahora sin el código de la regla.
            ->assertSee('Cupos por rol')
            ->assertSee('Duración máxima del préstamo')
            ->assertSee('Escala de mora')
            ->assertSee('Restricción por daños')
            ->assertSee('Elegibilidad')
            ->assertSee('Nivel de restricción por categoría');

        $roles = $this->withSession($this->sesionAdmin)
            ->get('/roles')
            ->assertOk()
            ->assertSee('Roles y cupos de préstamo');

        foreach (['/configuracion' => $configuracion, '/roles' => $roles] as $ruta => $response) {
            $dom = new \DOMDocument;
            @$dom->loadHTML($response->getContent());
            $xpath = new \DOMXPath($dom);

            // Solo el texto que se pinta: fuera scripts, estilos y comentarios HTML.
            $visible = '';
            foreach ($xpath->query('//body//text()[not(ancestor::script) and not(ancestor::style)]') as $nodo) {
                $visible .= ' '.$nodo->nodeValue;
            }

            $this->assertDoesNotMatchRegularExpression(
                '/RN-0\d/u',
                $visible,
                "La pantalla {$ruta} muestra las siglas internas de una regla en el texto visible"
            );
        }
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
     * Cerrar un modal (Escape o «×») dejaba el foco en <body>: el siguiente Tab
     * reiniciaba desde «Saltar al contenido». Los nueve diálogos montan la
     * misma directiva x-foco-modal, con el mismo estado que ya abre y cierra el
     * modal, para que el mecanismo sea uno solo y no se desincronice.
     */
    public function test_cada_modal_declara_la_devolucion_del_foco_con_su_mismo_estado(): void
    {
        $vistas = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($archivo) => str_ends_with($archivo->getFilename(), '.blade.php'))
            ->reject(fn ($archivo) => str_starts_with($archivo->getRelativePathname(), 'layouts'));

        $modales = 0;

        foreach ($vistas as $vista) {
            // Etiquetas de apertura <div …>: los atributos de Alpine llevan «>»
            // dentro del valor (`() => …`), así que no sirve `[^>]*`.
            preg_match_all('/<div\b(?:"[^"]*"|\'[^\']*\'|[^>"\'])*>/s', $vista->getContents(), $etiquetas);

            foreach ($etiquetas[0] as $etiqueta) {
                if (! str_contains($etiqueta, 'role="dialog"')) {
                    continue;
                }

                $modales++;
                $archivo = $vista->getRelativePathname();

                $this->assertMatchesRegularExpression(
                    '/\sx-foco-modal="open[A-Za-z]+"/',
                    $etiqueta,
                    "El diálogo de {$archivo} no declara x-foco-modal: al cerrarlo el foco se perdería"
                );

                preg_match('/x-foco-modal="([^"]+)"/', $etiqueta, $foco);
                preg_match('/x-effect="([^"\s]+)/', $etiqueta, $efecto);

                $this->assertSame(
                    $efecto[1],
                    $foco[1],
                    "El diálogo de {$archivo} observa «{$foco[1]}» pero se abre con «{$efecto[1]}»"
                );
            }
        }

        // usuarios (2), equipos (2), catálogo, entregas, devoluciones,
        // solicitudes y roles.
        $this->assertSame(9, $modales, 'Cambió el número de diálogos: revisa que cada uno devuelva el foco');
    }

    public function test_el_mecanismo_de_foco_cae_en_el_contenido_cuando_el_disparador_desaparece(): void
    {
        $js = file_get_contents(resource_path('js/app.js'));

        // La lógica es una sola y vive en app.js: directiva de Alpine + respaldo.
        $this->assertStringContainsString("window.Alpine.directive('foco-modal'", $js);
        $this->assertStringContainsString('disparadorDelDialogo', $js);
        $this->assertStringContainsString('devolverFoco', $js);

        // Si la fila que abrió el modal ya no existe, el foco va al mismo
        // destino que «Saltar al contenido», que es enfocable (tabindex="-1").
        $this->assertStringContainsString("const ID_CONTENIDO = 'contenido-principal';", $js);
        $this->assertStringContainsString('getElementById(ID_CONTENIDO)', $js);

        // Tras enviar un formulario del modal la página se recarga: el foco
        // vuelve al contenido en lugar de quedarse en <body>.
        $this->assertStringContainsString('sessionStorage.setItem(CLAVE_RETORNO', $js);
        $this->assertStringContainsString('sessionStorage.getItem(CLAVE_RETORNO)', $js);

        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertMatchesRegularExpression(
            '/<main id="contenido-principal" tabindex="-1"/',
            $layout,
            'El respaldo necesita un <main> enfocable con el id de «Saltar al contenido»'
        );
    }

    public function test_las_pantallas_con_modales_sirven_la_directiva_de_foco(): void
    {
        $this->fakeVacio();

        foreach (['/usuarios', '/equipos', '/equipos?vista=catalogo', '/roles', '/entregas-pendientes', '/devoluciones', '/solicitudes'] as $url) {
            $response = $this->withSession($this->sesionAdmin)->get($url)->assertOk();

            $this->assertSame(
                substr_count($response->getContent(), 'role="dialog"'),
                substr_count($response->getContent(), 'x-foco-modal='),
                "Cada diálogo de {$url} debe devolver el foco al cerrarse"
            );
        }
    }

    /**
     * Un modal sin ciclo de foco no atrapa la atención: con Tab desde el último
     * control el foco salía al menú lateral, detrás del velo, y el usuario
     * dejaba de saber en qué diálogo estaba. La directiva x-foco-modal cierra
     * el ciclo: Tab en la última parada vuelve a la primera y Shift+Tab en la
     * primera salta a la última. Medido en Chromium con dos vueltas completas
     * en usuarios, entregas y devoluciones: cero salidas del diálogo.
     *
     * Aquí se comprueba la lógica y sus bordes sobre app.js, que es donde vive
     * el mecanismo (una sola fuente para los nueve diálogos); la prueba de
     * navegador no forma parte de la suite porque el proyecto no instala Dusk.
     */
    public function test_el_dialogo_atrapa_el_foco_con_tab_mientras_esta_abierto(): void
    {
        $js = file_get_contents(resource_path('js/app.js'));

        // Paradas reales: enlaces con href, controles habilitados y tabindex
        // de recorrido; el `-1` del propio diálogo no es una parada.
        foreach ([
            'a[href]',
            'button:not([disabled])',
            'input:not([disabled])',
            'select',
            'textarea',
            '[tabindex]:not([tabindex="-1"])',
        ] as $selector) {
            $this->assertStringContainsString(
                $selector,
                $js,
                "El recorrido del diálogo debe considerar «{$selector}»"
            );
        }

        // Lo oculto y lo apagado no cuentan aunque esté en el HTML.
        $this->assertStringContainsString('!elemento.disabled', $js);
        $this->assertStringContainsString("!elemento.closest('[hidden]')", $js);
        $this->assertStringContainsString('getClientRects().length > 0', $js);

        // La decisión del ciclo: Tab en la última va a la primera…
        $this->assertMatchesRegularExpression(
            '/if \(!evento\.shiftKey && activo === ultima\) \{\s*evento\.preventDefault\(\);\s*primera\.focus\(\);/',
            $js,
            'Tab desde la última parada debe volver a la primera'
        );

        // …y Shift+Tab en la primera va a la última.
        $this->assertMatchesRegularExpression(
            '/if \(evento\.shiftKey && activo === primera\) \{\s*evento\.preventDefault\(\);\s*ultima\.focus\(\);/',
            $js,
            'Shift+Tab desde la primera parada debe saltar a la última'
        );

        // Borde: el foco entra por el extremo que toca cuando está en el
        // contenedor (tabindex="-1") o se perdió fuera del diálogo.
        $this->assertMatchesRegularExpression(
            '/if \(!el\.contains\(activo\) \|\| activo === el\) \{\s*evento\.preventDefault\(\);\s*\(evento\.shiftKey \? ultima : primera\)\.focus\(\);/',
            $js
        );

        // Borde: un diálogo sin paradas no deja escapar la tecla.
        $this->assertMatchesRegularExpression(
            '/if \(paradas\.length === 0\) \{\s*evento\.preventDefault\(\);/',
            $js
        );

        // Cerrado no se atrapa nada: el guardia es el mismo estado que abre el
        // modal y el listener se retira al desmontarse la directiva.
        $this->assertStringContainsString(
            "if (evento.key !== 'Tab' || !estabaAbierto) return;",
            $js,
            'Con el diálogo cerrado el Tab no debe quedar atrapado'
        );
        $this->assertStringContainsString("el.addEventListener('keydown', alTeclear)", $js);
        $this->assertStringContainsString("el.removeEventListener('keydown', alTeclear)", $js);
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

    /**
     * A 1024 px la tabla de devoluciones mide 953 px, desborda el contenedor y
     * la columna fija «Acciones» tapaba la píldora de estado por completo. Se
     * mueve «Estado» antes de las fechas y el encabezado fijo deja de ser
     * translúcido: con bg-slate-50/70 se veía el rótulo «ESTADO» por debajo.
     */
    public function test_devoluciones_pone_el_estado_antes_de_las_fechas(): void
    {
        $this->travelTo(Carbon::parse('2026-11-25 10:00', FechaNegocio::ZONA));
        $this->unaDevolucionPendiente();

        $response = $this->withSession($this->sesionEncargado)
            ->get('/devoluciones')
            ->assertOk();

        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $limpiar = fn (string $texto) => trim(preg_replace('/\s+/', ' ', $texto));

        $encabezados = [];
        foreach ($xpath->query('(//table)[1]/thead/tr/th') as $th) {
            $encabezados[] = $limpiar($th->textContent);
        }

        $this->assertSame(
            ['Solicitante', 'Equipo', 'Estado', 'F. Entrega', 'F. Devolución Esperada', 'Acciones'],
            $encabezados,
            '«Estado» debe ir después del equipo y antes de las fechas'
        );

        $celdas = [];
        foreach ($xpath->query('(//table)[1]/tbody/tr[1]/td') as $td) {
            $celdas[] = $limpiar($td->textContent);
        }

        $this->assertCount(6, $celdas, 'La fila debe tener las mismas celdas que encabezados');
        $this->assertStringContainsString('Entregado', $celdas[2], 'La tercera celda es la píldora de estado');
        $this->assertSame('20/11/2026', $celdas[3]);
        $this->assertSame('27/11/2026', $celdas[4]);

        // El <th> fijo no puede ser translúcido o deja ver el rótulo de debajo.
        $thAcciones = $xpath->query('(//table)[1]/thead/tr/th[last()]')->item(0);
        $this->assertMatchesRegularExpression('/\bbg-slate-50\b/', $thAcciones->getAttribute('class'));
        $this->assertDoesNotMatchRegularExpression('/bg-slate-50\/\d+/', $thAcciones->getAttribute('class'));
    }

    /**
     * La tabla no cabe entre 1024 y 1279 px: debajo de 1280 px se pintan
     * tarjetas con el estado en la primera línea y la acción a ancho completo.
     */
    public function test_devoluciones_usa_tarjetas_por_debajo_de_1280(): void
    {
        $this->travelTo(Carbon::parse('2026-11-25 10:00', FechaNegocio::ZONA));
        $this->unaDevolucionPendiente();

        $response = $this->withSession($this->sesionEncargado)
            ->get('/devoluciones')
            ->assertOk();

        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $limpiar = fn (string $texto) => trim(preg_replace('/\s+/', ' ', $texto));

        $this->assertSame(
            1,
            $xpath->query('//div[contains(@class, "hidden xl:block")]//table')->length,
            'La tabla debe quedar dentro de un contenedor «hidden xl:block»'
        );

        $contenedor = $xpath->query('//div[contains(@class, "xl:hidden")]');
        $this->assertSame(1, $contenedor->length, 'Debe existir una vista de tarjetas «xl:hidden»');

        $tarjeta = $limpiar($contenedor->item(0)->textContent);

        foreach (['Emmanuel Valencia', 'TAB-002', 'Entregado', '20/11/2026', '27/11/2026', 'Registrar devolución'] as $dato) {
            $this->assertStringContainsString($dato, $tarjeta, "La tarjeta no muestra «{$dato}»");
        }

        // El estado va en la primera línea, antes del equipo.
        $this->assertLessThan(
            mb_strpos($tarjeta, 'TAB-002'),
            mb_strpos($tarjeta, 'Entregado'),
            'El estado debe verse antes que el equipo'
        );

        // La acción de la tarjeta ocupa todo el ancho y abre el mismo modal.
        $botones = $xpath->query('.//button[contains(@class, "w-full")]', $contenedor->item(0));
        $this->assertSame(1, $botones->length, 'La tarjeta debe tener una acción a ancho completo');
        $this->assertStringContainsString('Registrar devolución', $limpiar($botones->item(0)->textContent));

        preg_match_all("/abrirModal\(JSON\.parse\('(\{.*?\})'\)\)/", $response->getContent(), $acciones);
        $this->assertCount(2, $acciones[1], 'Fila y tarjeta deben ofrecer «Registrar devolución»');
        $this->assertSame($acciones[1][0], $acciones[1][1], 'Tarjeta y fila deben abrir la misma devolución');
    }

    /**
     * KAN-121: en plazo, la píldora dice «Entregado» y no hay aviso de mora.
     */
    public function test_devoluciones_en_plazo_se_ve_entregado_sin_aviso_de_mora(): void
    {
        $this->travelTo(Carbon::parse('2026-11-25 10:00', FechaNegocio::ZONA));
        $this->unaDevolucionPendiente('2026-11-27T18:00:00-05:00');

        $response = $this->withSession($this->sesionEncargado)
            ->get('/devoluciones')
            ->assertOk()
            ->assertSee('Emmanuel Valencia')
            ->assertSee('Entregado')
            ->assertDontSee('Retrasado');

        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $fila = trim(preg_replace('/\s+/', ' ', (new \DOMXPath($dom))->query('//table/tbody/tr[1]')->item(0)->textContent));

        $this->assertStringNotContainsString('retraso', mb_strtolower($fila), 'En plazo no debe verse ningún aviso de mora');
    }

    /**
     * Un día de mora: la píldora cambia a «Retrasado» y se dice cuántos días son.
     */
    public function test_devoluciones_marca_un_dia_de_retraso(): void
    {
        // Venció a las 23:30 de Bogotá del día anterior: un solo día calendario.
        $this->travelTo(Carbon::parse('2026-11-25 00:30', FechaNegocio::ZONA));
        $this->unaDevolucionPendiente('2026-11-24T23:30:00-05:00');

        $response = $this->withSession($this->sesionEncargado)
            ->get('/devoluciones')
            ->assertOk()
            ->assertSee('Retrasado')
            ->assertSee('1 día de retraso')
            ->assertDontSee('2 días de retraso');

        // El modal de la préstamo atrasado lleva el aviso, con el número en singular.
        $response->assertSee('Este préstamo lleva', false);
        $this->assertStringContainsString(
            'selectedPrestamo.dias_retraso === 1 ? \'1 día de retraso\' : selectedPrestamo.dias_retraso + \' días de retraso\'',
            $response->getContent()
        );
    }

    /**
     * N días de mora contados en calendario de Bogotá: vence el 24 a las 23:30 y
     * se consulta el 27 a las 00:30 → 3 días, no los 2 que saldrían en UTC.
     */
    public function test_devoluciones_muestra_los_dias_de_retraso_en_calendario_de_bogota(): void
    {
        $this->travelTo(Carbon::parse('2026-11-27 00:30', FechaNegocio::ZONA));
        $this->unaDevolucionPendiente('2026-11-24T23:30:00-05:00');

        $this->withSession($this->sesionEncargado)
            ->get('/devoluciones')
            ->assertOk()
            ->assertSee('Retrasado')
            ->assertSee('3 días de retraso')
            ->assertDontSee('2 días de retraso')
            ->assertSee('bg-rose-50/30', false);
    }

    public function test_el_umbral_de_mora_se_cuenta_en_dias_calendario_de_bogota(): void
    {
        $ahora = CarbonImmutable::parse('2026-11-27 00:30', FechaNegocio::ZONA);

        // 0 días: en plazo, sin fecha o un préstamo que aún no se entregó.
        $this->assertSame(0, FechaNegocio::diasDeRetraso('entregado', '2026-11-27T23:30:00-05:00', $ahora));
        $this->assertSame(0, FechaNegocio::diasDeRetraso('entregado', null, $ahora));
        $this->assertSame(0, FechaNegocio::diasDeRetraso('aprobado', '2026-11-20T23:30:00-05:00', $ahora));

        // 1 día: venció ayer en Bogotá aunque en UTC ya sea hoy.
        $this->assertSame(1, FechaNegocio::diasDeRetraso('entregado', '2026-11-26T23:30:00-05:00', $ahora));

        // N días: tres días calendario de Bogotá (en UTC serían dos).
        $this->assertSame(3, FechaNegocio::diasDeRetraso('entregado', '2026-11-24T23:30:00-05:00', $ahora));

        // Vencido por horas del mismo día: ya es mora, mínimo 1 día.
        $this->assertSame(1, FechaNegocio::diasDeRetraso('entregado', '2026-11-27T00:00:00-05:00', $ahora));
    }

    /**
     * Un préstamo entregado pendiente de devolución, como lo sirve el backend.
     */
    private function unaDevolucionPendiente(string $fechaEstimada = '2026-11-27T18:00:00-05:00'): void
    {
        Http::fake(['*' => Http::response(['data' => [[
            'id' => 12,
            'estado' => 'entregado',
            'solicitante' => ['name' => 'Emmanuel Valencia', 'email' => 'emmanuel@ecci.edu.co'],
            'equipo' => ['nombre' => 'Tablet Wacom', 'codigo' => 'TAB-002'],
            'fecha_entrega_real' => '2026-11-20T08:00:00-05:00',
            'fecha_devolucion_estimada' => $fechaEstimada,
        ]]], 200)]);
    }
}
