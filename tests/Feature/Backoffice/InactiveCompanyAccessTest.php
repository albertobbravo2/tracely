<?php

namespace Tests\Feature\Backoffice;

use App\Models\Company;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Desactivar una empresa deja a su plantilla fuera del backoffice: ni las
 * pantallas de `routes/web.php` ni la API autenticada, aunque conserven sus
 * roles y permisos. Lo que no se toca: el seguimiento público, las cuentas
 * sin empresa y el superadministrador.
 */
class InactiveCompanyAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function empleadoDeEmpresaInactiva(string $rol = 'agente'): User
    {
        $company = Company::factory()->inactiva()->create();

        return User::factory()->create(['company_id' => $company->id])->assignRole($rol);
    }

    /**
     * @return list<array{string}>
     */
    public static function rutasDeBackoffice(): array
    {
        return [
            'pedidos' => ['backoffice.shipments'],
            'historial' => ['backoffice.shipment-histories'],
            'usuarios' => ['backoffice.users'],
            'documentos' => ['backoffice.documents'],
            'companias' => ['backoffice.companies'],
        ];
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

    #[DataProvider('rutasDeBackoffice')]
    public function test_un_empleado_de_empresa_desactivada_no_entra_a_las_pantallas(string $ruta): void
    {
        $this->actingAs($this->empleadoDeEmpresaInactiva())
            ->get(route($ruta))
            ->assertForbidden();
    }

    #[DataProvider('rolesDeEmpresa')]
    public function test_un_empleado_de_empresa_desactivada_no_usa_la_api(string $rol): void
    {
        $empleado = $this->empleadoDeEmpresaInactiva($rol);

        $this->actingAs($empleado)
            ->getJson(route('shipments.index'))
            ->assertForbidden();

        $this->actingAs($empleado)
            ->postJson(route('shipments.store'), [])
            ->assertForbidden();
    }

    public function test_un_empleado_de_empresa_activa_sigue_entrando(): void
    {
        $company = Company::factory()->create();
        $empleado = User::factory()->create(['company_id' => $company->id])->assignRole('agente');

        $this->actingAs($empleado)
            ->getJson(route('shipments.index'))
            ->assertOk();
    }

    public function test_el_superadministrador_no_se_bloquea_al_desactivar_su_empresa(): void
    {
        // Si se bloqueara, nadie podría volver a activarla: gestionar empresas
        // es un permiso exclusivo suyo.
        $company = Company::factory()->inactiva()->create();
        $superadmin = User::factory()->create(['company_id' => $company->id])
            ->assignRole('superadministrador');

        $this->actingAs($superadmin)
            ->getJson(route('companies.index'))
            ->assertOk();
    }

    public function test_un_cliente_sin_empresa_conserva_sus_pedidos(): void
    {
        $cliente = User::factory()->create();

        $this->actingAs($cliente)
            ->getJson(route('users.myshipments'))
            ->assertOk();
    }

    public function test_el_seguimiento_publico_sigue_funcionando(): void
    {
        // La consulta pública vive fuera del grupo autenticado: desactivar la
        // empresa del envío no esconde su seguimiento a quien tenga la guía.
        $company = Company::factory()->inactiva()->create();
        $shipment = Shipment::factory()->create(['company_id' => $company->id]);

        $this->getJson(route('shipments.show', $shipment->tracking_number))
            ->assertOk();
    }
}
