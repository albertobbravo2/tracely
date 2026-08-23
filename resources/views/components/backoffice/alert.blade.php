@props([
    'message',
])

{{-- Mensaje de error de la pantalla. Misma caja que ya usan ⚡searchfield y
     ⚡my-shipments para sus errores, para no inventar un tercer estilo. --}}
<div class="flex items-start gap-3 rounded-xl border border-alerta-claro bg-alerta-fondo px-4 py-3 dark:border-alerta-fuerte dark:bg-azul-900">
    <flux:icon name="exclamation-triangle" variant="outline" class="mt-0.5 size-5 shrink-0 text-alerta-fuerte dark:text-alerta-claro" />

    <flux:text class="text-alerta-fuerte dark:text-alerta-claro">{{ $message }}</flux:text>
</div>
