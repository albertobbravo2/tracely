@props([
    'name',
    'heading',
    'description' => null,
    // Tres anchos y ninguno puesto a ojo: un formulario de dos columnas
    // necesita más sitio que uno de una, y una confirmación de borrado no
    // necesita ninguno de los dos. Los tres salen de design.md → Modales:
    // 384 px para confirmaciones, 448–576 px para formularios.
    'size' => 'base',
])

@php
    $width = match ($size) {
        'wide' => 'md:w-[36rem]',
        'narrow' => 'md:w-96',
        default => 'md:w-[28rem]',
    };
@endphp

{{-- El diálogo de las diez ventanas del backoffice.

     Además de no repetir diez veces la misma cabecera, es lo que hace que
     todas midan igual y que su título y su explicación usen los mismos tokens
     que el resto de la página. El panel (fondo y borde) lo repinta app.css
     dentro de `.backoffice`: el de Flux viene en la escala zinc. --}}
<flux:modal :name="$name" {{ $attributes->class(['w-full rounded-2xl shadow-modal', $width]) }}>
    <div class="space-y-6">
        <div>
            <flux:heading size="lg" class="text-ink">{{ $heading }}</flux:heading>

            @if ($description)
                <flux:text class="mt-1.5 leading-relaxed text-ink-muted">{{ $description }}</flux:text>
            @endif
        </div>

        {{ $slot }}
    </div>
</flux:modal>
