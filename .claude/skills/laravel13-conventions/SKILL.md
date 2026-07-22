---
name: laravel13-conventions
description: Usar cuando el usuario cree o modifique componentes Livewire, modelos, jobs, form requests o lógica de validación/autorización en este proyecto Laravel. Fija estructura de componentes Livewire, uso de atributos PHP y patrones de Laravel 13. Asume Laravel 13.17+ (PHP 8.3+) y Livewire v4.3.x.
---

# Laravel 13 + Livewire v4 — Convenciones de este proyecto

Stack real instalado (`composer.lock`): `laravel/framework ^13.17`, `livewire/livewire ^4.1`
(resuelto en `4.3.3`), PHP `^8.3`. Si alguna fuente menciona Livewire v3
(`class extends Component` en archivo `.php` separado como único formato, sin single-file
components), trátalo como referencia histórica, no como el patrón por defecto aquí.

## Componentes Livewire: single-file por defecto

Livewire v4 soporta tres formatos. **Para componentes nuevos, usa single-file** salvo que el
equipo ya tenga el patrón class-based establecido en esa parte del código:

```php
<?php // resources/views/livewire/⚡order-list.blade.php

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\Order;

new class extends Component {
    public string $sortBy = 'date';
    public string $sortDirection = 'desc';

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    #[Computed]
    public function orders()
    {
        return Order::query()
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(15);
    }
};
?>

<div>
    {{-- Blade de la tabla aquí, ver skill flux-ui-components --}}
</div>
```

El emoji `⚡` en el nombre de archivo es opcional (configurable) — sigue la convención que ya
exista en `resources/views/livewire/` antes de decidir si lo usas.

Usa **class-based components** (`php artisan make:livewire NombreComponente --class`, que
genera `app/Livewire/NombreComponente.php` + vista separada) solo si el componente es grande,
se reutiliza, o el resto del módulo ya sigue ese patrón.

## Propiedades computadas

Para datos derivados que se recalculan en cada render (listados, agregados), usa
`#[Computed]` en vez de recalcular en el `render()` o duplicar la query en Blade:

```php
#[Livewire\Attributes\Computed]
public function orders()
{
    return Order::query()->paginate(15);
}
```

Se accede en Blade como `$this->orders` (no `$this->orders()`).

## Validación con atributos

Usa `#[Validate]` sobre la propiedad en vez de un array `$rules` separado — mantiene la regla
junto al dato que valida:

```php
use Livewire\Attributes\Validate;

class CreateOrder extends Component
{
    #[Validate('required|min:3')]
    public string $customerName = '';

    #[Validate('required|email')]
    public string $customerEmail = '';

    public function save(): void
    {
        $this->validate();

        Order::create([
            'customer_name' => $this->customerName,
            'customer_email' => $this->customerEmail,
        ]);
    }
}
```

`#[Validate]` valida automáticamente en cada actualización de la propiedad, pero **llama
siempre a `$this->validate()` antes de tocar la base de datos** — es el guard real contra
enviar datos inválidos vía una acción directa.

En Blade, muestra los errores con `<flux:error name="customerName" />` (ver skill
`flux-ui-components`), no con `@error` manual salvo un caso custom.

## Autorización

No confíes solo en que un botón esté oculto en la UI — autoriza también en el método que
ejecuta la acción, usando Policies:

```bash
php artisan make:policy OrderPolicy --model=Order
```

```php
// app/Policies/OrderPolicy.php
class OrderPolicy
{
    public function update(User $user, Order $order): bool
    {
        return $user->id === $order->user_id || $user->can('gestionar pedido');
    }
}
```

```php
// dentro del componente Livewire
public function save(): void
{
    $this->authorize('update', $this->order);
    // ...
}
```

Este proyecto ya usa Spatie Permission (ver commits `PERMISSIONS` en el historial) con roles
`cliente`, `agente`, `admin`. Combina la Policy con `$user->can('permiso')` cuando la regla
dependa de un permiso Spatie en vez de (o además de) propiedad del recurso.

Para tests, verifica el caso negativo explícitamente:

```php
Livewire::actingAs($otherUser)
    ->test(OrderEdit::class, ['order' => $order])
    ->set('customerName', 'Hacked')
    ->call('save')
    ->assertForbidden();
```

## Atributos PHP de Laravel 13

Laravel 13 expande el uso de atributos PHP para configuración declarativa colocada junto a la
clase, en vez de arrays de config separados. Úsalos donde apliquen:

```php
// Jobs: descartar silenciosamente si el modelo ya no existe
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

#[DeleteWhenMissingModels]
class ProcessOrderExport implements ShouldQueue
{
    // ...
}
```

```php
// Inyección contextual sin tocar un ServiceProvider
use Illuminate\Container\Attributes\Config;

public function __construct(
    #[Config('services.stripe.key')] protected string $stripeKey,
) {}
```

Atributos contextuales disponibles: `Storage`, `Auth`, `Cache`, `Config`, `Context`, `DB`,
`Give`, `Log`, `RouteParameter`, `Tag`. Prefiérelos sobre `config('...')` disperso cuando la
dependencia es de construcción (constructor de un job, controller, action) — no los uses
dentro de métodos de negocio normales, ahí `config()`/facades siguen siendo lo natural.

## Colas: despachar comandos Artisan

Si necesitas encolar un comando Artisan en vez de un Job dedicado:

```php
use Illuminate\Support\Facades\Artisan;

Artisan::queue('reports:generate', ['--month' => now()->month])
    ->onConnection('redis')
    ->onQueue('reports');
```

Prefiere un Job normal (`ShouldQueue`) para lógica de aplicación; reserva `Artisan::queue` para
tareas que ya existen como comando de mantenimiento.

## Form Requests y generación de scaffolding

Al generar un recurso nuevo con validación propia, usa el flag `--requests` para separar la
autorización/validación del controller:

```bash
php artisan make:model Order --controller --resource --requests --policy
```

En el Form Request, `authorize()` es el lugar para la regla de permiso — devuelve `false` y
Laravel responde 403 automáticamente sin tocar el controller:

```php
public function authorize(): bool
{
    return $this->user()->can('gestionar pedido');
}
```

## Referencia

Documentación verificada vía Context7 (`/websites/laravel_13_x` y
`/websites/livewire_laravel_4_x`), julio 2026.
