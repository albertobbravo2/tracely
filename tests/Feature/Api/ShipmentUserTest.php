<?php

namespace Tests\Feature\Api;

use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_un_usuario_final_puede_vincular_su_cuenta_a_un_envio(): void
    {
        $cliente = User::factory()->create();
        $shipment = Shipment::factory()->create();

        $this->actingAs($cliente)
            ->postJson(route('shipments.users.store', $shipment))
            ->assertCreated();

        $this->assertSame([$cliente->id], $shipment->fresh()->users->pluck('id')->all());
    }

    public function test_el_envio_aparece_en_la_lista_del_usuario(): void
    {
        $cliente = User::factory()->create();
        $shipment = Shipment::factory()->create();

        $this->actingAs($cliente)->postJson(route('shipments.users.store', $shipment));

        $this->assertSame([$shipment->id], $cliente->shipments->pluck('id')->all());
    }

    public function test_un_cliente_sin_rol_puede_vincularse(): void
    {
        // Los clientes no tienen rol por diseño: la ruta no exige permiso, solo
        // estar autenticado. Si algún día se le pone un `permission:`, este test
        // avisa de que se ha dejado fuera al usuario final.
        $cliente = User::factory()->create();
        $this->assertCount(0, $cliente->roles);

        $this->actingAs($cliente)
            ->postJson(route('shipments.users.store', Shipment::factory()->create()))
            ->assertCreated();
    }

    public function test_vincularse_dos_veces_no_duplica_ni_falla(): void
    {
        $cliente = User::factory()->create();
        $shipment = Shipment::factory()->create();

        // La pivote tiene un unique(shipment_id, user_id): con attach() la
        // segunda llamada reventaría, por eso el controlador usa sync.
        $this->actingAs($cliente)->postJson(route('shipments.users.store', $shipment))->assertCreated();
        $this->actingAs($cliente)->postJson(route('shipments.users.store', $shipment))->assertCreated();

        $this->assertDatabaseCount('shipment_user', 1);
    }

    public function test_varios_usuarios_pueden_seguir_el_mismo_envio(): void
    {
        $shipment = Shipment::factory()->create();
        $uno = User::factory()->create();
        $otro = User::factory()->create();

        $this->actingAs($uno)->postJson(route('shipments.users.store', $shipment));
        $this->actingAs($otro)->postJson(route('shipments.users.store', $shipment));

        $this->assertCount(2, $shipment->fresh()->users);
    }

    public function test_desvincularse_no_afecta_a_los_demas(): void
    {
        $shipment = Shipment::factory()->create();
        $seVa = User::factory()->create();
        $seQueda = User::factory()->create();
        $shipment->users()->attach([$seVa->id, $seQueda->id]);

        $this->actingAs($seVa)
            ->deleteJson(route('shipments.users.destroy', $shipment))
            ->assertNoContent();

        $this->assertSame([$seQueda->id], $shipment->fresh()->users->pluck('id')->all());
        // Ni el envío ni la cuenta se tocan: solo desaparece el vínculo.
        $this->assertDatabaseHas('shipments', ['id' => $shipment->id]);
        $this->assertDatabaseHas('users', ['id' => $seVa->id]);
    }

    public function test_sin_autenticar_no_se_puede_vincular(): void
    {
        $shipment = Shipment::factory()->create();

        $this->postJson(route('shipments.users.store', $shipment))
            ->assertUnauthorized();

        $this->assertDatabaseCount('shipment_user', 0);
    }
}
