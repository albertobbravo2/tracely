<?php

namespace Tests\Feature\Api;

use App\Models\Shipment;
use App\Models\ShipmentHistory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Listado de envíos vinculados a un usuario (pivote `shipment_user`).
 *
 * Son dos endpoints con la misma fuente de datos pero distinta puerta de
 * entrada: `users.myshipments` sale del token y no exige permiso (los clientes
 * no tienen rol), mientras que `users.shipments` recibe el id por la URL y está
 * cerrado con `permission:ver pedidos usuario`.
 */
class UserShipmentTest extends TestCase
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

    // ---------------------------------------------------------------
    // GET /api/users/shipments — la lista del propio usuario
    // ---------------------------------------------------------------

    public function test_un_cliente_ve_sus_envios_vinculados(): void
    {
        $cliente = User::factory()->create();
        $shipment = Shipment::factory()->create();
        $cliente->shipments()->attach($shipment);

        $response = $this->actingAs($cliente)->getJson(route('users.myshipments'));

        $response->assertOk();
        $this->assertSame([$shipment->id], collect($response->json('data'))->pluck('id')->all());
    }

    public function test_la_lista_propia_trae_el_historial_de_cada_envio(): void
    {
        // El dashboard pinta la línea de tiempo de cada envío a partir de esto:
        // sin el histórico dentro tendría que pedir cada envío por separado.
        $cliente = User::factory()->create();
        $shipment = Shipment::factory()->create();
        $cliente->shipments()->attach($shipment);

        $viejo = ShipmentHistory::factory()->forShipment($shipment)->create([
            'recorded_at' => now()->subDays(3),
        ]);
        $nuevo = ShipmentHistory::factory()->forShipment($shipment)->create([
            'recorded_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($cliente)->getJson(route('users.myshipments'));

        $response->assertOk();

        // Ordenado por `recorded_at`, igual que en shipments.show: la vista
        // marca el último evento como el estado actual y depende de ese orden.
        $this->assertSame(
            [$viejo->id, $nuevo->id],
            collect($response->json('data.0.histories'))->pluck('id')->all(),
        );
    }

    public function test_la_lista_propia_no_incluye_los_envios_de_otros(): void
    {
        $cliente = User::factory()->create();
        $suyo = Shipment::factory()->create();
        $cliente->shipments()->attach($suyo);

        $ajeno = Shipment::factory()->create();
        User::factory()->create()->shipments()->attach($ajeno);

        $response = $this->actingAs($cliente)->getJson(route('users.myshipments'));

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($suyo->id));
        $this->assertFalse($ids->contains($ajeno->id));
    }

    public function test_la_lista_propia_no_incluye_lo_registrado_como_remitente(): void
    {
        // sender_id y la pivote son relaciones distintas: un agente que registra
        // un envío no lo "sigue" salvo que se vincule explícitamente.
        $agente = $this->empleado();
        Shipment::factory()->create(['sender_id' => $agente->id]);

        $response = $this->actingAs($agente)->getJson(route('users.myshipments'));

        $response->assertOk();
        $this->assertSame([], $response->json('data'));
    }

    public function test_un_cliente_sin_rol_puede_consultar_su_propia_lista(): void
    {
        // Misma garantía que en ShipmentUserTest: si algún día se le cuelga un
        // `permission:` a esta ruta, el usuario final se queda fuera de su
        // propia funcionalidad y este test avisa.
        $cliente = User::factory()->create();
        $this->assertCount(0, $cliente->roles);

        $this->actingAs($cliente)
            ->getJson(route('users.myshipments'))
            ->assertOk();
    }

    public function test_sin_autenticar_no_se_puede_consultar_la_lista_propia(): void
    {
        $this->getJson(route('users.myshipments'))->assertUnauthorized();
    }

    // ---------------------------------------------------------------
    // GET /api/users/{user}/shipments — backoffice
    // ---------------------------------------------------------------

    public function test_un_empleado_ve_los_envios_de_otro_usuario(): void
    {
        $cliente = User::factory()->create();
        $shipment = Shipment::factory()->create();
        $cliente->shipments()->attach($shipment);

        $response = $this->actingAs($this->empleado())
            ->getJson(route('users.shipments', $cliente));

        $response->assertOk();
        $this->assertSame([$shipment->id], collect($response->json('data'))->pluck('id')->all());
    }

    public function test_un_usuario_sin_permiso_no_ve_los_envios_de_otro(): void
    {
        $cliente = User::factory()->create();
        $otro = User::factory()->create();
        $otro->shipments()->attach(Shipment::factory()->create());

        $this->actingAs($cliente)
            ->getJson(route('users.shipments', $otro))
            ->assertForbidden();
    }

    public function test_sin_autenticar_no_se_pueden_ver_los_envios_de_un_usuario(): void
    {
        $this->getJson(route('users.shipments', User::factory()->create()))
            ->assertUnauthorized();
    }

    public function test_un_usuario_inexistente_devuelve_404(): void
    {
        $this->actingAs($this->empleado())
            ->getJson(route('users.shipments', 999999))
            ->assertNotFound();
    }

    public function test_el_listado_esta_paginado_de_quince_en_quince(): void
    {
        $cliente = User::factory()->create();
        $cliente->shipments()->attach(Shipment::factory(20)->create());

        $response = $this->actingAs($this->empleado())
            ->getJson(route('users.shipments', $cliente));

        $response->assertOk();
        $this->assertCount(15, $response->json('data'));
        $this->assertSame(20, $response->json('total'));
    }
}
