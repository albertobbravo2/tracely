<?php

use App\Livewire\Backoffice\BackofficeComponent;
use Flux\Flux;
use Illuminate\Http\Client\PendingRequest;

/**
 * Los usuarios que siguen un pedido, dentro de su pantalla de detalle.
 *
 * Componente aparte, igual que ⚡shipment-history-list, para que quitar un
 * vínculo solo repinte esta lista y no arrastre consigo el formulario del
 * pedido ni el historial de al lado.
 *
 * Lo que se borra es la fila de la pivote `shipment_user`, nunca la cuenta:
 * el usuario deja de ver el pedido en "mis pedidos" y nada más.
 */
new class extends BackofficeComponent
{
    /**
     * Guía del pedido cuyos vínculos se listan. La pasa la pantalla de detalle.
     *
     * El parámetro de `mount()` es opcional solo por compatibilidad de firma:
     * la base declara `mount()` sin argumentos y PHP no deja añadir uno
     * obligatorio al sobrescribir.
     */
    public string $trackingNumber = '';

    /** @var list<array<string, mixed>> */
    public array $users = [];

    /** Id del usuario cuyo vínculo está pendiente de confirmar. */
    public ?int $detaching = null;

    public function mount(string $trackingNumber = ''): void
    {
        $this->trackingNumber = $trackingNumber;

        $this->loadRows();
    }

    protected function loadRows(): void
    {
        $this->errorMessage = null;

        if ($this->trackingNumber === '') {
            $this->users = [];

            return;
        }

        $response = $this->callApi(fn (PendingRequest $api) => $api->get(
            route('shipments.users.index', ['shipment' => $this->trackingNumber], absolute: false),
            ['page' => $this->page],
        ));

        if ($response === null || $response->failed()) {
            $this->users = [];
            $this->failListing($response, __('No pudimos cargar los usuarios vinculados.'));

            return;
        }

        $this->users = $this->rowsFrom($response);
    }

    public function confirmDetach(int $id): void
    {
        $this->detaching = $id;

        Flux::modal('shipment-user-detach')->show();
    }

    public function detach(): void
    {
        if ($this->detaching === null) {
            return;
        }

        $response = $this->callApi(fn (PendingRequest $api) => $api->delete(
            route('shipments.users.detach', [
                'shipment' => $this->trackingNumber,
                'user' => $this->detaching,
            ], absolute: false),
        ));

        Flux::modal('shipment-user-detach')->close();

        if ($response === null || $response->failed()) {
            $this->errorMessage = $this->apiErrorMessage($response, __('No pudimos quitar el vínculo.'));
            $this->detaching = null;

            return;
        }

        Flux::toast(variant: 'success', text: __('Vínculo eliminado. La cuenta sigue existiendo.'));

        $this->detaching = null;

        // Quitar el último vínculo de una página deja esa página vacía: se
        // vuelve a la primera en lugar de enseñar un hueco.
        if (count($this->users) === 1) {
            $this->page = max(1, $this->page - 1);
        }

        $this->loadRows();
    }

    /**
     * Nombre del usuario pendiente de desvincular, para poder nombrarlo en la
     * confirmación sin volver a pedírselo a la API: la fila ya está en pantalla.
     */
    public function detachingName(): string
    {
        return (string) ($this->rowById($this->users, $this->detaching)['name'] ?? __('esta cuenta'));
    }
};
?>

<div class="space-y-4 rounded-xl border border-line bg-surface p-6">
    <flux:heading size="lg" class="text-ink">
        {{ __('Clientes vinculados') }}
    </flux:heading>

    @if ($errorMessage)
        <x-backoffice.alert :message="$errorMessage" retry="retry" />
    @endif

    <x-backoffice.busy target="retry, nextPage, previousPage, detach">
        @if (empty($users))
            @if (! $errorMessage)
                <x-backoffice.empty
                    icon="users"
                    :heading="__('Sin vínculos')"
                    :text="__('Todavía no hay ninguna cuenta siguiendo este pedido.')"
                />
            @endif
        @else
            <ul class="divide-y divide-line">
                @foreach ($users as $user)
                    <li class="flex items-center justify-between gap-3 py-3 first:pt-0">
                        <div class="flex min-w-0 items-center gap-3">
                            {{-- Avatar en `surface-2`, como los del sidebar y la
                                 barra superior (design.md → Color). --}}
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-surface-2 text-sm font-semibold text-ink-2">
                                {{ \Illuminate\Support\Str::of($user['name'] ?? '—')->trim()->substr(0, 1)->upper() }}
                            </span>

                            <div class="min-w-0">
                                <p class="truncate font-semibold text-ink">{{ $user['name'] ?? '—' }}</p>
                                <flux:text size="sm" class="truncate text-ink-muted">
                                    {{ $user['email'] ?? '—' }}
                                </flux:text>
                            </div>
                        </div>

                        <flux:button
                            size="sm"
                            variant="ghost"
                            icon="minus-circle"
                            class="shrink-0"
                            :label="__('Quitar vínculo')"
                            wire:click="confirmDetach({{ $user['id'] }})"
                        />
                    </li>
                @endforeach
            </ul>

            <x-backoffice.pagination :meta="$meta" />
        @endif
    </x-backoffice.busy>

    <x-backoffice.modal
        name="shipment-user-detach"
        size="narrow"
        :heading="__('Quitar vínculo')"
        :description="__('El pedido dejará de aparecer en la lista de :nombre. La cuenta no se toca, y podrá volver a vincularlo con su número de guía.', ['nombre' => $this->detachingName()])"
    >
        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancelar') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" wire:click="detach" wire:loading.attr="disabled" wire:target="detach">
                {{ __('Quitar') }}
            </flux:button>
        </div>
    </x-backoffice.modal>
</div>
