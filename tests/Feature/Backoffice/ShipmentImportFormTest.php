<?php

namespace Tests\Feature\Backoffice;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El modal de importación de la pantalla de pedidos, contra una API fingida.
 *
 * El parseo del CSV se prueba en `Api\ShipmentImportTest`: aquí solo interesa
 * que la pantalla suba el fichero al endpoint y que enseñe lo que responda.
 */
class ShipmentImportFormTest extends TestCase
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

    private function csv(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'pedidos.csv',
            "tracking_number,receiver_name,origin,destination,estimated_delivery_date\n".
            "TRC-0000000001,Ana,Madrid,Lisboa,2026-10-01\n"
        );
    }

    /**
     * @param  array<string, mixed>|null  $informe
     */
    private function fingirApi(?array $informe = null, int $status = 200): void
    {
        $listado = Http::response(['data' => [], 'current_page' => 1, 'last_page' => 1, 'total' => 0]);

        Http::fake(function (Request $request) use ($listado, $informe, $status) {
            if ($request->method() === 'GET') {
                return $listado;
            }

            return Http::response($informe ?? [
                'total' => 1, 'created' => 1, 'failed' => 0, 'errors' => [],
            ], $status);
        });
    }

    public function test_el_fichero_se_sube_al_endpoint_de_importacion(): void
    {
        $this->fingirApi();

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipments')
            ->call('openImport')
            ->set('file', $this->csv())
            ->call('import')
            ->assertSet('importError', null)
            ->assertSet('importResult.created', 1);

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && str_contains($request->url(), '/api/shipments/import')
            && $request->isMultipart());
    }

    public function test_el_informe_de_filas_fallidas_llega_a_la_pantalla(): void
    {
        $this->fingirApi([
            'total' => 2,
            'created' => 1,
            'failed' => 1,
            'errors' => [
                ['line' => 3, 'tracking_number' => 'TRC-0000000002', 'errors' => ['La fecha no es válida.']],
            ],
        ]);

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipments')
            ->call('openImport')
            ->set('file', $this->csv())
            ->call('import')
            ->assertSet('importResult.failed', 1)
            ->assertSee('TRC-0000000002')
            ->assertSee('La fecha no es válida.');
    }

    public function test_el_error_de_fichero_de_la_api_cae_en_su_campo(): void
    {
        $this->fingirApi([
            'message' => 'El fichero está vacío.',
            'errors' => ['file' => ['El fichero está vacío.']],
        ], 422);

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipments')
            ->call('openImport')
            ->set('file', $this->csv())
            ->call('import')
            ->assertHasErrors('file')
            ->assertSet('importResult', null);
    }

    public function test_una_averia_de_la_api_se_explica_dentro_del_modal(): void
    {
        Http::fake(function (Request $request) {
            if ($request->method() === 'GET') {
                return Http::response(['data' => [], 'current_page' => 1, 'last_page' => 1, 'total' => 0]);
            }

            throw new ConnectionException('sin conexión');
        });

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipments')
            ->call('openImport')
            ->set('file', $this->csv())
            ->call('import')
            ->assertSet('importResult', null)
            ->assertSee('No pudimos conectar con el servicio.');
    }

    public function test_sin_fichero_no_se_llama_al_endpoint(): void
    {
        $this->fingirApi();

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipments')
            ->call('openImport')
            ->call('import')
            ->assertHasErrors('file');

        // El GET del listado sí sale, lo manda `mount()`: lo que no debe salir
        // es la subida.
        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
    }

    public function test_cerrar_el_modal_deja_la_importacion_limpia(): void
    {
        $this->fingirApi();

        Livewire::actingAs($this->empleado())
            ->test('backoffice.shipments')
            ->call('openImport')
            ->set('file', $this->csv())
            ->call('import')
            ->assertSet('importResult.created', 1)
            ->call('resetImport')
            ->assertSet('importResult', null)
            ->assertSet('file', null);
    }
}
