<?php

namespace Tests\Feature\Backoffice;

use App\Enums\ShipmentStatus;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El formulario de pedidos del backoffice, contra una API fingida.
 *
 * Lo que se comprueba aquí es el otro extremo de `ShipmentInitialHistoryTest`:
 * que la pantalla mande de verdad el bloque `history` que el observer espera, y
 * que lo que la API responda acabe donde el usuario pueda verlo.
 */
class ShipmentFormTest extends TestCase
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

    private function superadmin(): User
    {
        return User::factory()->create()->assignRole('superadministrador');
    }

    /**
     * API fingida que discrimina por método, no por orden de llamada: los
     * listados llevan `?page=1` en la URL, así que un patrón por ruta no
     * distingue el GET del listado del POST del alta.
     *
     * @param  callable(Request): (Response|PromiseInterface)|null  $escritura
     */
    private function fingirApi(?callable $escritura = null): void
    {
        $listado = Http::response(['data' => [], 'current_page' => 1, 'last_page' => 1, 'total' => 0]);

        Http::fake(function (Request $request) use ($escritura, $listado) {
            if ($request->method() === 'GET') {
                return $listado;
            }

            return $escritura
                ? $escritura($request)
                : Http::response(['id' => 1, 'tracking_number' => 'TRC-0000000001'], 201);
        });
    }

    public function test_el_alta_manda_el_bloque_de_historial(): void
    {
        $this->fingirApi();

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipments')
            ->call('create')
            ->set('tracking_number', 'TRC-0000000001')
            ->set('receiver_name', 'Ana Destinataria')
            ->set('origin', 'Madrid, España')
            ->set('destination', 'Lisboa, Portugal')
            ->set('estimated_delivery_date', '2026-09-02')
            ->set('history.location', 'Centro logístico de Madrid')
            ->set('history.description', 'Pedido registrado en el sistema.')
            ->call('save');

        Http::assertSent(function (Request $request) {
            if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/api/shipments')) {
                return false;
            }

            $data = $request->data();

            return $data['tracking_number'] === 'TRC-0000000001'
                && $data['history']['status'] === ShipmentStatus::Pendiente->value
                && $data['history']['location'] === 'Centro logístico de Madrid'
                && filled($data['history']['recorded_at']);
        });
    }

    public function test_la_edicion_no_manda_historial(): void
    {
        Http::fake([
            '*/api/shipments/TRC-0000000001' => Http::response([
                'id' => 1,
                'tracking_number' => 'TRC-0000000001',
                'receiver_name' => 'Ana Destinataria',
                'origin' => 'Madrid, España',
                'destination' => 'Lisboa, Portugal',
                'estimated_delivery_date' => '2026-09-02',
                'status' => 'en_transito',
                'company_id' => null,
            ]),
            '*' => Http::response(['data' => [], 'current_page' => 1, 'last_page' => 1, 'total' => 0]),
        ]);

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipments')
            ->call('edit', 'TRC-0000000001')
            ->set('receiver_name', 'Ana Corregida')
            ->call('save');

        Http::assertSent(function (Request $request) {
            if ($request->method() !== 'PUT') {
                return false;
            }

            // El historial de un pedido que ya existe se toca desde su propia
            // pantalla, no reenviando un "primer evento" en cada edición.
            return ! array_key_exists('history', $request->data());
        });
    }

    public function test_los_errores_de_validacion_de_la_api_caen_en_su_campo(): void
    {
        $this->fingirApi(fn () => Http::response([
            'message' => 'The given data was invalid.',
            'errors' => [
                'tracking_number' => ['El número de seguimiento ya está en uso.'],
                'history.recorded_at' => ['La fecha del evento no es válida.'],
            ],
        ], 422));

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipments')
            ->call('create')
            ->set('tracking_number', 'TRC-REPETIDO')
            // Ubicación y descripción son obligatorias en la pantalla: sin
            // ellas no se llegaría a llamar a la API.
            ->set('history.location', 'Centro logístico de Madrid')
            ->set('history.description', 'Pedido registrado en el sistema.')
            ->call('save')
            ->assertHasErrors('tracking_number')
            ->assertHasErrors('history.recorded_at')
            // Un 422 es un problema del formulario, no de la pantalla: no debe
            // pintar además la alerta roja de "no pudimos guardar".
            ->assertSet('errorMessage', null);
    }

    public function test_un_403_de_la_api_se_explica_por_el_rol(): void
    {
        Http::fake(['*' => Http::response(['message' => 'User does not have the right permissions.'], 403)]);

        Livewire::actingAs($this->empleado())
            ->test('backoffice.companies')
            ->assertSet('errorMessage', 'Tu rol no tiene permiso para esta operación.')
            ->assertSet('companies', []);
    }

    public function test_el_borrado_llama_al_endpoint_del_pedido(): void
    {
        Http::fake([
            '*/api/shipments/TRC-0000000001' => Http::response(status: 204),
            '*' => Http::response(['data' => [], 'current_page' => 1, 'last_page' => 1, 'total' => 0]),
        ]);

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipments')
            ->call('confirmDelete', 'TRC-0000000001')
            ->assertSet('deleting', 'TRC-0000000001')
            ->call('destroy')
            ->assertSet('deleting', null);

        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/api/shipments/TRC-0000000001'));
    }

    public function test_la_paginacion_pide_la_pagina_siguiente(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [['id' => 1, 'tracking_number' => 'TRC-1', 'receiver_name' => 'A', 'origin' => 'X', 'destination' => 'Y', 'estimated_delivery_date' => '2026-09-02', 'status' => 'pendiente']],
                'current_page' => 1,
                'last_page' => 3,
                'total' => 3,
            ]),
        ]);

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipments')
            ->call('nextPage')
            ->assertSet('page', 2);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'page=2'));
    }

    public function test_cambiar_el_filtro_vuelve_a_la_primera_pagina(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [],
                'current_page' => 1,
                'last_page' => 5,
                'total' => 60,
            ]),
        ]);

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipments')
            ->call('nextPage')
            ->assertSet('page', 2)
            // Filtrar desde la página 2 dejaría un listado vacío sin que se
            // entienda por qué.
            ->set('search', 'lisboa')
            ->assertSet('page', 1);
    }

    public function test_la_ubicacion_y_la_descripcion_son_obligatorias_en_el_alta(): void
    {
        $this->fingirApi();

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipments')
            ->call('create')
            ->set('tracking_number', 'TRC-0000000001')
            ->set('receiver_name', 'Ana Destinataria')
            ->set('origin', 'Madrid, España')
            ->set('destination', 'Lisboa, Portugal')
            ->set('estimated_delivery_date', '2026-09-02')
            ->call('save')
            ->assertHasErrors(['history.location', 'history.description']);

        // La validación es de la pantalla, así que corta antes de llamar a la API.
        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
    }

    public function test_el_alta_no_manda_estado_del_evento_ni_fecha_tecleados(): void
    {
        $this->fingirApi();

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipments')
            ->call('create')
            ->set('tracking_number', 'TRC-0000000001')
            ->set('history.location', 'Centro logístico de Madrid')
            ->set('history.description', 'Pedido registrado en el sistema.')
            ->call('save');

        Http::assertSent(function (Request $request) {
            if ($request->method() !== 'POST') {
                return false;
            }

            $data = $request->data();

            // El pedido nace pendiente y su primer evento lleva la fecha del
            // alta: ninguna de las dos cosas se pregunta en el formulario.
            return $data['status'] === ShipmentStatus::Pendiente->value
                && $data['history']['status'] === ShipmentStatus::Pendiente->value
                && Carbon::parse($data['history']['recorded_at'])->diffInMinutes(now()) < 1;
        });
    }

    public function test_un_agente_no_elige_empresa_y_no_la_manda(): void
    {
        $company = Company::factory()->create();
        $agente = User::factory()->create(['company_id' => $company->id])->assignRole('agente');

        $this->fingirApi();

        Livewire::actingAs($agente)
            ->test('backoffice.shipments')
            ->call('create')
            ->assertSet('companies', [])
            ->set('tracking_number', 'TRC-0000000001')
            ->set('history.location', 'Centro logístico de Madrid')
            ->set('history.description', 'Pedido registrado en el sistema.')
            ->call('save');

        // Ni siquiera pide la lista: se sabe por el rol, sin gastar un 403.
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/api/companies'));

        // Sin `company_id` en el payload, la API le asigna la del usuario.
        Http::assertSent(fn (Request $request) => $request->method() !== 'POST'
            || ! array_key_exists('company_id', $request->data()));
    }

    public function test_un_superadministrador_si_elige_empresa(): void
    {
        $this->fingirApi();

        Livewire::actingAs($this->superadmin())
            ->test('backoffice.shipments')
            ->call('create')
            ->set('tracking_number', 'TRC-0000000001')
            ->set('history.location', 'Centro logístico de Madrid')
            ->set('history.description', 'Pedido registrado en el sistema.')
            ->set('company_id', 7)
            ->call('save');

        Http::assertSent(fn (Request $request) => $request->method() !== 'POST'
            || $request->data()['company_id'] === 7);
    }

    public function test_el_alta_no_pregunta_estado_ni_fecha_del_evento(): void
    {
        $this->fingirApi();

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipments')
            ->call('create')
            // Se piden mezclados con el resto del pedido, sin bloque aparte.
            ->assertSee('Ubicación actual')
            ->assertSee('Descripción')
            // El estado es siempre pendiente y la fecha es la del alta.
            ->assertDontSee('Estado del evento')
            ->assertDontSee('Fecha del evento')
            ->assertDontSee('Primer evento del historial');
    }

    public function test_la_edicion_si_permite_cambiar_el_estado(): void
    {
        Http::fake([
            '*/api/shipments/TRC-0000000001' => Http::response([
                'id' => 1,
                'tracking_number' => 'TRC-0000000001',
                'receiver_name' => 'Ana Destinataria',
                'origin' => 'Madrid, España',
                'destination' => 'Lisboa, Portugal',
                'estimated_delivery_date' => '2026-09-02',
                'status' => 'en_transito',
                'company_id' => null,
            ]),
            '*' => Http::response(['data' => [], 'current_page' => 1, 'last_page' => 1, 'total' => 0]),
        ]);

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipments')
            ->call('edit', 'TRC-0000000001')
            ->assertSee('Estado')
            // El primer evento solo se teclea al crear.
            ->assertDontSee('Ubicación actual');
    }

    public function test_un_agente_no_ve_el_campo_empresa_y_un_superadministrador_si(): void
    {
        $this->fingirApi();

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipments')
            ->call('create')
            ->assertDontSee('Empresa');

        Livewire::actingAs($this->superadmin())
            ->test('backoffice.shipments')
            ->call('create')
            ->assertSee('Empresa');
    }
}
