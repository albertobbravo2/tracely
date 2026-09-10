@props([
    'title',
    'description',
])

<div class="flex w-full flex-col gap-2">
    <flux:heading class="!text-3xl !font-bold !tracking-tight text-ink">{{ $title }}</flux:heading>
    <flux:subheading class="!text-[0.9rem] text-ink-2">{{ $description }}</flux:subheading>
</div>
