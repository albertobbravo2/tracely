<?php

use Illuminate\Http\Client\ConnectionException;
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
     * Consultar la API de rastreo con el número de guía introducido.
     */
    public function search(): void
    {
        $this->validate();

        $this->shipment = null;
        $this->errorMessage = null;

        $request = Http::acceptJson()
            ->timeout(10)
            ->baseUrl(config('services.tracking.url'));

        if ($key = config('services.tracking.key')) {
            $request->withToken($key);
        }

        try {
            $response = $request->get('shipments/'.trim($this->tracking_number));
        } catch (ConnectionException) {
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

    <div class="mx-auto mt-3 max-w-xl text-start">
        <flux:error name="tracking_number" />

        @if ($errorMessage)
            <flux:text class="text-gris-600 dark:text-azul-100">{{ $errorMessage }}</flux:text>
        @endif
    </div>
</div>
