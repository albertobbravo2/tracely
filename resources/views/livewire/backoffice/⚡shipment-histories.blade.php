<?php

use App\Enums\ShipmentStatus;
use App\Livewire\Backoffice\BackofficeComponent;
use Flux\Flux;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new
#[Layout('layouts::backoffice')]
#[Title('Historial de pedidos')]
class extends BackofficeComponent
{
    // --- Listado -----------------------------------------------------------

    /** Número de guía por el que filtrar; se resuelve a `shipment_id`. */
    public string $trackingFilter = '';

    /** @var list<array<string, mixed>> */
    public array $histories = [];

    // --- Formulario --------------------------------------------------------

    /**
     * Aquí no se dan de alta eventos: el primero lo crea el observer al
     * registrar el pedido, y esta pantalla solo corrige o borra los que ya
     * existen. Por eso solo hay id de edición, sin modo "crear".
     */
    public ?int $editing = null;

    public string $status = '';

    public string $location = '';

    public string $description = '';

    public string $recorded_at = '';

    public ?int $deleting = null;

    // --- Listado -----------------------------------------------------------

    public function updatedTrackingFilter(): void
    {
        $this->resetAndReload();
    }

    /**
     * `shipment-histories.index` filtra por `shipment_id`, no por número de
     * guía, así que cuando hay filtro se resuelve antes la guía a su id. Las
     * dos peticiones van dentro de la misma llamada a `callApi()` para no
     * acuñar dos tokens.
     */
    protected function loadRows(): void
    {
        $this->errorMessage = null;

        $tracking = trim($this->trackingFilter);

        $response = $this->callApi(function (PendingRequest $api) use ($tracking): Response {
            $filters = ['page' => $this->page];

            if ($tracking !== '') {
                $lookup = $api->get(route('shipments.index', absolute: false), ['search' => $tracking]);

                if ($lookup->failed()) {
                    return $lookup;
                }

                $match = collect($lookup->json('data') ?? [])
                    ->firstWhere('tracking_number', $tracking);

                // Sin coincidencia exacta no se listan "todos": se manda un id
                // imposible para que la respuesta venga vacía de verdad.
                $filters['shipment_id'] = $match['id'] ?? 0;
            }

            return $api->get(route('shipment-histories.index', absolute: false), $filters);
        });

        if ($response === null || $response->failed()) {
            $this->histories = [];
            $this->failListing($response, __('No pudimos cargar el historial ahora mismo.'));

            return;
        }

        $this->histories = $this->rowsFrom($response);
    }

    // --- Edición -----------------------------------------------------------

    public function edit(int $id): void
    {
        $this->resetForm();

        $response = $this->callApi(fn (PendingRequest $api) => $api->get(
            route('shipment-histories.show', ['shipmentHistory' => $id], absolute: false),
        ));

        if ($response === null || $response->failed()) {
            $this->errorMessage = $this->apiErrorMessage($response, __('No pudimos cargar ese evento.'));

            return;
        }

        $history = $response->json();

        $this->editing = $id;
        $this->status = $history['status'] ?? '';
        $this->location = (string) ($history['location'] ?? '');
        $this->description = (string) ($history['description'] ?? '');
        $this->recorded_at = $this->forInput($history['recorded_at'] ?? null);

        Flux::modal('history-form')->show();
    }

    public function save(): void
    {
        if ($this->editing === null) {
            return;
        }

        $this->resetErrorBag();
        $this->errorMessage = null;

        // `location` y `description` se mandan aunque vayan vacíos: la API los
        // acepta como `nullable`, y así se puede borrar lo que se tecleó mal.
        $response = $this->callApi(fn (PendingRequest $api) => $api->put(
            route('shipment-histories.update', ['shipmentHistory' => $this->editing], absolute: false),
            [
                'status' => $this->status,
                'location' => trim($this->location) ?: null,
                'description' => trim($this->description) ?: null,
                'recorded_at' => $this->recorded_at,
            ],
        ));

        if ($this->applyApiValidationErrors($response)) {
            return;
        }

        if ($response === null || $response->failed()) {
            $this->errorMessage = $this->apiErrorMessage($response, __('No pudimos guardar el evento.'));

            return;
        }

        Flux::modal('history-form')->close();
        Flux::toast(variant: 'success', text: __('Evento del historial actualizado.'));

        $this->resetForm();
        $this->loadRows();
    }

    // --- Borrado -----------------------------------------------------------

    public function confirmDelete(int $id): void
    {
        $this->deleting = $id;

        Flux::modal('history-delete')->show();
    }

