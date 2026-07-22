---
name: flux-ui-components
description: Usar cuando el usuario construya o modifique interfaces con Flux UI (botones, inputs, tablas, modals, tabs, dropdowns) en componentes Blade/Livewire de este proyecto. Cubre qué componente usar, wire:model, estados de carga, dark mode y accesibilidad. Asume Flux UI v2.15.x sobre Tailwind CSS v4 y Livewire v4.x.
---

# Flux UI Components (v2.15.x)

Este proyecto usa **Flux UI v2** (`livewire/flux ^2.13`, instalado en `v2.15.0`), la librería
oficial de componentes de Livewire construida sobre Tailwind CSS v4. NO uses sintaxis de Flux v1
(`@fluxStyles`, `tailwind.config.js` con plugin de Flux): ese enfoque no aplica aquí.

## Regla de oro: usa el componente Flux, no HTML crudo

Antes de escribir `<button>`, `<input>`, `<select>`, `<table>` a mano, comprueba si existe un
componente Flux equivalente. Si existe, úsalo — no reinventes estilos con clases Tailwind sueltas.

Componentes más comunes en este stack:

| Necesidad | Componente Flux |
|---|---|
| Botón | `<flux:button>` |
| Campo de texto | `<flux:input>` |
| Select | `<flux:select>` + `<flux:select.option>` |
| Textarea | `<flux:textarea>` |
| Checkbox / grupo | `<flux:checkbox>` / `<flux:checkbox.group>` |
| Radio / grupo | `<flux:radio>` / `<flux:radio.group>` |
| Switch (toggle) | `<flux:switch>` |
| Tabla con datos | `<flux:table>` |
| Modal / diálogo | `<flux:modal>` |
| Pestañas | `<flux:tabs>` / `<flux:tab>` |
| Menú desplegable | `<flux:dropdown>` + `<flux:navmenu>` |
| Tooltip | `<flux:tooltip>` |
| Badge de estado | `<flux:badge>` |
| Avatar | `<flux:avatar>` |
| Toast / notificación | `<flux:toast>` (ver `@persist('toast')` en `layouts/app/header.blade.php`) |

## wire:model — binding de datos

Todos los componentes de formulario de Flux se enlazan igual que un input nativo de Livewire:

```blade
<flux:input wire:model="email" label="Email" />
<flux:checkbox wire:model="terms" />
<flux:switch wire:model.live="enabled" />
<flux:textarea wire:model="content" />
<flux:select wire:model="state" />
```

Componentes de agrupación también aceptan `wire:model` en el elemento raíz, no en cada opción:

```blade
<flux:checkbox.group wire:model="notifications">
    <flux:checkbox value="email" label="Email" />
    <flux:checkbox value="sms" label="SMS" />
</flux:checkbox.group>

<flux:radio.group wire:model="payment" variant="segmented">
    <flux:radio value="card" label="Tarjeta" />
    <flux:radio value="cash" label="Efectivo" />
</flux:radio.group>

<flux:tabs wire:model="activeTab">
    <flux:tab name="general">General</flux:tab>
    <flux:tab name="billing">Facturación</flux:tab>
</flux:tabs>
```

Usa `.live` solo cuando necesites reactividad inmediata (ej. filtros, contadores). Por defecto,
`wire:model` en Livewire v4 ya hace debounce razonable; no abuses de `.live` en inputs de texto
largos porque dispara un request por cada tecla.

## Formularios: flux:field + label + error

Para campos con label, descripción y error, envuelve en `<flux:field>` en vez de maquetar el
error a mano con `@error`:

```blade
<flux:field>
    <flux:label badge="Requerido">Nombre del cliente</flux:label>
    <flux:input wire:model="name" />
    <flux:error name="name" />
</flux:field>
```

`<flux:error name="name">` ya lee el mensaje de validación de Livewire — no dupliques con
`@error('name')` manual salvo que necesites un mensaje custom.

## Modal

