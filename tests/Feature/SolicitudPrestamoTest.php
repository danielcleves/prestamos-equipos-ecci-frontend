<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SolicitudPrestamoTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $authSession = [
        'auth_token' => 'token-de-prueba',
        'user' => ['name' => 'Admin ECCI', 'roles' => ['admin']],
    ];

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->post('/prestamos/solicitar', $this->payload())->assertRedirect('/login');
    }

    public function test_fecha_devolucion_de_mas_de_7_dias_no_produce_error_500(): void
    {
        $this->withSession($this->authSession)
            ->from('/prestamos/solicitar/1')
            ->post('/prestamos/solicitar', $this->payload([
                'fecha_inicio' => now()->format('Y-m-d'),
                'fecha_devolucion' => now()->addDays(10)->format('Y-m-d'),
            ]))
            ->assertRedirect('/prestamos/solicitar/1')
            ->assertSessionHasErrors('fecha_devolucion');
    }

    public function test_fecha_devolucion_dentro_del_limite_se_envia_al_backend(): void
    {
        Http::fake([
            '*/prestamos' => Http::response(['data' => ['id' => 1]], 201),
        ]);

        $this->withSession($this->authSession)
            ->post('/prestamos/solicitar', $this->payload([
                'fecha_inicio' => now()->format('Y-m-d'),
                'fecha_devolucion' => now()->addDays(3)->format('Y-m-d'),
            ]))
            ->assertRedirect(route('equipos.index', ['vista' => 'catalogo']));

        Http::assertSent(fn ($request) => $request->url() === config('services.backend.url').'/prestamos');
    }

    public function test_fecha_devolucion_exactamente_a_7_dias_es_aceptada(): void
    {
        Http::fake([
            '*/prestamos' => Http::response(['data' => ['id' => 1]], 201),
        ]);

        $this->withSession($this->authSession)
            ->post('/prestamos/solicitar', $this->payload([
                'fecha_inicio' => now()->format('Y-m-d'),
                'fecha_devolucion' => now()->addDays(7)->format('Y-m-d'),
            ]))
            ->assertRedirect(route('equipos.index', ['vista' => 'catalogo']));

        Http::assertSent(fn ($request) => $request->url() === config('services.backend.url').'/prestamos');
    }

    /** @return array<string, mixed> */
    private function payload(array $sobrescribir = []): array
    {
        return array_merge([
            'equipo_id' => 1,
            'fecha_inicio' => now()->format('Y-m-d'),
            'fecha_devolucion' => now()->addDays(3)->format('Y-m-d'),
            'motivo' => 'Préstamo para la clase de redes.',
        ], $sobrescribir);
    }
}
