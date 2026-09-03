<?php

namespace Tests\Feature\Api;

use App\Enums\ShipmentStatus;
use App\Models\Company;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ShipmentImportTest extends TestCase
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

    private function csv(string $contenido, string $nombre = 'pedidos.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, $contenido);
    }

    private const CABECERA = 'tracking_number,receiver_name,origin,destination,estimated_delivery_date';

    public function test_importa_los_pedidos_del_fichero(): void
    {
        $response = $this->actingAs($this->empleado())
            ->postJson(route('shipments.import'), [
                'file' => $this->csv(
                    self::CABECERA."\n".
                    "TRC-0000000001,Ana Destinataria,Madrid,Lisboa,2026-10-01\n".
                    "TRC-0000000002,Luis Destinatario,Sevilla,Oporto,2026-10-02\n"
                ),
            ]);

        $response->assertOk()->assertJson([
            'total' => 2,
            'created' => 2,
            'failed' => 0,
            'errors' => [],
        ]);

        $this->assertSame(2, Shipment::count());
        $this->assertSame('Ana Destinataria', Shipment::where('tracking_number', 'TRC-0000000001')->sole()->receiver_name);
        // Sin columna de estado, la columna cae a su valor por defecto.
        $this->assertSame(ShipmentStatus::Pendiente, Shipment::where('tracking_number', 'TRC-0000000002')->sole()->status);
    }

    public function test_la_fila_con_historial_crea_su_primer_evento(): void
    {
        $this->actingAs($this->empleado())
            ->postJson(route('shipments.import'), [
                'file' => $this->csv(
                    self::CABECERA.",status,history_location,history_description\n".
                    "TRC-0000000001,Ana,Madrid,Lisboa,2026-10-01,en_transito,Centro de Madrid,Salida del centro logístico\n".
                    "TRC-0000000002,Luis,Sevilla,Oporto,2026-10-02,,,\n"
                ),
            ])->assertOk();

        $conHistorial = Shipment::where('tracking_number', 'TRC-0000000001')->sole();
        $this->assertSame(ShipmentStatus::EnTransito, $conHistorial->status);

        $evento = $conHistorial->histories()->sole();
        $this->assertSame('Centro de Madrid', $evento->location);
        $this->assertSame('Salida del centro logístico', $evento->description);
        // El evento nace con el estado del pedido, no siempre "pendiente".
        $this->assertSame(ShipmentStatus::EnTransito, $evento->status);

        // Sin ubicación ni descripción no se le inventa un historial.
        $this->assertSame(0, Shipment::where('tracking_number', 'TRC-0000000002')->sole()->histories()->count());
    }

    public function test_el_remitente_y_la_empresa_salen_de_quien_importa(): void
    {
        $company = Company::factory()->create();
        $agente = User::factory()->for($company)->create()->assignRole('agente');

        $this->actingAs($agente)
            ->postJson(route('shipments.import'), [
                'file' => $this->csv(
                    self::CABECERA."\n".
                    "TRC-0000000001,Ana,Madrid,Lisboa,2026-10-01\n"
                ),
            ])->assertOk();

        $shipment = Shipment::sole();
        $this->assertSame($agente->id, $shipment->sender_id);
        $this->assertSame($company->id, $shipment->company_id);
    }

    public function test_una_fila_invalida_no_detiene_al_resto(): void
    {
        $response = $this->actingAs($this->empleado())
            ->postJson(route('shipments.import'), [
                'file' => $this->csv(
                    self::CABECERA."\n".
                    "TRC-0000000001,Ana,Madrid,Lisboa,no-es-una-fecha\n".
                    "TRC-0000000002,Luis,Sevilla,Oporto,2026-10-02\n"
                ),
            ]);

        $response->assertOk()->assertJson([
            'total' => 2,
            'created' => 1,
            'failed' => 1,
        ]);

        // La línea del informe es la del fichero, contando la cabecera.
        $this->assertSame(2, $response->json('errors.0.line'));
        $this->assertSame('TRC-0000000001', $response->json('errors.0.tracking_number'));

        $this->assertSame(1, Shipment::count());
        $this->assertSame('TRC-0000000002', Shipment::sole()->tracking_number);
    }

    public function test_la_guia_repetida_dentro_del_fichero_se_marca(): void
    {
        $response = $this->actingAs($this->empleado())
            ->postJson(route('shipments.import'), [
                'file' => $this->csv(
                    self::CABECERA."\n".
                    "TRC-0000000001,Ana,Madrid,Lisboa,2026-10-01\n".
                    "TRC-0000000001,Ana Repetida,Madrid,Lisboa,2026-10-03\n"
                ),
            ]);

        $response->assertOk()->assertJson(['created' => 1, 'failed' => 1]);
        $this->assertSame(1, Shipment::count());
        $this->assertSame('Ana', Shipment::sole()->receiver_name);
    }

    public function test_la_guia_que_ya_existe_en_la_base_se_marca(): void
    {
        Shipment::factory()->create(['tracking_number' => 'TRC-0000000001']);

        $response = $this->actingAs($this->empleado())
            ->postJson(route('shipments.import'), [
                'file' => $this->csv(
                    self::CABECERA."\n".
                    "TRC-0000000001,Ana,Madrid,Lisboa,2026-10-01\n"
                ),
            ]);

        $response->assertOk()->assertJson(['created' => 0, 'failed' => 1]);
        $this->assertSame(1, Shipment::count());
    }

    public function test_acepta_el_csv_de_excel_en_espanol(): void
    {
        // Punto y coma como separador, BOM al principio y fecha en d/m/Y: es
        // como sale un CSV exportado desde Excel en español.
        $this->actingAs($this->empleado())
            ->postJson(route('shipments.import'), [
                'file' => $this->csv(
                    "\xEF\xBB\xBF".str_replace(',', ';', self::CABECERA)."\n".
                    "TRC-0000000001;Ana;Madrid;Lisboa;01/10/2026\n".
                    "\n"
                ),
            ])->assertOk()->assertJson(['total' => 1, 'created' => 1]);

        $this->assertSame('2026-10-01', Shipment::sole()->estimated_delivery_date);
    }

    public function test_rechaza_el_fichero_sin_columnas_obligatorias(): void
    {
        $this->actingAs($this->empleado())
            ->postJson(route('shipments.import'), [
                'file' => $this->csv("tracking_number,receiver_name\nTRC-0000000001,Ana\n"),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->assertSame(0, Shipment::count());
    }

    public function test_rechaza_el_fichero_vacio(): void
    {
        $this->actingAs($this->empleado())
            ->postJson(route('shipments.import'), ['file' => $this->csv('')])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_rechaza_el_fichero_sin_ninguna_fila(): void
    {
        $this->actingAs($this->empleado())
            ->postJson(route('shipments.import'), ['file' => $this->csv(self::CABECERA."\n")])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_un_usuario_sin_permiso_no_puede_importar(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('shipments.import'), [
                'file' => $this->csv(self::CABECERA."\nTRC-0000000001,Ana,Madrid,Lisboa,2026-10-01\n"),
            ])
            ->assertForbidden();

        $this->assertSame(0, Shipment::count());
    }

    public function test_un_invitado_no_puede_importar(): void
    {
        $this->postJson(route('shipments.import'), [
            'file' => $this->csv(self::CABECERA."\nTRC-0000000001,Ana,Madrid,Lisboa,2026-10-01\n"),
        ])->assertUnauthorized();
    }
}
