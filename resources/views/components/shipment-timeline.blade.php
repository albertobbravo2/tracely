@use('App\Enums\ShipmentStatus')
@use('Illuminate\Support\Carbon')

{{-- Línea de tiempo de un envío: un nodo por evento del historial, en el orden
     en que llegan (la API los sirve por `recorded_at`), y el último marcado
     como el estado actual.

     `histories` solo viene en la respuesta autenticada de la API. Sin ese campo
     esto no pinta nada, que es también lo que pasa con el envío del usuario
     logueado que todavía no tiene ningún evento registrado. --}}
@props([
    'histories' => [],
])

@if ($histories)
    @php
        $lastKey = array_key_last($histories);
    @endphp

    <ol {{ $attributes }}>
        @foreach ($histories as $key => $event)
            @php
                $case = ShipmentStatus::tryFrom((string) ($event['status'] ?? ''));

                // Misma correspondencia estado → paleta que <x-backoffice.status-badge>,
                // pero resuelta a punto y titular en vez de a fondo de badge.
                $styles = match ($case) {
                    ShipmentStatus::Entregado => ['dot' => 'bg-ok', 'title' => 'text-ok-fuerte dark:text-ok-claro'],
                    ShipmentStatus::EnTransito, ShipmentStatus::EnAduana => ['dot' => 'bg-azul-600', 'title' => 'text-azul-600 dark:text-azul-200'],
                    ShipmentStatus::Incidencia => ['dot' => 'bg-alerta', 'title' => 'text-alerta-fuerte dark:text-alerta-claro'],
                    default => ['dot' => 'bg-gris-400', 'title' => 'text-gris-900 dark:text-azul-100'],
                };

                $meta = collect([
                    $event['location'] ?? null,
                    // locale('es') explícito: APP_LOCALE es 'en' pero la interfaz
                    // está en español, y sin esto saldría "9 Aug 2026". Y
                    // timezone() porque la API serializa en UTC aunque la app
                    // viva en Madrid: sin convertir, las 16:40 se leen 14:40.
                    isset($event['recorded_at'])
                        ? Carbon::parse($event['recorded_at'])->timezone(config('app.timezone'))->locale('es')->translatedFormat('j M Y · H:i')
                        : null,
                ])->filter()->join(' · ');
            @endphp

            <li class="relative border-s-2 border-gris-200 ps-6 pb-8 last:border-transparent last:pb-0 dark:border-azul-800">
                <span
                    aria-hidden="true"
                    @class([
                        'absolute -start-[7px] top-1.5 size-3 rounded-full ring-4 ring-blanco dark:ring-azul-900',
                        $styles['dot'],
                    ])
                ></span>

                <flux:heading
                    size="sm"
                    @class([
                        $styles['title'],
                        'font-semibold' => $key === $lastKey,
                    ])
                >
                    {{ $case?->label() ?? __('Estado desconocido') }}
                </flux:heading>

                @if ($event['description'] ?? null)
                    <flux:text class="mt-0.5 text-gris-900 dark:text-azul-100">
                        {{ $event['description'] }}
                    </flux:text>
                @endif

                @if ($meta)
                    <flux:text size="sm" class="mt-0.5 text-gris-600 dark:text-azul-200">
                        {{ $meta }}
                    </flux:text>
                @endif
            </li>
        @endforeach
    </ol>
@endif
