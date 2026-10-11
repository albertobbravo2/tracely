<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CompanyTest extends TestCase
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
     * @return array<string, mixed>
     */
    private function datosValidos(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Transportes del Norte',
            'slug' => 'transportes-del-norte',
        ], $overrides);
    }

    public function test_un_superadministrador_puede_crear_una_empresa_con_telefono_valido(): void
    {
        $response = $this->actingAs($this->superadmin())
            ->postJson(route('companies.store'), $this->datosValidos(['phone' => '+34 912 34 5678']));

        $response->assertCreated();
        $this->assertSame('+34 912 34 5678', Company::sole()->phone);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function telefonosInvalidos(): array
    {
        return [
            'letras' => ['abc123456'],
            'demasiado corto' => ['123'],
            'demasiado largo' => ['123456789012345678901'],
            'caracteres no permitidos' => ['912.345.678'],
        ];
    }

    #[DataProvider('telefonosInvalidos')]
    public function test_el_alta_rechaza_telefonos_con_formato_invalido(string $telefono): void
    {
        $response = $this->actingAs($this->superadmin())
            ->postJson(route('companies.store'), $this->datosValidos(['phone' => $telefono]));

        $response->assertInvalid(['phone']);
        $this->assertSame(
            'El teléfono solo puede contener dígitos, espacios, guiones, paréntesis y un "+" inicial.',
            $response->json('errors.phone.0'),
        );
    }

    public function test_el_telefono_es_opcional(): void
    {
        $response = $this->actingAs($this->superadmin())
            ->postJson(route('companies.store'), $this->datosValidos());

        $response->assertCreated();
        $this->assertNull(Company::sole()->phone);
    }

    public function test_la_edicion_tambien_valida_el_formato_del_telefono(): void
    {
        $company = Company::factory()->create();

        $response = $this->actingAs($this->superadmin())
            ->putJson(route('companies.update', $company), ['phone' => 'no-es-un-telefono!!']);

        $response->assertInvalid(['phone']);
    }
}
