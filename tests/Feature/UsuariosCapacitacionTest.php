<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * RN-01 · capacitación del usuario por categoría de equipo.
 *
 * Estar capacitado para un router no habilita un servidor: el administrador
 * marca, al crear o editar un usuario, las categorías en que está capacitado y
 * la lista viaja al backend como `capacitaciones` (ids de categoría) en
 * POST /api/usuarios y PUT /api/usuarios/{usuario}. En la edición se envía
 * siempre la lista completa, también [] al desmarcar todo.
 */
class UsuariosCapacitacionTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $sesionAdmin = [
        'auth_token' => 'token-admin-test',
        'user' => ['id' => 1, 'name' => 'Admin ECCI', 'roles' => ['admin']],
    ];

    /** @return array<string, mixed> */
    private function payloadAlta(array $sobrescribir = []): array
    {
        return array_merge([
            'name' => 'Nuevo Docente',
            'email' => 'docente@ecci.edu.co',
            'password' => 'secreta123',
            'role' => 'estudiante',
            'capacitaciones_enviadas' => '1',
        ], $sobrescribir);
    }

    /** @return array<string, mixed> */
    private function payloadEdicion(array $sobrescribir = []): array
    {
        return array_merge([
            'name' => 'Docente Editado',
            'email' => 'editado@ecci.edu.co',
            'role' => 'estudiante',
            'capacitaciones_enviadas' => '1',
        ], $sobrescribir);
    }

    /**
     * Listado de usuarios, catálogo de roles y configuración con las categorías.
     *
     * @param  list<array<string, mixed>>  $usuarios
     */
    private function fakePantalla(array $usuarios = [], ?array $categorias = null): void
    {
        Http::fake([
            '*/usuarios*' => Http::response(['data' => $usuarios, 'meta' => ['total' => count($usuarios), 'last_page' => 1]], 200),
            '*/roles' => Http::response(['data' => []], 200),
            '*/configuracion' => Http::response(['data' => [
                'parametros' => [],
                'categorias' => $categorias ?? [
                    ['id' => 1, 'nombre' => 'Portátil', 'nivel_restriccion' => 0, 'requiere_capacitacion' => false],
                    ['id' => 4, 'nombre' => 'Router', 'nivel_restriccion' => 0, 'requiere_capacitacion' => true],
                    ['id' => 5, 'nombre' => 'Servidor', 'nivel_restriccion' => 1, 'requiere_capacitacion' => true],
                ],
            ]], 200),
        ]);
    }

    public function test_el_alta_muestra_todas_las_categorias_sin_marcar_y_las_que_exigen_capacitacion(): void
    {
        $this->fakePantalla();

        $html = $this->withSession($this->sesionAdmin)
            ->get('/usuarios')
            ->assertOk()
            ->assertSee('Capacitaciones por categoría')
            ->assertDontSee('Capacitado para usar los equipos')
            ->assertDontSee('name="capacitado"', false)
            ->getContent();

        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        // Grupo accesible: fieldset con legend, una casilla por categoría.
        $this->assertSame(2, $xpath->query('//fieldset[legend[normalize-space()="Capacitaciones por categoría"]]')->length);
        $alta = $xpath->query('//input[starts-with(@id, "capacitacion-alta-")]');
        $this->assertSame(3, $alta->length);

        // Nadie arranca capacitado.
        foreach ($alta as $casilla) {
            $this->assertFalse($casilla->hasAttribute('checked'));
            $this->assertSame('capacitaciones[]', $casilla->getAttribute('name'));
        }

        // Solo Router y Servidor llevan la etiqueta «Exige capacitación» (× 2 modales).
        $this->assertSame(4, substr_count($html, 'Exige capacitación'));
        $this->assertSame(2, $xpath->query('//input[@type="hidden"][@name="capacitaciones_enviadas"]')->length);
    }

    public function test_el_alta_envia_las_categorias_marcadas(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 9]], 201)]);

        $this->withSession($this->sesionAdmin)
            ->post('/usuarios', $this->payloadAlta(['capacitaciones' => ['4', '5']]))
            ->assertRedirect(route('usuarios.index'));

        Http::assertSent(fn ($request) => $request->method() === 'POST' &&
            str_ends_with($request->url(), '/usuarios') &&
            $request['capacitaciones'] === [4, 5] &&
            ! isset($request['capacitado'])
        );
    }

    public function test_el_alta_sin_marcar_ninguna_envia_la_lista_vacia(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 9]], 201)]);

        $this->withSession($this->sesionAdmin)
            ->post('/usuarios', $this->payloadAlta())
            ->assertRedirect(route('usuarios.index'));

        Http::assertSent(fn ($request) => $request['capacitaciones'] === []);
    }

    public function test_la_edicion_envia_las_categorias_marcadas(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 7]], 200)]);

        $this->withSession($this->sesionAdmin)
            ->put('/usuarios/7', $this->payloadEdicion(['capacitaciones' => ['1', '4']]))
            ->assertRedirect(route('usuarios.index'));

        Http::assertSent(fn ($request) => $request->method() === 'PUT' &&
            str_ends_with($request->url(), '/usuarios/7') &&
            $request['capacitaciones'] === [1, 4] &&
            ! isset($request['capacitado'])
        );
    }

    public function test_la_edicion_al_desmarcar_todo_envia_la_lista_vacia(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 7]], 200)]);

        // Sin casillas marcadas el navegador no envía `capacitaciones[]`.
        $this->withSession($this->sesionAdmin)
            ->put('/usuarios/7', $this->payloadEdicion())
            ->assertRedirect(route('usuarios.index'));

        Http::assertSent(fn ($request) => $request->method() === 'PUT' &&
            array_key_exists('capacitaciones', $request->data()) &&
            $request['capacitaciones'] === []
        );
    }

    public function test_la_edicion_sin_el_grupo_en_pantalla_no_toca_las_capacitaciones(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 7]], 200)]);

        $this->withSession($this->sesionAdmin)
            ->put('/usuarios/7', $this->payloadEdicion(['capacitaciones_enviadas' => null]))
            ->assertRedirect(route('usuarios.index'));

        Http::assertSent(fn ($request) => $request->method() === 'PUT' &&
            ! array_key_exists('capacitaciones', $request->data())
        );
    }

    public function test_capacitaciones_invalidas_no_llegan_al_backend(): void
    {
        foreach ([
            ['capacitaciones' => 'router'],
            ['capacitaciones' => ['abc']],
            ['capacitaciones' => ['0']],
            ['capacitaciones' => ['4', '4']],
        ] as $invalido) {
            $this->withSession($this->sesionAdmin)
                ->put('/usuarios/7', $this->payloadEdicion($invalido))
                ->assertSessionHasErrors();
        }

        Http::assertNothingSent();
    }

    public function test_la_tabla_muestra_las_categorias_en_que_esta_capacitado(): void
    {
        $this->fakePantalla([
            ['id' => 3, 'name' => 'Ana Redes', 'email' => 'ana@ecci.edu.co', 'roles' => ['estudiante'], 'is_active' => true,
                'capacitaciones' => [
                    ['categoria_id' => 4, 'nombre' => 'Router', 'otorgada_en' => '2026-11-20T10:00:00-05:00'],
                    ['categoria_id' => 5, 'nombre' => 'Servidor', 'otorgada_en' => '2026-11-21T10:00:00-05:00'],
                ]],
            ['id' => 4, 'name' => 'Beto Sin Curso', 'email' => 'beto@ecci.edu.co', 'roles' => ['estudiante'], 'is_active' => true,
                'capacitaciones' => []],
        ]);

        $html = $this->withSession($this->sesionAdmin)->get('/usuarios')->assertOk()->getContent();

        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $texto = fn (string $consulta) => trim(preg_replace('/\s+/u', ' ', $xpath->query($consulta)->item(0)->textContent));

        // Tabla (≥768 px) y tarjeta móvil dicen lo mismo.
        $this->assertSame('Capacitado en: Router, Servidor', $texto('//*[@data-capacitaciones="3"]'));
        $this->assertSame('Capacitado en: Router, Servidor', $texto('//*[@data-capacitaciones-movil="3"]'));
        $this->assertSame('Sin capacitaciones', $texto('//*[@data-capacitaciones="4"]'));
        $this->assertSame('Sin capacitaciones', $texto('//*[@data-capacitaciones-movil="4"]'));

        // La píldora booleana desapareció.
        $this->assertStringNotContainsString('Sin capacitación<', $html);
    }

    public function test_el_modal_de_edicion_recibe_los_ids_de_las_capacitaciones(): void
    {
        $this->fakePantalla([
            ['id' => 3, 'name' => 'Ana Redes', 'email' => 'ana@ecci.edu.co', 'roles' => ['estudiante'], 'is_active' => true,
                'capacitaciones' => [['categoria_id' => 4, 'nombre' => 'Router', 'otorgada_en' => '2026-11-20T10:00:00-05:00']]],
        ]);

        $this->withSession($this->sesionAdmin)
            ->get('/usuarios')
            ->assertOk()
            // @js() serializa el arreglo de ids; la casilla de edición se enlaza con x-model.
            ->assertSee('\u0022capacitaciones\u0022:[4]', false)
            ->assertSee('x-model="editUser.capacitaciones"', false);
    }

    public function test_un_usuario_sin_la_clave_capacitaciones_se_ve_sin_capacitaciones(): void
    {
        $this->fakePantalla([
            ['id' => 8, 'name' => 'Carla Antigua', 'email' => 'carla@ecci.edu.co', 'roles' => ['estudiante'], 'is_active' => true],
        ]);

        $this->withSession($this->sesionAdmin)
            ->get('/usuarios')
            ->assertOk()
            ->assertSee('Sin capacitaciones');
    }

    public function test_si_la_configuracion_falla_se_avisa_y_se_conservan_las_categorias_de_los_usuarios(): void
    {
        Http::fake([
            '*/usuarios*' => Http::response(['data' => [
                ['id' => 3, 'name' => 'Ana Redes', 'email' => 'ana@ecci.edu.co', 'roles' => ['estudiante'], 'is_active' => true,
                    'capacitaciones' => [['categoria_id' => 4, 'nombre' => 'Router', 'otorgada_en' => '2026-11-20T10:00:00-05:00']]],
            ], 'meta' => ['total' => 1, 'last_page' => 1]], 200),
            '*/roles' => Http::response(['data' => []], 200),
            '*/configuracion' => Http::response(['message' => 'Error interno.'], 500),
        ]);

        $this->withSession($this->sesionAdmin)
            ->get('/usuarios')
            ->assertOk()
            ->assertSee('No fue posible cargar todas las categorías')
            // Router sigue en el grupo: editar a Ana no le quita la capacitación.
            ->assertSee('id="capacitacion-edicion-4"', false)
            ->assertViewHas('categorias', fn ($categorias) => array_column($categorias, 'id') === [4]);
    }

    public function test_sin_conexion_ni_categorias_el_grupo_no_se_envia(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/configuracion')) {
                throw new ConnectionException('backend caído');
            }

            return Http::response(['data' => [], 'meta' => ['total' => 0, 'last_page' => 1]], 200);
        });

        $this->withSession($this->sesionAdmin)
            ->get('/usuarios')
            ->assertOk()
            ->assertSee('No hay categorías registradas en el inventario.')
            // Sin categorías no se pinta el marcador: la edición no borraría nada.
            ->assertDontSee('name="capacitaciones_enviadas"', false);
    }
}
