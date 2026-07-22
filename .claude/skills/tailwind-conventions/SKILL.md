---
name: tailwind-conventions
description: Usar cuando el usuario escriba o modifique clases Tailwind CSS en archivos Blade/CSS de este proyecto. Fija convenciones de utilidades, orden de clases, tokens de color/espaciado y estructura de configuración CSS-first. Asume Tailwind CSS v4.1.x (NO v3, NO tailwind.config.js).
---

# Tailwind CSS v4 — Convenciones de este proyecto

Este proyecto usa **Tailwind CSS v4** (`tailwindcss ^4.0.7`, resuelto en `4.1.11`, vía
`@tailwindcss/vite`). La configuración es **CSS-first**: no existe ni debe crearse un
`tailwind.config.js`. Si ves instrucciones o código que asuma Tailwind v3
(`tailwind.config.js`, `@tailwind base/components/utilities`, `darkMode: 'class'` en JS),
son de una versión distinta y NO aplican aquí — ignóralas.

## Dónde vive la configuración

Todo el theme y las fuentes de contenido están en `resources/css/app.css`:

```css
@import 'tailwindcss';
@import '../../vendor/livewire/flux/dist/flux.css';

@source '../views';
@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../vendor/livewire/flux-pro/stubs/**/*.blade.php';
@source '../../vendor/livewire/flux/stubs/**/*.blade.php';

@custom-variant dark (&:where(.dark, .dark *));

@theme {
    --font-sans: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif, ...;
    --color-zinc-50: #fafafa;
    /* ... resto de la escala zinc ... */
    --color-accent: var(--color-neutral-800);
    --color-accent-content: var(--color-neutral-800);
    --color-accent-foreground: var(--color-white);
}
```

Reglas al tocar esto:

- **Nuevos design tokens** (colores, fuentes, breakpoints custom) van dentro del bloque `@theme`
  como variables `--color-*`, `--font-*`, `--breakpoint-*`, `--ease-*`. No los pongas en un
  archivo JS.
- **Nuevas rutas de contenido** (si añades una carpeta con Blade fuera de `resources/views`,
  o un paquete nuevo con sus propios stubs) se añaden con `@source '../ruta/*.blade.php'`,
  nunca en un array `content: []`.
- **Nuevos overrides globales de dark mode** van en `@layer theme { .dark { ... } }`, siguiendo
  el patrón ya usado para `--color-accent`.
- El variant `dark:` ya está definido vía `@custom-variant dark (&:where(.dark, .dark *));`.
  Úsalo tal cual (`dark:bg-zinc-800`, `dark:text-white`) — no redefinas el variant.

## Paleta

Usa la escala `zinc` ya definida en `@theme` para superficies, bordes y texto neutro
(`bg-zinc-50`, `border-zinc-200`, `dark:bg-zinc-900`, `dark:border-zinc-700`) — es la que ya
usa el layout base (`header.blade.php`). No mezcles `gray-*` o `slate-*` para lo mismo; sería
inconsistente con el resto de la UI.

Para acentos/CTA usa los tokens semánticos `accent` / `accent-content` / `accent-foreground`
en vez de un color Tailwind fijo (`bg-accent`, `text-accent-foreground`): así el acento
responde automáticamente a light/dark sin que tengas que escribir `dark:` cada vez.

## Orden de clases

Sigue el orden que aplica el plugin oficial `prettier-plugin-tailwindcss` (aunque no esté
instalado, escribe las clases ya en ese orden para minimizar diffs si se instala luego):

```
posición/layout → display → flex/grid → spacing (m/p) → sizing (w/h) → tipografía →
fondo/color → borde → efectos (shadow/opacity) → transición/animación → estado (hover:/focus:) →
responsive (sm:/lg:) → dark:
```

Ejemplo correcto (del propio repo, `header.blade.php:7`):

```blade
class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900"
```

Los variants de estado y responsive van **al final**, después de las utilidades base — nunca
intercalados.

## Utilidades a evitar

- No uses `!important` a mano (`class="!mt-4"` está bien puntualmente para pelear con un
  componente de Flux, pero no lo uses para maquetación normal — es señal de que falta
  especificidad real).
- No uses estilos inline (`style="..."`) salvo para valores dinámicos calculados en PHP
  (ej. un `width` porcentual desde una variable). Todo lo estático va en clases.
- No inventes espaciados arbitrarios (`mt-[13px]`) si existe un token de la escala estándar
  que se le acerque (`mt-3`, `mt-3.5`). Reserva `[valor-arbitrario]` para casos realmente
  puntuales (alinear con un asset externo, breakpoint exacto de diseño).

## Comprobación de contenido detectado

Si una clase Tailwind no se aplica en un `.blade.php`, antes de sospechar de un bug de Flux,
verifica que el archivo esté bajo una ruta cubierta por `@source` en `app.css` (por defecto
`resources/views/**` ya está cubierto vía `@source '../views'`). Archivos generados fuera de
`resources/` no se detectan automáticamente.

## Referencia

Documentación verificada vía Context7 (`/tailwindlabs/tailwindcss.com`), julio 2026. Config
real confirmada en `resources/css/app.css` de este repo.
