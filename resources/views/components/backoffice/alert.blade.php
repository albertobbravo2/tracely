@props([
    'message',
    // Método Livewire al que llama el botón de reintentar. Sin él la alerta no
    // ofrece salida: el listado se queda vacío y la única forma de volver a
    // pedirlo era recargar la página a mano.
    'retry' => null,
])

{{-- Mensaje de error de la pantalla: `danger` sobre `danger-soft`, el mismo par
     que usa el badge de incidencia.

     `role="alert"` porque aparece después de cargar la página, como respuesta a
     lo que acaba de hacer el usuario: sin él un lector de pantalla no lo
     anuncia y el fallo pasa desapercibido. --}}
<div
    role="alert"
    class="flex flex-col gap-3 rounded-xl border border-danger/25 bg-danger-soft px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
>
    <div class="flex items-start gap-3">
        <flux:icon name="exclamation-triangle" variant="outline" class="mt-0.5 size-5 shrink-0 text-danger" />

        <flux:text class="text-danger">{{ $message }}</flux:text>
    </div>

    @if ($retry)
        <flux:button
            size="sm"
            variant="ghost"
            icon="arrow-path"
            class="shrink-0 self-start sm:self-auto"
            wire:click="{{ $retry }}"
            wire:loading.attr="disabled"
            wire:target="{{ $retry }}"
        >
            {{ __('Reintentar') }}
        </flux:button>
    @endif
</div>
