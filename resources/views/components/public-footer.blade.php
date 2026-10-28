{{-- Pie de la parte pública (design.md → Home / seguimiento público). Lo pinta
     el layout `app` cuando la vista pasa `:footer="true"`, así que queda fuera
     del panel y del backoffice: es la salida a las páginas legales para quien
     llega a consultar un envío, no un elemento de la app.

     `flux:footer` y no un `<footer>` a secas: el layout de Flux es una rejilla
     con áreas nombradas, y sin `[grid-area:footer]` el pie cae en la columna
     del sidebar. El `!p-0` le quita el padding propio para que el borde y el
     fondo lleguen de lado a lado; el ancho lo pone el contenedor de dentro. --}}
<flux:footer class="!p-0 border-t border-line bg-surface">
    <div class="mx-auto w-full max-w-5xl px-6 py-10">
        <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5" wire:navigate>
                <x-brand-mark class="!size-[30px]" />
                <span class="text-[17px] font-bold tracking-[-0.02em] text-ink">Tracely</span>
            </a>

            <nav class="flex flex-wrap items-center gap-x-6 gap-y-2">
                @foreach ([
                    ['route' => 'legal.aviso', 'label' => __('Aviso legal')],
                    ['route' => 'legal.privacidad', 'label' => __('Política de privacidad')],
                    ['route' => 'legal.cookies', 'label' => __('Política de cookies')],
                    ['route' => 'legal.terminos', 'label' => __('Términos y condiciones')],
                ] as $link)
                    <a
                        href="{{ route($link['route']) }}"
                        @class([
                            'text-sm transition-colors hover:text-ink',
                            'font-semibold text-primary' => request()->routeIs($link['route']),
                            'text-ink-2' => ! request()->routeIs($link['route']),
                        ])
                        wire:navigate
                    >
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </nav>
        </div>

        <p class="mt-6 border-t border-line pt-6 text-xs text-ink-muted">
            &copy; {{ date('Y') }} Tracely. {{ __('Todos los derechos reservados.') }}
        </p>
    </div>
</flux:footer>
