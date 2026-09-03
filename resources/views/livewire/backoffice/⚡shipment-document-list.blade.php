<?php

use App\Livewire\Backoffice\BackofficeComponent;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Str;

/**
 * Los documentos de un pedido, dentro de su pantalla de detalle.
 *
 * Componente aparte, igual que ⚡shipment-history-list y ⚡shipment-user-list,
 * para que listarlos no dependa del formulario del pedido ni lo repinte.
 *
 * Solo lectura y descarga: subir, editar o borrar un documento sigue estando
 * en la pantalla de Documentos, que es la que los gestiona de verdad.
 */
new class extends BackofficeComponent
{
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

<div class="space-y-4 rounded-2xl border border-gris-200 bg-blanco p-6 shadow-sm dark:border-azul-800 dark:bg-azul-900">
    <flux:heading size="lg" class="text-gris-900 dark:text-blanco">
        {{ __('Documentos') }}
    </flux:heading>

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
            <ul class="divide-y divide-gris-200 dark:divide-azul-800">
                @foreach ($documents as $document)
                    <li class="flex items-center justify-between gap-3 py-3 first:pt-0">
                        <div class="flex min-w-0 items-center gap-3">
                            <flux:icon name="paper-clip" variant="outline" class="size-4 shrink-0 text-gris-400 dark:text-azul-200" />

                            <div class="min-w-0">
                                <p class="truncate font-semibold text-gris-900 dark:text-blanco">
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
</div>
