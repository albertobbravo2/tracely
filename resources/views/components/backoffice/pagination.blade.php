@props([
    'meta',
])

@php
    // `$meta` es el paginador tal cual lo devuelve la API. No se puede usar
    // `<flux:table :paginate>` porque eso espera una instancia de paginador de
    // Laravel, y aquí lo que hay es el JSON de la respuesta.
    $current = (int) ($meta['current_page'] ?? 1);
    $last = max(1, (int) ($meta['last_page'] ?? 1));
    $total = (int) ($meta['total'] ?? 0);
    $from = $meta['from'] ?? null;
    $to = $meta['to'] ?? null;
@endphp

@if ($total > 0)
    <div class="flex flex-col gap-3 border-t border-line pt-4 sm:flex-row sm:items-center sm:justify-between">
        <flux:text class="text-ink-muted">
            @if ($from && $to)
                {{ __(':from–:to de :total', ['from' => $from, 'to' => $to, 'total' => $total]) }}
            @else
                {{ trans_choice('{1} :total registro|[2,*] :total registros', $total, ['total' => $total]) }}
            @endif
        </flux:text>

        {{-- Los botones solo se deshabilitan por su propia navegación: sin el
             `wire:target` cualquier petición de la pantalla —teclear en el
             buscador, por ejemplo— los apagaba y encendía a cada pulsación. --}}
        <div class="flex items-center gap-2">
            <flux:button
                size="sm"
                variant="ghost"
                icon="chevron-left"
                :disabled="$current <= 1"
                wire:click="previousPage"
                wire:loading.attr="disabled"
                wire:target="previousPage, nextPage"
            >
                {{ __('Anterior') }}
            </flux:button>

            <flux:text class="px-1 font-medium whitespace-nowrap text-ink-2">
                {{ __(':current / :last', ['current' => $current, 'last' => $last]) }}
            </flux:text>

            <flux:button
                size="sm"
                variant="ghost"
                icon:trailing="chevron-right"
                :disabled="$current >= $last"
                wire:click="nextPage"
                wire:loading.attr="disabled"
                wire:target="previousPage, nextPage"
            >
                {{ __('Siguiente') }}
            </flux:button>
        </div>
    </div>
@endif