```blade
<flux:modal name="edit-client" class="md:w-96">
    <div class="space-y-6">
        <flux:heading size="lg">Editar cliente</flux:heading>

        <flux:field>
            <flux:label>Nombre</flux:label>
            <flux:input wire:model="name" />
            <flux:error name="name" />
        </flux:field>

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" x-on:click="$flux.modal('edit-client').close()">Cancelar</flux:button>
            <flux:button variant="primary" wire:click="save">Guardar</flux:button>
        </div>
    </div>
</flux:modal>

<flux:button x-on:click="$flux.modal('edit-client').show()">Editar</flux:button>
```

Puedes enlazar el estado abierto/cerrado a una propiedad Livewire con `wire:model` en el modal
si necesitas controlarlo desde el backend (ej. abrir tras validación fallida).

## Tabla con Livewire (paginación + orden)

Patrón real para listados (ej. pedidos, clientes):

```blade
<flux:table :paginate="$this->orders">
    <flux:table.columns>
        <flux:table.column>Cliente</flux:table.column>
        <flux:table.column sortable :sorted="$sortBy === 'date'" :direction="$sortDirection" wire:click="sort('date')">
            Fecha
        </flux:table.column>
        <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">
            Estado
        </flux:table.column>
    </flux:table.columns>

    <flux:table.rows>
        @foreach ($this->orders as $order)
            <flux:table.row :key="$order->id">
                <flux:table.cell>{{ $order->customer }}</flux:table.cell>
                <flux:table.cell class="whitespace-nowrap">{{ $order->date }}</flux:table.cell>
                <flux:table.cell>
                    <flux:badge size="sm" :color="$order->status_color">{{ $order->status }}</flux:badge>
                </flux:table.cell>
            </flux:table.row>
        @endforeach
    </flux:table.rows>
</flux:table>
```

```php
#[Livewire\Attributes\Computed]
public function orders()
{
    return Order::query()
        ->tap(fn ($query) => $this->sortBy ? $query->orderBy($this->sortBy, $this->sortDirection) : $query)
        ->paginate(15);
}
```

## Estados de carga (wire:loading)

Flux no reemplaza `wire:loading` de Livewire; combínalos:

```blade
<flux:button wire:click="save" wire:loading.attr="disabled" wire:target="save">
    <span wire:loading.remove wire:target="save">Guardar</span>
    <span wire:loading wire:target="save">Guardando...</span>
</flux:button>
```

Para botones simples, `<flux:button>` ya muestra un spinner automático cuando está dentro de un
`wire:click` en curso — no necesitas maquetar el spinner manualmente salvo texto condicional.

## Dark mode

El layout base (`resources/views/layouts/app/header.blade.php`) ya trae `@fluxAppearance` en el
`<head>` y la clase `dark` fija en `<html>`. NO añadas un segundo sistema de dark mode manual.

Para dejar que el usuario elija apariencia:

```blade
<flux:radio.group variant="segmented" x-model="$flux.appearance">
    <flux:radio value="light" icon="sun">Claro</flux:radio>
    <flux:radio value="dark" icon="moon">Oscuro</flux:radio>
    <flux:radio value="system" icon="computer-desktop">Sistema</flux:radio>
</flux:radio.group>
```

Para togglear programáticamente: `x-on:click="$flux.dark = ! $flux.dark"`.

Al escribir clases Tailwind propias junto a componentes Flux, sigue el patrón de este repo:
`dark:` con el variant custom ya definido en `resources/css/app.css`
(`@custom-variant dark (&:where(.dark, .dark *));`) — no instales `darkMode` en un config JS,
aquí no existe.

## Accesibilidad

- Usa siempre `<flux:label>` (o el prop `label` de los inputs) en vez de un `<label>` suelto —
  Flux conecta `for`/`aria-labelledby` automáticamente.
- En iconos sin texto (ej. `<flux:button icon="magnifying-glass">`), añade el prop `label` para
  que quede como `aria-label` (ver ejemplo del buscador en `header.blade.php`).
- `<flux:tooltip>` complementa pero no sustituye un label accesible.
- `<flux:error>` ya asocia el mensaje al campo vía `aria-describedby`; no dupliques con `role="alert"` manual.

## Referencia

Documentación verificada vía Context7 (`/websites/fluxui_dev`) contra fluxui.dev/docs, julio 2026.
