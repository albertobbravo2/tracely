@props([
    'title',
    'updated' => null,
])

{{-- Envoltorio común de las páginas legales: cabecera, ancho de lectura y
     estilos del texto. Las cuatro páginas comparten forma, así que los estilos
     de los elementos del cuerpo se declaran aquí una vez, con variantes
     arbitrarias, en vez de repetir clases en cada párrafo. --}}
<x-layouts::app :title="$title" :footer="true">
    <div class="mx-auto w-full max-w-3xl px-6 pb-20 pt-14 sm:pt-20">
        <h1 class="text-3xl font-bold tracking-[-0.03em] text-ink lg:text-[2.375rem] lg:leading-tight">
            {{ $title }}
        </h1>

        @if ($updated)
            <p class="mt-3 text-sm text-ink-muted">
                {{ __('Última actualización: :fecha', ['fecha' => $updated]) }}
            </p>
        @endif

        <div class="mt-10 space-y-6 text-[15px] leading-relaxed text-ink-2
            [&_h2]:mt-12 [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:tracking-[-0.02em] [&_h2]:text-ink
            [&_h2:first-child]:mt-0
            [&_ul]:list-disc [&_ul]:space-y-2 [&_ul]:ps-5
            [&_strong]:font-semibold [&_strong]:text-ink">
            {{ $slot }}
        </div>
    </div>
</x-layouts::app>
