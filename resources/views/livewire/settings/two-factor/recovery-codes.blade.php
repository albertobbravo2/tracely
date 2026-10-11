{{-- Bloque anidado dentro de la tarjeta de 2FA: va sobre `surface-2` en vez de
     abrir otra tarjeta `surface` (design.md → «No envuelvas cada elemento en su
     propia tarjeta»). --}}
<div
    class="space-y-5 rounded-xl border border-line bg-surface-2 p-5"
    wire:cloak
    x-data="{ showRecoveryCodes: false }"
>
    <div class="space-y-1.5">
        <div class="flex items-center gap-2">
            <flux:icon.lock-closed variant="outline" class="size-4 text-ink-muted" />
            <h4 class="text-base font-semibold tracking-[-0.01em] text-ink">{{ __('Códigos de recuperación 2FA') }}</h4>
        </div>

        <p class="text-sm leading-relaxed text-ink-2">
            {{ __('Los códigos de recuperación te permiten recuperar el acceso si pierdes tu dispositivo 2FA. Guárdalos en un gestor de contraseñas seguro.') }}
        </p>
    </div>

    <div>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <flux:button
                x-show="!showRecoveryCodes"
                icon="eye"
                icon:variant="outline"
                variant="primary"
                size="sm"
                @click="showRecoveryCodes = true;"
                aria-expanded="false"
                aria-controls="recovery-codes-section"
            >
                {{ __('Ver códigos de recuperación') }}
            </flux:button>

            <flux:button
                x-show="showRecoveryCodes"
                icon="eye-slash"
                icon:variant="outline"
                variant="primary"
                size="sm"
                @click="showRecoveryCodes = false"
                aria-expanded="true"
                aria-controls="recovery-codes-section"
            >
                {{ __('Ocultar códigos de recuperación') }}
            </flux:button>

            @if (filled($recoveryCodes))
                <flux:button
                    x-show="showRecoveryCodes"
                    icon="arrow-path"
                    variant="outline"
                    size="sm"
                    wire:click="regenerateRecoveryCodes"
                >
                    {{ __('Regenerar códigos') }}
                </flux:button>
            @endif
        </div>

        <div
            x-show="showRecoveryCodes"
            x-transition
            id="recovery-codes-section"
            class="relative overflow-hidden"
            x-bind:aria-hidden="!showRecoveryCodes"
        >
            <div class="mt-4 space-y-3">
                @error('recoveryCodes')
                    <div class="flex items-start gap-2.5 rounded-xl border border-danger/25 bg-danger-soft px-3.5 py-3 text-sm font-medium text-danger">
                        <flux:icon icon="x-circle" variant="mini" class="mt-px shrink-0" />
                        <span>{{ $message }}</span>
                    </div>
                @enderror

                @if (filled($recoveryCodes))
                    <div
                        class="grid gap-1 rounded-xl border border-line bg-surface p-4 font-mono text-sm text-ink"
                        role="list"
                        aria-label="{{ __('Códigos de recuperación') }}"
                    >
                        @foreach($recoveryCodes as $code)
                            <div
                                role="listitem"
                                class="select-text"
                                wire:loading.class="opacity-50 animate-pulse"
                            >
                                {{ $code }}
                            </div>
                        @endforeach
                    </div>

                    <p class="text-xs leading-relaxed text-ink-muted">
                        {{ __('Cada código de recuperación se puede usar una sola vez para acceder a tu cuenta y se elimina tras usarlo. Si necesitas más, pulsa Regenerar códigos arriba.') }}
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>
