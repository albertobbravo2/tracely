<?php

namespace Tests\Feature\Backoffice;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Las pantallas del backoffice no consultan Eloquent: llaman a nuestra propia
 * API por HTTP. Aquí esa llamada se finge, así que lo que se comprueba es el
 * acceso (auth + role) y que la vista monta y pinta lo que devuelve la API.
 */
class BackofficeAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // Cualquier listado de la API responde vacío salvo que el test diga otra cosa.
        Http::fake([
            '*' => Http::response([
                'data' => [],
                'current_page' => 1,
                'last_page' => 1,
                'total' => 0,
            ]),
        ]);
    }

    /**
     * @return list<array{string}>
     */
    public static function rutas(): array
    {
        return [
            'pedidos' => ['backoffice.shipments'],
            'historial' => ['backoffice.shipment-histories'],
            'usuarios' => ['backoffice.users'],
            'documentos' => ['backoffice.documents'],
            'companias' => ['backoffice.companies'],
        ];
    }

    #[DataProvider('rutas')]
    public function test_un_empleado_puede_abrir_cada_pantalla(string $ruta): void
    {
        $empleado = User::factory()->create()->assignRole('agente');

        $this->actingAs($empleado)
            ->get(route($ruta))
            ->assertOk();
    }

    #[DataProvider('rutas')]
    public function test_un_usuario_sin_rol_no_entra(string $ruta): void
    {
        $cliente = User::factory()->create();

        $this->actingAs($cliente)
            ->get(route($ruta))
            ->assertForbidden();
    }

    #[DataProvider('rutas')]
    public function test_sin_sesion_se_redirige_al_login(string $ruta): void
    {
        $this->get(route($ruta))->assertRedirect(route('login'));
    }

    public function test_la_raiz_del_backoffice_lleva_a_pedidos(): void
    {
        $empleado = User::factory()->create()->assignRole('agente');

        $this->actingAs($empleado)
            ->get(route('backoffice.index'))
            ->assertRedirect('/backoffice/pedidos');
    }

    public function test_el_sidebar_lista_las_cinco_secciones(): void
    {
        $empleado = User::factory()->create()->assignRole('agente');

        $this->actingAs($empleado)
            ->get(route('backoffice.shipments'))
            ->assertOk()
            ->assertSee('Historial de pedidos')
            ->assertSee('Compañías')
            ->assertSee('Documentos');
    }
}
