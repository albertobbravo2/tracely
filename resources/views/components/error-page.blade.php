@props([
    'code' => '500',
    'title' => null,
    'description' => null,
])

@php
    // Tono e icono salen del código: un 404 no es alarmante, un 4xx es una
    // petición que no podemos servir y un 5xx es fallo nuestro.
    [$tone, $icon] = match ((string) $code) {
        '404' => ['idle', 'search'],
        '401', '403' => ['warn', 'lock'],
        '419' => ['warn', 'refresh'],
        '429' => ['warn', 'clock'],
        '503' => ['info', 'clock'],
        default => ['danger', 'alert'],
    };

    // Los iconos van como SVG en línea y no como <flux:icon>: esta vista tiene
    // que poder pintarse cuando algo se ha roto, así que cuanto menos dependa
    // de que el resto de la app funcione, mejor.
    $paths = [
        'search' => 'm21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z',
        'lock' => 'M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z',
        'refresh' => 'M16.023 9.348h4.992V4.356m0 4.992-3.181-3.183a8.25 8.25 0 0 0-13.803 3.7M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7',
        'clock' => 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'alert' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
    ];

    $toneClasses = [
        'idle' => 'bg-idle-soft text-idle',
        'info' => 'bg-info-soft text-info',
        'warn' => 'bg-warn-soft text-warn',
        'danger' => 'bg-danger-soft text-danger',
    ][$tone];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $title])
    </head>
    <body class="min-h-screen bg-canvas antialiased">
        <div class="flex min-h-svh flex-col items-center justify-center gap-8 p-6">
            <a href="{{ url('/') }}" class="flex items-center gap-2.5">
                <x-brand-mark />
                <span class="text-lg font-bold tracking-[-0.02em] text-ink">Tracely</span>
            </a>

            <div class="w-full max-w-md rounded-2xl border border-line bg-surface px-8 py-10 text-center">
                <span class="mx-auto flex size-12 items-center justify-center rounded-full {{ $toneClasses }}">
                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $paths[$icon] }}" />
                    </svg>
                </span>

                <p class="mt-5 text-xs font-semibold uppercase tracking-[0.12em] text-ink-muted">
                    {{ __('Error :code', ['code' => $code]) }}
                </p>

                <h1 class="mt-2 text-3xl font-bold tracking-tight text-balance text-ink">
                    {{ $title ?? __('Algo ha ido mal') }}
                </h1>

                @if ($description)
                    <p class="mt-3 leading-relaxed text-ink-2">{{ $description }}</p>
                @endif

                <div class="mt-7 flex flex-wrap items-center justify-center gap-2">
                    <a
                        href="{{ url('/') }}"
                        class="rounded-[10px] bg-primary px-4 py-2.5 text-sm font-semibold text-on-primary shadow-elev transition-colors hover:bg-primary-hover"
                    >
                        {{ __('Volver al inicio') }}
                    </a>

                    @auth
                        <a
                            href="{{ route('dashboard') }}"
                            class="rounded-[10px] border border-line-strong bg-surface px-4 py-2.5 text-sm font-semibold text-ink transition-colors hover:bg-surface-2"
                        >
                            {{ __('Ir a mis pedidos') }}
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </body>
</html>
