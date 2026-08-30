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
 * Botón vincular/eliminar de <livewire:shipment-card>.
 *
 * Vive en la tarjeta y no en la pantalla que la monta, así que aquí se prueba
 * la tarjeta suelta y, al final, que el botón sale de verdad en las dos
 * pantallas que la pintan.
 *
 * La API interna va con `Http::fake`: que la pivote acabe como toca lo cubre
 * ShipmentUserTest sobre el endpoint de verdad.
 */
class ShipmentCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    // ---------------------------------------------------------------
    // La tarjeta suelta
    // ---------------------------------------------------------------

    public function test_un_invitado_no_ve_el_boton(): void
    {
        $shipment = Shipment::factory()->create();

        Livewire::test('shipment-card', ['shipment' => $this->payload($shipment)])
            ->assertSet('linked', false)
            ->assertDontSee(__('Vincular'))
            ->assertDontSee(__('Eliminar'));
    }

    public function test_un_envio_sin_vincular_muestra_el_boton_vincular(): void
    {
        $cliente = User::factory()->create();
        $shipment = Shipment::factory()->create();

        Livewire::actingAs($cliente)
            ->test('shipment-card', ['shipment' => $this->payload($shipment)])
            ->assertSet('linked', false)
            ->assertSee(__('Vincular'))
            ->assertDontSee(__('Eliminar'));
    }

    public function test_un_envio_ya_vinculado_muestra_el_boton_eliminar(): void
    {
        $cliente = User::factory()->create();
        $shipment = Shipment::factory()->create();
        $shipment->users()->attach($cliente);

        Livewire::actingAs($cliente)
            ->test('shipment-card', ['shipment' => $this->payload($shipment)])
            ->assertSet('linked', true)
            ->assertSee(__('Eliminar'))
            ->assertDontSee(__('Vincular'));
    }

    public function test_el_estado_vinculado_se_puede_dar_hecho_desde_fuera(): void
    {
        // El dashboard lista justo los envíos vinculados, así que pasa
        // `linked` en vez de hacer que cada tarjeta lo consulte.
        $cliente = User::factory()->create();
        $shipment = Shipment::factory()->create();

        Livewire::actingAs($cliente)
            ->test('shipment-card', ['shipment' => $this->payload($shipment), 'linked' => true])
            ->assertSet('linked', true)
            ->assertSee(__('Eliminar'));
    }

    public function test_vincular_llama_al_endpoint_de_la_pivote(): void
    {
        $cliente = User::factory()->create();
        $shipment = Shipment::factory()->create();

        $this->fakePivot($shipment);

        Livewire::actingAs($cliente)
            ->test('shipment-card', ['shipment' => $this->payload($shipment)])
            ->call('link')
            ->assertSet('linked', true)
            ->assertSet('errorMessage', null)
            ->assertSee(__('Eliminar'));

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === $this->pivotUrl($shipment));
    }

    public function test_eliminar_llama_al_endpoint_de_la_pivote(): void
    {
        $cliente = User::factory()->create();
        $shipment = Shipment::factory()->create();
        $shipment->users()->attach($cliente);

        $this->fakePivot($shipment);

        Livewire::actingAs($cliente)
            ->test('shipment-card', ['shipment' => $this->payload($shipment)])
            ->call('unlink')
            ->assertSet('linked', false)
            ->assertSet('errorMessage', null)
            ->assertSee(__('Vincular'));

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && $request->url() === $this->pivotUrl($shipment));
    }

    public function test_si_la_api_falla_el_boton_no_cambia(): void
    {
        $cliente = User::factory()->create();
        $shipment = Shipment::factory()->create();

        $this->fakePivot($shipment, status: 500);

        Livewire::actingAs($cliente)
            ->test('shipment-card', ['shipment' => $this->payload($shipment)])
            ->call('link')
            ->assertSet('linked', false)
            ->assertSee(__('Vincular'))
            ->assertSee(__('No pudimos vincular el envío a tu cuenta.'));
    }

    // ---------------------------------------------------------------
    // El botón sale en las dos pantallas que pintan la tarjeta
    // ---------------------------------------------------------------

    public function test_el_buscador_pinta_la_tarjeta_con_su_boton(): void
    {
        $cliente = User::factory()->create();
        $shipment = Shipment::factory()->create();

        Http::fake([
            $this->apiUrl("shipments/{$shipment->tracking_number}") => Http::response($this->payload($shipment)),
        ]);

        Livewire::actingAs($cliente)
            ->test('searchfield')
            ->set('tracking_number', $shipment->tracking_number)
            ->call('search')
            ->assertSee(__('Vincular'));
    }

    public function test_el_dashboard_pinta_cada_tarjeta_con_su_boton(): void
    {
        $cliente = User::factory()->create();

        foreach (range(1, 2) as $ignored) {
            $shipment = Shipment::factory()->create();
            $shipment->users()->attach($cliente);
            ShipmentHistory::factory()->forShipment($shipment)->status(ShipmentStatus::EnTransito)->create();
        }

        Http::fake([
            $this->apiUrl('users/shipments') => Http::response(
                $cliente->shipments()->with('histories')->paginate(15)->toArray()
            ),
        ]);

        $html = Livewire::actingAs($cliente)->test('my-shipments')->html();

        // Un botón por tarjeta, y "Eliminar" porque todo lo listado está ya
        // vinculado: es la pantalla de los envíos propios.
        $this->assertSame(2, substr_count($html, __('Eliminar')));
        $this->assertStringNotContainsString(__('Vincular'), $html);
    }

    /**
     * El envío tal y como lo devuelve la API a un usuario autenticado.
     *
     * @return array<string, mixed>
     */
    private function payload(Shipment $shipment): array
    {
        return $shipment->load('histories')->toArray();
    }

    private function fakePivot(Shipment $shipment, int $status = 200): void
    {
        Http::fake([$this->pivotUrl($shipment) => Http::response(status: $status)]);
    }

    private function pivotUrl(Shipment $shipment): string
    {
        return $this->apiUrl("shipments/{$shipment->tracking_number}/users");
    }

    private function apiUrl(string $path): string
    {
        return rtrim((string) config('services.internal_api.url'), '/')."/api/{$path}";
    }
}
