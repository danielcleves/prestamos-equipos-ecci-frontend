<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * La raíz redirige al login (los invitados no ven contenido protegido).
     */
    public function test_the_application_redirects_guests_to_the_login(): void
    {
        $response = $this->get('/');

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }
}
