# AGENTS.md

Guidance for AI coding agents working in this repository.

## Project

Tracely is a Laravel 13 + Livewire 4 shipment-tracking app ("rastreo de envíos"). Domain
vocabulary, permission names, route comments, and test names are in Spanish; PHP identifiers
and structure follow standard English Laravel conventions.

## Reglas del proyecto

- **Flujo de la API**: request → ruta (`routes/api.php`) → controlador en
  `app/Http/Controllers/Api` → modelo Eloquent. No metas una capa de
  servicios/repositorios/actions entre controlador y modelo salvo que se pida explícitamente
  — ver "API conventions" más abajo para el detalle de cómo está montado ese flujo hoy.
- **La API es de backoffice**: toda la API (`routes/api.php`) es hoy la forma en que
  agentes/administradores/superadministradores gestionan pedidos, empresas, usuarios y
  documentos — no es una API pública para integraciones de terceros. La única excepción
  intencional es la consulta pública de seguimiento (rama sin autenticar de `shipments.show`,
  ver "Auth & authorization"). Al añadir un endpoint nuevo, asume que es para backoffice
  (gated por `permission:`) salvo que se indique lo contrario.
- **Lee [design.md](design.md) antes de tocar la UI**: es obligatorio antes de modificar
  cualquier vista Blade/Livewire, clase de Tailwind o `resources/css/app.css`. Define la
  paleta semántica, la tipografía, el mapeo de `ShipmentStatus` a color y los patrones de
  navbar, sidebar, tabla, formulario y modal. El diseño de referencia del que sale vive en
  [UI.pen](UI.pen), sección «★ Tracely — Diseño final». Si lo que vas a hacer contradice
  `design.md`, dilo antes de escribir código.
- **Sin estilos inline por defecto**: al maquetar o ajustar diseño, reutiliza los componentes
  Flux y los tokens del tema definidos en `resources/css/app.css` en vez de añadir
  `style="..."` o clases/colores arbitrarios nuevos. Los tokens vigentes son los de
  `design.md`; la paleta antigua (`azul`/`ok`/`alerta`/`gris`, `brand-navy`) está en proceso
  de sustitución y no debe usarse en pantallas ya migradas. Solo te apartas de esto si el
  usuario lo pide explícitamente.
- **No toques la estructura de los datos sin que se pida**: no cambies migraciones, relaciones
  Eloquent, `casts`, los atributos `#[Fillable]`/`#[Hidden]`/`#[Appends]`, ni el enum
  `ShipmentStatus`, como efecto colateral de otra tarea. Si lo que se pide parece requerir un
  cambio de esquema o de relaciones, dilo y confirma antes de tocarlo — no lo des por incluido
  en el alcance.

## Environment: this project runs in Sail (Docker)

**Always run PHP/Composer/npm commands through Sail, never directly on the host.** The host
and the container don't share the same runtime paths — invoking `php artisan` or `composer`
straight from the host corrupts Livewire's compiled view cache in `storage/framework`.

```bash
./vendor/bin/sail up -d          # start php, pgsql, redis containers
./vendor/bin/sail artisan ...    # any artisan command
./vendor/bin/sail composer ...   # any composer command
./vendor/bin/sail npm ...        # any npm command
```

If the view cache ever does get corrupted (e.g. after an accidental host-side command), clear
it with `./vendor/bin/sail artisan view:clear` (and `optimize:clear` for good measure).

The app serves at `http://localhost:8080` (`APP_PORT` in `.env`). The database is Postgres
(`pgsql` service; hostname `pgsql` inside the Docker network), and Redis backs cache/queue.

## Commands

- Start the stack: `./vendor/bin/sail up -d`
- Dev servers (serve + queue + logs + vite, concurrently): `./vendor/bin/sail artisan dev`
- Lint, auto-fix (Pint): `./vendor/bin/sail composer lint`
- Lint, check only (CI mode): `./vendor/bin/sail composer lint:check`
- Static analysis (Larastan, level 7): `./vendor/bin/sail composer types:check`
- Full check suite (config:clear + lint:check + types:check + tests): `./vendor/bin/sail composer test`
- Tests only: `./vendor/bin/sail artisan test`
- Single test: `./vendor/bin/sail artisan test --filter=test_method_name` or
  `./vendor/bin/sail artisan test tests/Feature/Api/ShipmentTest.php`
