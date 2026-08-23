<?php

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Botón vincular/eliminar de la tarjeta de resultado de ⚡searchfield.
 *
 * La llamada a la API interna va con `Http::fake`: aquí se comprueba que el
 * componente pide lo que debe y pinta el botón correcto. Que la pivote acabe
 * como toca lo cubre ShipmentUserTest sobre el endpoint de verdad.
 */
class SearchfieldLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_un_invitado_no_ve_el_boton_de_vincular(): void
    {
        $shipment = Shipment::factory()->create();

        // Sin sesión la API solo devuelve los datos públicos, sin histórico.
        $this->fakeShow($shipment->tracking_number, withHistories: false);

        Livewire::test('searchfield')
            ->set('tracking_number', $shipment->tracking_number)
            ->call('search')
            ->assertSet('linked', false)
            ->assertDontSee(__('Vincular'))
            ->assertDontSee(__('Eliminar'));
    }

    public function test_un_envio_sin_vincular_muestra_el_boton_vincular(): void
    {
        $cliente = User::factory()->create();
        $shipment = Shipment::factory()->create();

        $this->fakeShow($shipment->tracking_number);

        Livewire::actingAs($cliente)
            ->test('searchfield')
            ->set('tracking_number', $shipment->tracking_number)
            ->call('search')
            ->assertSet('linked', false)
            ->assertSee(__('Vincular'))
            ->assertDontSee(__('Eliminar'));
    }

    public function test_un_envio_ya_vinculado_muestra_el_boton_eliminar(): void
    {
        $cliente = User::factory()->create();
        $shipment = Shipment::factory()->create();
        $shipment->users()->attach($cliente);

        $this->fakeShow($shipment->tracking_number);

        Livewire::actingAs($cliente)
            ->test('searchfield')
            ->set('tracking_number', $shipment->tracking_number)
            ->call('search')
            ->assertSet('linked', true)
            ->assertSee(__('Eliminar'))
            ->assertDontSee(__('Vincular'));
    }

    public function test_vincular_llama_al_endpoint_de_la_pivote(): void
    {
        $cliente = User::factory()->create();
        $shipment = Shipment::factory()->create();

        $this->fakeShow($shipment->tracking_number);

        Livewire::actingAs($cliente)
            ->test('searchfield')
            ->set('tracking_number', $shipment->tracking_number)
            ->call('search')
            ->call('link')
            ->assertSet('linked', true)
            ->assertSet('errorMessage', null)
            ->assertSee(__('Eliminar'));

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === $this->apiUrl("shipments/{$shipment->tracking_number}/users"));
    }

    public function test_eliminar_llama_al_endpoint_de_la_pivote(): void
    {
        $cliente = User::factory()->create();
        $shipment = Shipment::factory()->create();
        $shipment->users()->attach($cliente);

        $this->fakeShow($shipment->tracking_number);

        Livewire::actingAs($cliente)
            ->test('searchfield')
            ->set('tracking_number', $shipment->tracking_number)
            ->call('search')
            ->call('unlink')
            ->assertSet('linked', false)
            ->assertSet('errorMessage', null)
            ->assertSee(__('Vincular'));

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && $request->url() === $this->apiUrl("shipments/{$shipment->tracking_number}/users"));
    }

    public function test_si_la_api_falla_al_vincular_el_boton_no_cambia(): void
    {
        $cliente = User::factory()->create();
        $shipment = Shipment::factory()->create();

        $this->fakeShow($shipment->tracking_number, usersStatus: 500);

        Livewire::actingAs($cliente)
            ->test('searchfield')
            ->set('tracking_number', $shipment->tracking_number)
            ->call('search')
            ->call('link')
            ->assertSet('linked', false)
            ->assertSee(__('Vincular'));
    }

    /**
     * La API interna, tal y como la ve el componente: `shipments.show` con
     * histórico si hay sesión (recortada a los campos públicos si no) y el
     * endpoint de la pivote, que por defecto responde que sí.
     */
    private function fakeShow(string $trackingNumber, bool $withHistories = true, int $usersStatus = 200): void
    {
        $payload = Shipment::where('tracking_number', $trackingNumber)->firstOrFail();

        Http::fake([
            $this->apiUrl("shipments/{$trackingNumber}/users") => Http::response(status: $usersStatus),
            $this->apiUrl("shipments/{$trackingNumber}") => Http::response(
                $withHistories
                    ? $payload->load('histories')->toArray()
                    : $payload->only(['tracking_number', 'status', 'origin', 'destination', 'estimated_delivery_date'])
            ),
        ]);
    }

    private function apiUrl(string $path): string
    {
        return rtrim((string) config('services.internal_api.url'), '/')."/api/{$path}";
    }
}
