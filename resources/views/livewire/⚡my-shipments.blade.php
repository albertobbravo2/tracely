<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

new class extends Component
{
    /** @var list<array<string, mixed>> */
    public array $shipments = [];

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->load();
    }

    /**
     * Consultar la API interna por los envíos vinculados a la cuenta de
     * quien ve el dashboard. Mismo patrón que ⚡searchfield: llamada por HTTP
     * a nuestra propia API en vez de a Eloquent directamente, para heredar
     * de ahí las reglas de visibilidad en vez de reimplementarlas aquí.
     */
    private function load(): void
    {
        $this->errorMessage = null;

        $path = route('users.myshipments', absolute: false);

        $pending = Http::acceptJson()
            ->timeout(10)
            ->baseUrl(config('services.internal_api.url'));

        // La llamada sale del servidor, sin la sesión del navegador: le
        // acuñamos un token de un minuto para que la API reconozca al
        // usuario y devuelva su propia lista.
        $token = auth()->user()->createToken('my-shipments', ['shipment:read'], now()->addMinute());

        try {
            $response = $pending->withToken($token->plainTextToken)->get($path);
        } catch (ConnectionException) {
            $this->errorMessage = __('No pudimos conectar con el servicio de pedidos. Inténtalo de nuevo en unos segundos.');

            return;
        } finally {
            $token->accessToken->delete();
        }

        if ($response->failed()) {
            $this->errorMessage = __('No pudimos cargar tus pedidos ahora mismo.');

            return;
        }

        $this->shipments = $response->json('data') ?? [];
    }
};
?>

<div>
    <flux:heading size="lg" class="text-gris-900 dark:text-blanco">{{ __('Mis pedidos') }}</flux:heading>

    @if ($errorMessage)
        <div class="mt-4 rounded-xl border border-gris-200 bg-gris-050 px-4 py-3 dark:border-azul-800 dark:bg-azul-900">
            <flux:text class="text-gris-600 dark:text-azul-100">{{ $errorMessage }}</flux:text>
        </div>
    @elseif (empty($shipments))
        <div class="mt-4 rounded-xl border border-gris-200 bg-gris-050 px-4 py-3 dark:border-azul-800 dark:bg-azul-900">
            <flux:text class="text-gris-600 dark:text-azul-100">{{ __('Todavía no tienes ningún pedido vinculado.') }}</flux:text>
        </div>
    @else
        {{-- Una tarjeta por envío, cada una con su propia línea de tiempo, en
             vez de una fila por envío: el estado de un pedido se lee mejor como
             recorrido que como celda. --}}
        <div class="mt-4 space-y-6">
            @foreach ($shipments as $shipment)
                {{-- `linked` va dado: esta lista son justo los envíos vinculados,
                     así que la tarjeta no tiene que consultarlo una por una. --}}
                <livewire:shipment-card
                    :shipment="$shipment"
                    :linked="true"
                    :key="'shipment-'.$shipment['id']"
                />
            @endforeach
        </div>
    @endif
</div>
