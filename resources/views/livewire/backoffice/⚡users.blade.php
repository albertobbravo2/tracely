<?php

use App\Livewire\Backoffice\BackofficeComponent;
use Flux\Flux;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new
#[Layout('layouts::backoffice')]
#[Title('Usuarios')]
class extends BackofficeComponent
{
    // --- Listado -----------------------------------------------------------

    public string $search = '';

    /** @var list<array<string, mixed>> */
    public array $users = [];

    // --- Formulario --------------------------------------------------------

    public ?int $editing = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public ?int $company_id = null;

    public string $role = '';

    public ?int $deleting = null;

    /** @var list<array<string, mixed>> */
    public array $companies = [];

    public bool $companiesLoaded = false;

    // --- Listado -----------------------------------------------------------

    public function updatedSearch(): void
    {
        $this->resetAndReload();
    }

    protected function loadRows(): void
    {
        $this->errorMessage = null;

        $response = $this->callApi(fn (PendingRequest $api) => $api->get(
            route('users.index', absolute: false),
            array_filter([
                'search' => trim($this->search),
                'page' => $this->page,
            ]),
        ));

        if ($response === null || $response->failed()) {
            $this->users = [];
            $this->failListing($response, __('No pudimos cargar los usuarios ahora mismo.'));

            return;
        }

        $this->users = $this->rowsFrom($response);
    }

    // --- Alta y edición ----------------------------------------------------

    public function create(): void
    {
        $this->resetForm();
        $this->loadCompanies();

        Flux::modal('user-form')->show();
    }

    public function edit(int $id): void
    {
        $this->resetForm();

        $response = $this->callApi(fn (PendingRequest $api) => $api->get(
            route('users.show', ['user' => $id], absolute: false),
        ));

        if ($response === null || $response->failed()) {
            $this->errorMessage = $this->apiErrorMessage($response, __('No pudimos cargar ese usuario.'));

            return;
        }

        $user = $response->json();

        $this->editing = $id;
        $this->name = $user['name'] ?? '';
        $this->email = $user['email'] ?? '';
        $this->company_id = $user['company_id'] ?? null;
        $this->role = collect($user['roles'] ?? [])->pluck('name')->first() ?? '';

        $this->loadCompanies();

        Flux::modal('user-form')->show();
    }

    public function save(): void
    {
        $this->resetErrorBag();
        $this->errorMessage = null;

        if ($this->requiresCompany() && ! $this->company_id) {
            $this->addError('company_id', __('Elige una empresa: un agente o un administrador trabajan siempre dentro de una.'));

            return;
        }

        $payload = [
            'name' => trim($this->name),
            'email' => trim($this->email),
            // `company_id` viaja siempre, también vacío: es `nullable` en la
            // API, así que mandarlo a null es como se desvincula de la empresa.
            'company_id' => $this->company_id ?: null,
            // Igual que la empresa: mandarlo vacío es cómo se deja la cuenta
            // sin rol (cliente final). La API decide si el rol pedido está a
            // la altura de quien hace la petición.
            'role' => $this->role !== '' ? $this->role : null,
        ];

        // En la edición la contraseña solo se manda si se ha tecleado una nueva:
        // la regla de la API es `sometimes`, así que omitirla la deja intacta.
        if ($this->editing === null || trim($this->password) !== '') {
            $payload['password'] = $this->password;
        }

        $response = $this->editing === null
            ? $this->callApi(fn (PendingRequest $api) => $api->post(route('users.store', absolute: false), $payload))
            : $this->callApi(fn (PendingRequest $api) => $api->put(
                route('users.update', ['user' => $this->editing], absolute: false),
                $payload,
            ));

        if ($this->applyApiValidationErrors($response)) {
            return;
        }

        if ($response === null || $response->failed()) {
            $this->errorMessage = $this->apiErrorMessage($response, __('No pudimos guardar el usuario.'));

            return;
        }

        Flux::modal('user-form')->close();
        Flux::toast(variant: 'success', text: $this->editing === null
            ? __('Usuario creado.')
            : __('Usuario actualizado.'));

        $this->resetForm();
        $this->loadRows();
    }

    // --- Borrado -----------------------------------------------------------

    public function confirmDelete(int $id): void
    {
        $this->deleting = $id;

        Flux::modal('user-delete')->show();
    }

