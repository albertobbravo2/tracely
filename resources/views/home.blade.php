<x-layouts::app :title="__('Rastrea tu paquete')">
    <div class="mx-auto w-full max-w-5xl px-6 pb-20 pt-14 sm:pt-20">
        {{-- Hero. El serif de display (`font-display`) vive solo aquí: es el
             único titular del producto que lo lleva, según design.md. --}}
        <div class="mx-auto max-w-2xl text-center">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-soft px-3 py-1 text-xs font-semibold text-primary">
                <flux:icon name="signal" variant="micro" class="size-3.5" />
                {{ __('Seguimiento en tiempo real') }}
            </span>

            <h1 class="mt-5 font-display text-5xl leading-[1.05] tracking-[-0.03em] text-ink sm:text-6xl">
                {{ __('Rastrea tu paquete') }}
            </h1>

            <p class="mx-auto mt-4 max-w-lg text-[15px] leading-relaxed text-ink-muted">
                {{ __('Introduce tu número de guía y sigue el recorrido de tu envío paso a paso, desde la recogida hasta la entrega.') }}
            </p>
        </div>

        {{-- El buscador pinta debajo su propio resultado (⚡shipment-card), así
             que la tarjeta del envío aparece aquí, entre el buscador y las
             tarjetas informativas. --}}
        <div class="mt-10">
            <livewire:searchfield />
        </div>

        <div class="mt-16 grid gap-4 sm:grid-cols-3">
            @foreach ([
                [
                    'icon' => 'bolt',
                    'title' => __('Al instante'),
                    'text' => __('Cada movimiento del envío se registra en su historial en cuanto ocurre.'),
                ],
                [
                    'icon' => 'shield-check',
                    'title' => __('Sin registro'),
                    'text' => __('No necesitas cuenta para consultar un envío: basta con el número de guía.'),
                ],
                [
                    'icon' => 'squares-2x2',
                    'title' => __('Todo en un panel'),
                    'text' => __('Crea una cuenta y vincula tus guías para tenerlas siempre a mano.'),
                ],
            ] as $feature)
                <div class="rounded-xl border border-line bg-surface p-5">
                    <span class="flex size-9 items-center justify-center rounded-[10px] bg-primary-soft text-primary">
                        <flux:icon :name="$feature['icon']" variant="outline" class="size-[18px]" />
                    </span>

                    <p class="mt-4 font-semibold text-ink">{{ $feature['title'] }}</p>

                    <p class="mt-1 text-sm leading-relaxed text-ink-muted">{{ $feature['text'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</x-layouts::app>
