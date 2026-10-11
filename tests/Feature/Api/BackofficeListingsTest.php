<?php

namespace Tests\Feature\Api;

use App\Enums\ShipmentStatus;
use App\Models\Company;
use App\Models\Document;
use App\Models\Shipment;
use App\Models\ShipmentHistory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Filtros y relaciones que los listados de la API ganaron para poder pintar las
 * tablas del backoffice. Todo es aditivo: sin parámetros, la respuesta sigue
 * siendo la lista completa.
 */
class BackofficeListingsTest extends TestCase
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

    public function test_los_pedidos_se_filtran_por_guia(): void
    {
        Shipment::factory()->create(['tracking_number' => 'TRC-1111111111']);
        Shipment::factory()->create(['tracking_number' => 'TRC-2222222222']);

        $response = $this->actingAs($this->empleado())
            ->getJson(route('shipments.index', ['search' => '1111111111']));

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('TRC-1111111111', $response->json('data.0.tracking_number'));
    }

    public function test_los_pedidos_se_filtran_por_destinatario(): void
    {
        Shipment::factory()->create(['receiver_name' => 'Ana Destinataria']);
        Shipment::factory()->create(['receiver_name' => 'Luis Receptor']);

        $this->actingAs($this->empleado())
            ->getJson(route('shipments.index', ['search' => 'destinataria']))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_los_pedidos_se_filtran_por_estado(): void
    {
        Shipment::factory()->entregado()->create();
        Shipment::factory()->enTransito()->create();
        Shipment::factory()->enTransito()->create();

        $this->actingAs($this->empleado())
            ->getJson(route('shipments.index', ['status' => ShipmentStatus::EnTransito->value]))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_un_superadministrador_puede_filtrar_los_pedidos_por_empresa(): void
    {
        $company = Company::factory()->create();
        Shipment::factory()->create(['company_id' => $company->id]);
        Shipment::factory()->create();

        // Solo el superadministrador puede pedir una empresa por parámetro: ver
        // CompanyScopingTest para el resto de roles, que quedan fijados a la suya.
        $this->actingAs($this->superadmin())
            ->getJson(route('shipments.index', ['company_id' => $company->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_sin_filtros_los_pedidos_se_listan_todos(): void
    {
        Shipment::factory()->count(3)->create();

        $this->actingAs($this->empleado())
            ->getJson(route('shipments.index'))
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_el_listado_de_pedidos_ordena_por_el_mas_reciente(): void
    {
        $primero = Shipment::factory()->create();
        $ultimo = Shipment::factory()->create();

        $response = $this->actingAs($this->empleado())->getJson(route('shipments.index'));

        $this->assertSame($ultimo->id, $response->json('data.0.id'));
        $this->assertSame($primero->id, $response->json('data.1.id'));
    }

    public function test_el_listado_de_usuarios_incluye_los_roles(): void
    {
        $this->empleado();

        $response = $this->actingAs($this->empleado())->getJson(route('users.index'));

        $response->assertOk();
        $this->assertSame('agente', $response->json('data.0.roles.0.name'));
    }

    public function test_un_cliente_sin_rol_aparece_con_la_lista_de_roles_vacia(): void
    {
        $cliente = User::factory()->create();

        $response = $this->actingAs($this->empleado())
            ->getJson(route('users.index', ['search' => $cliente->email]));

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame([], $response->json('data.0.roles'));
    }

    public function test_los_usuarios_se_filtran_por_nombre_o_email(): void
    {
        User::factory()->create(['name' => 'Marta Agente', 'email' => 'marta@example.test']);
        User::factory()->create(['name' => 'Pedro Cliente', 'email' => 'pedro@example.test']);

        $this->actingAs($this->empleado())
            ->getJson(route('users.index', ['search' => 'marta@']))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_las_empresas_se_filtran_por_nombre(): void
    {
        Company::factory()->create(['name' => 'Transportes del Norte']);
        Company::factory()->create(['name' => 'Logística del Sur']);

        $this->actingAs($this->superadmin())
            ->getJson(route('companies.index', ['search' => 'norte']))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_el_listado_de_documentos_trae_la_guia_del_envio(): void
    {
        $shipment = Shipment::factory()->create(['tracking_number' => 'TRC-3333333333']);
        Document::factory()->create(['shipment_id' => $shipment->id]);

        $response = $this->actingAs($this->empleado())->getJson(route('documents.index'));

        $response->assertOk();
        $this->assertSame('TRC-3333333333', $response->json('data.0.shipment.tracking_number'));
    }

    public function test_el_listado_de_historial_trae_la_guia_del_envio(): void
    {
        $shipment = Shipment::factory()->create(['tracking_number' => 'TRC-4444444444']);
        ShipmentHistory::factory()->create(['shipment_id' => $shipment->id]);

        $response = $this->actingAs($this->empleado())->getJson(route('shipment-histories.index'));

        $response->assertOk();
        $this->assertSame('TRC-4444444444', $response->json('data.0.shipment.tracking_number'));
    }

    public function test_el_documento_sigue_sin_exponer_su_ruta_interna(): void
    {
        $shipment = Shipment::factory()->create();
        Document::factory()->create(['shipment_id' => $shipment->id]);

        // El eager load añade el envío, pero no destapa nada que estuviera oculto.
        $this->actingAs($this->empleado())
            ->getJson(route('documents.index'))
            ->assertOk()
            ->assertJsonMissingPath('data.0.file_path');
    }
}
