<?php

namespace Tests\Feature\Api;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\ShipmentHistory;
use App\Models\User;
use App\Observers\ShipmentObserver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * El primer evento del historial lo crea `ShipmentObserver::created()` a partir
 * del bloque `history` que trae el alta. Lo que decide si se crea o no es que
 * ese bloque venga informado, no de dónde salga la petición: así el backoffice
 * lo genera siempre y las altas que no lo mandan siguen comportándose igual
 * que antes.
 */
class ShipmentInitialHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        Cache::store('redis')->flush();

        parent::tearDown();
    }

    private function empleado(): User
    {
        return User::factory()->create()->assignRole('agente');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
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

    public function test_el_alta_con_historial_crea_su_primer_evento(): void
    {
        $response = $this->actingAs($this->empleado())
            ->postJson(route('shipments.store'), $this->datosValidos([
                'history' => [
                    'status' => ShipmentStatus::Pendiente->value,
                    'location' => 'Centro logístico de Madrid',
                    'description' => 'Pedido registrado en el sistema.',
                    'recorded_at' => '2026-08-20 09:30:00',
                ],
            ]));

        $response->assertCreated();

        $history = ShipmentHistory::sole();
        $this->assertSame(Shipment::sole()->id, $history->shipment_id);
        $this->assertSame(ShipmentStatus::Pendiente, $history->status);
        $this->assertSame('Centro logístico de Madrid', $history->location);
        $this->assertSame('2026-08-20 09:30:00', $history->recorded_at->format('Y-m-d H:i:s'));
    }

    public function test_el_alta_sin_historial_no_crea_ningun_evento(): void
    {
        $this->actingAs($this->empleado())
            ->postJson(route('shipments.store'), $this->datosValidos())
            ->assertCreated();

        $this->assertSame(0, ShipmentHistory::count());
    }

    public function test_la_factory_sigue_creando_envios_sin_historial(): void
    {
        Shipment::factory()->create();

        $this->assertSame(0, ShipmentHistory::count());
    }

    public function test_ubicacion_y_descripcion_son_opcionales(): void
    {
        $this->actingAs($this->empleado())
            ->postJson(route('shipments.store'), $this->datosValidos([
                'history' => [
                    'status' => ShipmentStatus::Pendiente->value,
                    'recorded_at' => now()->toDateTimeString(),
                ],
            ]))
            ->assertCreated();

        $history = ShipmentHistory::sole();
        $this->assertNull($history->location);
        $this->assertNull($history->description);
    }

    public function test_un_estado_invalido_en_el_historial_se_rechaza(): void
    {
        $this->actingAs($this->empleado())
            ->postJson(route('shipments.store'), $this->datosValidos([
                'history' => [
                    'status' => 'inventado',
                    'recorded_at' => now()->toDateTimeString(),
                ],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('history.status');

        $this->assertSame(0, Shipment::count());
    }

    public function test_el_historial_sin_fecha_se_rechaza(): void
    {
        $this->actingAs($this->empleado())
            ->postJson(route('shipments.store'), $this->datosValidos([
                'history' => ['status' => ShipmentStatus::Pendiente->value],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('history.recorded_at');
    }

    public function test_history_no_se_guarda_como_columna_del_envio(): void
    {
        $this->actingAs($this->empleado())
            ->postJson(route('shipments.store'), $this->datosValidos([
                'history' => [
                    'status' => ShipmentStatus::Pendiente->value,
                    'recorded_at' => now()->toDateTimeString(),
                ],
            ]))
            ->assertCreated()
            // `initialHistory` es una propiedad PHP, no un atributo Eloquent:
            // ni se persiste ni se serializa en la respuesta.
            ->assertJsonMissingPath('history')
            ->assertJsonMissingPath('initialHistory');
    }

    public function test_un_envio_que_nace_entregado_se_cachea_con_su_historial(): void
    {
        // `created` corre antes que `saved`, así que cuando el observer escribe
        // en Redis el evento inicial ya existe.
        $this->actingAs($this->empleado())
            ->postJson(route('shipments.store'), $this->datosValidos([
                'status' => ShipmentStatus::Entregado->value,
                'history' => [
                    'status' => ShipmentStatus::Entregado->value,
                    'recorded_at' => now()->toDateTimeString(),
                ],
            ]))
            ->assertCreated();

        $cached = Cache::store('redis')->get(ShipmentObserver::cacheKey('TRC-0000000001'));

        $this->assertNotNull($cached);
        $this->assertCount(1, $cached['histories']);
    }
}
