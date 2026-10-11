<x-layouts::auth :title="__('Verificación de correo')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Verifica tu correo')"
            :description="__('Verifica tu correo electrónico haciendo clic en el enlace que te acabamos de enviar.')"
        />

        @if (session('status') == 'verification-link-sent')
            {{-- Flux pinta sus callouts en su propia escala `green`/`red`; aquí
                 se usa el par ok/ok-soft del diseño (design.md → Color). --}}
            <div class="flex items-start gap-2.5 rounded-xl border border-ok/25 bg-ok-soft px-3.5 py-3 text-sm font-medium text-ok">
                <flux:icon icon="check-circle" variant="mini" class="mt-px shrink-0" />
                <span>{{ __('Se ha enviado un nuevo enlace de verificación al correo que indicaste al registrarte.') }}</span>
            </div>
        @endif

        <div class="flex flex-col gap-3">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <flux:button type="submit" variant="primary" class="w-full shadow-elev">
                    {{ __('Reenviar correo de verificación') }}
                </flux:button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <flux:button
                    variant="ghost"
                    type="submit"
                    class="w-full text-sm cursor-pointer text-ink-2"
                    data-test="logout-button"
                >
                    {{ __('Cerrar sesión') }}
                </flux:button>
            </form>
        </div>
    </div>
</x-layouts::auth>
