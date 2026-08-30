<?php

use App\Enums\ShipmentStatus;
use App\Livewire\Backoffice\BackofficeComponent;
use Flux\Flux;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new
#[Layout('layouts::backoffice')]
#[Title('Pedidos')]
class extends BackofficeComponent
{
    // --- Listado -----------------------------------------------------------

    public string $search = '';

    public string $statusFilter = '';

    /** @var list<array<string, mixed>> */
    public array $shipments = [];

    // --- Formulario --------------------------------------------------------

    /** Número de guía original del pedido que se edita; null si es un alta. */
    public ?string $editing = null;

    public string $tracking_number = '';

    public string $receiver_name = '';

    public string $origin = '';

    public string $destination = '';

    public string $estimated_delivery_date = '';

    public string $status = '';

    public ?int $company_id = null;

    /**
     * Primer evento del historial. Solo se manda en el alta: lo crea
     * `ShipmentObserver::created()` a partir de este mismo bloque.
     *
     * Solo se teclean `location` y `description`, y van mezcladas con el resto
     * de campos del pedido: el estado de ese primer evento es siempre
     * `pendiente` —igual que el del pedido recién creado— y su fecha es la del
     * alta, así que preguntarlas solo daría ocasión de equivocarse. Las dos se
     * rellenan en `save()`.
     *
     * Las claves se llaman igual que en la API (`history.location`, ...) para
     * que sus errores de validación caigan solos en el campo correcto.
     *
     * @var array<string, mixed>
     */
    public array $history = [
        'location' => '',
        'description' => '',
    ];

    /** Guía del pedido pendiente de confirmar borrado. */
    public ?string $deleting = null;

    /** @var list<array<string, mixed>> */
    public array $companies = [];

    public bool $companiesLoaded = false;

    // --- Listado -----------------------------------------------------------

    public function updatedSearch(): void
    {
        $this->resetAndReload();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetAndReload();
    }

    protected function loadRows(): void
    {
        $this->errorMessage = null;

        $response = $this->callApi(fn (PendingRequest $api) => $api->get(
            route('shipments.index', absolute: false),
            array_filter([
                'search' => trim($this->search),
                'status' => $this->statusFilter,
                'page' => $this->page,
            ]),
        ));

        if ($response === null || $response->failed()) {
            $this->shipments = [];
            $this->failListing($response, __('No pudimos cargar los pedidos ahora mismo.'));

            return;
        }

        $this->shipments = $this->rowsFrom($response);
    }

    // --- Alta y edición ----------------------------------------------------

    public function create(): void
    {
        $this->resetForm();

        // Un pedido nace siempre pendiente, así que el estado no se pregunta
        // en el alta: solo aparece al editar, que es cuando cambiarlo tiene
        // sentido.
        $this->status = ShipmentStatus::Pendiente->value;

        $this->loadCompanies();

        Flux::modal('shipment-form')->show();
    }

    public function edit(string $trackingNumber): void
    {
        $this->resetForm();

        $response = $this->callApi(fn (PendingRequest $api) => $api->get(
            route('shipments.show', ['shipment' => $trackingNumber], absolute: false),
        ));

        if ($response === null || $response->failed()) {
            $this->errorMessage = $this->apiErrorMessage($response, __('No pudimos cargar ese pedido.'));

            return;
        }

        $shipment = $response->json();

        $this->editing = $trackingNumber;
        $this->tracking_number = $shipment['tracking_number'] ?? '';
        $this->receiver_name = $shipment['receiver_name'] ?? '';
        $this->origin = $shipment['origin'] ?? '';
        $this->destination = $shipment['destination'] ?? '';
        $this->estimated_delivery_date = $shipment['estimated_delivery_date']
            ? Carbon::parse($shipment['estimated_delivery_date'])->format('Y-m-d')
            : '';
        $this->status = $shipment['status'] ?? '';
        $this->company_id = $shipment['company_id'] ?? null;

        $this->loadCompanies();

        Flux::modal('shipment-form')->show();
    }

