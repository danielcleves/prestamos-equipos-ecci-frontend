<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * BUG-0012 (EVD-034, caso `c3`): la confirmación de la solicitud y los errores de
 * validación no se veían porque los avisos vivían solo en `equipos.index`, mientras
 * que `PrestamoWebController::store()` redirige a `equipos.index?vista=catalogo`.
 *
 * El bloque se extrajo a `equipos._alertas` y lo pintan las dos vistas; aquí se prueba
 * el resultado visible para el estudiante y que la vista de gestión no lo perdió.
 */
class AvisosCatalogoTest extends TestCase
{
    /** @return array<string, mixed> */
    private function sesion(string $rol): array
    {
        return [
            'auth_token' => 'token-'.$rol,
            'user' => ['id' => 5, 'name' => 'Persona '.$rol, 'roles' => [$rol]],
        ];
    }

    private function urlCatalogo(): string
    {
        return route('equipos.index', ['vista' => 'catalogo']);
    }

    /** @return array<string, mixed> */
    private function payload(array $sobrescribir = []): array
    {
        return array_merge([
            'equipo_id' => 1,
            'fecha_inicio' => now()->format('Y-m-d'),
            'fecha_devolucion' => now()->addDays(2)->format('Y-m-d'),
            'motivo' => 'Práctica de laboratorio.',
        ], $sobrescribir);
    }

    /** @return array<string, mixed> */
    private function inventario(): array
    {
        return [
            'data' => [
                ['id' => 1, 'codigo' => 'EQ-001', 'nombre' => 'Osciloscopio', 'estado' => 'disponible', 'categoria' => ['nombre' => 'Electrónica']],
            ],
        ];
    }

    public function test_solicitud_valida_redirige_al_catalogo_con_el_aviso_de_exito(): void
    {
        Http::fake(['*/prestamos' => Http::response(['data' => ['id' => 9]], 201)]);

        $this->withSession($this->sesion('estudiante'))
            ->post('/prestamos/solicitar', $this->payload())
            ->assertRedirect($this->urlCatalogo())
            ->assertSessionHas('success', fn ($valor) => str_contains($valor, 'Solicitud de préstamo registrada con éxito'));
    }

    public function test_el_catalogo_muestra_el_aviso_de_exito_de_la_solicitud(): void
    {
        Http::fake([
            '*/prestamos' => Http::response(['data' => ['id' => 9]], 201),
            '*' => Http::response($this->inventario(), 200),
        ]);

        $this->withSession($this->sesion('estudiante'))
            ->followingRedirects()
            ->post('/prestamos/solicitar', $this->payload())
            ->assertOk()
            ->assertViewIs('equipos.catalogo')
            ->assertSee('¡Solicitud de préstamo registrada con éxito!');
    }

    public function test_el_catalogo_muestra_el_motivo_cuando_la_solicitud_no_es_valida(): void
    {
        Http::fake(['*' => Http::response($this->inventario(), 200)]);

        $this->withSession($this->sesion('estudiante'))
            ->followingRedirects()
            ->from($this->urlCatalogo())
            ->post('/prestamos/solicitar', $this->payload(['motivo' => '']))
            ->assertOk()
            ->assertViewIs('equipos.catalogo')
            ->assertSee('El motivo del préstamo es obligatorio.');
    }

    public function test_la_vista_de_gestion_sigue_mostrando_el_aviso_de_equipo_registrado(): void
    {
        Http::fake([
            '*/equipos' => Http::response(['data' => ['id' => 10]], 201),
            '*' => Http::response($this->inventario(), 200),
        ]);

        $this->withSession($this->sesion('admin'))
            ->followingRedirects()
            ->post(route('equipos.store'), [
                'codigo' => 'CP-TEST-01',
                'nombre' => 'Portátil de Prueba',
                'categoria_id' => 1,
                'descripcion' => 'Prueba de avisos',
                'observaciones' => null,
            ])
            ->assertOk()
            ->assertViewIs('equipos.index')
            ->assertSee('Equipo registrado exitosamente.');
    }

    public function test_el_catalogo_sin_avisos_no_pinta_el_bloque_de_error(): void
    {
        Http::fake(['*' => Http::response($this->inventario(), 200)]);

        $this->withSession($this->sesion('estudiante'))
            ->get($this->urlCatalogo())
            ->assertOk()
            ->assertDontSee('bg-rose-50');
    }
}
