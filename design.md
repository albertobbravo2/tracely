# design.md — Sistema visual de Tracely

**Léelo antes de tocar cualquier cosa de UI** (Blade, Livewire, clases de Tailwind,
`resources/css/app.css`). Este fichero es la fuente de verdad de *cómo debe verse* Tracely.
Las convenciones de *sintaxis* siguen viviendo en `.claude/skills/` (`flux-ui-components`,
`tailwind-conventions`, `laravel13-conventions`, `ui-design-guidelines`); esto define la
dirección visual que esas convenciones deben producir.

El diseño de referencia está en [UI.pen](UI.pen) (pen.dev), en la sección
**«★ Tracely — Diseño final»**. Ahí están todas las pantallas y los modales ya maquetados.
Si una pantalla no existe todavía en código, míralas ahí antes de inventar.

## De dónde sale este diseño

`UI.pen` contiene cuatro exploraciones (`1.` a `4. Tracely Redesign`). La dirección elegida
es una mezcla deliberada, no una de las cuatro tal cual:

| Pieza | Viene de |
| --- | --- |
| Paleta completa, tipografía, tabla, KPIs, tarjetas | Rediseño **3** |
| Home / seguimiento público (hero, buscador, resultado, footer) | Rediseño **3** |
| Backoffice: Pedidos y Detalle de pedido | Rediseño **3** |
| Navbar (barra superior pública y de app) | Rediseño **1** |
| Sidebar: separador + bloque «Plataforma» (Ir a la web / Mis pedidos) | Rediseño **1** |
| Login, registro y demás pantallas de autenticación (split con panel de marca) | Rediseño **1** |

Los rediseños 2 y 4 quedan descartados. Sus variables (`t2-*`, `t4-*`, `a1-*`) siguen en el
`.pen` como histórico: **no las uses**.

## Color

Todos los colores son semánticos y tienen valor en claro y en oscuro. Los nombres de esta
tabla son literalmente los que se usan como utilidades de Tailwind (`bg-surface`,
`text-ink-muted`, `border-line-strong`…) y están definidos en el bloque `@theme` de
`resources/css/app.css`. En el `.pen` son las variables `t3-*`.

| Token | Claro | Oscuro | Para qué |
| --- | --- | --- | --- |
| `canvas` | `#F5F7FC` | `#080B12` | Fondo de página |
| `surface` | `#FFFFFF` | `#111826` | Tarjetas, tablas, modales, navbar, sidebar |
| `surface-2` | `#EEF2F9` | `#18202F` | Fondos secundarios: cabecera de tabla, chips, avatares |
| `line` | `#E1E7F1` | `#232C3D` | Bordes y separadores por defecto |
| `line-strong` | `#CBD5E6` | `#313C52` | Borde de inputs y controles |
| `ink` | `#0C1220` | `#EEF2FA` | Texto principal y títulos |
| `ink-2` | `#3D4863` | `#B9C4D9` | Texto secundario, etiquetas de navegación |
| `ink-muted` | `#6B7793` | `#7C89A3` | Texto terciario, placeholders, metadatos |
| `primary` | `#2549E6` | `#7D97FF` | Marca, botones principales, enlaces, estado activo |
| `primary-hover` | `#1B39C4` | `#98ADFF` | Hover del primario y final del degradado de marca |
| `primary-soft` | `#E7ECFF` | `#1B2440` | Fondo del item activo del sidebar, chips de marca |
| `on-primary` | `#FFFFFF` | `#0A1024` | Texto/icono sobre `primary` |
| `ok` / `ok-soft` | `#047857` / `#DCFAEC` | `#4ADE9B` / `#0C2B22` | Entregado, confirmaciones |
| `info` / `info-soft` | `#1D5FD6` / `#E3EEFF` | `#78B4FF` / `#0F2440` | En tránsito |
| `aduana` / `aduana-soft` | `#6D3BD9` / `#EFE7FE` | `#B99BFF` / `#211838` | En aduana |
| `warn` / `warn-soft` | `#B45309` / `#FDF0DA` | `#FBBF5C` / `#2E2110` | Avisos, retrasos, deltas negativos |
| `danger` / `danger-soft` | `#C81E37` / `#FDE8EC` | `#FF8A9B` / `#33131A` | Incidencia, borrado, error |
| `idle` / `idle-soft` | `#5A6580` / `#EDF0F6` | `#98A4BC` / `#1C2434` | Pendiente, estados sin color propio |
| `shadow` | `#0C122014` | `#00000066` | Color de las sombras |

