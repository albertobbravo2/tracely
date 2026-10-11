<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Document;
use App\Models\Shipment;
use App\Models\ShipmentHistory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Quien no es superadministrador solo ve, en los listados del backoffice, los
 * datos de su propia empresa: pedidos, historial de pedidos, usuarios y
 * documentos. El superadministrador sigue viendo los de todas.
 */
class CompanyScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function empleado(string $rol, Company $company): User
    {
        return User::factory()->create(['company_id' => $company->id])->assignRole($rol);
    }

    private function superadmin(): User
    {
        return User::factory()->create()->assignRole('superadministrador');
    }

    /**
     * @return list<array{string}>
     */
    public static function rolesDeEmpresa(): array
    {
        return [
            'agente' => ['agente'],
            'administrador' => ['administrador'],
        ];
    }

    // --- Pedidos -------------------------------------------------------

    #[DataProvider('rolesDeEmpresa')]
    public function test_los_pedidos_solo_muestran_los_de_la_propia_empresa(string $rol): void
    {
        $propiaEmpresa = Company::factory()->create();
        $otraEmpresa = Company::factory()->create();
        $actor = $this->empleado($rol, $propiaEmpresa);

        Shipment::factory()->create(['company_id' => $propiaEmpresa->id]);
        Shipment::factory()->create(['company_id' => $otraEmpresa->id]);

        $response = $this->actingAs($actor)->getJson(route('shipments.index'));

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame($propiaEmpresa->id, $response->json('data.0.company_id'));
    }

    #[DataProvider('rolesDeEmpresa')]
    public function test_pedir_otra_empresa_por_parametro_no_la_destapa(string $rol): void
    {
        $propiaEmpresa = Company::factory()->create();
        $otraEmpresa = Company::factory()->create();
        $actor = $this->empleado($rol, $propiaEmpresa);

        Shipment::factory()->create(['company_id' => $otraEmpresa->id]);

        // El filtro `company_id` de la petición se ignora para quien no es
        // superadministrador: pedirlo por parámetro no basta para verla.
        $response = $this->actingAs($actor)
            ->getJson(route('shipments.index', ['company_id' => $otraEmpresa->id]));

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_un_superadministrador_ve_los_pedidos_de_todas_las_empresas(): void
    {
        Shipment::factory()->create(['company_id' => Company::factory()->create()->id]);
        Shipment::factory()->create(['company_id' => Company::factory()->create()->id]);

        $response = $this->actingAs($this->superadmin())->getJson(route('shipments.index'));

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    // --- Historial de pedidos -------------------------------------------

    #[DataProvider('rolesDeEmpresa')]
    public function test_el_historial_solo_muestra_el_de_pedidos_de_la_propia_empresa(string $rol): void
    {
        $propiaEmpresa = Company::factory()->create();
        $otraEmpresa = Company::factory()->create();
        $actor = $this->empleado($rol, $propiaEmpresa);

        $propio = Shipment::factory()->create(['company_id' => $propiaEmpresa->id]);
        $ajeno = Shipment::factory()->create(['company_id' => $otraEmpresa->id]);

        ShipmentHistory::factory()->create(['shipment_id' => $propio->id]);
        ShipmentHistory::factory()->create(['shipment_id' => $ajeno->id]);

        $response = $this->actingAs($actor)->getJson(route('shipment-histories.index'));

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_un_superadministrador_ve_el_historial_de_todas_las_empresas(): void
    {
        $uno = Shipment::factory()->create(['company_id' => Company::factory()->create()->id]);
        $otro = Shipment::factory()->create(['company_id' => Company::factory()->create()->id]);

        ShipmentHistory::factory()->create(['shipment_id' => $uno->id]);
        ShipmentHistory::factory()->create(['shipment_id' => $otro->id]);

        $response = $this->actingAs($this->superadmin())->getJson(route('shipment-histories.index'));

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    // --- Usuarios --------------------------------------------------------

    #[DataProvider('rolesDeEmpresa')]
    public function test_los_usuarios_solo_muestran_los_de_la_propia_empresa(string $rol): void
    {
        $propiaEmpresa = Company::factory()->create();
        $otraEmpresa = Company::factory()->create();
        $actor = $this->empleado($rol, $propiaEmpresa);

        User::factory()->create(['company_id' => $otraEmpresa->id]);

        $response = $this->actingAs($actor)->getJson(route('users.index'));

        // Solo el propio actor: la cuenta de la otra empresa no debe aparecer.
        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame($actor->id, $response->json('data.0.id'));
    }

    public function test_un_superadministrador_ve_los_usuarios_de_todas_las_empresas(): void
    {
        User::factory()->create(['company_id' => Company::factory()->create()->id]);
        User::factory()->create(['company_id' => Company::factory()->create()->id]);

        // El propio superadministrador (sin empresa) más los dos de las empresas.
        $response = $this->actingAs($this->superadmin())->getJson(route('users.index'));

        $response->assertOk()->assertJsonCount(3, 'data');
    }

    // --- Documentos --------------------------------------------------------

    #[DataProvider('rolesDeEmpresa')]
    public function test_los_documentos_solo_muestran_los_de_pedidos_de_la_propia_empresa(string $rol): void
    {
        $propiaEmpresa = Company::factory()->create();
        $otraEmpresa = Company::factory()->create();
        $actor = $this->empleado($rol, $propiaEmpresa);

        $propio = Shipment::factory()->create(['company_id' => $propiaEmpresa->id]);
        $ajeno = Shipment::factory()->create(['company_id' => $otraEmpresa->id]);

        Document::factory()->create(['shipment_id' => $propio->id]);
        Document::factory()->create(['shipment_id' => $ajeno->id]);

        $response = $this->actingAs($actor)->getJson(route('documents.index'));

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_un_superadministrador_ve_los_documentos_de_todas_las_empresas(): void
    {
        $uno = Shipment::factory()->create(['company_id' => Company::factory()->create()->id]);
        $otro = Shipment::factory()->create(['company_id' => Company::factory()->create()->id]);

        Document::factory()->create(['shipment_id' => $uno->id]);
        Document::factory()->create(['shipment_id' => $otro->id]);

        $response = $this->actingAs($this->superadmin())->getJson(route('documents.index'));

        $response->assertOk()->assertJsonCount(2, 'data');
    }
}
