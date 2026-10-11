<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Ajustes del perfil') }}</flux:heading>

    <x-settings.layout :heading="__('Perfil')" :subheading="__('Actualiza tu nombre y tu correo electrónico')">
        <form wire:submit="updateProfileInformation" class="w-full space-y-6">
            <flux:input wire:model="name" :label="__('Nombre')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Correo electrónico')" type="email" required autocomplete="email" />

                @if ($this->hasUnverifiedEmail)
                    <div class="mt-3 flex items-start gap-2.5 rounded-xl border border-warn/25 bg-warn-soft px-3.5 py-3 text-sm text-warn">
                        <flux:icon icon="exclamation-triangle" variant="mini" class="mt-px shrink-0" />

                        <p class="leading-relaxed">
                            {{ __('Tu correo electrónico no está verificado.') }}

                            <button
                                type="button"
                                class="font-semibold underline cursor-pointer underline-offset-2"
                                wire:click="resendVerificationNotification"
                            >
                                {{ __('Reenviar el correo de verificación.') }}
                            </button>
                        </p>
                    </div>
                @endif
            </div>

            <flux:button variant="primary" type="submit" class="shadow-elev">{{ __('Guardar') }}</flux:button>
        </form>

        @if ($this->showDeleteUser)
            <x-slot:extra>
                <livewire:settings.delete-user-form />
            </x-slot:extra>
        @endif
    </x-settings.layout>
</section>
