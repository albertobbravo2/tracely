<?php

use App\Livewire\Backoffice\BackofficeComponent;
use Flux\Flux;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new
#[Layout('layouts::backoffice')]
#[Title('Documentos')]
class extends BackofficeComponent
{
    use WithFileUploads;

    // --- Listado -----------------------------------------------------------

    /** Número de guía por el que filtrar; se resuelve a `shipment_id`. */
    public string $trackingFilter = '';

    /** @var list<array<string, mixed>> */
    public array $documents = [];

    // --- Formulario --------------------------------------------------------

    public ?int $editing = null;

    /**
     * El documento se cuelga de un pedido por su id, pero pedirle un id a una
     * persona no tiene sentido: se teclea el número de guía y se resuelve
     * contra `shipments.index` antes de mandar nada.
     */
    public string $tracking_number = '';

    public string $document_name = '';

    public string $status = '';

    public ?TemporaryUploadedFile $file = null;

    public ?int $deleting = null;

    // --- Listado -----------------------------------------------------------

    public function updatedTrackingFilter(): void
    {
        $this->resetAndReload();
    }

    protected function loadRows(): void
    {
        $this->errorMessage = null;

        $tracking = trim($this->trackingFilter);

        $response = $this->callApi(function (PendingRequest $api) use ($tracking): Response {
            $filters = ['page' => $this->page];

            if ($tracking !== '') {
                $shipmentId = $this->resolveShipmentId($api, $tracking);

                if ($shipmentId === null) {
                    // Sin coincidencia exacta se manda un id imposible, para que
                    // la respuesta venga vacía en vez de listarlo todo.
                    $shipmentId = 0;
                }

                $filters['shipment_id'] = $shipmentId;
            }

            return $api->get(route('documents.index', absolute: false), $filters);
        });

        if ($response === null || $response->failed()) {
            $this->documents = [];
            $this->failListing($response, __('No pudimos cargar los documentos ahora mismo.'));

            return;
        }

        $this->documents = $this->rowsFrom($response);
    }

    // --- Alta y edición ----------------------------------------------------

    public function create(): void
    {
        $this->resetForm();

        Flux::modal('document-form')->show();
    }

    public function edit(int $id): void
    {
        $this->resetForm();

        $response = $this->callApi(fn (PendingRequest $api) => $api->get(
            route('documents.show', ['document' => $id], absolute: false),
        ));

        if ($response === null || $response->failed()) {
            $this->errorMessage = $this->apiErrorMessage($response, __('No pudimos cargar ese documento.'));

            return;
        }

        $document = $response->json();

        $this->editing = $id;
        $this->document_name = $document['document_name'] ?? '';
        $this->status = $document['status'] ?? '';
        $this->tracking_number = $document['shipment']['tracking_number'] ?? '';

        Flux::modal('document-form')->show();
    }

    /**
     * Guardar el documento.
     *
     * El fichero se reenvía a la API como multipart. En la edición se usa POST
     * con `_method=PUT` porque PHP no parsea un cuerpo multipart en un PUT
     * real; el spoofing de método de Laravel sí lo encamina a la ruta PUT.
     */
    public function save(): void
    {
        $this->resetErrorBag();
        $this->errorMessage = null;

        if ($this->editing === null && $this->file === null) {
            $this->addError('file', __('Adjunta el fichero del documento.'));

            return;
        }

        $file = $this->file;
        $tracking = trim($this->tracking_number);
        $editing = $this->editing;

        $payload = [
            'document_name' => trim($this->document_name),
            'status' => trim($this->status),
        ];

        $response = $this->callApi(function (PendingRequest $api) use ($file, $tracking, $editing, $payload): ?Response {
            if ($tracking !== '') {
                $shipmentId = $this->resolveShipmentId($api, $tracking);

                if ($shipmentId === null) {
                    return null;
                }

                $payload['shipment_id'] = $shipmentId;
            }

            if ($file !== null) {
                $api->attach('file', $file->get(), $file->getClientOriginalName());
            }

            if ($editing === null) {
                return $api->post(route('documents.store', absolute: false), $payload);
            }

            $path = route('documents.update', ['document' => $editing], absolute: false);

            return $file === null
                ? $api->put($path, $payload)
                : $api->post($path, [...$payload, '_method' => 'PUT']);
        });

        // El closure devuelve null cuando la guía tecleada no existe: es un
        // error del formulario, no de conexión, y se marca en su propio campo.
        if ($response === null && trim($this->tracking_number) !== '') {
            $this->addError('tracking_number', __('No existe ningún pedido con esa guía.'));

            return;
        }

        if ($this->applyApiValidationErrors($response)) {
            return;
        }

        if ($response === null || $response->failed()) {
            $this->errorMessage = $this->apiErrorMessage($response, __('No pudimos guardar el documento.'));

            return;
        }

        Flux::modal('document-form')->close();
        Flux::toast(variant: 'success', text: $this->editing === null
            ? __('Documento subido.')
            : __('Documento actualizado.'));

        $this->resetForm();
        $this->loadRows();
    }

