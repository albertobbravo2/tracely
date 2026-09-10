<x-layouts::auth :title="__('Autenticación en dos pasos')">
    <div class="flex flex-col gap-6">
        <div
            class="relative w-full h-auto"
            x-cloak
            x-data="{
                showRecoveryInput: @js($errors->has('recovery_code')),
                code: '',
                recovery_code: '',
                focusOtp() {
                    this.$nextTick(() => this.$refs.otp?.querySelector('input')?.focus());
                },
                init() {
                    if (! this.showRecoveryInput) {
                        this.focusOtp();
                    }
                },
                toggleInput() {
                    this.showRecoveryInput = !this.showRecoveryInput;

                    this.code = '';
                    this.recovery_code = '';

                    $nextTick(() => {
                        this.showRecoveryInput
                            ? this.$refs.recovery_code?.focus()
                            : this.focusOtp();
                    });
                },
            }"
        >
            <div x-show="!showRecoveryInput">
                <x-auth-header
                    :title="__('Código de autenticación')"
                    :description="__('Introduce el código de autenticación de tu aplicación de autenticación.')"
                />
            </div>

            <div x-show="showRecoveryInput">
                <x-auth-header
                    :title="__('Código de recuperación')"
                    :description="__('Confirma el acceso a tu cuenta introduciendo uno de tus códigos de recuperación de emergencia.')"
                />
            </div>

            <form method="POST" action="{{ route('two-factor.login.store') }}">
                @csrf

                <div class="space-y-5 text-center">
                    <div x-show="!showRecoveryInput">
                        <div class="flex items-center justify-center my-6" x-ref="otp">
                            <flux:otp
                                x-model="code"
                                length="6"
                                name="code"
                                :label="__('Código de autenticación')"
                                label:sr-only
                                class="mx-auto"
                            />
                        </div>
                    </div>

                    <div x-show="showRecoveryInput">
                        <div class="my-6">
                            <flux:input
                                type="text"
                                name="recovery_code"
                                :label="__('Código de recuperación')"
                                label:sr-only
                                :placeholder="__('Código de recuperación')"
                                class="font-mono"
                                x-ref="recovery_code"
                                x-bind:required="showRecoveryInput"
                                autocomplete="one-time-code"
                                x-model="recovery_code"
                            />
                        </div>

                        @error('recovery_code')
                            <flux:text class="text-danger">
                                {{ $message }}
                            </flux:text>
                        @enderror
                    </div>

                    <flux:button
                        variant="primary"
                        type="submit"
                        class="w-full shadow-elev"
                    >
                        {{ __('Continuar') }}
                    </flux:button>
                </div>

                <div class="mt-6 space-x-1 text-sm text-center rtl:space-x-reverse text-ink-2">
                    <span>{{ __('o puedes') }}</span>
                    <button type="button" class="font-semibold cursor-pointer text-primary hover:text-primary-hover">
                        <span x-show="!showRecoveryInput" @click="toggleInput()">{{ __('acceder con un código de recuperación') }}</span>
                        <span x-show="showRecoveryInput" @click="toggleInput()">{{ __('acceder con un código de autenticación') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts::auth>
