<?php

namespace Tests\Feature\Api;

use App\Enums\ShipmentStatus;
use App\Models\Company;
use App\Models\Document;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentTest extends TestCase
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

    /**
     * @return array<string, string>
     */
    private function datosValidos(array $overrides = []): array
    {
        return array_merge([
            'tracking_number' => 'TRC-0000000001',
            'receiver_name' => 'Ana Destinataria',
            'origin' => 'Madrid, España',
            'destination' => 'Lisboa, Portugal',
            'estimated_delivery_date' => now()->addDays(5)->toDateString(),
        ], $overrides);
    }

    public function test_un_empleado_puede_registrar_un_envio(): void
    {
        $response = $this->actingAs($this->empleado())
            ->postJson(route('shipments.store'), $this->datosValidos());

        $response->assertCreated();

        $shipment = Shipment::sole();
        $this->assertSame('TRC-0000000001', $shipment->tracking_number);
        // La columna tiene "pendiente" por defecto, no hace falta mandarlo.
        $this->assertSame(ShipmentStatus::Pendiente, $shipment->status);
    }

    public function test_el_remitente_es_siempre_quien_hace_la_peticion(): void
    {
        $agente = $this->empleado();
        $otro = User::factory()->create();

        // Se manda sender_id de otro usuario a propósito: debe ignorarse.
        $this->actingAs($agente)
            ->postJson(route('shipments.store'), $this->datosValidos([
                'sender_id' => $otro->id,
            ]))
            ->assertCreated();

        $this->assertSame($agente->id, Shipment::sole()->sender_id);
    }

    public function test_el_envio_hereda_la_empresa_de_quien_lo_registra(): void
    {
        $company = Company::factory()->create();
        $agente = User::factory()->create(['company_id' => $company->id])->assignRole('agente');

        $this->actingAs($agente)
            ->postJson(route('shipments.store'), $this->datosValidos())
            ->assertCreated();

        $this->assertSame($company->id, Shipment::sole()->company_id);
    }

    public function test_no_se_puede_repetir_el_numero_de_seguimiento(): void
    {
        Shipment::factory()->create(['tracking_number' => 'TRC-0000000001']);

        $this->actingAs($this->empleado())
            ->postJson(route('shipments.store'), $this->datosValidos())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tracking_number');
    }

    public function test_un_usuario_sin_permiso_no_puede_registrar_envios(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('shipments.store'), $this->datosValidos())
            ->assertForbidden();

        $this->assertSame(0, Shipment::count());
    }

    public function test_se_puede_actualizar_el_estado_de_un_envio(): void
    {
        $shipment = Shipment::factory()->create();

        $this->actingAs($this->empleado())
            ->patchJson(route('shipments.update', $shipment), [
                'status' => ShipmentStatus::EnTransito->value,
            ])
            ->assertOk();

        $this->assertSame(ShipmentStatus::EnTransito, $shipment->fresh()->status);
    }

    public function test_actualizar_sin_cambiar_el_numero_de_seguimiento_no_choca_consigo_mismo(): void
    {
        $shipment = Shipment::factory()->create(['tracking_number' => 'TRC-0000000001']);

        // Un PUT reenvía el número actual: el ignore() del unique debe dejarlo pasar.
        $this->actingAs($this->empleado())
            ->putJson(route('shipments.update', $shipment), [
                'tracking_number' => 'TRC-0000000001',
                'destination' => 'Oporto, Portugal',
            ])
            ->assertOk();

        $this->assertSame('Oporto, Portugal', $shipment->fresh()->destination);
    }

    public function test_un_estado_invalido_se_rechaza(): void
    {
        $shipment = Shipment::factory()->create();

        $this->actingAs($this->empleado())
            ->patchJson(route('shipments.update', $shipment), ['status' => 'inventado'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_borrar_al_usuario_que_registro_un_envio_no_borra_el_envio(): void
    {
        $agente = $this->empleado();
        $shipment = Shipment::factory()->forSender($agente)->create();

        // Dar de baja a un empleado no puede llevarse por delante los envíos:
        // son registros de negocio, `sender_id` solo dice quién los dio de alta.
        $agente->delete();

        $this->assertDatabaseHas('shipments', ['id' => $shipment->id]);
        $this->assertNull($shipment->fresh()->sender_id);
    }

    public function test_borrar_una_empresa_no_borra_sus_envios(): void
    {
        $company = Company::factory()->create();
        $shipment = Shipment::factory()->create(['company_id' => $company->id]);

        $company->delete();

        $this->assertDatabaseHas('shipments', ['id' => $shipment->id]);
        $this->assertNull($shipment->fresh()->company_id);
    }

    public function test_borrar_un_envio_arrastra_su_historial(): void
    {
        $shipment = Shipment::factory()->create();
        $shipment->histories()->create([
            'status' => ShipmentStatus::Pendiente,
            'location' => 'Madrid',
            'description' => 'Paquete recogido',
            'recorded_at' => now(),
        ]);

        $this->actingAs($this->empleado())
            ->deleteJson(route('shipments.destroy', $shipment))
            ->assertNoContent();

        $this->assertSame(0, Shipment::count());
        $this->assertDatabaseCount('shipment_histories', 0);
    }

    public function test_la_consulta_publica_no_expone_los_documentos(): void
    {
        $shipment = Shipment::factory()->create();
        Document::factory()->for($shipment)->create();

        $this->getJson(route('shipments.show', ['shipment' => $shipment->tracking_number]))
            ->assertOk()
            ->assertJsonMissing(['documents'])
            ->assertJsonMissing(['histories']);
    }

    public function test_un_usuario_autenticado_recibe_los_documentos_del_envio(): void
    {
        $shipment = Shipment::factory()->create();
        $documento = Document::factory()->for($shipment)->create();

        $this->actingAs(User::factory()->create())
            ->getJson(route('shipments.show', ['shipment' => $shipment->tracking_number]))
            ->assertOk()
            ->assertJsonCount(1, 'documents')
            ->assertJsonPath('documents.0.document_name', $documento->document_name)
            // `file_path` es una ruta interna del disco: lo que se expone es la
            // URL de descarga, que sigue detrás de `permission:ver documento`.
            ->assertJsonMissing(['file_path']);
    }
}