    // --- Borrado -----------------------------------------------------------

    public function confirmDelete(int $id): void
    {
        $this->deleting = $id;

        Flux::modal('document-delete')->show();
    }

    public function destroy(): void
    {
        if ($this->deleting === null) {
            return;
        }

        $response = $this->callApi(fn (PendingRequest $api) => $api->delete(
            route('documents.destroy', ['document' => $this->deleting], absolute: false),
        ));

        Flux::modal('document-delete')->close();

        if ($response === null || $response->failed()) {
            $this->errorMessage = $this->apiErrorMessage($response, __('No pudimos eliminar el documento.'));
            $this->deleting = null;

            return;
        }

        Flux::toast(variant: 'success', text: __('Documento eliminado, junto con su fichero.'));

        $this->deleting = null;
        $this->loadRows();
    }

    // --- Apoyo -------------------------------------------------------------

    /**
     * Número de guía → id del envío, con coincidencia exacta.
     *
     * Recibe la petición ya autenticada para reutilizar el token de quien
     * llama en vez de acuñar uno nuevo solo para esta consulta.
     */
    private function resolveShipmentId(PendingRequest $api, string $trackingNumber): ?int
    {
        $lookup = $api->get(route('shipments.index', absolute: false), ['search' => $trackingNumber]);

        if ($lookup->failed()) {
            return null;
        }

        $match = collect($lookup->json('data') ?? [])->firstWhere('tracking_number', $trackingNumber);

        return isset($match['id']) ? (int) $match['id'] : null;
    }

    private function resetForm(): void
    {
        $this->resetErrorBag();

        $this->editing = null;
        $this->tracking_number = '';
        $this->document_name = '';
        $this->status = '';
        $this->file = null;
    }
};
?>

