<?php

namespace Tests\Feature\Api;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Límite `show-shipment` de la consulta pública de seguimiento.
 *
 * El buscador llama a la API desde el servidor, así que para la API la IP es
 * siempre la del servidor. Sin la cabecera firmada con la IP del visitante,
 * todos los anónimos compartían un único cupo de 3 consultas por segundo.
 *
 * El tiempo va congelado: el cupo es por segundo, y un test que cruzara el
 * cambio de segundo vería el contador reiniciado.
 */
class ShipmentLookupThrottleTest extends TestCase
{
    use RefreshDatabase;

    private Shipment $shipment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeTime();

        $this->shipment = Shipment::factory()->create();
    }

    // ---------------------------------------------------------------
    // La API
    // ---------------------------------------------------------------

    public function test_cada_visitante_del_buscador_tiene_su_propio_cupo(): void
    {
        foreach (range(1, 3) as $ignored) {
            $this->consultaDelBuscador('203.0.113.10')->assertOk();
        }

        $this->consultaDelBuscador('203.0.113.10')->assertStatus(429);

        // Mismo servidor llamando, otro visitante: no le afecta el cupo gastado.
        $this->consultaDelBuscador('203.0.113.20')->assertOk();
    }

    public function test_sin_firma_la_ip_de_la_cabecera_se_ignora(): void
    {
        foreach (range(1, 3) as $i) {
            $this->consultar(['X-Tracely-Visitor-Ip' => "198.51.100.{$i}"])->assertOk();
        }

        // Cada petición dice ser de una IP distinta, pero sin la firma cuentan
        // todas contra la IP real de quien llama.
        $this->consultar(['X-Tracely-Visitor-Ip' => '198.51.100.99'])->assertStatus(429);
    }

    public function test_una_firma_falsa_no_vale(): void
    {
        foreach (range(1, 3) as $i) {
            $this->consultar([
                'X-Tracely-Internal' => 'inventada',
                'X-Tracely-Visitor-Ip' => "198.51.100.{$i}",
            ])->assertOk();
        }

        $this->consultar([
            'X-Tracely-Internal' => 'inventada',
            'X-Tracely-Visitor-Ip' => '198.51.100.99',
        ])->assertStatus(429);
    }

    public function test_con_sesion_cuenta_el_usuario_y_no_la_ip(): void
    {
        $usuario = User::factory()->create();

        foreach (range(1, 3) as $ignored) {
            $this->actingAs($usuario, 'sanctum')->consultaDelBuscador('203.0.113.10')->assertOk();
        }

        $this->actingAs($usuario, 'sanctum')->consultaDelBuscador('203.0.113.30')->assertStatus(429);
    }

    // ---------------------------------------------------------------
    // El buscador
    // ---------------------------------------------------------------

    public function test_el_buscador_manda_la_ip_del_visitante_firmada(): void
    {
        Http::fake(['*' => Http::response($this->shipment->only('tracking_number', 'status'))]);

        Livewire::test('searchfield')
            ->set('tracking_number', $this->shipment->tracking_number)
            ->call('search');

        Http::assertSent(fn (HttpRequest $request) => $request->header('X-Tracely-Visitor-Ip') === ['127.0.0.1']
            && $request->header('X-Tracely-Internal') === [config('services.internal_api.secret')]);
    }

    public function test_el_buscador_explica_que_se_ha_pasado_del_limite(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Too Many Attempts.'], 429)]);

        Livewire::test('searchfield')
            ->set('tracking_number', $this->shipment->tracking_number)
            ->call('search')
            ->assertSet('errorMessage', 'Demasiadas búsquedas seguidas. Espera un momento y reinténtalo.');
    }

    // ---------------------------------------------------------------

    /**
     * Lo que manda el buscador: llega desde el servidor (127.0.0.1) con la IP
     * del visitante firmada.
     */
    private function consultaDelBuscador(string $visitorIp): TestResponse
    {
        return $this->consultar([
            'X-Tracely-Internal' => (string) config('services.internal_api.secret'),
            'X-Tracely-Visitor-Ip' => $visitorIp,
        ]);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function consultar(array $headers = []): TestResponse
    {
        return $this->getJson(route('shipments.show', $this->shipment->tracking_number), $headers);
    }
}