    public function destroy(): void
    {
        if ($this->deleting === null) {
            return;
        }

        $response = $this->callApi(fn (PendingRequest $api) => $api->delete(
            route('shipment-histories.destroy', ['shipmentHistory' => $this->deleting], absolute: false),
        ));

        Flux::modal('history-delete')->close();

        if ($response === null || $response->failed()) {
            $this->errorMessage = $this->apiErrorMessage($response, __('No pudimos eliminar el evento.'));
            $this->deleting = null;

            return;
        }

        Flux::toast(variant: 'success', text: __('Evento eliminado del historial.'));

        $this->deleting = null;
        $this->loadRows();
    }

    // --- Apoyo -------------------------------------------------------------

    private function resetForm(): void
    {
        $this->resetErrorBag();

        $this->editing = null;
        $this->status = '';
        $this->location = '';
        $this->description = '';
        $this->recorded_at = $this->forInput(null);
    }

    /**
     * Una fecha tal y como la entiende un `<input type="datetime-local">`, que
     * no sabe leer el ISO-8601 con zona que devuelve la API.
     *
     * Sin fecha se propone la de ahora, y no un campo en blanco: `recorded_at`
     * es obligatorio en `shipment-histories.update`, así que dejarlo vacío solo
     * conseguía un 422 al guardar. Además es la fecha que casi siempre toca —
     * un evento se registra cuando ocurre.
     */
    private function forInput(?string $date): string
    {
        // Sin `timezone()` la hora que devuelve la API (UTC) entraría tal cual
        // en el campo, y guardar sin tocarla movería el evento dos horas atrás.
        return ($date ? Carbon::parse($date)->timezone(config('app.timezone')) : now())->format('Y-m-d\TH:i');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function statuses(): array
    {
        return array_map(
            fn (ShipmentStatus $case) => ['value' => $case->value, 'label' => $case->label()],
            ShipmentStatus::cases(),
        );
    }

    public function eventDate(?string $date): string
    {
        // La API serializa las fechas en UTC (`...Z`) aunque la app viva en
        // Madrid, así que sin convertir aquí un evento de las 16:40 se leería
        // como las 14:40.
        return $date
            ? Carbon::parse($date)->timezone(config('app.timezone'))->locale('es')->translatedFormat('j M Y · H:i')
            : '—';
    }

    /**
     * El evento pendiente de borrar, descrito por su guía y su fecha: es lo que
     * lo distingue de los demás eventos del mismo pedido.
     */
    public function deletingLabel(): string
    {
        $event = $this->rowById($this->histories, $this->deleting);

        if ($event === []) {
            return '';
        }

        return collect([
            $event['shipment']['tracking_number'] ?? null,
            isset($event['recorded_at']) ? $this->eventDate((string) $event['recorded_at']) : null,
        ])->filter()->join(' · ');
    }
};
?>

<div class="space-y-6">
    <x-backoffice.heading
        :heading="__('Historial de pedidos')"
        :subheading="__('Eventos registrados en la línea de tiempo de cada envío. Los nuevos eventos nacen con el pedido, aquí solo se corrigen o se borran.')"
    />

    <x-backoffice.search
        model="trackingFilter"
        :label="__('Filtrar por guía')"
        :placeholder="__('Filtrar por número de guía exacto')"
    />

    @if ($errorMessage)
        <x-backoffice.alert :message="$errorMessage" retry="retry" />
    @endif