<div class="space-y-6">
    <x-backoffice.heading
        :heading="__('Documentos')"
        :subheading="__('Facturas, despachos de aduana y demás ficheros adjuntos a un pedido. No son públicos: solo se descargan con sesión y permiso.')"
    >
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="create">
                {{ __('Subir documento') }}
            </flux:button>
        </x-slot:actions>
    </x-backoffice.heading>

    <flux:input
        wire:model.live.debounce.400ms="trackingFilter"
        icon="magnifying-glass"
        class="sm:max-w-sm"
        :placeholder="__('Filtrar por número de guía exacto')"
        :label="__('Filtrar por guía')"
        label:class="sr-only"
    />

    @if ($errorMessage)
        <x-backoffice.alert :message="$errorMessage" />
    @endif

    @if (empty($documents))
        @if (! $errorMessage)
            <x-backoffice.empty
                icon="document-text"
                :heading="__('No hay documentos que mostrar')"
                :text="filled($trackingFilter)
                    ? __('Ese pedido no tiene documentos adjuntos.')
                    : __('Todavía no se ha subido ningún documento.')"
            >
                <x-slot:actions>
                    <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Subir documento') }}</flux:button>
                </x-slot:actions>
            </x-backoffice.empty>
        @endif
    @else
        <div class="space-y-4">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Documento') }}</flux:table.column>
                    <flux:table.column class="max-sm:hidden">{{ __('Guía') }}</flux:table.column>
                    <flux:table.column class="max-md:hidden">{{ __('Estado') }}</flux:table.column>
                    <flux:table.column class="text-end">{{ __('Acciones') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($documents as $document)
                        <flux:table.row :key="$document['id']">
                            <flux:table.cell>
                                <div class="flex items-center gap-3">
                                    <flux:icon name="paper-clip" variant="outline" class="size-4 shrink-0 text-gris-400 dark:text-azul-200" />

                                    <div>
                                        <span class="font-semibold text-gris-900 dark:text-blanco">
                                            {{ $document['document_name'] }}
                                        </span>

                                        <span class="block text-sm text-gris-600 sm:hidden dark:text-azul-200">
                                            {{ $document['shipment']['tracking_number'] ?? '—' }}
                                        </span>
                                    </div>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell class="whitespace-nowrap max-sm:hidden">
                                {{ $document['shipment']['tracking_number'] ?? '—' }}
                            </flux:table.cell>

                            <flux:table.cell class="max-md:hidden">
                                <flux:badge rounded size="sm" class="!bg-gris-050 !text-gris-600">
                                    {{ $document['status'] }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell class="text-end">
                                <div class="flex justify-end gap-1">
                                    {{-- Descarga directa del navegador: la petición
                                         lleva la cookie de sesión, y `statefulApi()`
                                         hace que el guard sanctum la reconozca. --}}
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="arrow-down-tray"
                                        :label="__('Descargar documento')"
                                        :href="$document['download_url']"
                                    />
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="pencil-square"
                                        :label="__('Editar documento')"
                                        wire:click="edit({{ $document['id'] }})"
                                    />
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="trash"
                                        :label="__('Eliminar documento')"
                                        wire:click="confirmDelete({{ $document['id'] }})"
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

    <flux:modal name="document-form" class="w-full md:w-[32rem]">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $editing === null ? __('Subir documento') : __('Editar documento') }}
                </flux:heading>
                <flux:text class="mt-1">
                    {{ __('Formatos admitidos: PDF, JPG y PNG, hasta 10 MB.') }}
                </flux:text>
            </div>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Número de guía del pedido') }}</flux:label>
                    <flux:input wire:model="tracking_number" placeholder="TRC-0000000001" />
                    <flux:error name="tracking_number" />
                    <flux:error name="shipment_id" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Nombre del documento') }}</flux:label>
                    <flux:input wire:model="document_name" placeholder="factura-comercial.pdf" />
                    <flux:error name="document_name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Estado') }}</flux:label>
                    <flux:input wire:model="status" :placeholder="__('pendiente de revisión')" />
                    <flux:error name="status" />
                </flux:field>

                <flux:field>
                    <flux:label badge="{{ $editing === null ? __('Requerido') : __('Opcional') }}">
                        {{ __('Fichero') }}
                    </flux:label>
                    <flux:input type="file" wire:model="file" accept=".pdf,.jpg,.jpeg,.png" />
                    @if ($editing !== null)
                        <flux:description>{{ __('Déjalo vacío para conservar el fichero actual.') }}</flux:description>
                    @endif
                    <flux:error name="file" />

                    <div wire:loading wire:target="file">
                        <flux:text class="text-gris-600 dark:text-azul-100">{{ __('Subiendo fichero...') }}</flux:text>
                    </div>
                </flux:field>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save,file">
                    <span wire:loading.remove wire:target="save">{{ __('Guardar') }}</span>
                    <span wire:loading wire:target="save">{{ __('Guardando...') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="document-delete" class="w-full md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Eliminar documento') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('Se borrará el registro y también su fichero del disco. No se puede deshacer.') }}
                </flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="destroy" wire:loading.attr="disabled" wire:target="destroy">
                    {{ __('Eliminar') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
