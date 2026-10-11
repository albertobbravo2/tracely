<?php

use App\Enums\ShipmentStatus;
use App\Livewire\Backoffice\BackofficeComponent;
use Flux\Flux;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

/**
 * Detalle de un pedido: su formulario, su borrado y su historial.
 *
 * Es la única pantalla del backoffice que no es un listado, pero sale de la
 * misma base: lo que se aprovecha de `BackofficeComponent` es la llamada a
 * nuestra propia API ya autenticada y la traducción del fallo a un mensaje,
 * que es idéntica aquí y en los cinco listados.
 *
 * El alta no vive aquí, y no es un olvido: un pedido se crea desde el listado,
 * que es donde hay un botón para ello. Esta pantalla es de uno que ya existe,
 * así que solo hace leer, editar y borrar.
 */
new
#[Layout('layouts::backoffice')]
#[Title('Pedido')]
class extends BackofficeComponent
{
    /**
     * Guía con la que el pedido está guardado: la de la URL, y con la que se
     * habla con la API.
     *
     * No confundir con `$tracking_number`, que es el campo del formulario y
     * puede llevar ya un valor nuevo sin guardar.
     */
    public string $editing = '';

    /** @var array<string, mixed> */
    public array $shipment = [];

    // --- Formulario --------------------------------------------------------

    public string $tracking_number = '';

    public string $receiver_name = '';

    public string $origin = '';

    public string $destination = '';

    public string $estimated_delivery_date = '';

    public string $status = '';

    public ?int $company_id = null;

    /** @var list<array<string, mixed>> */
    public array $companies = [];

    public bool $companiesLoaded = false;

    /**
     * El parámetro es opcional solo por compatibilidad de firma: la base
     * declara `mount()` sin argumentos y PHP no deja añadir uno obligatorio al
     * sobrescribir. La ruta siempre lo trae.
     */
    public function mount(string $tracking_number = ''): void
    {
        $this->editing = $tracking_number;

        $this->loadRows();
    }

    /**
     * Pedir el pedido a la API y volcarlo en el formulario.
     *
     * Es el `loadRows()` de esta pantalla —aquí la "fila" es un único pedido—,
     * así que el botón de reintentar de `<x-backoffice.alert>` funciona igual
     * que en los listados sin tener que declarar nada más.
     */
    protected function loadRows(): void
    {
        $this->errorMessage = null;

        $response = $this->callApi(fn (PendingRequest $api) => $api->get(
            route('shipments.show', ['shipment' => $this->editing], absolute: false),
        ));

        if ($response === null || $response->failed()) {
            $this->shipment = [];
            $this->errorMessage = $this->apiErrorMessage($response, __('No pudimos cargar ese pedido.'));

            return;
        }

        $payload = $response->json();

        $this->shipment = is_array($payload) ? $payload : [];

        $this->fillForm();
        $this->loadCompanies();
    }

    /**
     * Volcar el pedido recién traído sobre los campos del formulario.
     */
    private function fillForm(): void
    {
        $this->resetErrorBag();

        $this->tracking_number = $this->shipment['tracking_number'] ?? '';
        $this->receiver_name = $this->shipment['receiver_name'] ?? '';
        $this->origin = $this->shipment['origin'] ?? '';
        $this->destination = $this->shipment['destination'] ?? '';
        $this->estimated_delivery_date = $this->shipment['estimated_delivery_date']
            ? Carbon::parse($this->shipment['estimated_delivery_date'])->format('Y-m-d')
            : '';
        $this->status = $this->shipment['status'] ?? '';
        $this->company_id = $this->shipment['company_id'] ?? null;
    }

