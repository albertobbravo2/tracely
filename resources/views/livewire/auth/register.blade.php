<x-layouts::auth :title="__('Registrarse')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Crear una cuenta')" :description="__('Introduce tus datos para crear tu cuenta')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        {{-- design.md → Autenticación: login y registro llevan el botón de
             passkey encima del formulario, con el separador de correo. --}}
        <x-passkey-verify />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf
            <!-- Name -->
            <flux:input
                name="name"
                :label="__('Nombre')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('Nombre completo')"
            />

            <!-- País -->
            <flux:select
                name="country"
                wire:model="country"
                :label="__('País')"
                :placeholder="__('Selecciona tu país')"
            >
                <flux:select.option>España</flux:select.option>
                <flux:select.option>República Dominicana</flux:select.option>
            </flux:select>

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Correo electrónico')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Contraseña')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Contraseña')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('Confirmar contraseña')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Confirmar contraseña')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            {{-- Consentimiento: el campo va como `flux:field` explícito, y no
                 con `:label` en el checkbox, porque la etiqueta lleva enlaces
                 dentro. Se abren en pestaña nueva para no perder el formulario. --}}
            <flux:field variant="inline">
                <flux:checkbox name="terms" value="1" :checked="old('terms')" required />

                {{-- Todo el texto va dentro de un solo `<span>`: `flux:label` es
                     un `inline-flex`, así que sin envolverlo cada trozo de la
                     frase se convertiría en una columna suya. --}}
                <flux:label class="!text-sm !font-normal !text-ink-2">
                    <span>
                        {{ __('He leído y acepto los') }}
                        <flux:link :href="route('legal.terminos')" target="_blank">{{ __('términos y condiciones') }}</flux:link>
                        {{ __('y la') }}
                        <flux:link :href="route('legal.privacidad')" target="_blank">{{ __('política de privacidad') }}</flux:link>.
                    </span>
                </flux:label>

                <flux:error name="terms" />
            </flux:field>

            <flux:button
                type="submit"
                variant="primary"
                class="w-full shadow-elev"
                data-test="register-user-button"
            >
                {{ __('Crear cuenta') }}
            </flux:button>
        </form>

        <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-ink-2">
            <span>{{ __('¿Ya tienes cuenta?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Iniciar sesión') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
