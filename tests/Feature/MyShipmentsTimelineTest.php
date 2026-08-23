<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\ShipmentHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El dashboard pinta una tarjeta con línea de tiempo por cada envío vinculado,
 * una debajo de otra, en vez de una fila de tabla por envío.
 *
 * La llamada a la API interna va con `Http::fake`: que el endpoint devuelva el
 * histórico lo cubre UserShipmentTest.
 */
class MyShipmentsTimelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_cada_envio_se_pinta_con_su_propia_linea_de_tiempo(): void
    {
        $cliente = User::factory()->create();

        $primero = $this->shipmentFor($cliente, ShipmentStatus::EnTransito, 'Salida del centro logístico');
        $segundo = $this->shipmentFor($cliente, ShipmentStatus::Entregado, 'Entregado en recepción');

        $this->fakeMyShipments($cliente);

        $html = Livewire::actingAs($cliente)->test('my-shipments')->html();

        // Una tarjeta por envío...
        $this->assertStringContainsString($primero->tracking_number, $html);
        $this->assertStringContainsString($segundo->tracking_number, $html);

        // ...y dentro de cada una, su línea de tiempo con sus propios eventos.
        $this->assertSame(2, substr_count($html, '<ol'));
        $this->assertStringContainsString('Salida del centro logístico', $html);
        $this->assertStringContainsString('Entregado en recepción', $html);
    }

    public function test_un_envio_sin_historial_se_pinta_sin_linea_de_tiempo(): void
    {
        $cliente = User::factory()->create();
        $shipment = Shipment::factory()->create();
        $shipment->users()->attach($cliente);

        $this->fakeMyShipments($cliente);

        $html = Livewire::actingAs($cliente)->test('my-shipments')->html();

        $this->assertStringContainsString($shipment->tracking_number, $html);
        $this->assertStringNotContainsString('<ol', $html);
    }

    public function test_sin_envios_vinculados_sigue_saliendo_el_estado_vacio(): void
    {
        $cliente = User::factory()->create();

        $this->fakeMyShipments($cliente);

        Livewire::actingAs($cliente)
            ->test('my-shipments')
            ->assertSee(__('Todavía no tienes ningún pedido vinculado.'));
    }

    /**
     * Un envío vinculado al cliente, con un evento de historial.
     */
    private function shipmentFor(User $cliente, ShipmentStatus $status, string $description): Shipment
    {
        $shipment = Shipment::factory()->create(['status' => $status]);
        $shipment->users()->attach($cliente);

        ShipmentHistory::factory()->forShipment($shipment)->status($status)->create([
            'description' => $description,
        ]);

        return $shipment;
    }

    /**
     * `users.myshipments` tal y como responde de verdad: paginado y con el
     * histórico de cada envío dentro.
     */
    private function fakeMyShipments(User $cliente): void
    {
        $payload = $cliente->shipments()
            ->with(['histories' => fn ($query) => $query->orderBy('recorded_at')])
            ->paginate(15)
            ->toArray();

        Http::fake([
            rtrim((string) config('services.internal_api.url'), '/').'/api/users/shipments' => Http::response($payload),
        ]);
    }
}
