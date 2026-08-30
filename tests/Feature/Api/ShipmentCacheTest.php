<?php

namespace Tests\Feature\Api;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\User;
use App\Observers\ShipmentObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ShipmentCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Cache::store('redis')->flush();

        parent::tearDown();
    }

    public function test_un_envio_entregado_se_guarda_en_redis(): void
    {
        $shipment = Shipment::factory()->entregado()->create();

        $cached = Cache::store('redis')->get(ShipmentObserver::cacheKey($shipment->tracking_number));

        $this->assertNotNull($cached);
        $this->assertSame($shipment->tracking_number, $cached['tracking_number']);
    }

    public function test_un_envio_no_entregado_no_se_guarda_en_redis(): void
    {
        $shipment = Shipment::factory()->enTransito()->create();

        $this->assertNull(Cache::store('redis')->get(ShipmentObserver::cacheKey($shipment->tracking_number)));
    }

    public function test_al_dejar_de_estar_entregado_se_limpia_la_cache(): void
    {
        $shipment = Shipment::factory()->entregado()->create();
        $this->assertNotNull(Cache::store('redis')->get(ShipmentObserver::cacheKey($shipment->tracking_number)));

        $shipment->update(['status' => ShipmentStatus::Incidencia]);

        $this->assertNull(Cache::store('redis')->get(ShipmentObserver::cacheKey($shipment->tracking_number)));
    }

    public function test_al_borrar_un_envio_entregado_se_limpia_la_cache(): void
    {
        $shipment = Shipment::factory()->entregado()->create();

        $shipment->delete();

        $this->assertNull(Cache::store('redis')->get(ShipmentObserver::cacheKey($shipment->tracking_number)));
    }

    public function test_la_consulta_publica_de_un_envio_cacheado_no_expone_datos_privados(): void
    {
        $shipment = Shipment::factory()->entregado()->create();

        $response = $this->getJson(route('shipments.show', ['shipment' => $shipment->tracking_number]));

        $response->assertOk()
            ->assertJsonStructure(['tracking_number', 'status', 'origin', 'destination', 'estimated_delivery_date'])
            ->assertJsonMissing(['sender_id'])
            ->assertJsonMissing(['histories']);
    }

    public function test_un_usuario_autenticado_recibe_el_historial_cacheado(): void
    {
        $shipment = Shipment::factory()->enTransito()->create();
        $shipment->histories()->create([
            'status' => ShipmentStatus::EnTransito,
            'recorded_at' => now()->subDay(),
        ]);

        // Este save() es el que deja el envío como "entregado" y el que el
        // Observer usa para cachear: el historial de arriba, creado antes,
        // sí queda dentro del payload guardado en Redis.
        $shipment->update(['status' => ShipmentStatus::Entregado]);

        $response = $this->actingAs(User::factory()->create())
            ->getJson(route('shipments.show', ['shipment' => $shipment->tracking_number]));

        $response->assertOk()->assertJsonCount(1, 'histories');
    }

    public function test_un_tracking_number_inexistente_devuelve_404(): void
    {
        $this->getJson(route('shipments.show', ['shipment' => 'NO-EXISTE']))
            ->assertNotFound();
    }

    public function test_un_envio_no_cacheado_se_sirve_desde_la_base_de_datos(): void
    {
        $shipment = Shipment::factory()->enTransito()->create();

        $this->getJson(route('shipments.show', ['shipment' => $shipment->tracking_number]))
            ->assertOk()
            ->assertJsonPath('tracking_number', $shipment->tracking_number);
    }
}