    /**
     * Guardar el alta o la edición.
     *
     * No se valida aquí: la validación vive en `StoreShipmentRequest` /
     * `UpdateShipmentRequest`, y lo que devuelva la API (422) se vuelca sobre
     * los campos. Así no hay dos juegos de reglas que puedan divergir.
     */
    public function save(): void
    {
        $this->resetErrorBag();
        $this->errorMessage = null;

        if ($this->editing === null) {
            // Única validación local de la pantalla, y a propósito: la API deja
            // `location` y `description` como opcionales porque un alta por API
            // puede no tenerlas, pero aquí las queremos siempre — un primer
            // evento sin dónde ni qué no le cuenta nada a nadie.
            $this->validate([
                'history.location' => ['required', 'string', 'max:255'],
                'history.description' => ['required', 'string'],
            ], [
                'history.location.required' => __('Indica dónde se encuentra el pedido al registrarlo.'),
                'history.description.required' => __('Describe el primer evento del pedido.'),
            ]);
        }

        $payload = array_filter([
            'tracking_number' => trim($this->tracking_number),
            'receiver_name' => trim($this->receiver_name),
            'origin' => trim($this->origin),
            'destination' => trim($this->destination),
            'estimated_delivery_date' => $this->estimated_delivery_date,
            'status' => $this->status,
        ], fn ($value) => $value !== '' && $value !== null);

        // La empresa solo viaja si quien crea el pedido puede elegirla. Para el
        // resto se omite, y la API le asigna la del usuario que hace el alta
        // (ver `ShipmentController::store`), que es de donde debe salir.
        if ($this->canChooseCompany() && $this->company_id !== null) {
            $payload['company_id'] = $this->company_id;
        }

        if ($this->editing === null) {
            // El historial inicial solo viaja en el alta: en la edición el
            // historial ya existe y se toca desde su propia pantalla. El estado
            // y la fecha se ponen aquí, no en el formulario: son siempre
            // "pendiente" y "ahora".
            $payload['history'] = [
                'status' => ShipmentStatus::Pendiente->value,
                'location' => trim((string) $this->history['location']),
                'description' => trim((string) $this->history['description']),
                'recorded_at' => now()->toDateTimeString(),
            ];
        }

        $response = $this->editing === null
            ? $this->callApi(fn (PendingRequest $api) => $api->post(route('shipments.store', absolute: false), $payload))
            : $this->callApi(fn (PendingRequest $api) => $api->put(
                route('shipments.update', ['shipment' => $this->editing], absolute: false),
                $payload,
            ));

        if ($this->applyApiValidationErrors($response)) {
            return;
        }

        if ($response === null || $response->failed()) {
            $this->errorMessage = $this->apiErrorMessage($response, __('No pudimos guardar el pedido.'));

            return;
        }

        Flux::modal('shipment-form')->close();
        Flux::toast(variant: 'success', text: $this->editing === null
            ? __('Pedido creado con su primer evento de historial.')
            : __('Pedido actualizado.'));

        $this->resetForm();
        $this->loadRows();
    }

    // --- Borrado -----------------------------------------------------------

    public function confirmDelete(string $trackingNumber): void
    {
        $this->deleting = $trackingNumber;

        Flux::modal('shipment-delete')->show();
    }

    public function destroy(): void
    {
        if ($this->deleting === null) {
            return;
        }

        $response = $this->callApi(fn (PendingRequest $api) => $api->delete(
            route('shipments.destroy', ['shipment' => $this->deleting], absolute: false),
        ));

        Flux::modal('shipment-delete')->close();

        if ($response === null || $response->failed()) {
            $this->errorMessage = $this->apiErrorMessage($response, __('No pudimos eliminar el pedido.'));
            $this->deleting = null;

            return;
        }

        Flux::toast(variant: 'success', text: __('Pedido eliminado. Su historial y documentos se han borrado con él.'));

        $this->deleting = null;
        $this->loadRows();
    }

    // --- Apoyo -------------------------------------------------------------

    /**
     * ¿Quién mira puede elegir la empresa del pedido a mano?
     *
     * Solo el superadministrador, que es el único rol que gestiona empresas
     * (ver `RolesAndPermissionsSeeder`). Para todos los demás la empresa sale
     * de su propia cuenta y el desplegable ni se pinta.
     */
    public function canChooseCompany(): bool
    {
        return auth()->user()?->hasRole('superadministrador') ?? false;
    }

    /**
     * Empresas para el desplegable del formulario.
     *
     * Se recorren todas las páginas dentro de una sola llamada a `callApi()`
     * —y por tanto con un solo token— porque `companies.index` pagina de 15 en
     * 15 y el desplegable necesita la lista completa.
     */
    private function loadCompanies(): void
    {
        // Se comprueba el rol antes de llamar: sin esto, cada vez que un agente
        // abriera el formulario se llevaría un 403 de `companies.index` para
        // acabar sin desplegable igualmente.
        if ($this->companiesLoaded || ! $this->canChooseCompany()) {
            return;
        }

        $this->companiesLoaded = true;

        $this->callApi(function (PendingRequest $api) {
            $companies = [];
            $page = 1;

            do {
                $response = $api->get(route('companies.index', absolute: false), ['page' => $page]);

                if ($response->failed()) {
                    return $response;
                }

                $companies = [...$companies, ...($response->json('data') ?? [])];
                $lastPage = (int) ($response->json('last_page') ?? 1);
            } while (++$page <= $lastPage && $page <= 20);

            $this->companies = $companies;

            return $response;
        });
    }

