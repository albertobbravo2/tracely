@props([
    'name',
    'heading',
    'description' => null,
    // Tres anchos y ninguno puesto a ojo: un formulario de dos columnas
    // necesita más sitio que uno de una, y una confirmación de borrado no
    // necesita ninguno de los dos. Antes iban de 32rem a 38rem sin criterio.
    'size' => 'base',
])

@php
    $width = match ($size) {
        'wide' => 'md:w-[36rem]',
        'narrow' => 'md:w-96',
        default => 'md:w-[32rem]',
    };
@endphp

{{-- El diálogo de las diez ventanas del backoffice.

     Además de no repetir diez veces la misma cabecera, es lo que hace que
     todas midan igual y que su título y su explicación usen los mismos colores
     que el resto de la página (mismo par que <x-backoffice.heading>). El panel
     lo repinta app.css: el de Flux es gris zinc, y aquí todo es azul de marca. --}}
<flux:modal :name="$name" {{ $attributes->class(['w-full', $width]) }}>
    <div class="space-y-6">
        <div>
            <flux:heading size="lg" class="text-gris-900 dark:text-blanco">{{ $heading }}</flux:heading>

            @if ($description)
                <flux:text class="mt-1 text-gris-600 dark:text-azul-100">{{ $description }}</flux:text>
            @endif
        </div>

        {{ $slot }}
    </div>
</flux:modal>
