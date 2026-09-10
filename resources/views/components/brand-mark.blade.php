@props([
    'inverse' => false,
])

{{-- Cuadrado de marca (design.md → Navbar). `inverse` es para el panel
     degradado de autenticación, donde el fondo ya es `primary` y hay que
     invertir los papeles. --}}
<span {{ $attributes->class([
    'flex size-8 shrink-0 items-center justify-center rounded-[10px]',
    'bg-primary' => ! $inverse,
    'bg-on-primary' => $inverse,
]) }}>
    <x-app-logo-icon @class([
        'size-[17px] fill-current',
        'text-on-primary' => ! $inverse,
        'text-primary' => $inverse,
    ]) />
</span>
