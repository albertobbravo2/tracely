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
    <div class="flex flex-col gap-3 border-t border-gris-200 pt-4 sm:flex-row sm:items-center sm:justify-between dark:border-azul-800">
        <flux:text class="text-gris-600 dark:text-azul-100">
            @if ($from && $to)
                {{ __(':from–:to de :total', ['from' => $from, 'to' => $to, 'total' => $total]) }}
            @else
                {{ trans_choice('{1} :total registro|[2,*] :total registros', $total, ['total' => $total]) }}
            @endif
        </flux:text>

        <div class="flex items-center gap-2">
            <flux:button
                size="sm"
                variant="ghost"
                icon="chevron-left"
                :disabled="$current <= 1"
                wire:click="previousPage"
                wire:loading.attr="disabled"
            >
                {{ __('Anterior') }}
            </flux:button>

            <flux:text class="px-1 whitespace-nowrap text-gris-600 dark:text-azul-100">
                {{ __(':current / :last', ['current' => $current, 'last' => $last]) }}
            </flux:text>

            <flux:button
                size="sm"
                variant="ghost"
                icon:trailing="chevron-right"
                :disabled="$current >= $last"
                wire:click="nextPage"
                wire:loading.attr="disabled"
            >
                {{ __('Siguiente') }}
            </flux:button>
        </div>
    </div>
@endif
