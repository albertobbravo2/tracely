@props([
    'status',
])

@if ($status)
    <div {{ $attributes->merge(['class' => 'rounded-xl border border-ok/25 bg-ok-soft px-3.5 py-3 text-sm font-medium text-ok']) }}>
        {{ $status }}
    </div>
@endif
