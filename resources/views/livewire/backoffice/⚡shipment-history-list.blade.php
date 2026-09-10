<?php

use App\Enums\ShipmentStatus;
use App\Livewire\Backoffice\BackofficeComponent;
use Flux\Flux;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;

/**
 * El historial de un pedido, dentro de su pantalla de detalle.
 *
 * Es un componente aparte y no un trozo de ⚡shipment-detail para que editar un
 * evento solo repinte esta lista: el formulario del pedido que hay al lado
 * puede tener cambios a medio teclear, y no tienen por qué perderse porque se
 * corrija una fecha aquí.
 *
 * Crea y edita. Borrar un evento sigue estando en la pantalla de Historial de
 * pedidos, que es la que los gestiona de verdad.
 */
new class extends BackofficeComponent
{
    /**
     * Pedido cuyo historial se lista. Lo pasa la pantalla de detalle.
     *
     * El parámetro de `mount()` es opcional solo por compatibilidad de firma:
     * la base declara `mount()` sin argumentos y PHP no deja añadir uno
     * obligatorio al sobrescribir.
     */
    public ?int $shipmentId = null;

    /** @var list<array<string, mixed>> */
    public array $histories = [];

    // --- Formulario --------------------------------------------------------

    /** Id del evento que se edita; null si no hay ninguno abierto. */
    public ?int $editing = null;

    public string $status = '';

    public string $location = '';

    public string $description = '';

    public string $recorded_at = '';

    public function mount(?int $shipmentId = null): void
    {
        $this->shipmentId = $shipmentId;
        $this->recorded_at = $this->forInput(null);

        $this->loadRows();
    }

    protected function loadRows(): void
    {
        $this->errorMessage = null;

        // Sin pedido no hay historial que pedir: la pantalla de detalle monta
        // esta lista solo cuando lo ha cargado, pero así no depende de ello.
        if ($this->shipmentId === null) {
            $this->histories = [];

            return;
        }

        $response = $this->callApi(fn (PendingRequest $api) => $api->get(
            route('shipment-histories.index', absolute: false),
            ['shipment_id' => $this->shipmentId, 'page' => $this->page],
        ));

        if ($response === null || $response->failed()) {
            $this->histories = [];
            $this->failListing($response, __('No pudimos cargar el historial de este pedido.'));

            return;
        }

        $this->histories = $this->rowsFrom($response);
    }

    /**
     * Abrir el formulario para un evento nuevo.
     *
     * Lo dispara el botón de la pantalla de detalle, que está arriba con el
     * resto de acciones del pedido: el alta se maneja aquí —donde vive el
     * formulario y donde está la lista que hay que refrescar— pero el botón se
     * pinta con sus hermanos, no suelto sobre la columna.
     */
    #[On('crear-evento-historial')]
    public function create(): void
    {
        $this->resetForm();

        // Prefill: el último evento cargado es el estado en el que está hoy el
        // pedido, y lo normal es que el nuevo lo confirme o lo avance desde
        // ahí. Sin lista todavía, arranca en pendiente.
        $this->status = end($this->histories)['status'] ?? ShipmentStatus::Pendiente->value;

        Flux::modal('shipment-history-form')->show();
    }

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

        Flux::modal('shipment-history-form')->show();
    }

    /**
     * Guardar el alta o la edición.
     *
     * No se valida aquí: la validación vive en `ShipmentHistoryController` y lo
     * que devuelva la API (422) se vuelca sobre los campos.
     */
    public function save(): void
    {
        // Un alta necesita saber a qué pedido va; una edición, cuál se edita.
        // Sin una de las dos cosas no hay nada que guardar.
        if ($this->editing === null && $this->shipmentId === null) {
            return;
        }

        $this->resetErrorBag();
        $this->errorMessage = null;

        // `location` y `description` se mandan aunque vayan vacíos: la API los
        // acepta como `nullable`, y así se puede borrar lo que se tecleó mal.
        $payload = [
            'status' => $this->status,
            'location' => trim($this->location) ?: null,
            'description' => trim($this->description) ?: null,
            'recorded_at' => $this->recorded_at,
        ];

        $response = $this->editing === null
            ? $this->callApi(fn (PendingRequest $api) => $api->post(
                route('shipment-histories.store', absolute: false),
                // El pedido lo pone la pantalla, no el formulario: un evento se
                // crea desde el detalle de un pedido concreto, así que
                // preguntarlo solo daría ocasión de equivocarse.
                [...$payload, 'shipment_id' => $this->shipmentId],
            ))
            : $this->callApi(fn (PendingRequest $api) => $api->put(
                route('shipment-histories.update', ['shipmentHistory' => $this->editing], absolute: false),
                $payload,
            ));

        if ($this->applyApiValidationErrors($response)) {
            return;
        }

        if ($response === null || $response->failed()) {
            $this->errorMessage = $this->apiErrorMessage($response, __('No pudimos guardar el evento.'));

            return;
        }

        Flux::modal('shipment-history-form')->close();
        Flux::toast(variant: 'success', text: $this->editing === null
            ? __('Evento añadido al historial.')
            : __('Evento del historial actualizado.'));

        $this->resetForm();
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
     */
    private function forInput(?string $date): string
    {
        // Sin `timezone()` la hora que devuelve la API (UTC) entraría tal cual
        // en el campo, y guardar sin tocarla movería el evento dos horas atrás.
        return ($date ? Carbon::parse($date)->timezone(config('app.timezone')) : now())->format('Y-m-d\TH:i');
    }

    /**
     * Fecha del evento ya formateada para leerla de un vistazo.
     */
    public function eventDate(?string $date): string
    {
        // locale('es') explícito: APP_LOCALE es 'en' pero la interfaz está en
        // español, y sin esto saldría "9 Aug 2026".
        // La API serializa las fechas en UTC (`...Z`) aunque la app viva en
        // Madrid, así que sin convertir aquí un evento de las 16:40 se leería
        // como las 14:40.
        return $date
            ? Carbon::parse($date)->timezone(config('app.timezone'))->locale('es')->translatedFormat('j M Y · H:i')
            : '—';
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
};
?>

<div class="space-y-5 rounded-xl border border-line bg-surface p-6">
    <flux:heading size="lg" class="text-ink">
        {{ __('Historial del pedido') }}
    </flux:heading>

    @if ($errorMessage)
        <x-backoffice.alert :message="$errorMessage" retry="retry" />
    @endif

    <x-backoffice.busy target="retry, nextPage, previousPage, edit">
        @if (empty($histories))
            @if (! $errorMessage)
                <x-backoffice.empty
                    icon="clock"
                    :heading="__('Sin eventos')"
                    :text="__('Este pedido todavía no tiene ningún evento en su historial.')"
                />
            @endif
        @else
            {{-- Línea de tiempo, no lista con separadores: es el patrón que pide
                 design.md para el historial del detalle. Mismo lenguaje visual
                 que <x-shipment-timeline>, la que ve el cliente, con el botón
                 de editar añadido a la derecha de cada evento. --}}
            <ol>
                @foreach ($histories as $history)
                    @php
                        // Mismo mapeo estado → token que <x-backoffice.status-badge>
                        // (design.md → Estados de envío), aquí resuelto a icono:
                        // el badge da la etiqueta y el color de fondo, y el icono
                        // el color suelto que la línea de tiempo necesita.
                        $case = \App\Enums\ShipmentStatus::tryFrom((string) ($history['status'] ?? ''));

                        $marker = match ($case) {
                            \App\Enums\ShipmentStatus::Entregado => ['icon' => 'check-circle', 'chip' => 'bg-ok-soft text-ok'],
                            \App\Enums\ShipmentStatus::EnTransito => ['icon' => 'truck', 'chip' => 'bg-info-soft text-info'],
                            \App\Enums\ShipmentStatus::EnAduana => ['icon' => 'building-office-2', 'chip' => 'bg-aduana-soft text-aduana'],
                            \App\Enums\ShipmentStatus::Incidencia => ['icon' => 'exclamation-triangle', 'chip' => 'bg-danger-soft text-danger'],
                            default => ['icon' => 'clock', 'chip' => 'bg-idle-soft text-idle'],
                        };
                    @endphp

                    <li class="relative flex gap-4 pb-6 last:pb-0">
                        {{-- La línea cuelga del icono para quedar centrada bajo
                             él, y no se pinta en el último evento. --}}
                        @unless ($loop->last)
                            <span aria-hidden="true" class="absolute top-9 bottom-0 left-4 w-px -translate-x-1/2 bg-line"></span>
                        @endunless

                        <span
                            aria-hidden="true"
                            @class(['flex size-8 shrink-0 items-center justify-center rounded-full', $marker['chip']])
                        >
                            <flux:icon :name="$marker['icon']" variant="micro" class="size-4" />
                        </span>

                        <div class="flex min-w-0 flex-1 items-start justify-between gap-3">
                            <div class="min-w-0">
                                <x-backoffice.status-badge :status="$history['status'] ?? null" />

                                @if ($history['description'] ?? null)
                                    <p class="mt-1 text-ink">{{ $history['description'] }}</p>
                                @endif

                                <flux:text size="sm" class="mt-0.5 text-ink-muted">
                                    {{ collect([$history['location'] ?? null, $this->eventDate($history['recorded_at'] ?? null)])->filter()->join(' · ') }}
                                </flux:text>
                            </div>

                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="pencil-square"
                                class="shrink-0"
                                :label="__('Editar evento')"
                                wire:click="edit({{ $history['id'] }})"
                            />
                        </div>
                    </li>
                @endforeach
            </ol>

            <x-backoffice.pagination :meta="$meta" />
        @endif
    </x-backoffice.busy>

    <x-backoffice.modal
        name="shipment-history-form"
        :heading="$editing === null ? __('Nuevo evento') : __('Editar evento')"
        :description="$editing === null
            ? __('Se añade al historial de este pedido. El estado del pedido en sí se cambia abajo, en su formulario.')
            : __('Corrige lo que se registró mal. El pedido al que pertenece no se puede cambiar.')"
        wire:close="$refresh"
    >
        <form wire:submit="save" class="space-y-4">
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
                <flux:label>{{ __('Ubicación') }}</flux:label>
                <flux:input wire:model="location" placeholder="Centro logístico de Madrid" />
                <flux:error name="location" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Descripción') }}</flux:label>
                <flux:textarea wire:model="description" rows="2" />
                <flux:error name="description" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Fecha del evento') }}</flux:label>
                <flux:input type="datetime-local" wire:model="recorded_at" />
                <flux:error name="recorded_at" />
            </flux:field>

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
</div>
