<?php

namespace Tests\Feature\Backoffice;

use App\Enums\ShipmentStatus;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La pantalla de detalle de un pedido: leer, editar y borrar el que dice la
 * URL, y el enlace que lleva hasta ella desde el listado.
 *
 * Como el resto del backoffice no consulta Eloquent sino nuestra propia API,
 * así que aquí esa llamada se finge y lo que se comprueba es qué se pinta y
 * qué se manda.
 */
class ShipmentDetailTest extends TestCase
{
    use RefreshDatabase;

    private const GUIA = 'TRC-0000000001';

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
     * El pedido tal y como lo devuelve la rama autenticada de `shipments.show`.
     *
     * @return array<string, mixed>
     */
    private function pedido(): array
    {
        return [
            'id' => 1,
            'tracking_number' => self::GUIA,
            'receiver_name' => 'Ana Destinataria',
            'origin' => 'Madrid, España',
            'destination' => 'Lisboa, Portugal',
            'estimated_delivery_date' => '2026-09-02',
            'status' => ShipmentStatus::EnTransito->value,
            'company_id' => 3,
            'histories' => [
                [
                    'status' => ShipmentStatus::Pendiente->value,
                    'location' => 'Centro logístico de Madrid',
                    'description' => 'Pedido registrado en el sistema.',
                    'recorded_at' => '2026-08-28 09:00:00',
                ],
                [
                    'status' => ShipmentStatus::EnTransito->value,
                    'location' => 'Salamanca',
                    'description' => 'En ruta hacia el destino.',
                    'recorded_at' => '2026-08-29 18:30:00',
                ],
            ],
        ];
    }

    /**
     * API fingida que discrimina por método, como en `ShipmentFormTest`: el GET
     * es la carga de la pantalla, y el PUT/DELETE lo que se quiere comprobar.
     */
    private function fingirApi(int $estadoEscritura = 200): void
    {
        $pedido = $this->pedido();

        Http::fake(function (Request $request) use ($pedido, $estadoEscritura) {
            if ($request->method() === 'GET') {
                return Http::response($pedido);
            }

            return Http::response($pedido, $estadoEscritura);
        });
    }

    public function test_un_usuario_sin_rol_no_entra_al_detalle(): void
    {
        $cliente = User::factory()->create();

        $this->actingAs($cliente)
            ->get(route('backoffice.shipments.show', ['tracking_number' => self::GUIA]))
            ->assertForbidden();
    }

    public function test_sin_sesion_el_detalle_lleva_al_login(): void
    {
        $this->get(route('backoffice.shipments.show', ['tracking_number' => self::GUIA]))
            ->assertRedirect(route('login'));
    }

    public function test_el_listado_enlaza_al_detalle_de_cada_pedido(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [$this->pedido()],
                'current_page' => 1,
                'last_page' => 1,
                'total' => 1,
            ]),
        ]);

        $this->actingAs($this->empleado())
            ->get(route('backoffice.shipments'))
            ->assertOk()
            ->assertSee(route('backoffice.shipments.show', ['tracking_number' => self::GUIA]))
            ->assertSee('Ver pedido');
    }

    public function test_el_detalle_carga_el_pedido_en_el_formulario(): void
    {
        $this->fingirApi();

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipment-detail', ['tracking_number' => self::GUIA])
            ->assertOk()
            ->assertSet('editing', self::GUIA)
            ->assertSet('tracking_number', self::GUIA)
            ->assertSet('receiver_name', 'Ana Destinataria')
            ->assertSet('origin', 'Madrid, España')
            ->assertSet('destination', 'Lisboa, Portugal')
            ->assertSet('estimated_delivery_date', '2026-09-02')
            ->assertSet('status', ShipmentStatus::EnTransito->value)
            // Las tres acciones del pedido, juntas bajo el formulario.
            ->assertSee('Nuevo evento')
            ->assertSee('Eliminar pedido')
            ->assertSee('Guardar cambios');
    }

    public function test_guardar_manda_los_cambios_a_la_api(): void
    {
        $this->fingirApi();

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipment-detail', ['tracking_number' => self::GUIA])
            ->set('receiver_name', 'Beto Destinatario')
            ->set('status', ShipmentStatus::Entregado->value)
            ->call('save')
            ->assertHasNoErrors();

        Http::assertSent(function (Request $request) {
            if ($request->method() !== 'PUT') {
                return false;
            }

            return str_ends_with($request->url(), '/api/shipments/'.self::GUIA)
                && $request->data()['receiver_name'] === 'Beto Destinatario'
                && $request->data()['status'] === ShipmentStatus::Entregado->value;
        });
    }

    public function test_cambiar_la_guia_lleva_a_la_nueva_url(): void
    {
        $this->fingirApi();

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipment-detail', ['tracking_number' => self::GUIA])
            ->set('tracking_number', 'TRC-0000000002')
            ->call('save')
            ->assertRedirect(route('backoffice.shipments.show', ['tracking_number' => 'TRC-0000000002']));
    }

    public function test_la_api_decide_la_validacion_y_sus_errores_caen_en_los_campos(): void
    {
        Http::fake(function (Request $request) {
            if ($request->method() === 'GET') {
                return Http::response($this->pedido());
            }

            return Http::response([
                'message' => 'The given data was invalid.',
                'errors' => ['tracking_number' => ['Ya existe un pedido con esa guía.']],
            ], 422);
        });

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipment-detail', ['tracking_number' => self::GUIA])
            ->set('tracking_number', 'TRC-0000000002')
            ->call('save')
            ->assertHasErrors('tracking_number')
            ->assertNoRedirect();
    }

    public function test_eliminar_borra_el_pedido_y_vuelve_al_listado(): void
    {
        $this->fingirApi();

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipment-detail', ['tracking_number' => self::GUIA])
            ->call('destroy')
            ->assertRedirect(route('backoffice.shipments'));

        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/api/shipments/'.self::GUIA));
    }

    public function test_una_guia_que_no_existe_no_rompe_la_pantalla(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Not found'], 404)]);

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipment-detail', ['tracking_number' => 'TRC-9999999999'])
            ->assertOk()
            ->assertSet('shipment', [])
            ->assertSee('No encontramos el registro.');
    }
}