    private function resetForm(): void
    {
        $this->resetErrorBag();

        $this->editing = null;
        $this->tracking_number = '';
        $this->receiver_name = '';
        $this->origin = '';
        $this->destination = '';
        $this->estimated_delivery_date = '';
        $this->status = '';
        $this->company_id = null;
        $this->history = [
            'location' => '',
            'description' => '',
        ];
    }

    /**
     * Estados del envío para los desplegables.
     *
     * @return list<array{value: string, label: string}>
     */
    public function statuses(): array
    {
        return array_map(
            fn (ShipmentStatus $case) => ['value' => $case->value, 'label' => $case->label()],
            ShipmentStatus::cases(),
        );
    }

    /**
     * Fecha corta ya formateada, o un guion si no viene.
     */
    public function shortDate(?string $date): string
    {
        return $date ? Carbon::parse($date)->locale('es')->translatedFormat('j M Y') : '—';
    }
};
?>

<div class="space-y-6">
    <x-backoffice.heading
        :heading="__('Pedidos')"
        :subheading="__('Alta, edición y baja de envíos. Al crear uno se registra también su primer evento de historial.')"
    >
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="create">
                {{ __('Nuevo pedido') }}
            </flux:button>
        </x-slot:actions>
    </x-backoffice.heading>

    {{-- Filtros: en columna hasta sm para que no se aplasten en móvil. --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <x-backoffice.search
            model="search"
            :label="__('Buscar pedidos')"
            :placeholder="__('Buscar por guía, destinatario, origen o destino')"
        />

        <flux:select wire:model.live="statusFilter" class="sm:max-w-56" :label="__('Filtrar por estado')" label:class="sr-only">
            <flux:select.option value="">{{ __('Todos los estados') }}</flux:select.option>
            @foreach ($this->statuses() as $option)
                <flux:select.option :value="$option['value']">{{ $option['label'] }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if ($errorMessage)
        <x-backoffice.alert :message="$errorMessage" retry="retry" />
    @endif

    <x-backoffice.busy target="search, statusFilter, nextPage, previousPage, retry">
        @if (empty($shipments))
            @if (! $errorMessage)
                <x-backoffice.empty
                    icon="truck"
                    :heading="__('No hay pedidos que mostrar')"
                    :text="filled($search) || filled($statusFilter)
                        ? __('Ningún pedido coincide con el filtro aplicado.')
                        : __('Todavía no se ha registrado ningún envío.')"
                >
                    <x-slot:actions>
                        <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Nuevo pedido') }}</flux:button>
                    </x-slot:actions>
                </x-backoffice.empty>
            @endif
        @else
            <div class="space-y-4">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Guía') }}</flux:table.column>
                        <flux:table.column class="max-md:hidden">{{ __('Destinatario') }}</flux:table.column>
                        <flux:table.column class="max-lg:hidden">{{ __('Ruta') }}</flux:table.column>
                        <flux:table.column class="max-lg:hidden">{{ __('Entrega estimada') }}</flux:table.column>
                        <flux:table.column class="max-sm:hidden">{{ __('Estado') }}</flux:table.column>
                        <flux:table.column class="text-end">{{ __('Acciones') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($shipments as $shipment)
                            <flux:table.row :key="$shipment['id']">
                                <flux:table.cell>
                                    <span class="font-semibold text-gris-900 dark:text-blanco">
                                        {{ $shipment['tracking_number'] }}
                                    </span>

                                    {{-- En móvil solo caben dos columnas sin empujar las
                                         acciones fuera de pantalla, así que lo demás
                                         —incluido el estado— se pliega aquí debajo. --}}
                                    <span class="block text-sm text-gris-600 md:hidden dark:text-azul-200">
                                        {{ $shipment['receiver_name'] }} · {{ $shipment['destination'] }}
                                    </span>

                                    <span class="mt-1 block sm:hidden">
                                        <x-backoffice.status-badge :status="$shipment['status'] ?? null" />
                                    </span>
                                </flux:table.cell>

                                <flux:table.cell class="max-md:hidden">{{ $shipment['receiver_name'] }}</flux:table.cell>

                                <flux:table.cell class="whitespace-nowrap max-lg:hidden">
                                    {{ $shipment['origin'] }} → {{ $shipment['destination'] }}
                                </flux:table.cell>

                                <flux:table.cell class="whitespace-nowrap max-lg:hidden">
                                    {{ $this->shortDate($shipment['estimated_delivery_date'] ?? null) }}
                                </flux:table.cell>

                                <flux:table.cell class="max-sm:hidden">
                                    <x-backoffice.status-badge :status="$shipment['status'] ?? null" />
                                </flux:table.cell>

                                <flux:table.cell class="text-end">
                                    <div class="flex justify-end gap-1">
                                        {{-- Enlace, no `wire:click`: el detalle es una
                                             pantalla propia, así que se navega a ella
                                             (con `wire:navigate`, como el resto del
                                             backoffice) en vez de abrir un modal. --}}
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="eye"
                                            :label="__('Ver pedido')"
                                            :href="route('backoffice.shipments.show', ['tracking_number' => $shipment['tracking_number']])"
                                            wire:navigate
                                        />
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="pencil-square"
                                            :label="__('Editar pedido')"
                                            wire:click="edit('{{ $shipment['tracking_number'] }}')"
                                        />
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="trash"
                                            :label="__('Eliminar pedido')"
                                            wire:click="confirmDelete('{{ $shipment['tracking_number'] }}')"
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

    {{-- Alta y edición comparten formulario: lo único que cambia es que el
         bloque de historial solo aparece en el alta. --}}
    <x-backoffice.modal
        name="shipment-form"
        size="wide"
        :heading="$editing === null ? __('Nuevo pedido') : __('Editar pedido')"
        :description="$editing === null
            ? __('Nace como pendiente, con la ubicación y la descripción como primer evento de su historial.')
            : __('El historial de este pedido se gestiona desde la sección Historial de pedidos.')"
        wire:close="$refresh"
    >
        <form wire:submit="save" class="space-y-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Número de guía') }}</flux:label>
                    <flux:input wire:model="tracking_number" placeholder="TRC-0000000001" />
                    <flux:error name="tracking_number" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Destinatario') }}</flux:label>
                    <flux:input wire:model="receiver_name" />
                    <flux:error name="receiver_name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Origen') }}</flux:label>
                    <flux:input wire:model="origin" placeholder="Madrid, España" />
                    <flux:error name="origin" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Destino') }}</flux:label>
                    <flux:input wire:model="destination" placeholder="Lisboa, Portugal" />
                    <flux:error name="destination" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Entrega estimada') }}</flux:label>
                    <flux:input type="date" wire:model="estimated_delivery_date" />
                    <flux:error name="estimated_delivery_date" />
                </flux:field>

                {{-- El estado no se pregunta en el alta: un pedido nace
                     siempre pendiente. Solo se puede cambiar editando. --}}
                @if ($editing !== null)
                    <flux:field>
                        <flux:label>{{ __('Estado') }}</flux:label>
                        <flux:select wire:model="status">
                            @foreach ($this->statuses() as $option)
                                <flux:select.option :value="$option['value']">{{ $option['label'] }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="status" />
                    </flux:field>
                @endif

                {{-- Ubicación y descripción son el primer evento del historial,
                     pero se piden aquí, con el resto del pedido: quien da de
                     alta rellena un solo formulario, no dos bloques. --}}
                @if ($editing === null)
                    {{-- A media fila para emparejarse con "Entrega estimada",
                         que en el alta se queda sin el campo Estado al lado. --}}
                    <flux:field>
                        <flux:label>{{ __('Ubicación actual') }}</flux:label>
                        <flux:input wire:model="history.location" placeholder="Centro logístico de Madrid" />
                        <flux:error name="history.location" />
                    </flux:field>

                    <flux:field class="sm:col-span-2">
                        <flux:label>{{ __('Descripción') }}</flux:label>
                        <flux:textarea wire:model="history.description" rows="2" :placeholder="__('Pedido registrado en el sistema.')" />
                        <flux:error name="history.description" />
                    </flux:field>
                @endif

                {{-- Solo el superadministrador elige empresa. Para el resto sale
                     de su propia cuenta, así que el campo ni aparece. --}}
                @if ($this->canChooseCompany())
                    <flux:field class="sm:col-span-2">
                        <flux:label>{{ __('Empresa') }}</flux:label>
                        <flux:select wire:model="company_id">
                            <flux:select.option value="">{{ __('La empresa de quien registra el pedido') }}</flux:select.option>
                            @foreach ($companies as $company)
                                <flux:select.option :value="$company['id']">{{ $company['name'] }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="company_id" />
                    </flux:field>
                @endif
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

    <x-backoffice.modal
        name="shipment-delete"
        size="narrow"
        :heading="__('Eliminar pedido')"
        :description="__('Se eliminará :guia junto con su historial, sus documentos y los vínculos con usuarios. No se puede deshacer.', ['guia' => $deleting])"
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