Reglas:

- **Nunca escribas un hex a mano en una vista.** Si necesitas un color que no está aquí, el
  color está mal elegido o falta un token: dilo antes de añadirlo.
- El par `x` / `x-soft` es siempre texto-sobre-fondo. No los cruces (nada de `ok` sobre
  `danger-soft`).
- **No dupliques con `dark:`.** Cada token se redefine bajo `.dark` en `app.css`, así que
  `bg-surface` ya cambia solo de tema. Un `dark:` solo se justifica si hace algo que el token
  no cubre (por ejemplo el fondo blanco real que necesita el QR de 2FA para ser legible).
- La paleta antigua (`azul-*`, `ok-fuerte`/`ok-claro`/`ok-fondo`, `alerta-*`, `gris-*`,
  `blanco`, `brand-navy`) **ya no existe**: se retiró de `app.css` cuando se terminó la
  migración. Si te la encuentras citada en algún sitio, es documentación vieja.

### Estados de envío

Mapeo cerrado del enum `ShipmentStatus`. Es el que usa el diseño y no debe variar entre
pantallas:

| `ShipmentStatus` | Etiqueta | Token |
| --- | --- | --- |
| `entregado` | Entregado | `ok` sobre `ok-soft` |
| `en_transito` | En tránsito | `info` sobre `info-soft` |
| `en_aduana` | En aduana | `aduana` sobre `aduana-soft` |
| `incidencia` | Incidencia | `danger` sobre `danger-soft` |
| `pendiente` | Pendiente | `idle` sobre `idle-soft` |

Cualquier estado no reconocido cae en `idle`.

## Tipografía

- **Instrument Sans** para todo el producto (ya es `--font-sans` del proyecto).
- **Instrument Serif** solo como display, y solo en la home pública: el titular del hero
  («Rastrea tu paquete»). No la metas en el backoffice.
- **JetBrains Mono** para números de guía (`tracking_number`) cuando aparecen como dato
  destacado — el título del detalle de pedido, por ejemplo. En tablas basta la sans.

Escala usada en el diseño: 11 px (etiquetas de sección del sidebar, con `letter-spacing`
amplio y mayúsculas) · 12–13 px (metadatos, badges) · 14–14.5 px (cuerpo, celdas, items de
navegación) · 16 px (título de modal) · 20 px (título de tarjeta) · 30 px (título de auth) ·
38–46 px (títulos de página y hero).

Los títulos grandes llevan `letter-spacing` negativo (−0.8 a −1.4). El texto largo, altura de
línea ~1.55.

## Forma, sombra y espaciado

- Radios: **10 px** controles (botones, inputs, items de navegación), **12–14 px** tarjetas y
  modales, **99 px** píldoras (badges, avatares, chip de usuario).
- Bordes de 1 px en `line`; los inputs usan `line-strong`.
- Sombras, solo dos: elevación de botón primario (`0 4 12`) y elevación de modal
  (`0 8 24`), ambas con el color `shadow`. Nada de sombras decorativas.
- Ritmo de espaciado en múltiplos de 4. Padding habitual: 10–12 px en controles, 16–20 px en
  tarjetas, 24 px en modales, 32–40 px en el contenido de página.
- No envuelvas cada elemento en su propia tarjeta. Una tarjeta es un contenedor con función
  real, no una forma de separar visualmente.

## Patrones de componente

### Navbar (rediseño 1)

Barra de 63 px: fondo `surface`, borde inferior de 1 px, padding `[14, 32]`, marca a la
izquierda y **todo lo demás a la derecha** (no hay enlaces centrados). La marca es un
cuadrado de 30 px con radio 9 en `primary`, icono `radar` en `on-primary`, y el wordmark
«Tracely» a 17 px/700.

