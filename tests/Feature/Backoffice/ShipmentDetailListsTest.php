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
 * Las listas de la pantalla de detalle: el historial del pedido, las cuentas
 * que lo siguen y sus documentos adjuntos.
 *
 * Son componentes propios justo para poder refrescarse solos, así que se
 * prueban solos: cada uno con su API fingida, sin montar la pantalla entera.
 */
class ShipmentDetailListsTest extends TestCase
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
     * @param  list<array<string, mixed>>  $filas
     */
    private function paginado(array $filas): array
    {
        return [
            'data' => $filas,
            'current_page' => 1,
            'last_page' => 1,
            'total' => count($filas),
            'from' => 1,
            'to' => count($filas),
        ];
    }

    // --- Historial ---------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    private function eventos(): array
    {
        return [
            [
                'id' => 7,
                'shipment_id' => 1,
                'status' => ShipmentStatus::Pendiente->value,
                'location' => 'Centro logístico de Madrid',
                'description' => 'Pedido registrado en el sistema.',
                'recorded_at' => '2026-08-28 09:00:00',
            ],
            [
                'id' => 8,
                'shipment_id' => 1,
                'status' => ShipmentStatus::EnTransito->value,
                'location' => 'Salamanca',
                'description' => 'En ruta hacia el destino.',
                'recorded_at' => '2026-08-29 18:30:00',
            ],
        ];
    }

    public function test_el_historial_pide_solo_los_eventos_de_ese_pedido(): void
    {
        Http::fake(['*' => Http::response($this->paginado($this->eventos()))]);

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipment-history-list', ['shipmentId' => 1])
            ->assertOk()
            ->assertSee('En ruta hacia el destino.')
            ->assertSee('Salamanca');

        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && str_contains($request->url(), '/api/shipment-histories')
            && $request->data()['shipment_id'] === 1);
    }

    public function test_sin_pedido_el_historial_no_llama_a_la_api(): void
    {
        Http::fake();

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipment-history-list')
            ->assertOk()
            ->assertSet('histories', [])
            ->assertSee('Sin eventos');

        Http::assertNothingSent();
    }

    public function test_editar_un_evento_carga_sus_datos_y_guarda_los_cambios(): void
    {
        $evento = $this->eventos()[1];

        Http::fake(function (Request $request) use ($evento) {
            if ($request->method() === 'GET') {
                return str_contains($request->url(), '/api/shipment-histories/')
                    ? Http::response($evento)
                    : Http::response($this->paginado($this->eventos()));
            }

            return Http::response($evento);
        });

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipment-history-list', ['shipmentId' => 1])
            ->call('edit', 8)
            ->assertSet('editing', 8)
            ->assertSet('status', ShipmentStatus::EnTransito->value)
            ->assertSet('location', 'Salamanca')
            ->set('location', 'Valladolid')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('editing', null);

        Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
            && str_ends_with($request->url(), '/api/shipment-histories/8')
            && $request->data()['location'] === 'Valladolid');
    }

    public function test_crear_un_evento_lo_manda_con_el_pedido_de_la_pantalla(): void
    {
        Http::fake(function (Request $request) {
            return $request->method() === 'GET'
                ? Http::response($this->paginado($this->eventos()))
                : Http::response($this->eventos()[1], 201);
        });

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipment-history-list', ['shipmentId' => 1])
            // Lo dispara el botón de la pantalla de detalle, no un click aquí.
            ->dispatch('crear-evento-historial')
            ->assertSet('editing', null)
            // Prefill: el último evento cargado es el estado actual del pedido.
            ->assertSet('status', ShipmentStatus::EnTransito->value)
            ->set('location', 'Aduana de Vilar Formoso')
            ->set('description', 'Retenido para despacho.')
            ->call('save')
            ->assertHasNoErrors();

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/api/shipment-histories')
            && $request->data()['shipment_id'] === 1
            && $request->data()['location'] === 'Aduana de Vilar Formoso');
    }

    public function test_sin_pedido_no_se_puede_crear_un_evento(): void
    {
        Http::fake();

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipment-history-list')
            ->dispatch('crear-evento-historial')
            ->set('description', 'Un evento sin pedido al que pertenecer.')
            ->call('save');

        Http::assertNothingSent();
    }

    public function test_las_horas_se_leen_en_la_zona_del_proyecto_y_no_en_utc(): void
    {
        // Tal y como las serializa la API: en UTC y con Z, aunque el proyecto
        // viva en Europe/Madrid. En agosto son dos horas de diferencia.
        $evento = [
            'id' => 9,
            'shipment_id' => 1,
            'status' => ShipmentStatus::EnAduana->value,
            'location' => 'Vilar Formoso',
            'description' => 'Retenido para despacho.',
            'recorded_at' => '2026-08-29T16:30:00.000000Z',
        ];

        Http::fake(function (Request $request) use ($evento) {
            if ($request->method() === 'GET') {
                return str_contains($request->url(), '/api/shipment-histories/')
                    ? Http::response($evento)
                    : Http::response($this->paginado([$evento]));
            }

            return Http::response($evento);
        });

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipment-history-list', ['shipmentId' => 1])
            ->assertSee('18:30')
            ->assertDontSee('16:30')
            // Y el formulario recibe la misma hora local: sin esto, guardar sin
            // tocar la fecha movería el evento dos horas atrás.
            ->call('edit', 9)
            ->assertSet('recorded_at', '2026-08-29T18:30');
    }

    // --- Usuarios vinculados -----------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    private function vinculados(): array
    {
        return [
            ['id' => 4, 'name' => 'Ana Cliente', 'email' => 'ana@example.com'],
            ['id' => 5, 'name' => 'Beto Cliente', 'email' => 'beto@example.com'],
        ];
    }

    public function test_los_usuarios_vinculados_se_listan_con_su_correo(): void
    {
        Http::fake(['*' => Http::response($this->paginado($this->vinculados()))]);

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipment-user-list', ['trackingNumber' => self::GUIA])
            ->assertOk()
            ->assertSee('Ana Cliente')
            ->assertSee('beto@example.com');

        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && str_contains($request->url(), '/api/shipments/'.self::GUIA.'/users'));
    }

    public function test_quitar_un_vinculo_llama_al_endpoint_de_esa_cuenta(): void
    {
        Http::fake(function (Request $request) {
            return $request->method() === 'GET'
                ? Http::response($this->paginado($this->vinculados()))
                : Http::response(null, 204);
        });

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipment-user-list', ['trackingNumber' => self::GUIA])
            ->call('confirmDetach', 4)
            ->assertSet('detaching', 4)
            ->call('detach')
            ->assertSet('detaching', null);

        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/api/shipments/'.self::GUIA.'/users/4'));
    }

    public function test_un_fallo_de_la_api_deja_la_lista_con_su_mensaje(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Boom'], 500)]);

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipment-user-list', ['trackingNumber' => self::GUIA])
            ->assertOk()
            ->assertSet('users', [])
            ->assertSee('No pudimos cargar los usuarios vinculados.');
    }

    // --- Documentos ----------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    private function documentos(): array
    {
        return [
            [
                'id' => 11,
                'shipment_id' => 1,
                'document_name' => 'factura-comercial.pdf',
                'status' => 'aprobado',
            ],
            [
                'id' => 12,
                'shipment_id' => 1,
                'document_name' => 'despacho-aduana.pdf',
                'status' => 'pendiente de revisión',
            ],
        ];
    }

    public function test_los_documentos_piden_solo_los_de_ese_pedido(): void
    {
        Http::fake(['*' => Http::response($this->paginado($this->documentos()))]);

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipment-document-list', ['shipmentId' => 1])
            ->assertOk()
            ->assertSee('factura-comercial.pdf')
            ->assertSee('despacho-aduana.pdf');

        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && str_contains($request->url(), '/api/documents')
            && $request->data()['shipment_id'] === 1);
    }

    public function test_sin_pedido_los_documentos_no_llaman_a_la_api(): void
    {
        Http::fake();

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipment-document-list')
            ->assertOk()
            ->assertSet('documents', [])
            ->assertSee('Sin documentos');

        Http::assertNothingSent();
    }

    public function test_un_fallo_de_la_api_deja_los_documentos_con_su_mensaje(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Boom'], 500)]);

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipment-document-list', ['shipmentId' => 1])
            ->assertOk()
            ->assertSet('documents', [])
            ->assertSee('No pudimos cargar los documentos de este pedido.');
    }
}
