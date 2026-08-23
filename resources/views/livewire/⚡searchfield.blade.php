<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    #[Validate('required|string|min:4|max:40')]
    public string $tracking_number = '';

    /** @var array<string, mixed>|null */
    public ?array $shipment = null;

    public ?string $errorMessage = null;

    /**
     * Consultar la API interna de envíos con el número de guía introducido.
     */
    public function search(): void
    {
        $this->validate();

        $this->shipment = null;
        $this->errorMessage = null;

        $path = route('shipments.show', ['shipment' => trim($this->tracking_number)], absolute: false);

        $response = $this->callApi('get', $path);

        if ($response === null) {
            $this->errorMessage = __('No pudimos conectar con el servicio de rastreo. Inténtalo de nuevo en unos segundos.');

            return;
        }

        if ($response->status() === 404) {
            $this->errorMessage = __('No encontramos ningún envío con ese número de guía.');

            return;
        }

        if ($response->failed()) {
            $this->errorMessage = __('El servicio de rastreo no está disponible ahora mismo.');

            return;
        }

        $this->shipment = $response->json();

        $this->dispatch('shipment-found', shipment: $this->shipment);
    }

    /**
     * Llamar a nuestra propia API desde el servidor.
     *
     * La petición no arrastra la sesión del navegador: para la API seríamos un
     * invitado y solo devolvería los datos públicos. Si hay usuario logueado le
     * acuñamos un token de un minuto para que lo reconozca, y lo borramos en
     * `finally` para que no quede vivo si la conexión falla.
     *
     * Devuelve null si no se pudo conectar; el mensaje lo decide quien llama.
     */
    private function callApi(string $method, string $path): ?Response
    {
        $pending = Http::acceptJson()
            ->timeout(10)
            ->baseUrl(config('services.internal_api.url'));

        $token = auth()->user()?->createToken('searchfield', ['shipment:read'], now()->addMinute());

        if ($token) {
            $pending->withToken($token->plainTextToken);
        }

        try {
            return $pending->send($method, $path);
        } catch (ConnectionException) {
            return null;
        } finally {
            $token?->accessToken->delete();
        }
    }

};
?>

<div>
    <form wire:submit="search" class="mx-auto flex max-w-xl items-center gap-3">
        <flux:input
            wire:model="tracking_number"
            name="tracking_number"
            aria-label="{{ __('Número de guía') }}"
            placeholder="RY-4820-1174-MX"
            class="flex-1"
            class:input="!border-transparent !bg-blanco !text-gris-900 !shadow-none placeholder:!text-gris-400"
        />

        <flux:button
            type="submit"
            variant="primary"
            wire:loading.attr="disabled"
            wire:target="search"
            class="shrink-0 px-6 font-semibold [--color-accent-foreground:var(--color-white)] [--color-accent:var(--color-brand-navy)]"
        >
            <span wire:loading.remove wire:target="search">{{ __('Buscar') }}</span>
            <span wire:loading wire:target="search">{{ __('Buscando...') }}</span>
        </flux:button>
    </form>

    <div class="mx-auto mt-4 max-w-xl text-start">
        <flux:error name="tracking_number" />

        @if ($errorMessage)
            <div class="rounded-xl border border-gris-200 bg-gris-050 px-4 py-3 dark:border-azul-800 dark:bg-azul-900">
                <flux:text class="text-gris-600 dark:text-azul-100">{{ $errorMessage }}</flux:text>
            </div>
        @endif

        @if ($shipment)
            {{-- El botón de vincular/eliminar va dentro de la tarjeta, no aquí:
                 así aparece en cualquier pantalla que la pinte. La clave es la
                 guía para que buscar otro envío monte una tarjeta nueva en vez
                 de reusar el estado de la anterior. --}}
            <livewire:shipment-card :shipment="$shipment" :key="$shipment['tracking_number']" />
        @endif
    </div>
</div>
