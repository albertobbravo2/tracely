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

                // Mismo mapeo estado → token que <x-backoffice.status-badge>
                // (design.md → Estados de envío), pero resuelto a icono y no a
                // badge. Las clases van escritas enteras a propósito: Tailwind
                // v4 rastrea el fuente y no vería un `bg-{$tono}-soft` armado
                // por interpolación.
                $styles = match ($case) {
                    ShipmentStatus::Entregado => ['icon' => 'check-circle', 'chip' => 'bg-ok-soft text-ok'],
                    ShipmentStatus::EnTransito => ['icon' => 'truck', 'chip' => 'bg-info-soft text-info'],
                    ShipmentStatus::EnAduana => ['icon' => 'building-office-2', 'chip' => 'bg-aduana-soft text-aduana'],
                    ShipmentStatus::Incidencia => ['icon' => 'exclamation-triangle', 'chip' => 'bg-danger-soft text-danger'],
                    default => ['icon' => 'clock', 'chip' => 'bg-idle-soft text-idle'],
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

            <li class="relative flex gap-4 pb-6 last:pb-0">
                {{-- La línea cuelga del icono, no del borde del <li>: así queda
                     centrada bajo él y no llega al último evento. --}}
                @unless ($key === $lastKey)
                    <span aria-hidden="true" class="absolute top-9 bottom-0 left-4 w-px -translate-x-1/2 bg-line"></span>
                @endunless

                <span
                    aria-hidden="true"
                    @class(['flex size-8 shrink-0 items-center justify-center rounded-full', $styles['chip']])
                >
                    <flux:icon :name="$styles['icon']" variant="micro" class="size-4" />
                </span>

                <div class="min-w-0 pt-1">
                    <flux:heading
                        size="sm"
                        @class([
                            'text-ink',
                            'font-semibold' => $key === $lastKey,
                        ])
                    >
                        {{ $case?->label() ?? __('Estado desconocido') }}
                    </flux:heading>

                    @if ($event['description'] ?? null)
                        <flux:text class="mt-0.5 text-ink-2">
                            {{ $event['description'] }}
                        </flux:text>
                    @endif

                    @if ($meta)
                        <flux:text size="sm" class="mt-0.5 text-ink-muted">
                            {{ $meta }}
                        </flux:text>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
@endif
