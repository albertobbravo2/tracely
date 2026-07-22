---
name: ui-design-guidelines
description: Usar cuando el usuario diseñe o revise la apariencia visual de una pantalla o componente (jerarquía, tipografía, espaciado, responsive). Complementa a flux-ui-components y tailwind-conventions con criterio de diseño, no de sintaxis. Úsala para decidir CÓMO debe verse algo, no solo con qué componente construirlo.
---

# Guías de diseño visual de este proyecto

Esta skill fija criterio de diseño. Para sintaxis de componentes ve a `flux-ui-components`;
para clases y tokens Tailwind ve a `tailwind-conventions`. Úsalas juntas.

## Corrección sobre la fuente

La fuente base real de este proyecto (definida en `resources/css/app.css`, `--font-sans`) es
**`Instrument Sans`**, no Inter. Usa siempre `font-sans` (el token del `@theme`) para heredarla
— no fuerces `font-['Inter']` ni añadas Inter como fuente nueva sin que el usuario lo pida
explícitamente, porque rompería la identidad tipográfica ya establecida.

## Jerarquía tipográfica

No uses tamaños de fuente al azar. Escala recomendada (Tailwind + `font-sans` del theme):

| Uso | Clases |
|---|---|
| Título de página | `text-2xl font-semibold` o `text-3xl font-semibold` |
| Título de sección/card | `text-lg font-semibold` |
| Cuerpo de texto | `text-sm` (por defecto en Flux) o `text-base` |
| Texto secundario/metadata | `text-sm text-zinc-500 dark:text-zinc-400` |
| Etiqueta de campo | delegar a `<flux:label>`, no maquetar a mano |

Reglas:

- Un solo `text-2xl`/`text-3xl` por vista como título principal — si hay dos, no hay jerarquía.
- No mezcles `font-bold` y `font-semibold` para el mismo nivel de importancia en la misma
  pantalla; elige uno y sé consistente.
- Prefiere `<flux:heading size="lg">` sobre un `<h1 class="text-2xl font-semibold">` a mano
  cuando el contexto ya está dentro de un layout Flux — mantiene tamaños consistentes con el
  resto de la librería.

## Espaciado

- Usa la escala estándar de Tailwind (`gap-2`, `gap-4`, `gap-6`, `p-4`, `p-6`) — no arbitrarios.
- Espaciado entre secciones de una página: `space-y-6` o `space-y-8` en el contenedor padre, no
  `mb-6` repetido en cada hijo.
- Dentro de un formulario, usa `space-y-6` entre `<flux:field>` y dentro de una fila con
  varios campos, `gap-4` en un `flex`/`grid`.
- Padding de card/contenedor: `p-6` para contenido principal, `p-4` para elementos densos
  (filas de tabla, items de lista).

## Responsive-first

Escribe primero el layout para móvil (sin prefijo), y añade `sm:`/`lg:` para expandir, nunca al
revés:

```blade
{{-- Correcto: columna en móvil, fila desde lg --}}
<div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">

{{-- Evitar: asumir escritorio y "arreglar" móvil después --}}
<div class="flex items-center justify-between max-lg:flex-col">
```

El layout base de este proyecto ya oculta/colapsa navegación en móvil vía
`flux:sidebar.toggle` + clases `max-lg:hidden` (ver `header.blade.php`) — sigue ese mismo
patrón (`max-lg:hidden` para ocultar en móvil lo que solo tiene sentido en desktop) en vez de
introducir un sistema de breakpoints distinto.

## Criterios "esto parece plantilla genérica" → no

Señales de que una pantalla necesita más trabajo antes de darla por terminada:

1. **Iconos y texto sin relación con el dominio real** (ej. "Item 1", "Item 2", iconos random
   sin conexión con pedidos/clientes/agentes de Tracely). Usa nombres y datos del dominio real
   de la app.
2. **Cards idénticas repetidas sin datos que las diferencien visualmente** (mismo badge, mismo
   color, mismo estado en todas) — si el estado varía en los datos reales, debe variar en la UI
   (`:color="$order->status_color"` como en el ejemplo de tabla de `flux-ui-components`).
3. **Ausencia total de estados vacíos/carga/error**: si una lista puede estar vacía, diseña el
   estado vacío (icono + texto + acción), no dejes la tabla en blanco.
4. **Contraste plano**: todo el texto al mismo `zinc-500` sin jerarquía — el texto principal
   debe leerse claramente más "fuerte" (`zinc-900`/`white`) que el secundario (`zinc-500`).
5. **Botones de acción sin distinción de importancia**: todas las acciones como `variant="ghost"`
   o todas como `variant="primary"` — la acción principal de la pantalla debe destacar
   (`primary`), el resto debe ser `ghost`/`subtle`.
6. **Espaciado inconsistente entre secciones similares** de la misma pantalla o entre pantallas
   del mismo módulo — reutiliza los valores de la tabla de espaciado de arriba en vez de ajustar
   "a ojo" cada vez.

Antes de dar una pantalla por terminada, repásala contra esta lista.

## Dark mode como requisito, no como extra

Este proyecto fuerza `class="dark"` en `<html>` (ver `header.blade.php:2`) y expone control de
apariencia vía `$flux.appearance` (ver `flux-ui-components`). Cualquier color que añadas fuera
de los componentes Flux (que ya vienen preparados) necesita su contraparte `dark:` explícita —
no asumas que "se ve bien en claro" es suficiente.

## Referencia

Basado en los tokens reales de `resources/css/app.css` y el layout de
`resources/views/layouts/app/header.blade.php` de este repo, más convenciones de Flux UI
verificadas vía Context7 (`/websites/fluxui_dev`), julio 2026.
