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

<div class="space-y-6">
    <x-backoffice.heading
        :heading="$editing"
        :subheading="__('Edita el pedido o elimínalo. Su historial se gestiona desde Historial de pedidos.')"
    >
        <x-slot:actions>
            <flux:button icon="arrow-left" :href="route('backoffice.shipments')" wire:navigate>
                {{ __('Volver a pedidos') }}
            </flux:button>
        </x-slot:actions>
    </x-backoffice.heading>

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
            {{-- El formulario va en la página, no en un diálogo como en el
                 listado: aquí el pedido es toda la pantalla, y abrir un modal
                 encima sería taparla con lo mismo que ya se está mirando. --}}
            <form
                wire:submit="save"
                class="space-y-6 rounded-2xl border border-gris-200 bg-blanco p-6 shadow-sm sm:p-8 dark:border-azul-800 dark:bg-azul-900"
            >
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

                {{-- Las tres acciones del pedido juntas y a la derecha. La de
                     añadir evento no la resuelve esta pantalla: la dispara para
                     que la atienda la lista del historial, que es quien tiene el
                     formulario y quien tiene que refrescarse después. --}}
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <flux:button icon="plus" wire:click="$dispatch('crear-evento-historial')">
                        {{ __('Nuevo evento') }}
                    </flux:button>

                    <flux:modal.trigger name="shipment-delete">
                        <flux:button variant="danger" icon="trash">
                            {{ __('Eliminar pedido') }}
                        </flux:button>
                    </flux:modal.trigger>

                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">{{ __('Guardar cambios') }}</span>
                        <span wire:loading wire:target="save">{{ __('Guardando...') }}</span>
                    </flux:button>
                </div>
            </form>

        @endif
    </x-backoffice.busy>

    {{-- Las listas van fuera del bloque de arriba y cada una en su propio
         componente: así editar un evento o quitar un vínculo repinta solo esa
         columna, y no el formulario del pedido, que puede tener cambios a medio
         teclear. --}}
    @if (! empty($shipment))
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <livewire:backoffice.shipment-history-list
                :shipment-id="$shipment['id'] ?? null"
                :key="'historial-'.($shipment['id'] ?? 0)"
            />

            <livewire:backoffice.shipment-user-list
                :tracking-number="$editing"
                :key="'vinculados-'.$editing"
            />

            {{-- Dentro de la misma rejilla que las otras dos, no a ancho
                 completo: una fila de documentos con nombre, estado y
                 descarga no necesita todo el ancho de la pantalla. --}}
            <livewire:backoffice.shipment-document-list
                :shipment-id="$shipment['id'] ?? null"
                :key="'documentos-'.($shipment['id'] ?? 0)"
            />
        </div>
    @endif

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