Los items de navegación son píldoras de radio 9 con icono de 16 px + etiqueta de 14 px: en
reposo, transparente con icono `ink-muted` y texto `ink-2`; activo, fondo `primary-soft` con
icono y texto en `primary` a peso 600. A la derecha del grupo, el conmutador de tema (34 px,
`surface-2`, icono `sun-moon`) y luego:

- **Público (sin sesión):** botón «Iniciar sesión» en `primary`.
- **App (con sesión):** píldora de usuario con avatar de 28 px, nombre a 13.5 px/600 y
  chevron, sobre `surface-2` con borde.

### Sidebar del backoffice (rediseño 3 + bloque «Plataforma» del rediseño 1)

252 px de ancho, fondo `surface`, borde derecho de 1 px, padding `[20, 16]`, disposición
vertical con separación de 22 px. De arriba abajo:

1. Marca (igual que la del navbar, wordmark a 18 px). Debajo del wordmark, en
   11.5 px `ink-muted`, el nombre de la empresa de quien mira — es el recorte con el que va
   a ver todos los listados. Solo para `agente` y `administrador`: el superadministrador está
   por encima de las empresas y no tiene ninguna que enseñar.
2. `GENERAL` — Pedidos, Historial.
3. `GESTIÓN` — Documentos, Usuarios, Compañías (Compañías solo para superadministrador).
4. **Separador horizontal de 1 px.**
5. `PLATAFORMA` — Ir a la web (`globe`), Mis pedidos (`map-pin`).
6. Espaciador flexible.
7. Tarjeta de usuario abajo: fondo `surface-2`, radio 12, avatar redondo de 34 px en
   `primary`, nombre a 13.5 px/600, rol a 11.5 px en `ink-muted`, icono `chevrons-up-down`.

El `.pen` dibuja además un «Panel» en `GENERAL` y una «Ayuda» en `PLATAFORMA`. Ninguna de las
dos existe como página: `backoffice.index` es solo un redirect a Pedidos y no hay ruta de
ayuda. Están fuera del sidebar a propósito — antes que un enlace a ninguna parte, no hay
enlace. Si algún día se crean esas pantallas, ese es su sitio.

El bloque «Plataforma» va **debajo de las páginas y separado por el separador**: son salidas
de la app, no secciones del backoffice. Los títulos de sección son 11 px/600 en `ink-muted`,
mayúsculas, `letter-spacing` 1.

**En el sidebar no hay sección de cuenta.** Ajustes, perfil y cerrar sesión cuelgan del menú
que abre la tarjeta de usuario de abajo, y no se duplican como items de navegación. Por eso
las pantallas de Ajustes no marcan ningún item del sidebar como activo.

Item de navegación: radio 10, padding `[10, 12]`, icono de 18 px + etiqueta de 14.5 px.
Activo = fondo `primary-soft`, icono y texto en `primary` a peso 600. Un item puede llevar un
badge de conteo a la derecha (píldora `primary` con texto `on-primary` a 11 px).

**Solo hay un item activo en toda la barra**, y es el de la página actual.

### Página de backoffice

Sidebar + zona principal. La principal lleva, en este orden: breadcrumb en `ink-muted` →
cabecera con título de 38 px y botones de acción a la derecha (secundarios con borde,
primario en `primary`) → fila de tarjetas KPI → barra de filtros y búsqueda → tabla →
paginación.

- **KPI:** tarjeta `surface` con borde, etiqueta en `ink-muted`, cifra grande, y un delta con
  icono de tendencia en `ok` o `danger`.
- **Tabla:** cabecera sobre `surface-2` con etiquetas de 12 px en `ink-muted`; filas separadas
  por 1 px de `line`; número de guía en `primary`; estado como badge según el mapeo de
  arriba. Nada de bordes verticales.
- **Badge de estado:** píldora de radio 99, padding `[3, 8]`, texto de 12 px/600 sobre el
  `-soft` correspondiente.

### Detalle de pedido