    <x-backoffice.busy target="trackingFilter, nextPage, previousPage, retry">
        @if (empty($histories))
            @if (! $errorMessage)
                {{-- Aquí no se crean eventos, así que el estado vacío no tiene
                     acción propia: la salida es la pantalla donde sí nacen. --}}
                <x-backoffice.empty
                    icon="clock"
                    :heading="__('No hay eventos que mostrar')"
                    :text="filled($trackingFilter)
                        ? __('Ningún pedido con esa guía tiene eventos registrados.')
                        : __('Todavía no se ha registrado ningún evento de historial.')"
                >
                    <x-slot:actions>
                        <flux:button variant="filled" icon="truck" :href="route('backoffice.shipments')" wire:navigate>
                            {{ __('Ir a Pedidos') }}
                        </flux:button>
                    </x-slot:actions>
                </x-backoffice.empty>
            @endif
        @else
            <div class="space-y-4">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column align="center">{{ __('Guía') }}</flux:table.column>
                        <flux:table.column align="center" class="max-sm:hidden">{{ __('Estado') }}</flux:table.column>
                        <flux:table.column align="center" class="max-md:hidden">{{ __('Ubicación') }}</flux:table.column>
                        <flux:table.column align="center" class="max-xl:hidden">{{ __('Descripción') }}</flux:table.column>
                        <flux:table.column align="center" class="max-sm:hidden">{{ __('Ocurrió') }}</flux:table.column>
                        <flux:table.column align="center">{{ __('Acciones') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($histories as $history)
                            <flux:table.row :key="$history['id']">
                                <flux:table.cell>
                                    <span class="font-semibold text-gris-900 dark:text-blanco">
                                        {{ $history['shipment']['tracking_number'] ?? '—' }}
                                    </span>

                                    {{-- En móvil solo caben dos columnas sin empujar las
                                         acciones fuera de pantalla: el resto se pliega aquí. --}}
                                    <span class="block text-sm text-gris-600 sm:hidden dark:text-azul-200">
                                        {{ $this->eventDate($history['recorded_at'] ?? null) }}
                                    </span>

                                    <span class="mt-1 block sm:hidden">
                                        <x-backoffice.status-badge :status="$history['status'] ?? null" />
                                    </span>
                                </flux:table.cell>

                                <flux:table.cell align="center" class="max-sm:hidden">
                                    <x-backoffice.status-badge :status="$history['status'] ?? null" />
                                </flux:table.cell>

                                <flux:table.cell class="max-md:hidden">{{ $history['location'] ?: '—' }}</flux:table.cell>

                                {{-- La descripción se corta para que no descuadre la
                                     tabla, así que el texto entero se deja al alcance
                                     del cursor en vez de obligar a abrir el editor. --}}
                                <flux:table.cell class="max-xl:hidden">
                                    <span class="block max-w-56 truncate" title="{{ $history['description'] ?: '' }}">
                                        {{ $history['description'] ?: '—' }}
                                    </span>
                                </flux:table.cell>

                                <flux:table.cell class="whitespace-nowrap max-sm:hidden">
                                    {{ $this->eventDate($history['recorded_at'] ?? null) }}
                                </flux:table.cell>

                                <flux:table.cell align="center">
                                    <div class="flex justify-center gap-1">
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="pencil-square"
                                            :label="__('Editar evento')"
                                            wire:click="edit({{ $history['id'] }})"
                                        />
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="trash"
                                            :label="__('Eliminar evento')"
                                            wire:click="confirmDelete({{ $history['id'] }})"
                                        />
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>

                <x-backoffice.pagination :meta="$meta" />
            </div>
        @endif
    </x-backoffice.busy>

    <x-backoffice.modal
        name="history-form"
        size="wide"
        :heading="__('Editar evento del historial')"
        :description="__('Un evento no se puede mover a otro pedido: para eso, bórralo y créalo donde corresponda.')"
    >
        <form wire:submit="save" class="space-y-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Estado') }}</flux:label>
                    <flux:select wire:model="status">
                        @foreach ($this->statuses() as $option)
                            <flux:select.option :value="$option['value']">{{ $option['label'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="status" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Fecha del evento') }}</flux:label>
                    <flux:input type="datetime-local" wire:model="recorded_at" />
                    <flux:error name="recorded_at" />
                </flux:field>

                <flux:field class="sm:col-span-2">
                    <flux:label badge="{{ __('Opcional') }}">{{ __('Ubicación') }}</flux:label>
                    <flux:input wire:model="location" />
                    <flux:error name="location" />
                </flux:field>

                <flux:field class="sm:col-span-2">
                    <flux:label badge="{{ __('Opcional') }}">{{ __('Descripción') }}</flux:label>
                    <flux:textarea wire:model="description" rows="2" />
                    <flux:error name="description" />
                </flux:field>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">{{ __('Guardar') }}</span>
                    <span wire:loading wire:target="save">{{ __('Guardando...') }}</span>
                </flux:button>
            </div>
        </form>
    </x-backoffice.modal>

    {{-- El modal se abre desde una fila y la tapa: sin decir de qué evento
         hablamos, confirmar es un acto de fe. --}}
    <x-backoffice.modal
        name="history-delete"
        size="narrow"
        :heading="__('Eliminar evento')"
        :description="$this->deletingLabel() !== ''
            ? __('El evento de :evento desaparecerá de la línea de tiempo del pedido. No se puede deshacer.', ['evento' => $this->deletingLabel()])
            : __('El evento desaparecerá de la línea de tiempo del pedido. No se puede deshacer.')"
    >
        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancelar') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" wire:click="destroy" wire:loading.attr="disabled" wire:target="destroy">
                {{ __('Eliminar') }}
            </flux:button>
        </div>
    </x-backoffice.modal>
</div>
