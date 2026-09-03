@props([
    'status',
])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-ok-fuerte dark:text-ok-claro']) }}>
        {{ $status }}
    </div>
@endif
