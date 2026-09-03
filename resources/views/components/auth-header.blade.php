@props([
    'title',
    'description',
])

<div class="flex w-full flex-col text-center">
    <flux:heading size="xl" class="text-brand-navy dark:text-blanco">{{ $title }}</flux:heading>
    <flux:subheading class="text-azul-600 dark:text-azul-100">{{ $description }}</flux:subheading>
</div>
