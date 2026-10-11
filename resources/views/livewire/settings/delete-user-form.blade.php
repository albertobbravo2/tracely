<section class="rounded-xl border border-danger/30 bg-danger-soft p-5 sm:p-6">
    <h2 class="text-xl font-semibold tracking-[-0.02em] text-danger">{{ __('Eliminar cuenta') }}</h2>

    <p class="mt-1 max-w-lg text-sm leading-relaxed text-ink-2">
        {{ __('Elimina tu cuenta y todos sus datos de forma permanente. Esta acción no se puede deshacer.') }}
    </p>

    <flux:modal.trigger name="confirm-user-deletion">
        <flux:button
            variant="danger"
            class="mt-5"
            x-data=""
            x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
        >
            {{ __('Eliminar cuenta') }}
        </flux:button>
    </flux:modal.trigger>

    <flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-md">
        <form method="POST" wire:submit="deleteUser" class="space-y-6">
            <div class="space-y-1.5">
                <flux:heading size="lg">{{ __('¿Seguro que quieres eliminar tu cuenta?') }}</flux:heading>

                <flux:text class="text-ink-muted">
                    {{ __('Una vez eliminada tu cuenta, todos sus datos se borrarán de forma permanente. Introduce tu contraseña para confirmar que quieres eliminarla definitivamente.') }}
                </flux:text>
            </div>

            <flux:input wire:model="password" :label="__('Contraseña')" type="password" viewable />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost" class="text-ink-2">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" type="submit">{{ __('Eliminar cuenta') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
