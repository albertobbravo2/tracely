<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Quién puede asignar qué rol al crear o editar un usuario: la jerarquía
 * agente < administrador < superadministrador, y el que un no-superadmin
 * siempre gestiona cuentas de su propia empresa.
 */
class UserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function agente(?Company $company = null): User
    {
        return User::factory()->create(['company_id' => $company?->id])->assignRole('agente');
    }

    private function administrador(?Company $company = null): User
    {
        return User::factory()->create(['company_id' => $company?->id])->assignRole('administrador');
    }

    private function superadmin(): User
    {
        return User::factory()->create()->assignRole('superadministrador');
    }

    /**
     * @return array<string, mixed>
     */
    private function datosValidos(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ana Agente',
            'email' => 'ana@tracely.test',
            'password' => 'contrasena-larga',
        ], $overrides);
    }

    public function test_un_superadministrador_puede_crear_un_superadministrador(): void
    {
        $response = $this->actingAs($this->superadmin())
            ->postJson(route('users.store'), $this->datosValidos(['role' => 'superadministrador']));

        $response->assertCreated();
        $this->assertTrue(User::where('email', 'ana@tracely.test')->sole()->hasRole('superadministrador'));
    }

    public function test_un_administrador_no_puede_crear_un_superadministrador(): void
    {
        $response = $this->actingAs($this->administrador())
            ->postJson(route('users.store'), $this->datosValidos(['role' => 'superadministrador']));

        $response->assertInvalid(['role']);
        $this->assertSame(0, User::where('email', 'ana@tracely.test')->count());
    }

    public function test_un_administrador_puede_crear_hasta_administrador(): void
    {
        $response = $this->actingAs($this->administrador())
            ->postJson(route('users.store'), $this->datosValidos(['role' => 'administrador']));

        $response->assertCreated();
        $this->assertTrue(User::where('email', 'ana@tracely.test')->sole()->hasRole('administrador'));
    }

    public function test_un_agente_no_puede_crear_un_administrador(): void
    {
        $response = $this->actingAs($this->agente())
            ->postJson(route('users.store'), $this->datosValidos(['role' => 'administrador']));

        $response->assertInvalid(['role']);
    }

    public function test_un_agente_puede_crear_otro_agente(): void
    {
        $response = $this->actingAs($this->agente())
            ->postJson(route('users.store'), $this->datosValidos(['role' => 'agente']));

        $response->assertCreated();
        $this->assertTrue(User::where('email', 'ana@tracely.test')->sole()->hasRole('agente'));
    }

    public function test_el_rol_es_opcional_y_deja_la_cuenta_sin_rol(): void
    {
        $response = $this->actingAs($this->superadmin())
            ->postJson(route('users.store'), $this->datosValidos());

        $response->assertCreated();
        $this->assertCount(0, User::where('email', 'ana@tracely.test')->sole()->roles);
    }

    public function test_un_administrador_crea_la_cuenta_en_su_propia_empresa_aunque_pida_otra(): void
    {
        $propiaEmpresa = Company::factory()->create();
        $otraEmpresa = Company::factory()->create();
        $actor = $this->administrador($propiaEmpresa);

        $response = $this->actingAs($actor)
            ->postJson(route('users.store'), $this->datosValidos(['company_id' => $otraEmpresa->id]));

        $response->assertCreated();
        $this->assertSame($propiaEmpresa->id, User::where('email', 'ana@tracely.test')->sole()->company_id);
    }

    public function test_un_superadministrador_si_puede_elegir_la_empresa(): void
    {
        $empresa = Company::factory()->create();

        $response = $this->actingAs($this->superadmin())
            ->postJson(route('users.store'), $this->datosValidos(['company_id' => $empresa->id]));

        $response->assertCreated();
        $this->assertSame($empresa->id, User::where('email', 'ana@tracely.test')->sole()->company_id);
    }

    public function test_un_administrador_no_puede_degradar_a_un_superadministrador(): void
    {
        $superadmin = $this->superadmin();
        $actor = $this->administrador();

        $response = $this->actingAs($actor)
            ->putJson(route('users.update', $superadmin), ['role' => 'administrador']);

        $response->assertInvalid(['role']);
        $this->assertTrue($superadmin->fresh()->hasRole('superadministrador'));
    }

    public function test_un_superadministrador_puede_cambiar_el_rol_de_cualquiera(): void
    {
        $administrador = $this->administrador();

        $response = $this->actingAs($this->superadmin())
            ->putJson(route('users.update', $administrador), ['role' => 'superadministrador']);

        $response->assertOk();
        $this->assertTrue($administrador->fresh()->hasRole('superadministrador'));
    }

    public function test_mandar_el_rol_a_null_deja_la_cuenta_como_cliente(): void
    {
        $agente = $this->agente();

        $response = $this->actingAs($this->superadmin())
            ->putJson(route('users.update', $agente), ['role' => null]);

        $response->assertOk();
        $this->assertCount(0, $agente->fresh()->roles);
    }

    public function test_omitir_el_rol_al_editar_no_lo_toca(): void
    {
        $agente = $this->agente();

        $response = $this->actingAs($this->superadmin())
            ->putJson(route('users.update', $agente), ['name' => 'Nuevo Nombre']);

        $response->assertOk();
        $this->assertTrue($agente->fresh()->hasRole('agente'));
    }
}