- Build frontend assets: `./vendor/bin/sail npm run build`

Tests run against the `testing` Postgres database that Sail's `pgsql` container provisions
automatically (see [compose.yaml](compose.yaml)) — Sail must be up for `artisan test` to work.
Feature tests use plain PHPUnit (`test_*` methods, not Pest), typically with `RefreshDatabase`
and, for anything permission-gated, `$this->seed(RolesAndPermissionsSeeder::class)` in
`setUp()` — see [tests/Feature/Api/ShipmentTest.php](tests/Feature/Api/ShipmentTest.php) for
the pattern.

## Architecture

### Domain

A `Company` has many `User`s and `Shipment`s. A `Shipment` (envío) is identified by its
`tracking_number`, which is the route-model-binding key used everywhere in the API
(`{shipment:tracking_number}`, not `{shipment}`). It has a `sender` (the agent `User` who
registered it), a `status` (`ShipmentStatus` string-backed enum: `pendiente` / `en_transito` /
`en_aduana` / `entregado` / `incidencia`), a `HasMany` of `ShipmentHistory` timeline events, a
`HasMany` of `Document`s, and a separate `BelongsToMany` of end-user `User`s — customers who
added that tracking number to their own account via the `shipment_user` pivot. Don't confuse
that pivot relation with `sender()`: one is who registered the shipment, the other is who is
watching it.

### Auth & authorization

- Roles (Spatie `laravel-permission`): `agente`, `administrador`, `superadministrador`. There
  is no `cliente` role — end customers are either unauthenticated or authenticated users with
  no role at all. They only ever act on their own pivot row
  ([ShipmentUserController](app/Http/Controllers/Api/ShipmentUserController.php)), which is
  deliberately left without a `permission:` middleware so customers aren't locked out of their
  own feature.
- Permissions are Spanish strings (`"ver pedido"`, `"crear empresa"`, ...), seeded idempotently
  in [database/seeders/RolesAndPermissionsSeeder.php](database/seeders/RolesAndPermissionsSeeder.php)
  via `firstOrCreate`/`syncRoles`, safe to re-run without `migrate:fresh`. Company-management
  permissions are `superadministrador`-only; everything else goes to all three roles.
- Authorization is enforced **at the route level**, not in controllers or
  `FormRequest::authorize()`: every protected route in [routes/api.php](routes/api.php) carries
  `->middleware('permission:<name>')`, and every `FormRequest::authorize()` just returns `true`
  (with a comment pointing back at the route). There are no Policy classes yet — if you need
  resource-based authorization (e.g. "only the sender can edit their own shipment") rather than
  flat permission checks, that's the gap to fill.
- API auth is Sanctum, mounted with `statefulApi()` (in [bootstrap/app.php](bootstrap/app.php))
  so same-origin requests carrying the app's own session cookie are recognized by the `sanctum`
  guard without a token; external/token clients authenticate via `Authorization: Bearer`.
- `shipments.show` (public tracking lookup) sits outside the `auth:sanctum` group and branches
  its response on whether a Sanctum user is present: unauthenticated requests get a whitelisted
  subset of fields, authenticated requests get the full model plus `histories`. It's also the
  only route with a dedicated rate limiter (`show-shipment`, 3 req/s keyed by user id or IP —
  see `AppServiceProvider::configureRateLimiting()`).

### API conventions

Controllers in `app/Http/Controllers/Api` are plain resource controllers returning
`response()->json(...)` directly — no API Resource classes. Listings use `Model::paginate(15)`.
Validation is a `FormRequest` where one exists (`Store`/`UpdateShipmentRequest`), otherwise
inline `$request->validate([...])` in the controller (Document, ShipmentHistory, Company,
User). Match whichever pattern the controller you're touching already uses.

Models declare `fillable`/`hidden`/`appends` via PHP attributes on the class
(`#[Fillable([...])]`, `#[Hidden(...)]`, `#[Appends(...)]`) instead of protected properties —
this is the Laravel 13 convention used throughout `app/Models`; keep using it for new models.

### The searchfield component calls the API over HTTP, not Eloquent

