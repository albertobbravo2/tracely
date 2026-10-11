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
    <div>
        <h1 class="text-3xl font-bold tracking-[-0.03em] text-ink sm:text-[38px]">{{ __('Mis pedidos') }}</h1>

        <p class="mt-1.5 text-[15px] leading-relaxed text-ink-muted">
            {{ __('Los envíos que has vinculado a tu cuenta, con su estado y su historial.') }}
        </p>
    </div>

    @if ($errorMessage)
        <div class="mt-6 flex items-start gap-3 rounded-xl border border-line bg-surface px-4 py-3">
            <flux:icon name="exclamation-triangle" variant="outline" class="mt-0.5 size-5 shrink-0 text-warn" />

            <flux:text class="text-ink-2">{{ $errorMessage }}</flux:text>
        </div>
    @elseif (empty($shipments))
        <div class="mt-6 rounded-2xl border border-line bg-surface px-6 py-14 text-center">
            <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-surface-2 text-ink-muted">
                <flux:icon name="inbox" variant="outline" class="size-6" />
            </span>

            <p class="mt-4 font-semibold text-ink">{{ __('Todavía no tienes ningún pedido vinculado.') }}</p>

            <p class="mx-auto mt-1 max-w-sm text-sm leading-relaxed text-ink-muted">
                {{ __('Busca un envío por su número de guía y vincúlalo a tu cuenta para seguirlo desde aquí.') }}
            </p>

            <flux:button
                :href="route('home')"
                variant="primary"
                size="sm"
                icon="magnifying-glass"
                class="mt-6 shadow-elev"
                wire:navigate
            >
                {{ __('Buscar un envío') }}
            </flux:button>
        </div>
    @else
        {{-- Una tarjeta por envío, cada una con su propia línea de tiempo, en
             vez de una fila por envío: el estado de un pedido se lee mejor como
             recorrido que como celda. --}}
        <div class="mt-6 space-y-4">
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