    public function destroy(): void
    {
        if ($this->deleting === null) {
            return;
        }

        $response = $this->callApi(fn (PendingRequest $api) => $api->delete(
            route('users.destroy', ['user' => $this->deleting], absolute: false),
        ));

        Flux::modal('user-delete')->close();

        if ($response === null || $response->failed()) {
            $this->errorMessage = $this->apiErrorMessage($response, __('No pudimos eliminar el usuario.'));
            $this->deleting = null;

            return;
        }

        Flux::toast(variant: 'success', text: __('Usuario eliminado. Los pedidos que registró se conservan.'));

        $this->deleting = null;
        $this->loadRows();
    }

    // --- Apoyo -------------------------------------------------------------

    /**
     * ¿Quién mira puede asignar la empresa de una cuenta?
     *
     * Solo el superadministrador, que es el único rol con los permisos de
     * empresas (ver `RolesAndPermissionsSeeder`). Misma comprobación que en la
     * pantalla de pedidos.
     */
    public function canChooseCompany(): bool
    {
        return auth()->user()?->hasRole('superadministrador') ?? false;
    }

    /**
     * ¿El rol elegido obliga a asignar empresa?
     *
     * Un agente o un administrador trabajan siempre dentro de una empresa: es
     * el `company_id` con el que los listados les recortan lo que pueden ver,
     * así que sin empresa la cuenta no sirve de nada. Las dos excepciones son
     * el superadministrador, que va por encima de las empresas, y el cliente
     * final —rol vacío—, que se vincula a pedidos sueltos y no a una empresa.
     *
     * Solo aplica a quien puede elegirla: al resto la API le impone la suya
     * (ver `UserController::store()`), así que no hay nada que exigirle.
     */
    public function requiresCompany(): bool
    {
        return $this->canChooseCompany()
            && $this->role !== ''
            && $this->role !== 'superadministrador';
    }

    /**
     * Roles que quien mira puede asignar a otra cuenta: los suyos y los que
     * estén por debajo en la jerarquía agente < administrador <
     * superadministrador. Solo decide qué opciones se pintan — el filtro
     * real, el que importa, lo aplica `UserController`.
     *
     * @return list<string>
     */
    public function assignableRoles(): array
    {
        $hierarchy = ['agente', 'administrador', 'superadministrador'];
        $user = auth()->user();

        $level = 0;

        foreach ($hierarchy as $index => $role) {
            if ($user?->hasRole($role)) {
                $level = $index + 1;
            }
        }

        return array_slice($hierarchy, 0, $level);
    }

    /**
     * Etiqueta legible de un rol para las opciones del desplegable.
     */
    public function roleOptionLabel(string $role): string
    {
        return Str::ucfirst($role);
    }

    /**
     * Igual que en la pantalla de pedidos: se recorren todas las páginas dentro
     * de una sola llamada, y se comprueba el rol antes de llamar — sin esto,
     * cada vez que un agente abría el formulario se llevaba un 403 de
     * `companies.index` para acabar sin desplegable igualmente.
     */
    private function loadCompanies(): void
    {
        if ($this->companiesLoaded || ! $this->canChooseCompany()) {
            return;
        }

        $this->companiesLoaded = true;

        $this->callApi(function (PendingRequest $api) {
            $companies = [];
            $page = 1;

            do {
                $response = $api->get(route('companies.index', absolute: false), ['page' => $page]);

                if ($response->failed()) {
                    return $response;
                }

                $companies = [...$companies, ...($response->json('data') ?? [])];
                $lastPage = (int) ($response->json('last_page') ?? 1);
            } while (++$page <= $lastPage && $page <= 20);

            $this->companies = $companies;

            return $response;
        });
    }

    private function resetForm(): void
    {
        $this->resetErrorBag();

        $this->editing = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->company_id = null;
        $this->role = '';
    }

    /**
     * Roles del usuario, ya en texto y con la inicial en mayúscula. Vienen del
     * eager load de `users.index`.
     *
     * @param  array<string, mixed>  $user
     * @return list<string>
     */
    public function roleNames(array $user): array
    {
        return collect($user['roles'] ?? [])
            ->pluck('name')
            ->filter()
            ->map(fn ($name) => Str::ucfirst((string) $name))
            ->values()
            ->all();
    }

    /**
     * Los roles en una sola línea, para el resumen de móvil.
     *
     * @param  array<string, mixed>  $user
     */
    public function roleLabel(array $user): string
    {
        $roles = $this->roleNames($user);

        // Los clientes finales no tienen rol: no es un dato que falte, es lo
        // que los distingue de una cuenta de empleado.
        return $roles === [] ? __('Cliente') : implode(', ', $roles);
    }

    /**
     * Nombre de la cuenta pendiente de borrar, para que el modal de
     * confirmación diga cuál es en vez de un genérico "el usuario".
     */
    public function deletingLabel(): string
    {
        return (string) ($this->rowById($this->users, $this->deleting)['name'] ?? '');
    }
};
?>