[resources/views/livewire/⚡searchfield.blade.php](resources/views/livewire/⚡searchfield.blade.php) —
the public tracking search on the homepage — looks up shipments by making a real HTTP request
to its own `shipments.show` API route (`Http::baseUrl(config('services.internal_api.url'))`)
rather than querying `Shipment` directly. This is deliberate: it lets the Livewire component
reuse the API's own public-vs-authenticated visibility rules (above) instead of re-implementing
them. Because the request is server-to-server it doesn't carry the browser session, so it mints
a 1-minute-lived Sanctum token for the logged-in user (if any) before the call and deletes it in
a `finally` block regardless of outcome. `services.internal_api.url` reads `APP_INTERNAL_URL`
(separate from `APP_URL`) because inside the Sail container the app listens on port 80, while
`APP_URL` holds the host-published port (8080) — unreachable from inside the container. Follow
this same "call our own API" pattern for any other component that needs shipment data shaped by
auth state, rather than querying `Shipment::` directly from Livewire.

### Los envíos entregados se cachean en Redis

`ShipmentController::show()` (la consulta de seguimiento, pública y limitada por
`show-shipment`) primero mira en Redis (`Cache::store('redis')`) y solo cae a Postgres si no
hay nada cacheado. Por eso la ruta `shipments.show` ya no usa binding implícito
(`{shipment}`, no `{shipment:tracking_number}`): el binding automático habría consultado
Postgres antes de que el controlador pudiera mirar la caché primero.

Lo que se cachea, y cuándo, lo decide [ShipmentObserver](app/Observers/ShipmentObserver.php)
(enganchado al modelo vía `#[ObservedBy]`, no en un ServiceProvider): solo los envíos con
`status` `entregado` — es el único estado que ya no cambia — bajo la clave
`ShipmentObserver::cacheKey($tracking_number)`. Si el estado deja de ser `entregado`, o el
envío se borra, el Observer limpia esa entrada. El payload cacheado es el envío completo
(incluye `histories`); el controlador aplica el mismo recorte público/privado sobre el dato
cacheado que ya aplicaba sobre el dato de base de datos, así que un hit de caché nunca expone
a un usuario sin sesión más de lo que vería sin caché.

Dos cosas a tener en cuenta si tocas esto:

- Un fallo de Redis (conexión caída, etc.) nunca debe tumbar la consulta pública ni bloquear
  un `save()`: tanto la lectura en el controlador como la escritura/borrado en el Observer
  están en `try/catch`, cayendo a Postgres o simplemente no cacheando si Redis no responde.
- El caché se recalcula cuando se guarda el `Shipment`, no cuando se crea un `ShipmentHistory`
  suyo. Si añades un historial a un envío ya `entregado` sin volver a guardar el `Shipment`,
  ese historial nuevo no estará en la copia cacheada hasta el siguiente `save()` del envío.
  No hay Observer en `ShipmentHistory` para esto todavía — es una limitación conocida, no un
  descuido.

### Frontend stack

Livewire 4 (single-file components preferred: `new class extends Component { ... }` inline in
the `.blade.php`), Flux UI 2 (paid component library — CI authenticates the composer repo via
`FLUX_USERNAME`/`FLUX_LICENSE_KEY` secrets), Tailwind 4 with CSS-first config — all theme
tokens live in [resources/css/app.css](resources/css/app.css); there is no `tailwind.config.js`.
Project-specific conventions for these are written up in `.claude/skills/`
(`laravel13-conventions`, `flux-ui-components`, `tailwind-conventions`, `ui-design-guidelines`).
Claude Code loads these automatically when relevant; if you're a different agent, read them
directly — they're plain Markdown, not Claude-specific.

Those skill docs cover *syntax*; the *visual direction* — palette, typography, status colors
and component patterns — lives in [design.md](design.md), which is required reading before
any UI change.

One thing worth knowing that isn't spelled out in either: beyond the `zinc` neutral scale,
`app.css` still defines the older custom brand palette (`azul-*`, `ok*`, `alerta*`, `gris-*`,
`blanco`, `brand-navy`) that parts of the product UI (e.g. the searchfield result card) use
for shipment-status semantics — `ok` for entregado, `alerta` for incidencia, `azul` for
en_transito/en_aduana, `gris` for pendiente/default/unrecognized. That palette is being
replaced by the semantic tokens in `design.md` (which splits en_transito and en_aduana into
distinct colors). In a view that hasn't been migrated yet, match the surrounding palette
rather than mixing the two mid-file.

There's no `resources/lang` directory — `__()` wraps hardcoded Spanish strings for
future-proofing; it isn't backed by an active translation table today.
