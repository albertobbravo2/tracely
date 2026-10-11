<?php

use App\Livewire\Backoffice\BackofficeComponent;
use Flux\Flux;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
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

    /**
     * Coincidencias que se ofrecen mientras se teclea la guía.
     *
     * @var list<array{tracking_number: string, label: string}>
     */
    public array $trackingSuggestions = [];

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

        // `documents.show` devuelve el documento a secas, sin su envío, así que
        // la guía sale de la fila del listado —que sí la trae por el eager load
        // de `documents.index`—. Sin esto el campo salía vacío al editar, como
        // si el documento hubiera perdido su pedido.
        $this->tracking_number = (string) ($document['shipment']['tracking_number']
            ?? $this->rowById($this->documents, $id)['shipment']['tracking_number']
            ?? '');

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
    /**
     * Buscar pedidos mientras se teclea la guía en el formulario.
     *
     * Va contra `shipments.index`, el mismo endpoint que resuelve la guía al
     * guardar, así que las sugerencias respetan el filtro por empresa que la
     * API ya aplica: a nadie se le ofrece un pedido que luego no podría usar.
     */
    public function updatedTrackingNumber(): void
    {
        $this->trackingSuggestions = [];

        $term = trim($this->tracking_number);

        // Con una sola letra la lista sería medio catálogo y no ayuda a elegir.
        if (mb_strlen($term) < 2) {
            return;
        }

        $response = $this->callApi(fn (PendingRequest $api) => $api->get(
            route('shipments.index', absolute: false),
            ['search' => $term],
        ));

        if ($response === null || $response->failed()) {
            return;
        }

        $rows = $response->json('data');

        if (! is_array($rows)) {
            return;
        }

        $this->trackingSuggestions = collect($rows)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(fn (array $row): array => [
                'tracking_number' => (string) ($row['tracking_number'] ?? ''),
                'label' => implode(' · ', array_filter([
                    $row['receiver_name'] ?? null,
                    $row['destination'] ?? null,
                ])),
            ])
            ->filter(fn (array $row): bool => $row['tracking_number'] !== '')
            // Si la guía ya está escrita entera no queda nada que sugerir.
            ->reject(fn (array $row): bool => $row['tracking_number'] === $term)
            ->take(6)
            ->values()
            ->all();
    }

    /**
     * Elegir una sugerencia. Se identifica por posición y no por su número de
     * guía para que la vista no tenga que meter texto de la API dentro de una
     * expresión `wire:click`.
     */
    public function selectTracking(int $index): void
    {
        $suggestion = $this->trackingSuggestions[$index] ?? null;

        if ($suggestion === null) {
            return;
        }

        $this->tracking_number = $suggestion['tracking_number'];
        $this->trackingSuggestions = [];
    }

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
        $this->trackingSuggestions = [];
        $this->document_name = '';
        $this->status = '';
        $this->file = null;
    }

    /**
     * Tono del distintivo de estado de un documento.
     *
     * El estado es texto libre en la API —no hay enum detrás, a diferencia del
     * de los envíos—, así que se reconocen las raíces habituales en castellano
     * y todo lo demás se queda en gris. Pintarlos todos igual convertía la
     * columna en un adorno: no distinguía una factura aprobada de una
     * rechazada.
     */
    public function statusTone(?string $status): string
    {
        $normalized = Str::lower(Str::ascii(trim((string) $status)));

        return match (true) {
            Str::contains($normalized, ['aprobad', 'validad', 'aceptad', 'verificad', 'conform']) => 'ok',
            Str::contains($normalized, ['rechazad', 'denegad', 'caducad', 'error', 'incidencia', 'invalid']) => 'alerta',
            Str::contains($normalized, ['pendiente', 'revis', 'proces', 'tramit', 'espera']) => 'azul',
            default => 'gris',
        };
    }

    /**
     * Estado del documento tal y como se pinta: con la inicial en mayúscula, y
     * con un texto propio cuando viene vacío en vez de un distintivo mudo.
     */
    public function statusLabel(?string $status): string
    {
        $status = trim((string) $status);

        return $status === '' ? __('Sin estado') : Str::ucfirst($status);
    }

    /**
     * Nombre del documento pendiente de borrar, para que el modal de
     * confirmación diga cuál es en vez de un genérico "el documento".
     */
    public function deletingLabel(): string
    {
        return (string) ($this->rowById($this->documents, $this->deleting)['document_name'] ?? '');
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

    <x-backoffice.search
        model="trackingFilter"
        :label="__('Filtrar por guía')"
        :placeholder="__('Filtrar por número de guía exacto')"
    />

    @if ($errorMessage)
        <x-backoffice.alert :message="$errorMessage" retry="retry" />
    @endif

    <x-backoffice.busy target="trackingFilter, nextPage, previousPage, retry">
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
            {{-- Tabla como tarjeta: `surface` con borde, cabecera sobre `surface-2`
                 y filas separadas por 1 px de `line` (design.md → Tabla). Las
                 celdas de los extremos recuperan el padding lateral que Flux les
                 quita (`first:ps-0`), que aquí las pegaba al borde de la tarjeta. --}}
            <div class="overflow-hidden rounded-xl border border-line bg-surface">
                <flux:table class="[&_td:first-child]:ps-5 [&_td:last-child]:pe-5 [&_th:first-child]:ps-5 [&_th:last-child]:pe-5">
                    <flux:table.columns class="bg-surface-2 [&_th]:text-xs [&_th]:font-semibold">
                        <flux:table.column align="center">{{ __('Documento') }}</flux:table.column>
                        <flux:table.column align="center" class="max-sm:hidden">{{ __('Guía') }}</flux:table.column>
                        <flux:table.column align="center" class="max-md:hidden">{{ __('Estado') }}</flux:table.column>
                        <flux:table.column align="center">{{ __('Acciones') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($documents as $document)
                            <flux:table.row :key="$document['id']">
                                <flux:table.cell>
                                    <div class="flex items-center gap-3">
                                        <flux:icon name="paper-clip" variant="outline" class="size-4 shrink-0 text-ink-muted" />

                                        {{-- `min-w-0` para que el truncado de dentro
                                             tenga contra qué truncar: sin él un nombre
                                             de fichero largo ensancha la celda y empuja
                                             las acciones fuera de la pantalla. --}}
                                        <div class="min-w-0">
                                            <span class="block truncate font-semibold text-ink">
                                                {{ $document['document_name'] }}
                                            </span>

                                            <span class="block truncate text-sm text-ink-2 sm:hidden">
                                                {{ $document['shipment']['tracking_number'] ?? '—' }}
                                            </span>

                                            {{-- El estado tiene columna propia desde md;
                                                 por debajo se pliega aquí en vez de
                                                 desaparecer. --}}
                                            <span class="mt-1 block md:hidden">
                                                <x-backoffice.tone-badge :tone="$this->statusTone($document['status'] ?? null)">
                                                    {{ $this->statusLabel($document['status'] ?? null) }}
                                                </x-backoffice.tone-badge>
                                            </span>
                                        </div>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell class="whitespace-nowrap max-sm:hidden">
                                    <span class="font-medium text-primary">
                                        {{ $document['shipment']['tracking_number'] ?? '—' }}
                                    </span>
                                </flux:table.cell>

                                <flux:table.cell align="center" class="max-md:hidden">
                                    <x-backoffice.tone-badge :tone="$this->statusTone($document['status'] ?? null)">
                                        {{ $this->statusLabel($document['status'] ?? null) }}
                                    </x-backoffice.tone-badge>
                                </flux:table.cell>

                                <flux:table.cell align="center">
                                    <div class="flex justify-center gap-1">
                                        {{-- Descarga directa del navegador: la petición
                                             lleva la cookie de sesión, y `statefulApi()`
                                             hace que el guard sanctum la reconozca.

                                             El enlace se arma aquí y no con el
                                             `download_url` que devuelve la API: esa URL
                                             la genera Laravel a partir del host de quien
                                             pregunta, y quien pregunta es el propio
                                             servidor por `APP_INTERNAL_URL`, así que
                                             salía apuntando a `http://localhost` —el
                                             puerto 80 de dentro del contenedor—, que
                                             desde el navegador no es la aplicación.
                                             Relativa, además, para que valga sea cual
                                             sea el host por el que se entre. --}}
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="arrow-down-tray"
                                            :label="__('Descargar documento')"
                                            :href="route('documents.download', ['document' => $document['id']], absolute: false)"
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

                <div class="px-5 pb-4">
                    <x-backoffice.pagination :meta="$meta" />
                </div>
            </div>
        @endif
    </x-backoffice.busy>

    <x-backoffice.modal
        name="document-form"
        :heading="$editing === null ? __('Subir documento') : __('Editar documento')"
        :description="__('Formatos admitidos: PDF, JPG y PNG, hasta 10 MB.')"
    >
        <form wire:submit="save" class="space-y-6">
            <div class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Número de guía del pedido') }}</flux:label>

                    {{-- Flux no trae combobox con búsqueda en esta edición —solo
                         el `select` nativo—, así que la lista de coincidencias va
                         con utilidades sobre el `flux:input`. --}}
                    <div
                        class="relative"
                        x-data="{ open: false }"
                        x-on:click.outside="open = false"
                        x-on:keydown.escape="open = false"
                    >
                        <flux:input
                            wire:model.live.debounce.300ms="tracking_number"
                            placeholder="TRC-0000000001"
                            autocomplete="off"
                            x-on:focus="open = true"
                            x-on:input="open = true"
                        />

                        @if ($trackingSuggestions !== [])
                            <div
                                x-show="open"
                                x-cloak
                                class="absolute inset-x-0 top-full z-20 mt-1 overflow-hidden rounded-[10px] border border-line bg-surface shadow-modal"
                            >
                                <ul class="max-h-56 overflow-y-auto py-1">
                                    @foreach ($trackingSuggestions as $index => $suggestion)
                                        <li>
                                            <button
                                                type="button"
                                                wire:click="selectTracking({{ $index }})"
                                                x-on:click="open = false"
                                                class="flex w-full flex-col items-start gap-0.5 px-3 py-2 text-start transition-colors hover:bg-primary-soft"
                                            >
                                                <span class="font-mono text-sm font-semibold text-primary">
                                                    {{ $suggestion['tracking_number'] }}
                                                </span>

                                                @if ($suggestion['label'] !== '')
                                                    <span class="text-xs text-ink-muted">{{ $suggestion['label'] }}</span>
                                                @endif
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>

                    <flux:error name="tracking_number" />
                    <flux:error name="shipment_id" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Nombre del documento') }}</flux:label>
                    <flux:input wire:model="document_name" placeholder="factura comercial" />
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

                    <div class="hidden items-center gap-2" wire:loading.flex wire:target="file">
                        <flux:icon name="loading" variant="micro" class="text-primary" />

                        <flux:text class="text-ink-2">{{ __('Subiendo fichero...') }}</flux:text>
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
    </x-backoffice.modal>

    {{-- El modal se abre desde una fila y la tapa: sin nombrar el fichero,
         confirmar es un acto de fe. --}}
    <x-backoffice.modal
        name="document-delete"
        size="narrow"
        :heading="__('Eliminar documento')"
        :description="$this->deletingLabel() !== ''
            ? __('Se borrará :documento y también su fichero del disco. No se puede deshacer.', ['documento' => $this->deletingLabel()])
            : __('Se borrará el registro y también su fichero del disco. No se puede deshacer.')"
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