<div class="space-y-6">
    <x-backoffice.heading
        :heading="__('Usuarios')"
        :subheading="__('Cuentas de empleados y clientes.')"
    >
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="create">
                {{ __('Nuevo usuario') }}
            </flux:button>
        </x-slot:actions>
    </x-backoffice.heading>

    <x-backoffice.search
        model="search"
        :label="__('Buscar usuarios')"
        :placeholder="__('Buscar por nombre o email')"
    />

    @if ($errorMessage)
        <x-backoffice.alert :message="$errorMessage" retry="retry" />
    @endif

    <x-backoffice.busy target="search, nextPage, previousPage, retry">
        @if (empty($users))
            @if (! $errorMessage)
                <x-backoffice.empty
                    icon="users"
                    :heading="__('No hay usuarios que mostrar')"
                    :text="filled($search)
                        ? __('Ningún usuario coincide con la búsqueda.')
                        : __('Todavía no hay ninguna cuenta registrada.')"
                >
                    <x-slot:actions>
                        <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Nuevo usuario') }}</flux:button>
                    </x-slot:actions>
                </x-backoffice.empty>
            @endif
        @else
            {{-- Tabla como tarjeta: `surface` con borde, cabecera sobre `surface-2`
                 y filas separadas por 1 px de `line` (design.md → Tabla). Las
                 celdas de los extremos recuperan el padding lateral que Flux les
                 quita (`first:ps-0`), que aquí las pegaba al borde de la tarjeta. --}}
            <div class="overflow-hidden rounded-xl border border-line bg-surface">
                <flux:table class="[&_td:first-child]:ps-5 [&_td:last-child]:pe-5 [&_th:first-child]:ps-5 [&_th:last-child]:pe-5">
                    <flux:table.columns class="bg-surface-2 [&_th]:text-xs [&_th]:font-semibold">
                        <flux:table.column align="center">{{ __('Nombre') }}</flux:table.column>
                        <flux:table.column align="center" class="max-sm:hidden">{{ __('Email') }}</flux:table.column>
                        <flux:table.column align="center" class="max-md:hidden">{{ __('Rol') }}</flux:table.column>
                        <flux:table.column align="center">{{ __('Acciones') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($users as $user)
                            @php
                                $roles = $this->roleNames($user);
                            @endphp

                            <flux:table.row :key="$user['id']">
                                <flux:table.cell>
                                    <div class="flex items-center gap-3">
                                        <flux:avatar size="sm" :name="$user['name']" />

                                        {{-- `min-w-0` para que el truncado de dentro
                                             tenga contra qué truncar: sin él un nombre
                                             o un email largo ensancha la celda y empuja
                                             las acciones fuera de la pantalla. --}}
                                        <div class="min-w-0">
                                            <span class="block truncate font-semibold text-ink">
                                                {{ $user['name'] }}
                                            </span>

                                            {{-- Email y rol se ocultan en pantallas
                                                 pequeñas: aquí van resumidos. --}}
                                            <span class="block truncate text-sm text-ink-2 sm:hidden">
                                                {{ $user['email'] }}
                                            </span>
                                            <span class="block truncate text-sm text-ink-2 md:hidden">
                                                {{ $this->roleLabel($user) }}
                                            </span>
                                        </div>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell class="max-sm:hidden">{{ $user['email'] }}</flux:table.cell>

                                <flux:table.cell align="center" class="max-md:hidden">
                                    @if ($roles === [])
                                        <x-backoffice.tone-badge tone="idle">{{ __('Cliente') }}</x-backoffice.tone-badge>
                                    @else
                                        <div class="flex flex-wrap justify-center gap-1">
                                            @foreach ($roles as $role)
                                                <x-backoffice.tone-badge tone="info">{{ $role }}</x-backoffice.tone-badge>
                                            @endforeach
                                        </div>
                                    @endif
                                </flux:table.cell>

                                <flux:table.cell align="center">
                                    <div class="flex justify-center gap-1">
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="pencil-square"
                                            :label="__('Editar usuario')"
                                            wire:click="edit({{ $user['id'] }})"
                                        />
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="trash"
                                            :label="__('Eliminar usuario')"
                                            wire:click="confirmDelete({{ $user['id'] }})"
                                        />
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>

                <div class="px-5 pb-4">
                    <x-backoffice.pagination :meta="$meta" />
                </div>
            </div>
        @endif
    </x-backoffice.busy>

    <x-backoffice.modal
        name="user-form"
        :heading="$editing === null ? __('Nuevo usuario') : __('Editar usuario')"
        :description="__('Una cuenta sin rol es un cliente final: solo ve sus propios pedidos.')"
    >
        <form wire:submit="save" class="space-y-6">
            {{-- Todos los campos ocupan la fila entera, así que la rejilla de dos
                 columnas no pintaba nada aquí. --}}
            <div class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Nombre') }}</flux:label>
                    <flux:input wire:model="name" autocomplete="name" />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Email') }}</flux:label>
                    <flux:input type="email" wire:model="email" autocomplete="email" />
                    <flux:error name="email" />
                </flux:field>

                <flux:field>
                    <flux:label badge="{{ $editing === null ? __('Requerido') : __('Opcional') }}">
                        {{ __('Contraseña') }}
                    </flux:label>
                    <flux:input type="password" wire:model="password" autocomplete="new-password" viewable />
                    @if ($editing !== null)
                        {{-- `x-show` con `$wire.password` (no un `x-data` propio) a
                             propósito: el valor tecleado ya vive reactivo en el cliente
                             vía `wire:model` —sin `.live`, sin pedir nada al servidor
                             por cada tecla— y así no hay estado de Alpine duplicado
                             que se quede obsoleto si se reabre el modal en otra fila. --}}
                        <flux:callout
                            x-show="$wire.password.trim() !== ''"
                            variant="warning"
                            icon="exclamation-triangle"
                            heading="{{ __('Al guardar, se cambiará la contraseña de este usuario.') }}"
                        />
                        <flux:description x-show="$wire.password.trim() === ''">
                            {{ __('Déjalo vacío para no cambiarla.') }}
                        </flux:description>
                    @endif
                    <flux:error name="password" />
                </flux:field>

                <flux:field>
                    <flux:label badge="{{ __('Opcional') }}">{{ __('Rol') }}</flux:label>
                    {{-- `.live` porque el rol decide si la empresa es obligatoria:
                         el distintivo de abajo tiene que cambiar al elegirlo, no
                         al intentar guardar. --}}
                    <flux:select wire:model.live="role">
                        <flux:select.option value="">{{ __('Cliente') }}</flux:select.option>
                        @foreach ($this->assignableRoles() as $roleOption)
                            <flux:select.option :value="$roleOption">{{ $this->roleOptionLabel($roleOption) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:description>{{ __('Solo puedes asignar tu rol o uno por debajo.') }}</flux:description>
                    <flux:error name="role" />
                </flux:field>

                @if ($companies)
                    <flux:field>
                        <flux:label badge="{{ $this->requiresCompany() ? __('Requerido') : __('Opcional') }}">
                            {{ __('Empresa') }}
                        </flux:label>
                        <flux:select wire:model="company_id">
                            {{-- La opción vacía se queda aunque la empresa sea
                                 obligatoria: quitarla dejaría el desplegable
                                 mostrando la primera empresa como si estuviera
                                 elegida, cuando `company_id` sigue a null. --}}
                            <flux:select.option value="">
                                {{ $this->requiresCompany() ? __('Elige una empresa') : __('Sin empresa') }}
                            </flux:select.option>
                            @foreach ($companies as $company)
                                <flux:select.option :value="$company['id']">{{ $company['name'] }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        @if ($this->requiresCompany())
                            <flux:description>
                                {{ __('Los pedidos que verá esta cuenta son los de su empresa.') }}
                            </flux:description>
                        @endif
                        <flux:error name="company_id" />
                    </flux:field>
                @endif
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">{{ __('Guardar') }}</span>
                    <span wire:loading wire:target="save">{{ __('Guardando...') }}</span>
                </flux:button>
            </div>
        </form>
    </x-backoffice.modal>

    {{-- El modal se abre desde una fila y la tapa: sin nombrar la cuenta,
         confirmar es un acto de fe. --}}
    <x-backoffice.modal
        name="user-delete"
        size="narrow"
        :heading="__('Eliminar usuario')"
        :description="$this->deletingLabel() !== ''
            ? __('Se eliminará la cuenta de :nombre y sus vínculos con pedidos. Los envíos que haya registrado se conservan, sin remitente. No se puede deshacer.', ['nombre' => $this->deletingLabel()])
            : __('Se eliminará la cuenta y sus vínculos con pedidos. Los envíos que haya registrado se conservan, sin remitente. No se puede deshacer.')"
    >
        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancelar') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" wire:click="destroy" wire:loading.attr="disabled" wire:target="destroy">
                {{ __('Eliminar') }}
            </flux:button>
        </div>
    </x-backoffice.modal>
</div>
