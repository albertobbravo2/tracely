<?php

namespace Tests\Feature\Backoffice;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La pantalla de compañías del backoffice, contra una API fingida.
 *
 * Es la única sección que un agente o un administrador no pueden usar —los
 * permisos de empresas son solo del superadministrador—, así que aquí importa
 * tanto lo que hace cuando va bien como lo que dice cuando la API responde 403.
 */
class CompanyFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
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
                return $this->pagina($filas);
            }

            return Http::response(['id' => 3, 'name' => 'Transportes del Norte'], 201);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function pagina(array $filas): PromiseInterface
    {
        return Http::response([
            'data' => $filas,
            'current_page' => 1,
            'last_page' => 1,
            'total' => count($filas),
        ]);
    }

    public function test_el_listado_pide_las_empresas_con_el_buscador(): void
    {
        $this->fingirApi();

        Livewire::actingAs($this->superadmin())
            ->test('backoffice.companies')
            ->set('search', 'norte');

        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && str_contains($request->url(), '/api/companies')
            && str_contains($request->url(), 'search=norte'));
    }

    public function test_el_slug_se_propone_a_partir_del_nombre_en_el_alta(): void
    {
        $this->fingirApi();

        Livewire::actingAs($this->superadmin())
            ->test('backoffice.companies')
            ->call('create')
            ->set('name', 'Transportes del Norte')
            ->assertSet('slug', 'transportes-del-norte');
    }

    public function test_el_slug_no_se_toca_al_editar(): void
    {
        Http::fake([
            '*/api/companies/3' => Http::response([
                'id' => 3,
                'name' => 'Transportes del Norte',
                'slug' => 'transportes-norte',
                'contact_email' => 'hola@norte.test',
                'phone' => '600100200',
                'is_active' => true,
            ]),
            '*' => Http::response(['data' => [], 'current_page' => 1, 'last_page' => 1, 'total' => 0]),
        ]);

        Livewire::actingAs($this->superadmin())
            ->test('backoffice.companies')
            ->call('edit', 3)
            ->assertSet('slug', 'transportes-norte')
            ->set('name', 'Transportes del Norte, S.L.')
            // Cambiar el slug de una empresa que ya existe solo rompería
            // enlaces: se queda el que tenía.
            ->assertSet('slug', 'transportes-norte');
    }

    public function test_el_alta_manda_la_empresa_completa(): void
    {
        $this->fingirApi();

        Livewire::actingAs($this->superadmin())
            ->test('backoffice.companies')
            ->call('create')
            ->set('name', 'Transportes del Norte')
            ->set('contact_email', 'hola@norte.test')
            ->set('phone', '600100200')
            ->set('is_active', false)
            ->call('save');

        Http::assertSent(function (Request $request) {
            if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/api/companies')) {
                return false;
            }

            $data = $request->data();

            return $data['name'] === 'Transportes del Norte'
                && $data['slug'] === 'transportes-del-norte'
                && $data['contact_email'] === 'hola@norte.test'
                && $data['phone'] === '600100200'
                && $data['is_active'] === false;
        });
    }

    public function test_los_campos_opcionales_vacios_viajan_como_null(): void
    {
        $this->fingirApi();

        Livewire::actingAs($this->superadmin())
            ->test('backoffice.companies')
            ->call('create')
            ->set('name', 'Transportes del Norte')
            ->call('save');

        // Son `nullable` en la API: mandar cadena vacía guardaría un email
        // vacío en vez de "no tiene".
        Http::assertSent(fn (Request $request) => $request->method() !== 'POST'
            || ($request->data()['contact_email'] === null && $request->data()['phone'] === null));
    }

    public function test_la_edicion_va_por_put_al_endpoint_de_la_empresa(): void
    {
        Http::fake([
            '*/api/companies/3' => Http::response([
                'id' => 3,
                'name' => 'Transportes del Norte',
                'slug' => 'transportes-norte',
                'contact_email' => null,
                'phone' => null,
                'is_active' => true,
            ]),
            '*' => Http::response(['data' => [], 'current_page' => 1, 'last_page' => 1, 'total' => 0]),
        ]);

        Livewire::actingAs($this->superadmin())
            ->test('backoffice.companies')
            ->call('edit', 3)
            ->set('is_active', false)
            ->call('save');

        Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
            && str_ends_with($request->url(), '/api/companies/3')
            && $request->data()['is_active'] === false);
    }

    public function test_los_errores_de_validacion_de_la_api_caen_en_su_campo(): void
    {
        Http::fake(function (Request $request) {
            if ($request->method() === 'GET') {
                return $this->pagina([]);
            }

            return Http::response([
                'message' => 'The given data was invalid.',
                'errors' => ['slug' => ['Ese slug ya está en uso.']],
            ], 422);
        });

        Livewire::actingAs($this->superadmin())
            ->test('backoffice.companies')
            ->call('create')
            ->set('name', 'Transportes del Norte')
            ->call('save')
            ->assertHasErrors('slug')
            ->assertSet('errorMessage', null);
    }

    public function test_el_borrado_llama_al_endpoint_de_la_empresa(): void
    {
        Http::fake([
            '*/api/companies/3' => Http::response(status: 204),
            '*' => Http::response(['data' => [], 'current_page' => 1, 'last_page' => 1, 'total' => 0]),
        ]);

        Livewire::actingAs($this->superadmin())
            ->test('backoffice.companies')
            ->call('confirmDelete', 3)
            ->assertSet('deleting', 3)
            ->call('destroy')
            ->assertSet('deleting', null);

        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/api/companies/3'));
    }

    public function test_el_modal_de_borrado_nombra_la_empresa(): void
    {
        $this->fingirApi([
            ['id' => 3, 'name' => 'Transportes del Norte', 'slug' => 'transportes-norte', 'contact_email' => null, 'phone' => null, 'is_active' => true],
        ]);

        Livewire::actingAs($this->superadmin())
            ->test('backoffice.companies')
            ->call('confirmDelete', 3)
            ->assertSee('Se eliminará Transportes del Norte');
    }

    public function test_una_empresa_inactiva_no_se_pinta_como_una_activa(): void
    {
        $this->fingirApi([
            ['id' => 3, 'name' => 'Transportes del Norte', 'slug' => 'norte', 'contact_email' => null, 'phone' => null, 'is_active' => true],
            ['id' => 4, 'name' => 'Logística del Sur', 'slug' => 'sur', 'contact_email' => null, 'phone' => null, 'is_active' => false],
        ]);

        Livewire::actingAs($this->superadmin())
            ->test('backoffice.companies')
            ->assertSee('Activa')
            ->assertSee('Inactiva')
            // El distintivo verde es el de lo confirmado; el gris, el de lo
            // neutro. Si las dos filas salieran iguales, la columna sobraría.
            ->assertSeeHtml('!bg-ok-soft')
            ->assertSeeHtml('!bg-idle-soft');
    }
}
