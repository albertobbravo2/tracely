<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Ajustes de seguridad') }}</flux:heading>

    <x-settings.layout
        :heading="__('Actualizar contraseña')"
        :subheading="__('Asegúrate de usar una contraseña larga y aleatoria para mantener tu cuenta segura')"
    >
        <form method="POST" wire:submit="updatePassword" class="space-y-6">
            <flux:input
                wire:model="current_password"
                :label="__('Contraseña actual')"
                type="password"
                required
                autocomplete="current-password"
                viewable
            />
            <flux:input
                wire:model="password"
                :label="__('Nueva contraseña')"
                type="password"
                required
                autocomplete="new-password"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />
            <flux:input
                wire:model="password_confirmation"
                :label="__('Confirmar contraseña')"
                type="password"
                required
                autocomplete="new-password"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <flux:button variant="primary" type="submit" class="shadow-elev" data-test="update-password-button">
                {{ __('Guardar') }}
            </flux:button>
        </form>

        {{-- 2FA y passkeys son bloques con función propia: van en su propia
             tarjeta, no dentro de la de contraseña. --}}
        <x-slot:extra>
            @if ($canManageTwoFactor)
                <section class="rounded-xl border border-line bg-surface p-5 sm:p-6">
                    <h2 class="text-xl font-semibold tracking-[-0.02em] text-ink">{{ __('Autenticación en dos pasos') }}</h2>

                    <p class="mt-1 max-w-lg text-sm leading-relaxed text-ink-2">
                        {{ __('Gestiona tus ajustes de autenticación en dos pasos') }}
                    </p>

                    <div class="mt-6 flex w-full flex-col space-y-5 text-sm" wire:cloak>
                        @if ($twoFactorEnabled)
                            <div>
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-ok-soft px-2 py-0.5 text-xs font-semibold text-ok">
                                    <flux:icon icon="check-circle" variant="micro" />
                                    {{ __('Activada') }}
                                </span>
                            </div>

                            <p class="max-w-lg leading-relaxed text-ink-2">
                                {{ __('Se te pedirá un pin seguro y aleatorio al iniciar sesión, que podrás obtener desde la aplicación compatible con TOTP de tu móvil.') }}
                            </p>

                            <livewire:settings.two-factor.recovery-codes :$requiresConfirmation />

                            <div class="flex justify-start">
                                <flux:button variant="danger" size="sm" wire:click="disable">
                                    {{ __('Desactivar 2FA') }}
                                </flux:button>
                            </div>
                        @else
                            <p class="max-w-lg leading-relaxed text-ink-2">
                                {{ __('Al activar la autenticación en dos pasos, se te pedirá un pin seguro al iniciar sesión. Puedes obtener ese pin desde una aplicación compatible con TOTP en tu móvil.') }}
                            </p>

                            <div class="flex justify-start">
                                <flux:button variant="primary" wire:click="enable">
                                    {{ __('Activar 2FA') }}
                                </flux:button>
                            </div>
                        @endif
                    </div>
                </section>
            @endif

            @if ($canManagePasskeys)
                <section class="rounded-xl border border-line bg-surface p-5 sm:p-6">
                    <h2 class="text-xl font-semibold tracking-[-0.02em] text-ink">{{ __('Passkeys') }}</h2>

                    <p class="mt-1 max-w-lg text-sm leading-relaxed text-ink-2">
                        {{ __('Gestiona tus passkeys para iniciar sesión sin contraseña') }}
                    </p>

                    <div class="mt-6 flex w-full flex-col space-y-5 text-sm" wire:cloak>
                        <div class="overflow-hidden rounded-xl border border-line">
                            @forelse ($passkeys as $passkey)
                                <div class="flex items-center justify-between gap-4 p-4 {{ ! $loop->last ? 'border-b border-line' : '' }}">
                                    <div class="flex min-w-0 items-center gap-3.5">
                                        <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-surface-2">
                                            <flux:icon.key class="size-5 text-ink-muted" />
                                        </div>

                                        <div class="min-w-0 space-y-1">
                                            <div class="flex items-center gap-2.5">
                                                <p class="truncate font-semibold tracking-[-0.01em] text-ink">{{ $passkey['name'] }}</p>

                                                @if ($passkey['authenticator'])
                                                    <flux:badge size="sm">{{ $passkey['authenticator'] }}</flux:badge>
                                                @endif
                                            </div>

                                            <p class="text-xs text-ink-muted">
                                                {{ __('Añadida :time', ['time' => $passkey['created_at_diff']]) }}
                                                @if ($passkey['last_used_at_diff'])
                                                    <span class="mx-1 text-line-strong">/</span>
                                                    {{ __('Usada por última vez :time', ['time' => $passkey['last_used_at_diff']]) }}
                                                @endif
                                            </p>
                                        </div>
                                    </div>

                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="trash"
                                        icon:variant="outline"
                                        wire:click="confirmDelete({{ $passkey['id'] }})"
                                        class="shrink-0 text-danger hover:bg-danger-soft"
                                        :aria-label="__('Eliminar passkey')"
                                    />
                                </div>
                            @empty
                                <div class="p-8 text-center">
                                    <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl bg-surface-2">
                                        <flux:icon.key class="size-7 text-ink-muted" />
                                    </div>

                                    <p class="font-semibold text-ink">{{ __('Todavía no tienes passkeys') }}</p>
                                    <p class="mt-1 text-sm text-ink-2">{{ __('Añade una passkey para iniciar sesión sin contraseña') }}</p>
                                </div>
                            @endforelse
                        </div>

                        <x-passkey-registration />
                    </div>
                </section>
            @endif
        </x-slot:extra>
    </x-settings.layout>

    @if ($canManageTwoFactor)
        <flux:modal
            name="two-factor-setup-modal"
            class="max-w-md md:min-w-md"
            @close="closeModal"
            wire:model="showModal"
        >
            <div class="space-y-6">
                <div class="flex flex-col items-center space-y-4">
                    <div class="w-auto rounded-full border border-line bg-surface p-0.5 shadow-elev">
                        <div class="relative overflow-hidden rounded-full border border-line bg-surface-2 p-2.5">
                            <div class="absolute inset-0 flex h-full w-full items-stretch justify-around divide-x divide-line opacity-50 [&>div]:flex-1">
                                @for ($i = 1; $i <= 5; $i++)
                                    <div></div>
                                @endfor
                            </div>

                            <div class="absolute inset-0 flex h-full w-full flex-col items-stretch justify-around divide-y divide-line opacity-50 [&>div]:flex-1">
                                @for ($i = 1; $i <= 5; $i++)
                                    <div></div>
                                @endfor
                            </div>

                            <flux:icon.qr-code class="relative z-20 text-ink" />
                        </div>
                    </div>

                    <div class="space-y-1.5 text-center">
                        <flux:heading size="lg">{{ $this->modalConfig['title'] }}</flux:heading>
                        <flux:text class="text-ink-2">{{ $this->modalConfig['description'] }}</flux:text>
                    </div>
                </div>

                @if ($showVerificationStep)
                    <div class="space-y-6">
                        <div
                            class="flex flex-col items-center justify-center space-y-3"
                            x-data
                            x-init="$nextTick(() => $el.querySelector('input')?.focus())"
                        >
                            <flux:otp
                                name="code"
                                wire:model="code"
                                length="6"
                                :label="__('Código de autenticación')"
                                label:sr-only
                                class="mx-auto"
                            />
                        </div>

                        <div class="flex items-center gap-3">
                            <flux:button variant="outline" class="flex-1" wire:click="resetVerification">
                                {{ __('Atrás') }}
                            </flux:button>

                            <flux:button
                                variant="primary"
                                class="flex-1"
                                wire:click="confirmTwoFactor"
                                x-bind:disabled="$wire.code.length < 6"
                            >
                                {{ __('Confirmar') }}
                            </flux:button>
                        </div>
                    </div>
                @else
                    @error('setupData')
                        <div class="flex items-start gap-2.5 rounded-xl border border-danger/25 bg-danger-soft px-3.5 py-3 text-sm font-medium text-danger">
                            <flux:icon icon="x-circle" variant="mini" class="mt-px shrink-0" />
                            <span>{{ $message }}</span>
                        </div>
                    @enderror

                    <div class="flex justify-center">
                        <div class="relative aspect-square w-64 overflow-hidden rounded-xl border border-line">
                            @empty($qrCodeSvg)
                                <div class="absolute inset-0 flex animate-pulse items-center justify-center bg-surface-2">
                                    <flux:icon.loading />
                                </div>
                            @else
                                {{-- El QR necesita fondo blanco real para ser legible por la
                                     cámara; en oscuro se invierte en bloque en vez de cambiarle
                                     el color a los módulos. --}}
                                <div class="flex h-full items-center justify-center p-4">
                                    <div class="rounded bg-white p-3 dark:invert dark:brightness-150">
                                        {!! $qrCodeSvg !!}
                                    </div>
                                </div>
                            @endempty
                        </div>
                    </div>

                    <flux:button
                        :disabled="$errors->has('setupData')"
                        variant="primary"
                        class="w-full shadow-elev"
                        wire:click="showVerificationIfNecessary"
                    >
                        {{ $this->modalConfig['buttonText'] }}
                    </flux:button>

                    <div class="space-y-4">
                        <div class="flex items-center gap-3.5">
                            <div class="h-px flex-1 bg-line"></div>
                            <span class="text-[0.65rem] font-semibold uppercase tracking-[0.1em] text-ink-muted">
                                {{ __('o introduce el código manualmente') }}
                            </span>
                            <div class="h-px flex-1 bg-line"></div>
                        </div>

                        <div
                            x-data="{
                                copied: false,
                                async copy() {
                                    try {
                                        await navigator.clipboard.writeText('{{ $manualSetupKey }}');
                                        this.copied = true;
                                        setTimeout(() => this.copied = false, 1500);
                                    } catch (e) {
                                        console.warn('Could not copy to clipboard');
                                    }
                                }
                            }"
                        >
                            <div class="flex w-full items-stretch overflow-hidden rounded-xl border border-line-strong bg-surface">
                                @empty($manualSetupKey)
                                    <div class="flex w-full items-center justify-center bg-surface-2 p-3">
                                        <flux:icon.loading variant="mini" />
                                    </div>
                                @else
                                    <input
                                        type="text"
                                        readonly
                                        value="{{ $manualSetupKey }}"
                                        aria-label="{{ __('Clave de configuración manual') }}"
                                        class="w-full bg-transparent p-3 font-mono text-sm text-ink outline-none"
                                    />

                                    <button
                                        type="button"
                                        @click="copy()"
                                        aria-label="{{ __('Copiar clave') }}"
                                        class="cursor-pointer border-s border-line px-3 text-ink-2 transition-colors hover:bg-surface-2 hover:text-ink"
                                    >
                                        <flux:icon.document-duplicate x-show="!copied" variant="outline" class="size-5" />
                                        <flux:icon.check x-show="copied" variant="solid" class="size-5 text-ok" />
                                    </button>
                                @endempty
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </flux:modal>
    @endif

    <flux:modal
        name="delete-passkey-modal"
        class="max-w-md md:min-w-md"
        @close="closeDeleteModal"
        wire:model="showDeleteModal"
    >
        <div class="space-y-6">
            <div class="space-y-1.5">
                <flux:heading size="lg">{{ __('Eliminar passkey') }}</flux:heading>
                <flux:text class="text-ink-2">
                    {{ __('¿Seguro que quieres eliminar la passkey ":name"? Ya no podrás usarla para iniciar sesión.', ['name' => $deletingPasskeyName]) }}
                </flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" class="text-ink-2" wire:click="closeDeleteModal">
                    {{ __('Cancelar') }}
                </flux:button>

                <flux:button variant="danger" wire:click="deletePasskey">
                    {{ __('Eliminar passkey') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