    /**
     * Guardar los cambios del pedido.
     *
     * No se valida aquí: la validación vive en `UpdateShipmentRequest`, y lo
     * que devuelva la API (422) se vuelca sobre los campos. Así no hay dos
     * juegos de reglas que puedan divergir.
     */
    public function save(): void
    {
        $this->resetErrorBag();
        $this->errorMessage = null;

        $payload = array_filter([
            'tracking_number' => trim($this->tracking_number),
            'receiver_name' => trim($this->receiver_name),
            'origin' => trim($this->origin),
            'destination' => trim($this->destination),
            'estimated_delivery_date' => $this->estimated_delivery_date,
            'status' => $this->status,
        ], fn ($value) => $value !== '' && $value !== null);

        // La empresa solo viaja si quien edita puede elegirla; para el resto ni
        // se pinta el desplegable, así que mandarla sería mandar un null.
        if ($this->canChooseCompany() && $this->company_id !== null) {
            $payload['company_id'] = $this->company_id;
        }

        $response = $this->callApi(fn (PendingRequest $api) => $api->put(
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

        // La guía está en la URL: si se ha cambiado, la de la barra de
        // direcciones ya no existe y hay que saltar a la nueva. Sin toast en
        // este camino a propósito — es un aviso de cliente y no sobrevive a la
        // navegación.
        if (trim($this->tracking_number) !== $this->editing) {
            $this->redirectRoute(
                'backoffice.shipments.show',
                ['tracking_number' => trim($this->tracking_number)],
                navigate: true,
            );

            return;
        }

        Flux::toast(variant: 'success', text: __('Pedido actualizado.'));

        $this->loadRows();
    }

    /**
     * Borrar el pedido y volver al listado: quedarse en el detalle de algo que
     * ya no existe no lleva a ninguna parte.
     */
    public function destroy(): void
    {
        $response = $this->callApi(fn (PendingRequest $api) => $api->delete(
            route('shipments.destroy', ['shipment' => $this->editing], absolute: false),
        ));

        Flux::modal('shipment-delete')->close();

        if ($response === null || $response->failed()) {
            $this->errorMessage = $this->apiErrorMessage($response, __('No pudimos eliminar el pedido.'));

            return;
        }

        $this->redirectRoute('backoffice.shipments', navigate: true);
    }

    // --- Apoyo -------------------------------------------------------------

    /**
     * ¿Quién mira puede cambiar la empresa del pedido?
     *
     * Solo el superadministrador, que es el único rol que gestiona empresas
     * (ver `RolesAndPermissionsSeeder`). Mismo criterio que en el listado.
     */
    public function canChooseCompany(): bool
    {
        return auth()->user()?->hasRole('superadministrador') ?? false;
    }

    /**
     * Empresas para el desplegable.
     *
     * Se recorren todas las páginas dentro de una sola llamada a `callApi()`
     * —y por tanto con un solo token— porque `companies.index` pagina de 15 en
     * 15 y el desplegable necesita la lista completa.
     */
    private function loadCompanies(): void
    {
        // Se comprueba el rol antes de llamar: sin esto, cada vez que un agente
        // abriera la pantalla se llevaría un 403 de `companies.index` para
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

    /**
     * Estados del envío para el desplegable.
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
};
?>

<div class="space-y-8">

    @php
        $estadoActual = ShipmentStatus::tryFrom((string) ($shipment['status'] ?? ''));

        // Recorrido normal de un envío. `incidencia` no es un paso del recorrido:
        // es una salida de él, así que cuando el pedido está en ese estado no se
        // marca ningún nodo y se avisa debajo del stepper.
        $pasos = [
            ['case' => ShipmentStatus::Pendiente, 'icon' => 'clock'],
            ['case' => ShipmentStatus::EnTransito, 'icon' => 'truck'],
            ['case' => ShipmentStatus::EnAduana, 'icon' => 'building-office-2'],
            ['case' => ShipmentStatus::Entregado, 'icon' => 'check-circle'],
        ];

        $indiceActual = match ($estadoActual) {
            ShipmentStatus::Pendiente => 0,
            ShipmentStatus::EnTransito => 1,
            ShipmentStatus::EnAduana => 2,
            ShipmentStatus::Entregado => 3,
            default => -1,
        };

        // Fechas: la API serializa en UTC y la interfaz va en español, así que las
        // dos cosas se fijan aquí igual que en las listas.
        $fecha = fn (?string $valor, bool $conHora = false) => $valor
            ? Carbon::parse($valor)->timezone(config('app.timezone'))->locale('es')->translatedFormat($conHora ? 'j M Y · H:i' : 'j M Y')
            : '—';

        $empresa = collect($companies)->firstWhere('id', $shipment['company_id'] ?? null)['name'] ?? null;
    @endphp

    {{-- Cabecera: migas, la guía como título —en `font-mono`, que es lo que
         design.md reserva para el número de guía destacado— y las acciones del
         pedido a la derecha. --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0">
            <nav aria-label="{{ __('Migas de pan') }}" class="flex items-center gap-1.5 text-sm text-ink-muted">
                <a href="{{ route('backoffice.shipments') }}" wire:navigate class="hover:text-primary">
                    {{ __('Pedidos') }}
                </a>

                <span aria-hidden="true">/</span>

                <span class="truncate">{{ $editing }}</span>
            </nav>

            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h1 class="font-mono text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
                    {{ $editing }}
                </h1>

                @if (! empty($shipment))
                    <x-backoffice.status-badge :status="$shipment['status'] ?? null" />
                @endif
            </div>

            <flux:text class="mt-2 text-ink-2">
                {{ __('Sigue el progreso del pedido, revisa sus datos y edítalo si hace falta.') }}
            </flux:text>
        </div>

        <div class="flex shrink-0 flex-wrap items-center gap-2">
            <flux:button icon="arrow-left" :href="route('backoffice.shipments')" wire:navigate>
                {{ __('Volver a pedidos') }}
            </flux:button>

            @if (! empty($shipment))
                {{-- El alta de evento no la resuelve esta pantalla: la dispara
                     para que la atienda la lista del historial, que es quien
                     tiene el formulario y quien tiene que refrescarse después. --}}
                <flux:button icon="plus" wire:click="$dispatch('crear-evento-historial')">
                    {{ __('Nuevo evento') }}
                </flux:button>

                <flux:modal.trigger name="shipment-delete">
                    <flux:button variant="danger" icon="trash">
                        {{ __('Eliminar pedido') }}
                    </flux:button>
                </flux:modal.trigger>
            @endif
        </div>
    </div>

    @if ($errorMessage)
        <x-backoffice.alert :message="$errorMessage" retry="retry" />
    @endif

    <x-backoffice.busy target="retry, save">
        @if (empty($shipment))
            {{-- Sin pedido y sin error no hay nada que reintentar: la guía de la
                 URL no corresponde a ningún pedido. --}}
            @if (! $errorMessage)
                <x-backoffice.empty
                    icon="truck"
                    :heading="__('No encontramos ese pedido')"
                    :text="__('Ningún pedido responde a esa guía. Puede que lo haya borrado otra persona.')"
                >
                    <x-slot:actions>
                        <flux:button variant="primary" :href="route('backoffice.shipments')" wire:navigate>
                            {{ __('Ver todos los pedidos') }}
                        </flux:button>
                    </x-slot:actions>
                </x-backoffice.empty>
            @endif
        @else
            {{-- Dos columnas (design.md → Detalle de pedido): la ancha con el
                 progreso y el historial, la estrecha con los datos, los clientes
                 vinculados y los documentos. --}}
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <div class="space-y-6 rounded-xl border border-line bg-surface p-6">
                        <flux:heading size="lg" class="text-ink">
                            {{ __('Progreso del envío') }}
                        </flux:heading>

                        <ol class="flex items-start">
                            @foreach ($pasos as $paso)
                                @php
                                    $completado = $indiceActual >= $loop->index;
                                    $esActual = $indiceActual === $loop->index;

                                    $nodo = $completado
                                        ? 'border-primary bg-primary text-on-primary'
                                        : 'border-line bg-surface text-ink-muted';

                                    $etiqueta = match (true) {
                                        $esActual => 'text-ink font-semibold',
                                        $completado => 'text-ink-2',
                                        default => 'text-ink-muted',
                                    };
                                @endphp

                                <li @class(['flex items-start', 'flex-1' => ! $loop->last])>
                                    <div class="flex w-20 shrink-0 flex-col items-center gap-2 text-center sm:w-24">
                                        <span
                                            aria-hidden="true"
                                            @class([
                                                'flex size-9 items-center justify-center rounded-full border',
                                                'ring-4 ring-primary-soft' => $esActual,
                                                $nodo,
                                            ])
                                        >
                                            <flux:icon :name="$paso['icon']" variant="micro" class="size-4" />
                                        </span>

                                        <span @class(['text-xs leading-tight', $etiqueta])>
                                            {{ $paso['case']->label() }}
                                        </span>
                                    </div>

                                    @unless ($loop->last)
                                        <span
                                            aria-hidden="true"
                                            @class([
                                                'mt-4 h-0.5 flex-1 rounded-full',
                                                $indiceActual > $loop->index ? 'bg-primary' : 'bg-line',
                                            ])
                                        ></span>
                                    @endunless
                                </li>
                            @endforeach
                        </ol>

                        @if ($estadoActual === ShipmentStatus::Incidencia)
                            <div class="flex items-start gap-3 rounded-xl border border-danger/25 bg-danger-soft px-4 py-3">
                                <flux:icon name="exclamation-triangle" variant="outline" class="mt-0.5 size-5 shrink-0 text-danger" />

                                <flux:text class="text-danger">
                                    {{ __('Este pedido tiene una incidencia abierta: su recorrido está detenido hasta que se resuelva.') }}
                                </flux:text>
                            </div>
                        @endif
                    </div>

                    {{-- Cada lista en su propio componente: así editar un evento
                         o quitar un vínculo repinta solo esa tarjeta, y no el
                         formulario del pedido, que puede tener cambios a medio
                         teclear. --}}
                    <livewire:backoffice.shipment-history-list
                        :shipment-id="$shipment['id'] ?? null"
                        :key="'historial-'.($shipment['id'] ?? 0)"
                    />

                    {{-- El formulario va en la página, no en un diálogo como en el
                         listado: aquí el pedido es toda la pantalla, y abrir un modal
                         encima sería taparla con lo mismo que ya se está mirando. Va
                         al final de la columna del recorrido porque lo primero que se
                         viene a ver es el estado del envío, no a corregirlo. --}}
                    <form wire:submit="save" class="space-y-6 rounded-xl border border-line bg-surface p-6">
                        <div>
                            <flux:heading size="lg" class="text-ink">{{ __('Editar pedido') }}</flux:heading>

                            <flux:text class="mt-1 text-ink-2">
                                {{ __('Cambia los datos del pedido. Su historial se gestiona evento a evento, arriba.') }}
                            </flux:text>
                        </div>

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

                            <flux:field>
                                <flux:label>{{ __('Estado') }}</flux:label>
                                <flux:select wire:model="status">
                                    @foreach ($this->statuses() as $option)
                                        <flux:select.option :value="$option['value']">{{ $option['label'] }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="status" />
                            </flux:field>

                            {{-- Solo el superadministrador elige empresa. Para el resto
                                 sale de su propia cuenta, así que el campo ni aparece. --}}
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

                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">
                                <span wire:loading.remove wire:target="save">{{ __('Guardar cambios') }}</span>
                                <span wire:loading wire:target="save">{{ __('Guardando...') }}</span>
                            </flux:button>
                        </div>
                    </form>
                </div>

                <div class="space-y-6">
                    <div class="space-y-4 rounded-xl border border-line bg-surface p-6">
                        <flux:heading size="lg" class="text-ink">
                            {{ __('Datos del envío') }}
                        </flux:heading>

                        <dl class="divide-y divide-line text-sm">
                            <div class="flex items-start justify-between gap-4 py-2.5 first:pt-0">
                                <dt class="shrink-0 text-ink-muted">{{ __('Guía') }}</dt>
                                <dd class="min-w-0 truncate text-right font-mono font-medium text-ink">
                                    {{ $shipment['tracking_number'] ?? '—' }}
                                </dd>
                            </div>

                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="shrink-0 text-ink-muted">{{ __('Estado') }}</dt>
                                <dd><x-backoffice.status-badge :status="$shipment['status'] ?? null" /></dd>
                            </div>

                            <div class="flex items-start justify-between gap-4 py-2.5">
                                <dt class="shrink-0 text-ink-muted">{{ __('Destinatario') }}</dt>
                                <dd class="min-w-0 text-right font-medium break-words text-ink">
                                    {{ $shipment['receiver_name'] ?? '—' }}
                                </dd>
                            </div>

                            <div class="flex items-start justify-between gap-4 py-2.5">
                                <dt class="shrink-0 text-ink-muted">{{ __('Origen') }}</dt>
                                <dd class="min-w-0 text-right font-medium break-words text-ink">
                                    {{ $shipment['origin'] ?? '—' }}
                                </dd>
                            </div>

                            <div class="flex items-start justify-between gap-4 py-2.5">
                                <dt class="shrink-0 text-ink-muted">{{ __('Destino') }}</dt>
                                <dd class="min-w-0 text-right font-medium break-words text-ink">
                                    {{ $shipment['destination'] ?? '—' }}
                                </dd>
                            </div>

                            <div class="flex items-start justify-between gap-4 py-2.5">
                                <dt class="shrink-0 text-ink-muted">{{ __('Entrega estimada') }}</dt>
                                <dd class="min-w-0 text-right font-medium text-ink">
                                    {{ $fecha($shipment['estimated_delivery_date'] ?? null) }}
                                </dd>
                            </div>

                            @if ($empresa)
                                <div class="flex items-start justify-between gap-4 py-2.5">
                                    <dt class="shrink-0 text-ink-muted">{{ __('Empresa') }}</dt>
                                    <dd class="min-w-0 text-right font-medium break-words text-ink">{{ $empresa }}</dd>
                                </div>
                            @endif

                            <div class="flex items-start justify-between gap-4 py-2.5">
                                <dt class="shrink-0 text-ink-muted">{{ __('Registrado') }}</dt>
                                <dd class="min-w-0 text-right font-medium text-ink">
                                    {{ $fecha($shipment['created_at'] ?? null, true) }}
                                </dd>
                            </div>

                            <div class="flex items-start justify-between gap-4 py-2.5 last:pb-0">
                                <dt class="shrink-0 text-ink-muted">{{ __('Última actualización') }}</dt>
                                <dd class="min-w-0 text-right font-medium text-ink">
                                    {{ $fecha($shipment['updated_at'] ?? null, true) }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <livewire:backoffice.shipment-user-list
                        :tracking-number="$editing"
                        :key="'vinculados-'.$editing"
                    />

                    <livewire:backoffice.shipment-document-list
                        :shipment-id="$shipment['id'] ?? null"
                        :key="'documentos-'.($shipment['id'] ?? 0)"
                    />
                </div>
            </div>

        @endif
    </x-backoffice.busy>

    <x-backoffice.modal
        name="shipment-delete"
        size="narrow"
        :heading="__('Eliminar pedido')"
        :description="__('Se eliminará :guia junto con su historial, sus documentos y los vínculos con usuarios. No se puede deshacer.', ['guia' => $editing])"
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
