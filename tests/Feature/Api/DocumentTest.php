<?php

namespace Tests\Feature\Api;

use App\Models\Document;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentTest extends TestCase
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

    public function test_el_fichero_se_guarda_en_el_disco_privado(): void
    {
        Storage::fake('documents');

        $shipment = Shipment::factory()->create();

        $response = $this->actingAs($this->empleado())->postJson(route('documents.store'), [
            'shipment_id' => $shipment->id,
            'document_name' => 'factura.pdf',
            'status' => 'pendiente',
            'file' => UploadedFile::fake()->create('factura.pdf', 12, 'application/pdf'),
        ]);

        $response->assertCreated();

        // El path lo decide el servidor: carpeta por envío y nombre aleatorio.
        $document = Document::sole();
        $this->assertStringStartsWith("{$shipment->id}/", $document->file_path);
        $this->assertNotSame('factura.pdf', basename($document->file_path));
        Storage::disk('documents')->assertExists($document->file_path);
    }

    public function test_el_json_expone_la_url_de_descarga_y_no_la_ruta_interna(): void
    {
        Storage::fake('documents');

        $shipment = Shipment::factory()->create();

        $response = $this->actingAs($this->empleado())->postJson(route('documents.store'), [
            'shipment_id' => $shipment->id,
            'document_name' => 'factura.pdf',
            'status' => 'pendiente',
            'file' => UploadedFile::fake()->create('factura.pdf', 12, 'application/pdf'),
        ]);

        $response->assertJsonMissingPath('file_path');
        $response->assertJsonPath('download_url', route('documents.download', Document::sole()));
    }

    public function test_el_documento_se_descarga_con_su_nombre_original(): void
    {
        Storage::fake('documents');

        $shipment = Shipment::factory()->create();
        $path = UploadedFile::fake()->create('factura.pdf', 12, 'application/pdf')
            ->store((string) $shipment->id, 'documents');

        $document = Document::create([
            'shipment_id' => $shipment->id,
            'document_name' => 'factura.pdf',
            'file_path' => $path,
            'status' => 'pendiente',
        ]);

        $this->actingAs($this->empleado())
            ->get(route('documents.download', $document))
            ->assertOk()
            ->assertDownload('factura.pdf');
    }

    public function test_sin_sesion_no_se_descarga_nada(): void
    {
        Storage::fake('documents');

        $document = Document::create([
            'shipment_id' => Shipment::factory()->create()->id,
            'document_name' => 'factura.pdf',
            'file_path' => 'ruta/inventada.pdf',
            'status' => 'pendiente',
        ]);

        $this->getJson(route('documents.download', $document))->assertUnauthorized();
    }

    public function test_al_sustituir_el_fichero_se_borra_el_anterior(): void
    {
        Storage::fake('documents');

        $shipment = Shipment::factory()->create();
        $document = Document::create([
            'shipment_id' => $shipment->id,
            'document_name' => 'factura.pdf',
            'file_path' => UploadedFile::fake()->create('factura.pdf', 12, 'application/pdf')
                ->store((string) $shipment->id, 'documents'),
            'status' => 'pendiente',
        ]);

        $anterior = $document->file_path;

        $this->actingAs($this->empleado())
            ->putJson(route('documents.update', $document), [
                'file' => UploadedFile::fake()->create('corregida.pdf', 12, 'application/pdf'),
            ])
            ->assertOk();

        Storage::disk('documents')->assertMissing($anterior);
        Storage::disk('documents')->assertExists($document->refresh()->file_path);
    }

    public function test_al_borrar_el_documento_desaparece_el_fichero(): void
    {
        Storage::fake('documents');

        $shipment = Shipment::factory()->create();
        $document = Document::create([
            'shipment_id' => $shipment->id,
            'document_name' => 'factura.pdf',
            'file_path' => UploadedFile::fake()->create('factura.pdf', 12, 'application/pdf')
                ->store((string) $shipment->id, 'documents'),
            'status' => 'pendiente',
        ]);

        $path = $document->file_path;

        $this->actingAs($this->empleado())
            ->deleteJson(route('documents.destroy', $document))
            ->assertNoContent();

        Storage::disk('documents')->assertMissing($path);
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_un_envio_lista_sus_documentos(): void
    {
        Storage::fake('documents');

        $shipment = Shipment::factory()->create();

        Document::create([
            'shipment_id' => $shipment->id,
            'document_name' => 'factura.pdf',
            'file_path' => UploadedFile::fake()->create('factura.pdf', 12, 'application/pdf')
                ->store((string) $shipment->id, 'documents'),
            'status' => 'pendiente',
        ]);

        $this->assertCount(1, $shipment->documents);
    }
}
