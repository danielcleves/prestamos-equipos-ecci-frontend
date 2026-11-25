<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClienteRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\Support\ErroresDeSesion;
use Tests\TestCase;

/**
 * RN-02 (rediseño) — Roles personalizados con cupo de préstamos (KAN-291).
 *
 * Cubre la pantalla /roles (solo administrador): acceso por rol, listado,
 * alta, edición, borrado y manejo de errores del backend; además el impacto
 * del catálogo dinámico de roles en la barra lateral, en Usuarios y en
 * Configuración (días extra RN-06 por cada rol).
 *
 * Casos TC-FRONT-107 … TC-FRONT-143.
 */
class RolesWebTest extends TestCase
{
    use ErroresDeSesion;

    /** @return array<string, mixed> */
    private function sesion(string $rol): array
    {
        return [
            'auth_token' => 'token-'.$rol,
            'user' => ['id' => 1, 'name' => 'Persona '.$rol, 'roles' => [$rol]],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function catalogo(): array
    {
        return [
            ['id' => 1, 'nombre' => 'admin', 'etiqueta' => 'Administrador', 'descripcion' => null, 'cupo_prestamos' => 10, 'es_sistema' => true, 'es_predeterminado' => false, 'usuarios_count' => 1],
            ['id' => 2, 'nombre' => 'encargado', 'etiqueta' => 'Personal de préstamo', 'descripcion' => null, 'cupo_prestamos' => 5, 'es_sistema' => true, 'es_predeterminado' => false, 'usuarios_count' => 2],
            ['id' => 3, 'nombre' => 'estudiante', 'etiqueta' => 'Estudiante', 'descripcion' => 'Pregrado', 'cupo_prestamos' => 3, 'es_sistema' => false, 'es_predeterminado' => true, 'usuarios_count' => 40],
            ['id' => 4, 'nombre' => 'profesor', 'etiqueta' => 'Profesor', 'descripcion' => 'Docentes de planta', 'cupo_prestamos' => 2, 'es_sistema' => false, 'es_predeterminado' => false, 'usuarios_count' => 0],
        ];
    }

    /** @return array<string, mixed> */
    private function formularioRol(array $sobrescribir = []): array
    {
        return array_merge([
            'etiqueta' => 'Profesor',
            'descripcion' => 'Docentes de planta',
            'cupo_prestamos' => '2',
        ], $sobrescribir);
    }

    private function fakeCreado(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 4, 'nombre' => 'profesor', 'etiqueta' => 'Profesor']], 201)]);
    }

    // ── Acceso ─────────────────────────────────────────────────────────────

    /** TC-FRONT-107 · RN-02: sin sesión /roles redirige al login y no consulta el backend. */
    public function test_roles_sin_sesion_redirige_al_login(): void
    {
        Http::fake();

        $this->get('/roles')->assertRedirect(route('login'));

        Http::assertNothingSent();
    }

    /** TC-FRONT-108 · RN-02: el estudiante no entra a /roles; vuelve a inicio con el aviso. */
    public function test_estudiante_no_puede_ver_roles(): void
    {
        Http::fake();

        $this->withSession($this->sesion('estudiante'))
            ->get('/roles')
            ->assertRedirect(route('inicio'))
            ->assertSessionHasErrors(['inicio' => 'Esta sección es exclusiva para administradores.']);

        Http::assertNothingSent();
    }

    /** TC-FRONT-109 · RN-02: el encargado (rol de sistema no admin) tampoco entra a /roles. */
    public function test_encargado_no_puede_ver_roles(): void
    {
        Http::fake();

        $this->withSession($this->sesion('encargado'))
            ->get('/roles')
            ->assertRedirect(route('inicio'))
            ->assertSessionHasErrors('inicio');

        Http::assertNothingSent();
    }

    /** TC-FRONT-110 · RN-02 (seguridad): un no admin no puede crear, editar ni borrar roles enviando el formulario a mano. */
    public function test_no_admin_no_puede_crear_editar_ni_borrar_roles(): void
    {
        Http::fake();

        foreach (['estudiante', 'encargado', 'profesor'] as $rol) {
            $this->withSession($this->sesion($rol))->post('/roles', $this->formularioRol())
                ->assertRedirect(route('inicio'));
            $this->withSession($this->sesion($rol))->put('/roles/4', $this->formularioRol())
                ->assertRedirect(route('inicio'));
            $this->withSession($this->sesion($rol))->delete('/roles/4')
                ->assertRedirect(route('inicio'));
        }

        Http::assertNothingSent();
    }

    // ── Listado ────────────────────────────────────────────────────────────

    /** TC-FRONT-111 · RN-02: el admin ve los roles del backend, incluido el personalizado «Profesor», con cupo, usuarios y badges. */
    public function test_admin_lista_los_roles_con_los_personalizados(): void
    {
        Http::fake(['*/roles' => Http::response(['data' => $this->catalogo()], 200)]);

        $response = $this->withSession($this->sesion('admin'))->get('/roles');

        $response->assertOk();
        $response->assertSee('Roles y cupos de préstamo');
        $response->assertSee('Profesor');
        $response->assertSee('Docentes de planta');
        $response->assertSee('Predeterminado');
        $response->assertSee('Sistema');
        // Los de solicitante van primero, con el predeterminado a la cabeza.
        $response->assertViewHas('roles', fn ($roles) => array_column($roles, 'nombre') === ['estudiante', 'profesor', 'admin', 'encargado']);
        $response->assertSeeInOrder(['Estudiante', 'Profesor', 'Administrador', 'Personal de préstamo']);

        Http::assertSent(fn (ClienteRequest $r) => $r->method() === 'GET'
            && str_ends_with($r->url(), '/roles')
            && $r->hasHeader('Authorization', 'Bearer token-admin'));
    }

    /** TC-FRONT-112 · RN-02: solo se ofrece «Eliminar» para roles de solicitante que no son el predeterminado. */
    public function test_eliminar_solo_se_ofrece_para_roles_borrables(): void
    {
        Http::fake(['*/roles' => Http::response(['data' => $this->catalogo()], 200)]);

        $response = $this->withSession($this->sesion('admin'))->get('/roles');

        $response->assertOk();
        $response->assertSee('action="'.route('roles.destroy', 4).'"', false);
        foreach ([1, 2, 3] as $id) {
            $response->assertDontSee('action="'.route('roles.destroy', $id).'"', false);
        }
    }

    /** TC-FRONT-113 · RN-02 (seguridad): la etiqueta de un rol se escapa en el listado. */
    public function test_la_etiqueta_del_rol_se_escapa_en_el_listado(): void
    {
        $catalogo = $this->catalogo();
        $catalogo[3]['etiqueta'] = '<script>alert(1)</script>';
        Http::fake(['*/roles' => Http::response(['data' => $catalogo], 200)]);

        $response = $this->withSession($this->sesion('admin'))->get('/roles');

        $response->assertOk();
        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    /** TC-FRONT-114 · RN-02: si el backend responde 500, la pantalla muestra su mensaje y los roles de respaldo no se inventan como reales. */
    public function test_listado_muestra_el_error_del_backend(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Roles no disponibles.'], 500)]);

        $this->withSession($this->sesion('admin'))
            ->get('/roles')
            ->assertOk()
            ->assertSee('Roles no disponibles.')
            ->assertSee('No hay roles para mostrar.');
    }

    /** TC-FRONT-115 · RN-02: con el backend caído la pantalla avisa en lugar de romperse. */
    public function test_listado_avisa_si_no_hay_conexion(): void
    {
        Http::fake(fn () => throw new ConnectionException('backend caído'));

        $this->withSession($this->sesion('admin'))
            ->get('/roles')
            ->assertOk()
            ->assertSee('No fue posible conectar con el servicio de roles.');
    }

    /** TC-FRONT-116 · RN-02: con el token vencido (401) se limpia la sesión y se va al login. */
    public function test_listado_con_sesion_expirada_redirige_al_login(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

        $this->withSession($this->sesion('admin'))
            ->get('/roles')
            ->assertRedirect(route('login'))
            ->assertSessionMissing('auth_token');
    }

    // ── Alta ───────────────────────────────────────────────────────────────

    /** TC-FRONT-117 · RN-02 (válido): crear «Profesor» con cupo 2 envía el payload esperado al backend. */
    public function test_admin_crea_un_rol_y_envia_el_payload(): void
    {
        $this->fakeCreado();

        $this->withSession($this->sesion('admin'))
            ->post('/roles', $this->formularioRol())
            ->assertRedirect(route('roles.index'))
            ->assertSessionHas('success', 'Rol «Profesor» creado correctamente.');

        Http::assertSent(fn (ClienteRequest $r) => $r->method() === 'POST'
            && str_ends_with($r->url(), '/roles')
            && $r['etiqueta'] === 'Profesor'
            && $r['descripcion'] === 'Docentes de planta'
            && $r['cupo_prestamos'] === 2
            && $r['es_predeterminado'] === false);
    }

    /** TC-FRONT-118 · RN-02: marcar «predeterminado» en el alta envía es_predeterminado = true. */
    public function test_alta_con_predeterminado_lo_envia_en_true(): void
    {
        $this->fakeCreado();

        $this->withSession($this->sesion('admin'))
            ->post('/roles', $this->formularioRol(['es_predeterminado' => '1']))
            ->assertRedirect(route('roles.index'));

        Http::assertSent(fn (ClienteRequest $r) => $r['es_predeterminado'] === true);
    }

    /** TC-FRONT-119 · RN-02 (límite): una etiqueta de 3 caracteres y cupo 0 son válidos. */
    public function test_alta_acepta_los_limites_inferiores(): void
    {
        $this->fakeCreado();

        $this->withSession($this->sesion('admin'))
            ->post('/roles', $this->formularioRol(['etiqueta' => 'Tec', 'cupo_prestamos' => '0']))
            ->assertRedirect(route('roles.index'))
            ->assertSessionHasNoErrors();

        Http::assertSent(fn (ClienteRequest $r) => $r['etiqueta'] === 'Tec' && $r['cupo_prestamos'] === 0);
    }

    /** TC-FRONT-120 · RN-02 (límite): cupo 1000 y etiqueta de 60 caracteres son válidos. */
    public function test_alta_acepta_los_limites_superiores(): void
    {
        $this->fakeCreado();
        $etiqueta = str_repeat('a', 60);

        $this->withSession($this->sesion('admin'))
            ->post('/roles', $this->formularioRol(['etiqueta' => $etiqueta, 'cupo_prestamos' => '1000']))
            ->assertSessionHasNoErrors();

        Http::assertSent(fn (ClienteRequest $r) => $r['etiqueta'] === $etiqueta && $r['cupo_prestamos'] === 1000);
    }

    /** TC-FRONT-121 · RN-02 (inválido): una etiqueta de 2 caracteres se rechaza sin llamar al backend. */
    public function test_alta_rechaza_una_etiqueta_corta(): void
    {
        Http::fake();

        $this->withSession($this->sesion('admin'))
            ->from('/roles')
            ->post('/roles', $this->formularioRol(['etiqueta' => 'Ab']))
            ->assertRedirect('/roles')
            ->assertSessionHasErrors(['etiqueta' => 'El nombre del rol debe tener al menos 3 caracteres.']);

        Http::assertNothingSent();
    }

    /** TC-FRONT-122 · RN-02 (inválido): una etiqueta de 61 caracteres o vacía se rechaza sin llamar al backend. */
    public function test_alta_rechaza_una_etiqueta_larga_o_vacia(): void
    {
        Http::fake();

        $this->withSession($this->sesion('admin'))
            ->post('/roles', $this->formularioRol(['etiqueta' => str_repeat('a', 61)]))
            ->assertSessionHasErrors(['etiqueta' => 'El nombre del rol no puede superar 60 caracteres.']);

        $this->withSession($this->sesion('admin'))
            ->post('/roles', $this->formularioRol(['etiqueta' => '   ']))
            ->assertSessionHasErrors(['etiqueta' => 'El nombre del rol es obligatorio.']);

        Http::assertNothingSent();
    }

    /** TC-FRONT-123 · RN-02 (inválido/límite): cupo -1 se rechaza sin llamar al backend. */
    public function test_alta_rechaza_un_cupo_negativo(): void
    {
        Http::fake();

        $this->withSession($this->sesion('admin'))
            ->post('/roles', $this->formularioRol(['cupo_prestamos' => '-1']))
            ->assertSessionHasErrors(['cupo_prestamos' => 'El cupo no puede ser negativo.']);

        Http::assertNothingSent();
    }

    /** TC-FRONT-124 · RN-02 (inválido/límite): cupo 1001 se rechaza sin llamar al backend. */
    public function test_alta_rechaza_un_cupo_mayor_a_1000(): void
    {
        Http::fake();

        $this->withSession($this->sesion('admin'))
            ->post('/roles', $this->formularioRol(['cupo_prestamos' => '1001']))
            ->assertSessionHasErrors(['cupo_prestamos' => 'El cupo no puede superar 1000.']);

        Http::assertNothingSent();
    }

    /** TC-FRONT-125 · RN-02 (inválido): un cupo no entero o ausente se rechaza. */
    public function test_alta_rechaza_un_cupo_no_entero_o_ausente(): void
    {
        Http::fake();

        $this->withSession($this->sesion('admin'))
            ->post('/roles', $this->formularioRol(['cupo_prestamos' => '2.5']))
            ->assertSessionHasErrors(['cupo_prestamos' => 'El cupo debe ser un número entero.']);

        $this->withSession($this->sesion('admin'))
            ->post('/roles', ['etiqueta' => 'Profesor'])
            ->assertSessionHasErrors(['cupo_prestamos' => 'El cupo de préstamos es obligatorio.']);

        Http::assertNothingSent();
    }

    /** TC-FRONT-126 · RN-02: el 422 del backend (rol duplicado) se muestra en rol_error y se conserva lo escrito. */
    public function test_alta_muestra_el_error_422_del_backend(): void
    {
        Http::fake(['*/roles' => Http::response([
            'message' => 'The given data was invalid.',
            'errors' => ['etiqueta' => ['Ya existe un rol con ese nombre.']],
        ], 422)]);

        $response = $this->withSession($this->sesion('admin'))
            ->from('/roles')
            ->post('/roles', $this->formularioRol());

        $response->assertRedirect('/roles');
        $response->assertSessionHasErrors(['rol_error' => 'Ya existe un rol con ese nombre.']);
        $response->assertSessionHasInput('etiqueta', 'Profesor');
    }

    /** TC-FRONT-127 · RN-02: la pantalla pinta el rol_error que deja la redirección. */
    public function test_la_pantalla_pinta_el_error_del_backend(): void
    {
        session($this->sesion('admin'));
        $errores = (new ViewErrorBag)->put('default', new MessageBag(['rol_error' => ['Ya existe un rol con ese nombre.']]));

        $this->view('roles.index', [
            'roles' => $this->catalogo(),
            'errorConexion' => null,
            'errors' => $errores,
        ])->assertSee('Ya existe un rol con ese nombre.');
    }

    // ── Edición ────────────────────────────────────────────────────────────

    /** TC-FRONT-128 · RN-02 (válido): editar el cupo envía PATCH /roles/{id} sin es_predeterminado si no se marca. */
    public function test_editar_envia_patch_con_el_cupo_y_sin_predeterminado(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 4]], 200)]);

        $this->withSession($this->sesion('admin'))
            ->put('/roles/4', $this->formularioRol(['cupo_prestamos' => '5']))
            ->assertRedirect(route('roles.index'))
            ->assertSessionHas('success', 'Rol actualizado correctamente.');

        Http::assertSent(function (ClienteRequest $r) {
            return $r->method() === 'PATCH'
                && str_ends_with($r->url(), '/roles/4')
                && $r['cupo_prestamos'] === 5
                && $r['etiqueta'] === 'Profesor'
                && ! array_key_exists('es_predeterminado', $r->data());
        });
    }

    /** TC-FRONT-129 · RN-02: marcar «predeterminado» en la edición sí envía es_predeterminado = true. */
    public function test_editar_con_predeterminado_lo_envia(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 4]], 200)]);

        $this->withSession($this->sesion('admin'))
            ->put('/roles/4', $this->formularioRol(['es_predeterminado' => '1']))
            ->assertRedirect(route('roles.index'));

        Http::assertSent(fn (ClienteRequest $r) => $r->method() === 'PATCH' && $r['es_predeterminado'] === true);
    }

    /** TC-FRONT-130 · RN-02 (inválido): desmarcar (es_predeterminado=0) no se envía como false al backend. */
    public function test_editar_con_predeterminado_en_cero_no_lo_envia(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 3]], 200)]);

        $this->withSession($this->sesion('admin'))
            ->put('/roles/3', $this->formularioRol(['es_predeterminado' => '0']))
            ->assertRedirect(route('roles.index'));

        Http::assertSent(fn (ClienteRequest $r) => ! array_key_exists('es_predeterminado', $r->data()));
    }

    /** TC-FRONT-131 · RN-02 (inválido): editar con cupo 1001 o -1 no llega al backend. */
    public function test_editar_rechaza_cupos_fuera_de_rango(): void
    {
        Http::fake();

        $this->withSession($this->sesion('admin'))
            ->put('/roles/4', $this->formularioRol(['cupo_prestamos' => '1001']))
            ->assertSessionHasErrors('cupo_prestamos');
        $this->withSession($this->sesion('admin'))
            ->put('/roles/4', $this->formularioRol(['cupo_prestamos' => '-1']))
            ->assertSessionHasErrors('cupo_prestamos');

        Http::assertNothingSent();
    }

    /** TC-FRONT-132 · RN-02: el 422 del backend al editar (p. ej. quitar el predeterminado) se muestra. */
    public function test_editar_muestra_el_error_422_del_backend(): void
    {
        Http::fake(['*' => Http::response([
            'message' => 'No puedes quitar el rol predeterminado.',
            'errors' => ['es_predeterminado' => ['No puedes quitar el rol predeterminado.']],
        ], 422)]);

        $this->withSession($this->sesion('admin'))
            ->from('/roles')
            ->put('/roles/3', $this->formularioRol())
            ->assertRedirect('/roles')
            ->assertSessionHasErrors(['rol_error' => 'No puedes quitar el rol predeterminado.']);
    }

    /** TC-FRONT-133 · RN-02 (inválido): un id no numérico en la ruta da 404 y no llama al backend. */
    public function test_editar_o_borrar_con_id_no_numerico_da_404(): void
    {
        Http::fake();

        $this->withSession($this->sesion('admin'))->put('/roles/abc', $this->formularioRol())->assertNotFound();
        $this->withSession($this->sesion('admin'))->delete('/roles/abc')->assertNotFound();

        Http::assertNothingSent();
    }

    // ── Borrado ────────────────────────────────────────────────────────────

    /** TC-FRONT-134 · RN-02 (válido): borrar un rol sin usuarios envía DELETE /roles/{id}. */
    public function test_borrar_un_rol_envia_delete(): void
    {
        Http::fake(['*' => Http::response(null, 204)]);

        $this->withSession($this->sesion('admin'))
            ->delete('/roles/4')
            ->assertRedirect(route('roles.index'))
            ->assertSessionHas('success', 'Rol eliminado correctamente.');

        Http::assertSent(fn (ClienteRequest $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/roles/4'));
    }

    /** TC-FRONT-135 · RN-02 (inválido): borrar un rol con usuarios muestra el aviso del backend (422 con message). */
    public function test_borrar_un_rol_con_usuarios_muestra_el_aviso(): void
    {
        Http::fake(['*' => Http::response([
            'message' => 'El rol tiene 3 usuario(s) asignado(s). Cámbiales el rol antes de eliminarlo.',
        ], 422)]);

        $this->withSession($this->sesion('admin'))
            ->from('/roles')
            ->delete('/roles/4')
            ->assertRedirect('/roles')
            ->assertSessionHasErrors(['rol_error' => 'El rol tiene 3 usuario(s) asignado(s). Cámbiales el rol antes de eliminarlo.']);
    }

    /** TC-FRONT-136 · RN-02: con el backend caído al borrar se avisa. */
    public function test_borrar_con_conexion_caida_avisa(): void
    {
        Http::fake(fn () => throw new ConnectionException('backend caído'));

        $this->withSession($this->sesion('admin'))
            ->from('/roles')
            ->delete('/roles/4')
            ->assertRedirect('/roles')
            ->assertSessionHasErrors(['rol_error' => 'No fue posible conectar con el servicio de roles.']);
    }

    // ── Barra lateral ──────────────────────────────────────────────────────

    /** TC-FRONT-137 · RN-02: solo el admin ve el enlace «Roles y cupos» en la barra lateral. */
    public function test_solo_el_admin_ve_el_enlace_roles_y_cupos(): void
    {
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $this->withSession($this->sesion('admin'))
            ->get('/equipos')
            ->assertOk()
            ->assertSee('Roles y cupos')
            ->assertSee('href="'.route('roles.index').'"', false);

        foreach (['encargado', 'estudiante', 'profesor'] as $rol) {
            $this->withSession($this->sesion($rol))
                ->get('/equipos')
                ->assertOk()
                ->assertDontSee('Roles y cupos')
                ->assertDontSee('href="'.route('roles.index').'"', false);
        }
    }

    // ── Usuarios ───────────────────────────────────────────────────────────

    /** TC-FRONT-138 · RN-02: Usuarios ofrece «Profesor» en los selects/filtro y pinta su etiqueta en la tabla. */
    public function test_usuarios_muestra_el_rol_personalizado(): void
    {
        Http::fake(function (ClienteRequest $r) {
            if (str_ends_with(parse_url($r->url(), PHP_URL_PATH), '/roles')) {
                return Http::response(['data' => $this->catalogo()], 200);
            }

            return Http::response([
                'data' => [
                    ['id' => 7, 'name' => 'Pablo Profe', 'email' => 'pablo@ecci.edu.co', 'roles' => ['profesor'], 'rol_principal' => 'profesor', 'rol_etiqueta' => 'Profesor', 'is_active' => true],
                ],
                'meta' => ['total' => 1, 'last_page' => 1],
            ], 200);
        });

        $response = $this->withSession($this->sesion('admin'))->get('/usuarios');

        $response->assertOk();
        $response->assertSee('Pablo Profe');
        // Opción en el filtro y en los selects de alta/edición.
        $html = $response->getContent();
        $this->assertGreaterThanOrEqual(3, substr_count($html, '<option value="profesor"'));
        // Etiqueta visible en la fila (no «Solicitante» genérico).
        $response->assertSeeInOrder(['Pablo Profe', 'Profesor']);
        // El alta propone por defecto el rol predeterminado (estudiante).
        $response->assertSee('<option value="estudiante" selected>', false);
    }

    /** TC-FRONT-139 · RN-02: el alta de usuario acepta un rol personalizado y rechaza nombres técnicos inválidos. */
    public function test_crear_usuario_con_rol_personalizado(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 9]], 201)]);

        $base = ['name' => 'Pablo Profe', 'email' => 'pablo@ecci.edu.co', 'password' => 'secreta123'];

        $this->withSession($this->sesion('admin'))
            ->post('/usuarios', $base + ['role' => 'profesor'])
            ->assertSessionHasNoErrors();

        Http::assertSent(fn (ClienteRequest $r) => $r->method() === 'POST' && $r['role'] === 'profesor');

        $this->withSession($this->sesion('admin'))
            ->post('/usuarios', $base + ['role' => 'Profesor <b>'])
            ->assertSessionHasErrors('role');
    }

    /** TC-FRONT-140 · RN-02: si /api/roles falla, Usuarios sigue funcionando con los tres roles iniciales. */
    public function test_usuarios_usa_los_roles_de_respaldo_si_el_catalogo_falla(): void
    {
        Http::fake(function (ClienteRequest $r) {
            if (str_ends_with(parse_url($r->url(), PHP_URL_PATH), '/roles')) {
                return Http::response(['message' => 'error'], 500);
            }

            return Http::response(['data' => [], 'meta' => ['total' => 0, 'last_page' => 1]], 200);
        });

        $response = $this->withSession($this->sesion('admin'))->get('/usuarios');

        $response->assertOk();
        $response->assertViewHas('roles', fn ($roles) => array_column($roles, 'nombre') === ['estudiante', 'encargado', 'admin']);
        $response->assertDontSee('<option value="profesor"', false);
    }

    // ── Configuración ──────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function configuracionConRoles(): array
    {
        return [
            'data' => [
                'parametros' => [
                    'duracion.categorias' => ['1' => 7],
                    'duracion.roles_extra' => ['estudiante' => 0, 'encargado' => 2, 'admin' => 3, 'profesor' => 4],
                    'mora.dias_bloqueo' => 2,
                    'danos.dias_restriccion' => 5,
                ],
                'categorias' => [['id' => 1, 'nombre' => 'Portátil', 'nivel_restriccion' => 0]],
                'roles' => $this->catalogo(),
            ],
        ];
    }

    /** TC-FRONT-141 · RN-02/RN-06: Configuración pinta duracion_extra[profesor] con su valor y el cupo del rol en solo lectura. */
    public function test_configuracion_pinta_los_dias_extra_del_rol_personalizado(): void
    {
        Http::fake(['*/configuracion' => Http::response($this->configuracionConRoles(), 200)]);

        $response = $this->withSession($this->sesion('admin'))->get('/configuracion');

        $response->assertOk();
        $response->assertSee('name="duracion_extra[profesor]"', false);
        $response->assertSee('Días extra · Profesor');
        $response->assertViewHas('parametros', fn ($p) => $p['duracion.roles_extra']['profesor'] === 4
            && $p['cupos.roles']['profesor'] === 2);
        $response->assertDontSee('name="cupos[', false);
    }

    /** TC-FRONT-142 · RN-06: guardar Configuración envía los días extra del rol personalizado. */
    public function test_configuracion_guarda_los_dias_extra_del_rol_personalizado(): void
    {
        Http::fake(['*' => Http::response($this->configuracionConRoles(), 200)]);

        $this->withSession($this->sesion('admin'))
            ->put('/configuracion', [
                'duracion_categorias' => [1 => 7],
                'duracion_extra' => ['estudiante' => 0, 'encargado' => 2, 'admin' => 3, 'profesor' => 6],
                'mora_dias_bloqueo' => 2,
                'danos_dias_restriccion' => 5,
            ])
            ->assertSessionHasNoErrors();

        Http::assertSent(fn (ClienteRequest $r) => $r->method() === 'PUT'
            && ($r->data()['duracion.roles_extra']['profesor'] ?? null) === 6
            && ! array_key_exists('cupos.roles', $r->data()));
    }

    /** TC-FRONT-143 · RN-06 (inválido): días extra negativos para el rol personalizado se rechazan sin llamar al backend. */
    public function test_configuracion_rechaza_dias_extra_negativos_del_rol_personalizado(): void
    {
        Http::fake();

        $this->withSession($this->sesion('admin'))
            ->put('/configuracion', [
                'duracion_categorias' => [1 => 7],
                'duracion_extra' => ['estudiante' => 0, 'profesor' => -1],
                'mora_dias_bloqueo' => 2,
                'danos_dias_restriccion' => 5,
            ])
            ->assertSessionHasErrors(['duracion_extra.profesor' => 'Los días extra por rol no pueden ser negativos.']);

        Http::assertNothingSent();
    }
}
