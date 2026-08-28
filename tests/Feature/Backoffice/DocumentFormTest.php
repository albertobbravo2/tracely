<?php

namespace Tests\Feature\Backoffice;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La pantalla de documentos del backoffice, contra una API fingida.
 *
 * Es la pantalla con más traducción entre lo que se teclea y lo que se manda:
 * quien sube un documento escribe un número de guía, pero la API espera el id
 * del envío, y el fichero viaja como multipart. Eso es lo que se comprueba aquí.
 */
class DocumentFormTest extends TestCase
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
     * API fingida: la búsqueda de la guía responde con un envío conocido y el
     * resto de listados con las filas que pida cada test.
     *
     * @param  list<array<string, mixed>>  $documentos
     * @param  list<array<string, mixed>>  $envios
     */
    private function fingirApi(array $documentos = [], ?array $envios = null): void
    {
        $envios ??= [['id' => 42, 'tracking_number' => 'TRC-0000000001']];

        Http::fake(function (Request $request) use ($documentos, $envios) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/api/shipments')) {
                return $this->pagina($envios);
            }

            if ($request->method() === 'GET') {
                return $this->pagina($documentos);
            }

            return Http::response(['id' => 5, 'document_name' => 'factura.pdf'], 201);
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
     * Los campos de un cuerpo multipart, indexados por nombre.
     *
     * El cliente HTTP los guarda como `[['name' => ..., 'contents' => ...]]`,
     * que no se puede consultar directamente por clave.
     *
     * @return array<string, mixed>
     */
    private function camposMultipart(Request $request): array
    {
        return collect($request->data())
            ->filter(fn ($campo) => is_array($campo) && isset($campo['name']))
            ->mapWithKeys(fn ($campo) => [$campo['name'] => $campo['contents'] ?? null])
            ->all();
    }

    public function test_sin_filtro_el_listado_no_manda_shipment_id(): void
    {
        $this->fingirApi();

        Livewire::actingAs($this->empleado())->test('backoffice.documents');

        // Sin guía tecleada no hace falta resolver nada: se pide el listado
        // entero de una sola llamada.
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/api/shipments'));

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/api/documents')
            && ! str_contains($request->url(), 'shipment_id'));
    }

    public function test_el_filtro_resuelve_la_guia_a_su_id(): void
    {
        $this->fingirApi();

        Livewire::actingAs($this->empleado())
            ->test('backoffice.documents')
            ->set('trackingFilter', 'TRC-0000000001');

        // `documents.index` filtra por `shipment_id`, no por guía: la pantalla
        // traduce lo tecleado antes de pedir el listado.
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/api/shipments')
            && str_contains($request->url(), 'search=TRC-0000000001'));

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/api/documents')
            && str_contains($request->url(), 'shipment_id=42'));
    }

    public function test_una_guia_que_no_existe_deja_el_listado_vacio(): void
    {
        // La búsqueda responde con otro envío: no hay coincidencia exacta.
        $this->fingirApi(envios: [['id' => 99, 'tracking_number' => 'TRC-OTRA']]);

        Livewire::actingAs($this->empleado())
            ->test('backoffice.documents')
            ->set('trackingFilter', 'TRC-NO-EXISTE')
            ->assertSet('documents', []);

        // Un id imposible en vez de omitir el filtro: si no, "no existe" se
        // vería como "aquí están todos los documentos".
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/api/documents')
            && str_contains($request->url(), 'shipment_id=0'));
    }

    public function test_el_alta_sin_fichero_no_llama_a_la_api(): void
    {
        $this->fingirApi();

        Livewire::actingAs($this->empleado())
            ->test('backoffice.documents')
            ->call('create')
            ->set('tracking_number', 'TRC-0000000001')
            ->set('document_name', 'factura.pdf')
            ->set('status', 'pendiente de revisión')
            ->call('save')
            ->assertHasErrors('file');

        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
    }

    public function test_el_alta_manda_el_fichero_y_el_id_del_envio(): void
    {
        $this->fingirApi();

        Livewire::actingAs($this->empleado())
            ->test('backoffice.documents')
            ->call('create')
            ->set('tracking_number', 'TRC-0000000001')
            ->set('document_name', 'factura.pdf')
            ->set('status', 'pendiente de revisión')
            ->set('file', UploadedFile::fake()->create('factura.pdf', 12, 'application/pdf'))
            ->call('save');

        Http::assertSent(function (Request $request) {
            if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/api/documents')) {
                return false;
            }

            $campos = $this->camposMultipart($request);

            return $request->isMultipart()
                && $request->hasFile('file')
                && $campos['document_name'] === 'factura.pdf'
                && (int) $campos['shipment_id'] === 42;
        });
    }

    public function test_una_guia_inexistente_se_marca_en_su_campo(): void
    {
        $this->fingirApi(envios: [['id' => 99, 'tracking_number' => 'TRC-OTRA']]);

        Livewire::actingAs($this->empleado())
            ->test('backoffice.documents')
            ->call('create')
            ->set('tracking_number', 'TRC-NO-EXISTE')
            ->set('document_name', 'factura.pdf')
            ->set('status', 'pendiente')
            ->set('file', UploadedFile::fake()->create('factura.pdf', 12, 'application/pdf'))
            ->call('save')
            ->assertHasErrors('tracking_number')
            // No es una avería de la API: es el formulario, así que no debe
            // pintar además la alerta roja de la pantalla.
            ->assertSet('errorMessage', null);

        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/api/documents'));
    }

    public function test_editar_recupera_la_guia_aunque_la_api_no_la_devuelva(): void
    {
        Http::fake(function (Request $request) {
            // `documents.show` devuelve el documento a secas, sin su envío.
            if (str_ends_with($request->url(), '/api/documents/5')) {
                return Http::response([
                    'id' => 5,
                    'document_name' => 'factura.pdf',
                    'status' => 'pendiente de revisión',
                ]);
            }

            return $this->pagina([[
                'id' => 5,
                'document_name' => 'factura.pdf',
                'status' => 'pendiente de revisión',
                'download_url' => 'http://localhost/api/documents/5/download',
                'shipment' => ['id' => 42, 'tracking_number' => 'TRC-0000000001'],
            ]]);
        });

        Livewire::actingAs($this->empleado())
            ->test('backoffice.documents')
            ->call('edit', 5)
            ->assertSet('document_name', 'factura.pdf')
            // La guía sale de la fila del listado, que sí la trae: dejar el
            // campo vacío hacía parecer que el documento había perdido su
            // pedido, y al guardar se quedaba sin poder reasignarlo.
            ->assertSet('tracking_number', 'TRC-0000000001');
    }

    public function test_la_edicion_con_fichero_nuevo_va_por_post_con_method_put(): void
    {
        Http::fake(function (Request $request) {
            if ($request->method() === 'GET' && str_ends_with($request->url(), '/api/documents/5')) {
                return Http::response(['id' => 5, 'document_name' => 'factura.pdf', 'status' => 'pendiente']);
            }

            if ($request->method() === 'GET' && str_contains($request->url(), '/api/shipments')) {
                return $this->pagina([['id' => 42, 'tracking_number' => 'TRC-0000000001']]);
            }

            if ($request->method() === 'GET') {
                return $this->pagina([]);
            }

            return Http::response(['id' => 5]);
        });

        Livewire::actingAs($this->empleado())
            ->test('backoffice.documents')
            ->call('edit', 5)
            ->set('tracking_number', 'TRC-0000000001')
            ->set('file', UploadedFile::fake()->create('nueva.pdf', 12, 'application/pdf'))
            ->call('save');

        // PHP no parsea un cuerpo multipart en un PUT real: el spoofing de
        // método es lo que encamina la petición a la ruta PUT.
        Http::assertSent(function (Request $request) {
            if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/api/documents/5')) {
                return false;
            }

            return ($this->camposMultipart($request)['_method'] ?? null) === 'PUT';
        });
    }

    public function test_la_edicion_sin_fichero_va_por_put(): void
    {
        Http::fake(function (Request $request) {
            if ($request->method() === 'GET' && str_ends_with($request->url(), '/api/documents/5')) {
                return Http::response(['id' => 5, 'document_name' => 'factura.pdf', 'status' => 'pendiente']);
            }

            if ($request->method() === 'GET' && str_contains($request->url(), '/api/shipments')) {
                return $this->pagina([['id' => 42, 'tracking_number' => 'TRC-0000000001']]);
            }

            if ($request->method() === 'GET') {
                return $this->pagina([]);
            }

            return Http::response(['id' => 5]);
        });

        Livewire::actingAs($this->empleado())
            ->test('backoffice.documents')
            ->call('edit', 5)
            ->set('tracking_number', 'TRC-0000000001')
            ->set('document_name', 'factura-corregida.pdf')
            ->call('save');

        Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
            && str_ends_with($request->url(), '/api/documents/5')
            && $request->data()['document_name'] === 'factura-corregida.pdf');
    }

    public function test_el_borrado_llama_al_endpoint_del_documento(): void
    {
        $this->fingirApi();

        Livewire::actingAs($this->empleado())
            ->test('backoffice.documents')
            ->call('confirmDelete', 5)
            ->assertSet('deleting', 5)
            ->call('destroy')
            ->assertSet('deleting', null);

        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/api/documents/5'));
    }

    public function test_el_modal_de_borrado_nombra_el_documento(): void
    {
        $this->fingirApi([[
            'id' => 5,
            'document_name' => 'factura-comercial.pdf',
            'status' => 'aprobado',
            'download_url' => 'http://localhost/api/documents/5/download',
            'shipment' => ['id' => 42, 'tracking_number' => 'TRC-0000000001'],
        ]]);

        Livewire::actingAs($this->empleado())
            ->test('backoffice.documents')
            ->call('confirmDelete', 5)
            ->assertSee('Se borrará factura-comercial.pdf');
    }

    public function test_el_estado_del_documento_se_pinta_segun_lo_que_dice(): void
    {
        $componente = Livewire::actingAs($this->empleado())->test('backoffice.documents');

        // El estado es texto libre en la API: se reconocen las raíces
        // habituales y todo lo demás se queda en gris, sin inventar semántica.
        $this->assertSame('ok', $componente->instance()->statusTone('Aprobado por aduanas'));
        $this->assertSame('alerta', $componente->instance()->statusTone('rechazado'));
        $this->assertSame('azul', $componente->instance()->statusTone('pendiente de revisión'));
        $this->assertSame('gris', $componente->instance()->statusTone('cualquier otra cosa'));
        $this->assertSame('gris', $componente->instance()->statusTone(null));

        $this->assertSame('Sin estado', $componente->instance()->statusLabel(''));
        $this->assertSame('Aprobado', $componente->instance()->statusLabel('aprobado'));
    }
}
