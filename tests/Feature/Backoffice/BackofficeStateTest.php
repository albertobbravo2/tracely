<?php

namespace Tests\Feature\Backoffice;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Lo que las cinco pantallas del backoffice tienen en común: qué pasa mientras
 * la API responde, qué se ve cuando no responde y cómo se sale de ahí.
 *
 * Vive aparte de los tests de cada formulario porque no es de ninguno: es el
 * comportamiento de `BackofficeComponent` y de los componentes compartidos, y
 * si se rompe se rompe en las cinco a la vez.
 */
class BackofficeStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function empleado(): User
    {
        return User::factory()->create()->assignRole('superadministrador');
    }

    /**
     * Las cinco pantallas con el mensaje que da cada una cuando su listado no
     * carga por una avería (un 500, no un permiso).
     *
     * @return array<string, array{string, string}>
     */
    public static function pantallas(): array
    {
        return [
            'pedidos' => ['backoffice.shipments', 'No pudimos cargar los pedidos ahora mismo.'],
            'historial' => ['backoffice.shipment-histories', 'No pudimos cargar el historial ahora mismo.'],
            'usuarios' => ['backoffice.users', 'No pudimos cargar los usuarios ahora mismo.'],
            'documentos' => ['backoffice.documents', 'No pudimos cargar los documentos ahora mismo.'],
            'companias' => ['backoffice.companies', 'No pudimos cargar las empresas ahora mismo.'],
        ];
    }

    private function fingirListadoVacio(): void
    {
        Http::fake(['*' => Http::response([
            'data' => [],
            'current_page' => 1,
            'last_page' => 1,
            'total' => 0,
        ])]);
    }

    #[DataProvider('pantallas')]
    public function test_una_averia_de_la_api_se_explica_en_la_pantalla(string $componente, string $mensaje): void
    {
        Http::fake(['*' => Http::response(['message' => 'Server Error'], 500)]);

        Livewire::actingAs($this->empleado())
            ->test($componente)
            ->assertSet('errorMessage', $mensaje)
            ->assertSee($mensaje)
            // El estado vacío es otra cosa: "no hay nada" no es lo mismo que
            // "no lo hemos podido cargar", y pintar los dos a la vez confunde.
            ->assertDontSee('que mostrar');
    }

    #[DataProvider('pantallas')]
    public function test_la_alerta_ofrece_reintentar_y_reintentar_recarga(string $componente, string $mensaje): void
    {
        $llamadas = 0;

        Http::fake(function () use (&$llamadas) {
            $llamadas++;

            return $llamadas === 1
                ? Http::response(['message' => 'Server Error'], 500)
                : Http::response(['data' => [], 'current_page' => 1, 'last_page' => 1, 'total' => 0]);
        });

        Livewire::actingAs($this->empleado())
            ->test($componente)
            ->assertSet('errorMessage', $mensaje)
            ->assertSee('Reintentar')
            ->assertSeeHtml('wire:click="retry"')
            // Sin esto la única salida de un error era recargar la página a
            // mano: el listado se quedaba vacío y sin nada que pulsar.
            ->call('retry')
            ->assertSet('errorMessage', null);

        $this->assertGreaterThan(1, $llamadas);
    }

    #[DataProvider('pantallas')]
    public function test_sin_conexion_se_dice_que_es_del_servicio(string $componente, string $mensaje): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection refused');
        });

        Livewire::actingAs($this->empleado())
            ->test($componente)
            // Que la API no conteste no es lo mismo que que conteste mal: el
            // mensaje invita a reintentar, no a llamar a soporte.
            ->assertSet('errorMessage', 'No pudimos conectar con el servicio. Inténtalo de nuevo en unos segundos.');
    }

    #[DataProvider('pantallas')]
    public function test_demasiadas_peticiones_se_explica_como_tal(string $componente, string $mensaje): void
    {
        Http::fake(['*' => Http::response(['message' => 'Too Many Attempts.'], 429)]);

        Livewire::actingAs($this->empleado())
            ->test($componente)
            ->assertSet('errorMessage', 'Demasiadas peticiones seguidas. Espera un momento y reinténtalo.');
    }

    #[DataProvider('pantallas')]
    public function test_la_region_de_resultados_avisa_de_que_esta_cargando(string $componente, string $mensaje): void
    {
        $this->fingirListadoVacio();

        Livewire::actingAs($this->empleado())
            ->test($componente)
            // Filtrar o cambiar de página es una petición HTTP: sin señal, la
            // pantalla se quedaba idéntica mientras tanto.
            ->assertSeeHtml('wire:loading.class="pointer-events-none opacity-40"')
            ->assertSeeHtml('wire:loading.attr="aria-busy"')
            ->assertSeeHtml('Cargando resultados...');
    }

    #[DataProvider('pantallas')]
    public function test_el_buscador_lleva_su_propio_indicador(string $componente, string $mensaje): void
    {
        // <flux:input> pinta su indicador de carga solo cuando el `wire:model`
        // es `.live`, y acotado a esa propiedad. Si el buscador dejara de serlo
        // el campo se quedaría mudo mientras la API responde.
        $this->fingirListadoVacio();

        $filtro = in_array($componente, ['backoffice.documents', 'backoffice.shipment-histories'], true)
            ? 'trackingFilter'
            : 'search';

        Livewire::actingAs($this->empleado())
            ->test($componente)
            ->assertSeeHtml('wire:target="'.$filtro.'"');
    }

    public function test_la_paginacion_solo_se_apaga_por_su_propia_navegacion(): void
    {
        Http::fake(['*' => Http::response([
            'data' => [['id' => 1, 'name' => 'Transportes del Norte', 'slug' => 'norte', 'contact_email' => null, 'phone' => null, 'is_active' => true]],
            'current_page' => 1,
            'last_page' => 3,
            'total' => 45,
            'from' => 1,
            'to' => 15,
        ])]);

        Livewire::actingAs($this->empleado())
            ->test('backoffice.companies')
            // Sin acotar el `wire:target`, teclear en el buscador apagaba y
            // encendía los botones de paginar a cada pulsación.
            ->assertSeeHtml('wire:target="previousPage, nextPage"')
            ->assertSee('1–15 de 45')
            ->assertSee('1 / 3');
    }

    #[DataProvider('pantallas')]
    public function test_pasar_de_pagina_pide_la_siguiente_y_vuelve(string $componente, string $mensaje): void
    {
        Http::fake(['*' => Http::response([
            'data' => [],
            'current_page' => 1,
            'last_page' => 3,
            'total' => 45,
        ])]);

        Livewire::actingAs($this->empleado())
            ->test($componente)
            ->call('nextPage')
            ->assertSet('page', 2)
            ->call('previousPage')
            ->assertSet('page', 1)
            // En la primera página no se retrocede más: `page` nunca llega a 0.
            ->call('previousPage')
            ->assertSet('page', 1);
    }

    public function test_no_se_pasa_de_la_ultima_pagina(): void
    {
        Http::fake(['*' => Http::response([
            'data' => [],
            'current_page' => 1,
            'last_page' => 1,
            'total' => 5,
        ])]);

        Livewire::actingAs($this->empleado())
            ->test('backoffice.users')
            ->call('nextPage')
            ->assertSet('page', 1);
    }
}
