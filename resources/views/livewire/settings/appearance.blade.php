<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Ajustes de apariencia') }}</flux:heading>

    <x-settings.layout :heading="__('Apariencia')" :subheading="__('Elige cómo quieres ver Tracely en este navegador')">
        {{-- `$flux.appearance` es la misma API que usa el conmutador del navbar:
             se cambia la presentación, no el mecanismo. --}}
        <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
            <flux:radio value="light" icon="sun">{{ __('Claro') }}</flux:radio>
            <flux:radio value="dark" icon="moon">{{ __('Oscuro') }}</flux:radio>
            <flux:radio value="system" icon="computer-desktop">{{ __('Sistema') }}</flux:radio>
        </flux:radio.group>

        <p class="mt-4 text-sm leading-relaxed text-ink-muted">
            {{ __('«Sistema» sigue la preferencia de tu dispositivo y cambia solo entre claro y oscuro.') }}
        </p>
    </x-settings.layout>
</section>
