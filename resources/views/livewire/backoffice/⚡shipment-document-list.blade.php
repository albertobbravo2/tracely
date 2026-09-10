<?php

use App\Livewire\Backoffice\BackofficeComponent;
use Flux\Flux;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Los documentos de un pedido, dentro de su pantalla de detalle.
 *
 * Componente aparte, igual que ⚡shipment-history-list y ⚡shipment-user-list,
 * para que listarlos no dependa del formulario del pedido ni lo repinte.
 *
 * Editar o borrar un documento sigue estando solo en la pantalla de
 * Documentos, que es la que los gestiona de verdad. Subir uno nuevo sí vive
 * también aquí: el pedido ya está identificado por la propia pantalla, así
 * que no hace falta teclear su guía como en el formulario general.
 */
new class extends BackofficeComponent
{
    use WithFileUploads;

    // --- Alta ----------------------------------------------------------

    public string $document_name = '';

    public string $status = '';

    public ?TemporaryUploadedFile $file = null;

    /**
     * Pedido cuyos documentos se listan. Lo pasa la pantalla de detalle.
     *
     * El parámetro de `mount()` es opcional solo por compatibilidad de firma:
     * la base declara `mount()` sin argumentos y PHP no deja añadir uno
     * obligatorio al sobrescribir.
     */
    public ?int $shipmentId = null;

    /** @var list<array<string, mixed>> */
    public array $documents = [];

    public function mount(?int $shipmentId = null): void
    {
        $this->shipmentId = $shipmentId;

        $this->loadRows();
    }

    protected function loadRows(): void
    {
        $this->errorMessage = null;

        // Sin pedido no hay documentos que pedir: la pantalla de detalle monta
        // esta lista solo cuando lo ha cargado, pero así no depende de ello.
        if ($this->shipmentId === null) {
            $this->documents = [];

            return;
        }

        $response = $this->callApi(fn (PendingRequest $api) => $api->get(
            route('documents.index', absolute: false),
            ['shipment_id' => $this->shipmentId, 'page' => $this->page],
        ));

        if ($response === null || $response->failed()) {
            $this->documents = [];
            $this->failListing($response, __('No pudimos cargar los documentos de este pedido.'));

            return;
        }

        $this->documents = $this->rowsFrom($response);
    }

    // --- Alta ----------------------------------------------------------

    public function create(): void
    {
        $this->resetForm();

        Flux::modal('shipment-document-upload')->show();
    }

    /**
     * Subir un documento nuevo para este pedido.
     *
     * Sin `tracking_number` que resolver: el pedido ya lo trae `$shipmentId`,
     * a diferencia del formulario de la pantalla general de Documentos.
     */
    public function save(): void
    {
        $this->resetErrorBag();
        $this->errorMessage = null;

        if ($this->file === null) {
            $this->addError('file', __('Adjunta el fichero del documento.'));

            return;
        }

        if ($this->shipmentId === null) {
            return;
        }

        $file = $this->file;
        $payload = [
            'shipment_id' => $this->shipmentId,
            'document_name' => trim($this->document_name),
            'status' => trim($this->status),
        ];

        $response = $this->callApi(function (PendingRequest $api) use ($file, $payload): Response {
            $api->attach('file', $file->get(), $file->getClientOriginalName());

            return $api->post(route('documents.store', absolute: false), $payload);
        });

        if ($this->applyApiValidationErrors($response)) {
            return;
        }

        if ($response === null || $response->failed()) {
            $this->errorMessage = $this->apiErrorMessage($response, __('No pudimos subir el documento.'));

            return;
        }

        Flux::modal('shipment-document-upload')->close();
        Flux::toast(variant: 'success', text: __('Documento subido.'));

        $this->resetForm();
        $this->loadRows();
    }

    private function resetForm(): void
    {
        $this->resetErrorBag();

        $this->document_name = '';
        $this->status = '';
        $this->file = null;
    }

    // --- Apoyo -------------------------------------------------------------

    /**
     * Tono del distintivo de estado de un documento.
     *
     * Mismo criterio que ⚡documents.blade.php: el estado es texto libre en la
     * API —no hay enum detrás, a diferencia del de los envíos—, así que se
     * reconocen las raíces habituales en castellano y todo lo demás se queda
     * en gris.
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
};
?>

<div class="space-y-4 rounded-xl border border-line bg-surface p-6">
    <div class="flex items-center justify-between gap-3">
        <flux:heading size="lg" class="text-ink">
            {{ __('Documentos') }}
        </flux:heading>

        {{-- Secundario, no primario: el único botón relleno de la pantalla es
             «Guardar cambios» del pedido (design.md → Formularios). --}}
        <flux:button size="sm" icon="plus" wire:click="create">
            {{ __('Subir') }}
        </flux:button>
    </div>

    @if ($errorMessage)
        <x-backoffice.alert :message="$errorMessage" retry="retry" />
    @endif

    <x-backoffice.busy target="retry, nextPage, previousPage">
        @if (empty($documents))
            @if (! $errorMessage)
                <x-backoffice.empty
                    icon="document-text"
                    :heading="__('Sin documentos')"
                    :text="__('Este pedido todavía no tiene ningún documento adjunto.')"
                />
            @endif
        @else
            <ul class="divide-y divide-line">
                @foreach ($documents as $document)
                    <li class="flex items-center justify-between gap-3 py-3 first:pt-0">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-surface-2">
                                <flux:icon name="paper-clip" variant="micro" class="size-4 text-ink-muted" />
                            </span>

                            <div class="min-w-0">
                                <p class="truncate font-semibold text-ink">
                                    {{ $document['document_name'] ?? '—' }}
                                </p>

                                <x-backoffice.tone-badge :tone="$this->statusTone($document['status'] ?? null)" class="mt-1">
                                    {{ $this->statusLabel($document['status'] ?? null) }}
                                </x-backoffice.tone-badge>
                            </div>
                        </div>

                        {{-- Relativa y armada aquí, no con el `download_url` que
                             devuelve la API: misma razón que en ⚡documents.blade.php
                             — esa URL sale con el host de dentro del contenedor. --}}
                        <flux:button
                            size="sm"
                            variant="ghost"
                            icon="arrow-down-tray"
                            class="shrink-0"
                            :label="__('Descargar documento')"
                            :href="route('documents.download', ['document' => $document['id']], absolute: false)"
                        />
                    </li>
                @endforeach
            </ul>

            <x-backoffice.pagination :meta="$meta" />
        @endif
    </x-backoffice.busy>

    <x-backoffice.modal
        name="shipment-document-upload"
        :heading="__('Subir documento')"
        :description="__('Formatos admitidos: PDF, JPG y PNG, hasta 10 MB.')"
    >
        <form wire:submit="save" class="space-y-6">
            <div class="space-y-4">
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
                    <flux:label badge="{{ __('Requerido') }}">{{ __('Fichero') }}</flux:label>
                    <flux:input type="file" wire:model="file" accept=".pdf,.jpg,.jpeg,.png" />
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
</div>
