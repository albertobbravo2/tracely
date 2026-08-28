<?php

namespace Tests\Feature\Backoffice;

use App\Enums\ShipmentStatus;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La pantalla de historial de pedidos del backoffice, contra una API fingida.
 *
 * Aquí no se dan de alta eventos —el primero lo crea `ShipmentObserver` con el
 * pedido—, así que lo que se comprueba es lo que sí hace: filtrar por guía,
 * corregir un evento y borrarlo.
 */
class ShipmentHistoryFormTest extends TestCase
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
     * @param  list<array<string, mixed>>  $eventos
     * @param  list<array<string, mixed>>  $envios
     */
    private function fingirApi(array $eventos = [], ?array $envios = null): void
    {
        $envios ??= [['id' => 42, 'tracking_number' => 'TRC-0000000001']];

        Http::fake(function (Request $request) use ($eventos, $envios) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/api/shipments?')) {
                return $this->pagina($envios);
            }

            if ($request->method() === 'GET') {
                return $this->pagina($eventos);
            }

            return Http::response(['id' => 9]);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function pagina(array $filas): PromiseInterface
    {
        return Http::response([
            'data' => $filas,
            'current_page' => 1,
            'last_page' => 1,
            'total' => count($filas),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function evento(): array
    {
        return [
            'id' => 9,
            'shipment_id' => 42,
            'status' => ShipmentStatus::EnTransito->value,
            'location' => 'Centro logístico de Madrid',
            'description' => 'Salida del centro logístico.',
            'recorded_at' => '2026-08-20T10:30:00.000000Z',
            'shipment' => ['id' => 42, 'tracking_number' => 'TRC-0000000001'],
        ];
    }

    public function test_sin_filtro_el_listado_no_resuelve_ninguna_guia(): void
    {
        $this->fingirApi();

        Livewire::actingAs($this->empleado())->test('backoffice.shipment-histories');

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/api/shipments?'));

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/api/shipment-histories')
            && ! str_contains($request->url(), 'shipment_id'));
    }

    public function test_el_filtro_resuelve_la_guia_a_su_id(): void
    {
        $this->fingirApi();

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipment-histories')
            ->set('trackingFilter', 'TRC-0000000001');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/api/shipment-histories')
            && str_contains($request->url(), 'shipment_id=42'));
    }

    public function test_una_guia_que_no_existe_deja_el_listado_vacio(): void
    {
        $this->fingirApi(envios: [['id' => 99, 'tracking_number' => 'TRC-OTRA']]);

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipment-histories')
            ->set('trackingFilter', 'TRC-NO-EXISTE')
            ->assertSet('histories', []);

        // Un id imposible: "no existe" no puede verse como "aquí está todo".
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/api/shipment-histories')
            && str_contains($request->url(), 'shipment_id=0'));
    }

    public function test_editar_carga_el_evento_en_el_formulario(): void
    {
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/api/shipment-histories/9')) {
                return Http::response($this->evento());
            }

            return $this->pagina([]);
        });

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipment-histories')
            ->call('edit', 9)
            ->assertSet('editing', 9)
            ->assertSet('status', ShipmentStatus::EnTransito->value)
            ->assertSet('location', 'Centro logístico de Madrid')
            ->assertSet('description', 'Salida del centro logístico.')
            // El input es `datetime-local`, que no entiende el ISO-8601 con
            // zona que devuelve la API.
            ->assertSet('recorded_at', '2026-08-20T10:30');
    }

    public function test_guardar_manda_el_evento_por_put(): void
    {
        Http::fake(function (Request $request) {
            if ($request->method() === 'GET' && str_ends_with($request->url(), '/api/shipment-histories/9')) {
                return Http::response($this->evento());
            }

            if ($request->method() === 'GET') {
                return $this->pagina([]);
            }

            return Http::response(['id' => 9]);
        });

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipment-histories')
            ->call('edit', 9)
            ->set('status', ShipmentStatus::EnAduana->value)
            ->set('location', 'Aduana de Lisboa')
            ->call('save');

        Http::assertSent(function (Request $request) {
            if ($request->method() !== 'PUT' || ! str_ends_with($request->url(), '/api/shipment-histories/9')) {
                return false;
            }

            $data = $request->data();

            return $data['status'] === ShipmentStatus::EnAduana->value
                && $data['location'] === 'Aduana de Lisboa'
                && $data['recorded_at'] === '2026-08-20T10:30';
        });
    }

    public function test_vaciar_ubicacion_y_descripcion_las_manda_a_null(): void
    {
        Http::fake(function (Request $request) {
            if ($request->method() === 'GET' && str_ends_with($request->url(), '/api/shipment-histories/9')) {
                return Http::response($this->evento());
            }

            if ($request->method() === 'GET') {
                return $this->pagina([]);
            }

            return Http::response(['id' => 9]);
        });

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipment-histories')
            ->call('edit', 9)
            ->set('location', '  ')
            ->set('description', '')
            ->call('save');

        // Son `nullable` en la API: así se borra lo que se tecleó mal, en vez
        // de dejar una cadena vacía en la línea de tiempo.
        Http::assertSent(fn (Request $request) => $request->method() !== 'PUT'
            || ($request->data()['location'] === null && $request->data()['description'] === null));
    }

    public function test_guardar_sin_evento_en_edicion_no_llama_a_la_api(): void
    {
        $this->fingirApi();

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipment-histories')
            ->assertSet('editing', null)
            ->call('save');

        // Esta pantalla no crea eventos: sin uno cargado no hay nada que
        // mandar, y desde luego no un alta.
        Http::assertNotSent(fn (Request $request) => in_array($request->method(), ['POST', 'PUT'], true));
    }

    public function test_el_borrado_llama_al_endpoint_del_evento(): void
    {
        Http::fake([
            '*/api/shipment-histories/9' => Http::response(status: 204),
            '*' => Http::response(['data' => [], 'current_page' => 1, 'last_page' => 1, 'total' => 0]),
        ]);

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipment-histories')
            ->call('confirmDelete', 9)
            ->assertSet('deleting', 9)
            ->call('destroy')
            ->assertSet('deleting', null);

        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/api/shipment-histories/9'));
    }

    public function test_el_modal_de_borrado_identifica_el_evento(): void
    {
        $this->fingirApi([$this->evento()]);

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipment-histories')
            ->call('confirmDelete', 9)
            // Un pedido tiene varios eventos: la guía sola no distingue cuál,
            // así que el modal la acompaña de la fecha.
            ->assertSee('TRC-0000000001 · 20 ago. 2026 · 10:30');
    }

    public function test_el_estado_vacio_lleva_a_la_pantalla_donde_nacen_los_eventos(): void
    {
        $this->fingirApi();

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipment-histories')
            ->assertSee('No hay eventos que mostrar')
            ->assertSee('Ir a Pedidos')
            ->assertSeeHtml(route('backoffice.shipments', absolute: false));
    }
}
