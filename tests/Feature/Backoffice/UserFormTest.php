<?php

namespace Tests\Feature\Backoffice;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La pantalla de usuarios del backoffice, contra una API fingida.
 *
 * Lo que se comprueba es que cada gesto de la interfaz acabe en la llamada que
 * le corresponde —y con el payload que le corresponde—, porque la pantalla no
 * toca Eloquent: todo lo hace por HTTP contra nuestra propia API.
 */
class UserFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function empleado(): User
    {
        return User::factory()->create()->assignRole('agente');
    }

    private function superadmin(): User
    {
        return User::factory()->create()->assignRole('superadministrador');
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function fingirApi(array $filas = []): void
    {
        Http::fake(function (Request $request) use ($filas) {
            if ($request->method() === 'GET') {
                return Http::response([
                    'data' => $filas,
                    'current_page' => 1,
                    'last_page' => 1,
                    'total' => count($filas),
                    'from' => $filas === [] ? null : 1,
                    'to' => $filas === [] ? null : count($filas),
                ]);
            }

            return Http::response(['id' => 1, 'name' => 'Ana Agente'], 201);
        });
    }

    public function test_el_listado_pide_los_usuarios_con_el_buscador(): void
    {
        $this->fingirApi();

        Livewire::actingAs($this->empleado())
            ->test('backoffice.users')
            ->set('search', 'ana');

        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && str_contains($request->url(), '/api/users')
            && str_contains($request->url(), 'search=ana')
            && str_contains($request->url(), 'page=1'));
    }

    public function test_el_alta_manda_la_cuenta_completa(): void
    {
        $this->fingirApi();

        Livewire::actingAs($this->superadmin())
            ->test('backoffice.users')
            ->call('create')
            ->set('name', 'Ana Agente')
            ->set('email', '  ana@tracely.test  ')
            ->set('password', 'contrasena-larga')
            ->set('company_id', 3)
            ->call('save');

        Http::assertSent(function (Request $request) {
            if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/api/users')) {
                return false;
            }

            $data = $request->data();

            return $data['name'] === 'Ana Agente'
                // El email va recortado: un espacio pegado al teclear no debe
                // convertirse en una cuenta distinta.
                && $data['email'] === 'ana@tracely.test'
                && $data['password'] === 'contrasena-larga'
                && $data['company_id'] === 3;
        });
    }

    public function test_la_edicion_sin_contrasena_nueva_no_la_manda(): void
    {
        Http::fake([
            '*/api/users/7' => Http::response([
                'id' => 7,
                'name' => 'Ana Agente',
                'email' => 'ana@tracely.test',
                'company_id' => 2,
            ]),
            '*' => Http::response(['data' => [], 'current_page' => 1, 'last_page' => 1, 'total' => 0]),
        ]);

        Livewire::actingAs($this->superadmin())
            ->test('backoffice.users')
            ->call('edit', 7)
            ->assertSet('name', 'Ana Agente')
            ->assertSet('email', 'ana@tracely.test')
            ->assertSet('company_id', 2)
            ->set('name', 'Ana Corregida')
            ->call('save');

        Http::assertSent(function (Request $request) {
            if ($request->method() !== 'PUT') {
                return false;
            }

            // La regla de la API es `sometimes`: omitirla deja la contraseña
            // intacta, mandarla vacía la rompería.
            return ! array_key_exists('password', $request->data())
                && $request->data()['name'] === 'Ana Corregida';
        });
    }

    public function test_la_edicion_con_contrasena_nueva_si_la_manda(): void
    {
        Http::fake([
            '*/api/users/7' => Http::response(['id' => 7, 'name' => 'Ana', 'email' => 'ana@tracely.test']),
            '*' => Http::response(['data' => [], 'current_page' => 1, 'last_page' => 1, 'total' => 0]),
        ]);

        Livewire::actingAs($this->superadmin())
            ->test('backoffice.users')
            ->call('edit', 7)
            ->set('password', 'otra-contrasena-larga')
            ->call('save');

        Http::assertSent(fn (Request $request) => $request->method() !== 'PUT'
            || $request->data()['password'] === 'otra-contrasena-larga');
    }

    public function test_dejar_la_empresa_vacia_la_manda_a_null(): void
    {
        $this->fingirApi();

        Livewire::actingAs($this->superadmin())
            ->test('backoffice.users')
            ->call('create')
            ->set('name', 'Ana Agente')
            ->set('email', 'ana@tracely.test')
            ->set('password', 'contrasena-larga')
            ->call('save');

        // `company_id` viaja siempre, también vacío: así es como se desvincula
        // una cuenta de su empresa.
        Http::assertSent(fn (Request $request) => $request->method() !== 'POST'
            || (array_key_exists('company_id', $request->data()) && $request->data()['company_id'] === null));
    }

    public function test_un_agente_no_pide_la_lista_de_empresas(): void
    {
        $this->fingirApi();

        Livewire::actingAs($this->empleado())
            ->test('backoffice.users')
            ->call('create')
            ->assertSet('companies', [])
            ->assertDontSee('Sin empresa');

        // `ver empresas` es solo de superadministrador: preguntar era gastar un
        // 403 seguro para acabar sin desplegable igualmente.
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/api/companies'));
    }

    public function test_un_superadministrador_si_pide_la_lista_de_empresas(): void
    {
        Http::fake([
            '*/api/companies*' => Http::response([
                'data' => [['id' => 2, 'name' => 'Transportes del Norte']],
                'current_page' => 1,
                'last_page' => 1,
                'total' => 1,
            ]),
            '*' => Http::response(['data' => [], 'current_page' => 1, 'last_page' => 1, 'total' => 0]),
        ]);

        Livewire::actingAs($this->superadmin())
            ->test('backoffice.users')
            ->call('create')
            ->assertSee('Transportes del Norte');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/api/companies'));
    }

    public function test_los_errores_de_validacion_de_la_api_caen_en_su_campo(): void
    {
        Http::fake(function (Request $request) {
            if ($request->method() === 'GET') {
                return Http::response(['data' => [], 'current_page' => 1, 'last_page' => 1, 'total' => 0]);
            }

            return Http::response([
                'message' => 'The given data was invalid.',
                'errors' => ['email' => ['Ese email ya está registrado.']],
            ], 422);
        });

        Livewire::actingAs($this->superadmin())
            ->test('backoffice.users')
            ->call('create')
            ->set('name', 'Ana Agente')
            ->set('email', 'repetido@tracely.test')
            ->set('password', 'contrasena-larga')
            ->call('save')
            ->assertHasErrors('email')
            // Un 422 es un problema del formulario, no de la pantalla.
            ->assertSet('errorMessage', null);
    }

    public function test_el_borrado_llama_al_endpoint_del_usuario(): void
    {
        Http::fake([
            '*/api/users/7' => Http::response(status: 204),
            '*' => Http::response(['data' => [], 'current_page' => 1, 'last_page' => 1, 'total' => 0]),
        ]);

        Livewire::actingAs($this->superadmin())
            ->test('backoffice.users')
            ->call('confirmDelete', 7)
            ->assertSet('deleting', 7)
            ->call('destroy')
            ->assertSet('deleting', null);

        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/api/users/7'));
    }

    public function test_el_modal_de_borrado_nombra_la_cuenta(): void
    {
        $this->fingirApi([
            ['id' => 7, 'name' => 'Ana Agente', 'email' => 'ana@tracely.test', 'roles' => []],
        ]);

        Livewire::actingAs($this->superadmin())
            ->test('backoffice.users')
            ->call('confirmDelete', 7)
            // El modal tapa la fila desde la que se ha pulsado: sin el nombre,
            // confirmar un borrado irreversible es un acto de fe.
            ->assertSee('Se eliminará la cuenta de Ana Agente');
    }

    public function test_una_cuenta_sin_rol_se_pinta_como_cliente(): void
    {
        $this->fingirApi([
            ['id' => 7, 'name' => 'Cliente Final', 'email' => 'cliente@tracely.test', 'roles' => []],
            ['id' => 8, 'name' => 'Ana Agente', 'email' => 'ana@tracely.test', 'roles' => [['name' => 'agente']]],
        ]);

        Livewire::actingAs($this->superadmin())
            ->test('backoffice.users')
            ->assertSee('Cliente (sin rol)')
            // El rol viene en minúscula de la API; en pantalla se lee como un
            // nombre, no como un identificador.
            ->assertSee('Agente');
    }
}
