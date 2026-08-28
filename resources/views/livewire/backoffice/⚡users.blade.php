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

        $this->loadCompanies();

        Flux::modal('user-form')->show();
    }

    public function save(): void
    {
        $this->resetErrorBag();
        $this->errorMessage = null;

        $payload = [
            'name' => trim($this->name),
            'email' => trim($this->email),
            // `company_id` viaja siempre, también vacío: es `nullable` en la
            // API, así que mandarlo a null es como se desvincula de la empresa.
            'company_id' => $this->company_id ?: null,
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
            ? __('Usuario creado. Su rol se asigna fuera del backoffice.')
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
        return $roles === [] ? __('Cliente (sin rol)') : implode(', ', $roles);
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
        :subheading="__('Cuentas de empleados y clientes. El rol se muestra pero se asigna fuera del backoffice.')"
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
            <div class="space-y-4">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Nombre') }}</flux:table.column>
                        <flux:table.column class="max-sm:hidden">{{ __('Email') }}</flux:table.column>
                        <flux:table.column class="max-md:hidden">{{ __('Rol') }}</flux:table.column>
                        <flux:table.column class="text-end">{{ __('Acciones') }}</flux:table.column>
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
                                            <span class="block truncate font-semibold text-gris-900 dark:text-blanco">
                                                {{ $user['name'] }}
                                            </span>

                                            {{-- Email y rol se ocultan en pantallas
                                                 pequeñas: aquí van resumidos. --}}
                                            <span class="block truncate text-sm text-gris-600 sm:hidden dark:text-azul-200">
                                                {{ $user['email'] }}
                                            </span>
                                            <span class="block truncate text-sm text-gris-600 md:hidden dark:text-azul-200">
                                                {{ $this->roleLabel($user) }}
                                            </span>
                                        </div>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell class="max-sm:hidden">{{ $user['email'] }}</flux:table.cell>

                                <flux:table.cell class="max-md:hidden">
                                    @if ($roles === [])
                                        <x-backoffice.tone-badge tone="gris">{{ __('Cliente (sin rol)') }}</x-backoffice.tone-badge>
                                    @else
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($roles as $role)
                                                <x-backoffice.tone-badge tone="azul">{{ $role }}</x-backoffice.tone-badge>
                                            @endforeach
                                        </div>
                                    @endif
                                </flux:table.cell>

                                <flux:table.cell class="text-end">
                                    <div class="flex justify-end gap-1">
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

                <x-backoffice.pagination :meta="$meta" />
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
                        <flux:description>{{ __('Déjalo vacío para no cambiarla.') }}</flux:description>
                    @endif
                    <flux:error name="password" />
                </flux:field>

                @if ($companies)
                    <flux:field>
                        <flux:label badge="{{ __('Opcional') }}">{{ __('Empresa') }}</flux:label>
                        <flux:select wire:model="company_id">
                            <flux:select.option value="">{{ __('Sin empresa') }}</flux:select.option>
                            @foreach ($companies as $company)
                                <flux:select.option :value="$company['id']">{{ $company['name'] }}</flux:select.option>
                            @endforeach
                        </flux:select>
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
