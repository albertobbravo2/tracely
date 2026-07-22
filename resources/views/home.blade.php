<x-layouts::app :title="__('Home')">
    <div class="bg-blue-50 px-6 py-16 text-center">
        <flux:heading size="xl" class="text-brand-navy !text-3xl">
            {{ __('Rastrea tu paquete') }}
        </flux:heading>

        <flux:text class="mt-2 text-blue-700">
            {{ __('Ingresa tu número de guía para ver el estado en tiempo real') }}
        </flux:text>

        <form action="#" method="POST" class="mx-auto mt-6 flex max-w-xl items-center gap-3">
            <flux:input
                name="tracking_number"
                placeholder="RY-4820-1174-MX"
                class="flex-1"
                class:input="!border-transparent !bg-white !text-zinc-900 !shadow-none placeholder:!text-zinc-500"
            />
            <flux:button
                type="submit"
                variant="primary"
                class="shrink-0 px-6 font-semibold [--color-accent-foreground:var(--color-white)] [--color-accent:var(--color-brand-navy)]"
            >
                {{ __('Buscar') }}
            </flux:button>
        </form>
    </div>

    @php
        $shipment = [
            'tracking_number' => 'RY-4820-1174-MX',
            'status' => __('En tránsito'),
            'origin' => 'Guadalajara, JAL',
            'destination' => 'Monterrey, NL',
            'eta' => __('Hoy, 18:00'),
        ];

        $timeline = [
            ['title' => __('Paquete recogido'), 'meta' => '21 jul · 09:14 · Guadalajara', 'state' => 'done'],
            ['title' => __('En centro de distribución'), 'meta' => '22 jul · 02:40 · Zapopan', 'state' => 'done'],
            ['title' => __('En ruta de entrega'), 'meta' => '22 jul · 11:05 · Monterrey', 'state' => 'active'],
            ['title' => __('Entregado'), 'meta' => __('Pendiente'), 'state' => 'pending'],
        ];
    @endphp

    <div class="mx-auto max-w-xl px-6 py-10">
        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <flux:text class="text-zinc-500">{{ __('Guía') }}</flux:text>
                    <flux:heading size="lg" class="text-zinc-900">{{ $shipment['tracking_number'] }}</flux:heading>
                </div>

                <flux:badge rounded class="shrink-0 !bg-green-100 !text-green-700">
                    {{ $shipment['status'] }}
                </flux:badge>
            </div>

            <div class="mt-6 grid grid-cols-3 gap-4">
                <div>
                    <flux:text class="text-zinc-500">{{ __('Origen') }}</flux:text>
                    <p class="font-semibold text-zinc-900">{{ $shipment['origin'] }}</p>
                </div>
                <div>
                    <flux:text class="text-zinc-500">{{ __('Destino') }}</flux:text>
                    <p class="font-semibold text-zinc-900">{{ $shipment['destination'] }}</p>
                </div>
                <div>
                    <flux:text class="text-zinc-500">{{ __('Entrega estimada') }}</flux:text>
                    <p class="font-semibold text-green-600">{{ $shipment['eta'] }}</p>
                </div>
            </div>

            <hr class="mt-6 border-zinc-200">

            <ol class="mt-6 space-y-6">
                @foreach ($timeline as $step)
                    <li class="relative flex gap-4">
                        @unless ($loop->last)
                            <span class="absolute top-4 start-[6.5px] h-full w-px bg-zinc-200"></span>
                        @endunless

                        <span @class([
                            'relative z-10 mt-1 size-3.5 shrink-0 rounded-full',
                            'bg-green-500' => $step['state'] === 'done',
                            'bg-blue-600' => $step['state'] === 'active',
                            'border-2 border-zinc-300 bg-white' => $step['state'] === 'pending',
                        ])></span>

                        <div>
                            <p @class([
                                'font-semibold',
                                'text-zinc-900' => $step['state'] === 'done',
                                'text-blue-600' => $step['state'] === 'active',
                                'text-zinc-400' => $step['state'] === 'pending',
                            ])>{{ $step['title'] }}</p>
                            <p class="text-sm text-zinc-500">{{ $step['meta'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </div>
</x-layouts::app>