Dos columnas: la ancha con el progreso del envío (stepper horizontal con nodos en `primary`
para lo completado y `line` para lo pendiente) y el historial como línea de tiempo con
icono por evento coloreado según su estado; la estrecha con «Datos del envío» (lista
etiqueta/valor, etiqueta en `ink-muted` a la izquierda y valor en `ink` a la derecha),
«Clientes vinculados» y «Documentos».

### Formularios

Campo = etiqueta de 13.5 px/600 en `ink` + caja de radio 11, fondo `surface`, borde
`line-strong`, padding `[12, 14]`, placeholder en `ink-muted` e icono opcional a la derecha en
`ink-muted`. Botón primario: `primary` / `on-primary`, radio 11, padding `[14, 16]`, peso 600.
Botón secundario: `surface` con borde `line-strong` y texto `ink`. Botón destructivo:
`danger` con texto blanco.

### Autenticación (rediseño 1)

Pantalla partida en dos, sin scroll:

- **Izquierda, 596 px:** panel de marca con degradado lineal de `primary` a `primary-hover`.
  Marca arriba, titular de 44 px/700 en `on-primary` en medio, pie de copyright abajo al 70 %
  de opacidad.
- **Derecha:** tarjeta de 420 px centrada — título de 30 px/700, subtítulo en `ink-2`, y el
  formulario. En login y registro, encima del formulario va el botón de passkey con borde y
  un separador con el texto «O CONTINÚA CON TU CORREO» (10.5 px/600 en `ink-muted`). El pie
  enlaza a la pantalla contraria en `primary` a peso 700.

### Modales

Fondo `surface`, radio 14, borde de 1 px, padding 24, separación vertical de 24 px y sombra
de modal. Título de 16 px/600, descripción de 14 px en `ink-muted`, y pie alineado a la derecha
con «Cancelar» sin relleno (texto `ink-2`) y la acción principal a su derecha — en
`primary`, o en `danger` si es destructiva. El ancho lo fija el contenido: 384 px para
confirmaciones, 448–576 px para formularios.

### Páginas de error

Todas salen del mismo componente, `x-error-page` (`components/error-page.blade.php`), y las
vistas de `resources/views/errors/` solo le pasan código, título y explicación. Marca arriba,
y debajo una tarjeta `surface` centrada de 448 px: icono en círculo, «ERROR ␣código» en
11 px/600 versalitas `ink-muted`, título de 30 px/700 y descripción en `ink-2`. Abajo,
«Volver al inicio» en `primary` y, con sesión, «Ir a mis pedidos» como secundario.

El tono del icono lo decide el código: 404 en `idle` (no es una avería), 401/403/419/429 en
`warn`, 503 en `info` y el resto en `danger`. Los iconos van como **SVG en línea, no como
`flux:icon`**: esta vista tiene que pintarse justo cuando algo se ha roto, así que cuanto
menos dependa del resto de la app, mejor. `errors/minimal.blade.php` recoge los códigos sin
vista propia para que ninguno caiga en la plantilla en inglés del framework.

Ojo con una cosa de Laravel: en un 404 de ruta inexistente no llega a correr el middleware de
sesión, así que `@auth` es falso y el botón de «Ir a mis pedidos» no aparece aunque haya
sesión. Es lo esperado, no un fallo de la vista.

## Cómo se traduce a código

- Tailwind v4, **CSS-first**: todos los tokens viven en el bloque `@theme` de
  `resources/css/app.css`. No hay `tailwind.config.js` y no se añade.
- Cada token de la tabla de arriba es un `--color-<token>` y se usa como `bg-surface`,
  `text-ink-muted`, `border-line-strong`, etc. Los valores de modo oscuro se redefinen bajo
  `.dark` en `@layer theme`, que es el patrón que el fichero ya usa.
- Reutiliza los componentes de **Flux UI** y estos tokens. Nada de `style="..."` ni de
  colores arbitrarios: si Flux no da el color correcto, ajústalo con los tokens del tema
  acotando por contexto, como ya se hace en `app.css` para `.backoffice`.
- Los textos van en español y envueltos en `__()`, igual que hasta ahora.
