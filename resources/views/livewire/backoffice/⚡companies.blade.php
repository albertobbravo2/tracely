<?php

use App\Livewire\Backoffice\BackofficeComponent;
use Flux\Flux;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new
#[Layout('layouts::backoffice')]
#[Title('Compañías')]
class extends BackofficeComponent
{
    // --- Listado -----------------------------------------------------------

    public string $search = '';

    /** @var list<array<string, mixed>> */
    public array $companies = [];

    // --- Formulario --------------------------------------------------------

    public ?int $editing = null;

    public string $name = '';

    public string $slug = '';

    public string $contact_email = '';

    public string $phone = '';

    public bool $is_active = true;

    public ?int $deleting = null;

    // --- Listado -----------------------------------------------------------

    public function updatedSearch(): void
    {
        $this->resetAndReload();
    }

    protected function loadRows(): void
    {
        $this->errorMessage = null;

        $response = $this->callApi(fn (PendingRequest $api) => $api->get(
            route('companies.index', absolute: false),
            array_filter([
                'search' => trim($this->search),
                'page' => $this->page,
            ]),
        ));

        if ($response === null || $response->failed()) {
            $this->companies = [];
            // Gestionar empresas es solo del superadministrador: para un agente
            // o un administrador el 403 es lo esperado, no una avería.
            $this->failListing($response, __('No pudimos cargar las empresas ahora mismo.'));

            return;
        }

        $this->companies = $this->rowsFrom($response);
    }

    // --- Alta y edición ----------------------------------------------------

    public function create(): void
    {
        $this->resetForm();

        Flux::modal('company-form')->show();
    }

    public function edit(int $id): void
    {
        $this->resetForm();

        $response = $this->callApi(fn (PendingRequest $api) => $api->get(
            route('companies.show', ['company' => $id], absolute: false),
        ));

        if ($response === null || $response->failed()) {
            $this->errorMessage = $this->apiErrorMessage($response, __('No pudimos cargar esa empresa.'));

            return;
        }

        $company = $response->json();

        $this->editing = $id;
        $this->name = $company['name'] ?? '';
        $this->slug = $company['slug'] ?? '';
        $this->contact_email = (string) ($company['contact_email'] ?? '');
        $this->phone = (string) ($company['phone'] ?? '');
        $this->is_active = (bool) ($company['is_active'] ?? true);

        Flux::modal('company-form')->show();
    }

    /**
     * El slug se propone a partir del nombre mientras se teclea, pero solo en
     * el alta y solo si aún no se ha tocado a mano: en la edición cambiarlo
     * solo rompería enlaces existentes.
     */
    public function updatedName(string $value): void
    {
        if ($this->editing === null) {
            $this->slug = Str::slug($value);
        }
    }

    public function save(): void
    {
        $this->resetErrorBag();
        $this->errorMessage = null;

        $payload = [
            'name' => trim($this->name),
            'slug' => trim($this->slug),
            'contact_email' => trim($this->contact_email) ?: null,
            'phone' => trim($this->phone) ?: null,
            'is_active' => $this->is_active,
        ];

        $response = $this->editing === null
            ? $this->callApi(fn (PendingRequest $api) => $api->post(route('companies.store', absolute: false), $payload))
            : $this->callApi(fn (PendingRequest $api) => $api->put(
                route('companies.update', ['company' => $this->editing], absolute: false),
                $payload,
            ));

        if ($this->applyApiValidationErrors($response)) {
            return;
        }

        if ($response === null || $response->failed()) {
            $this->errorMessage = $this->apiErrorMessage($response, __('No pudimos guardar la empresa.'));

            return;
        }

        Flux::modal('company-form')->close();
        Flux::toast(variant: 'success', text: $this->editing === null
            ? __('Empresa creada.')
            : __('Empresa actualizada.'));

        $this->resetForm();
        $this->loadRows();
    }

    // --- Borrado -----------------------------------------------------------

    public function confirmDelete(int $id): void
    {
        $this->deleting = $id;

        Flux::modal('company-delete')->show();
    }

    public function destroy(): void
    {
        if ($this->deleting === null) {
            return;
        }

        $response = $this->callApi(fn (PendingRequest $api) => $api->delete(
            route('companies.destroy', ['company' => $this->deleting], absolute: false),
        ));

        Flux::modal('company-delete')->close();

        if ($response === null || $response->failed()) {
            $this->errorMessage = $this->apiErrorMessage($response, __('No pudimos eliminar la empresa.'));
            $this->deleting = null;

            return;
        }

        Flux::toast(variant: 'success', text: __('Empresa eliminada. Sus pedidos y usuarios se conservan, sin empresa.'));

        $this->deleting = null;
        $this->loadRows();
    }

    // --- Apoyo -------------------------------------------------------------

    private function resetForm(): void
    {
        $this->resetErrorBag();

        $this->editing = null;
        $this->name = '';
        $this->slug = '';
        $this->contact_email = '';
        $this->phone = '';
        $this->is_active = true;
    }
};
?>

<div class="space-y-6">
    <x-backoffice.heading
        :heading="__('Compañías')"
        :subheading="__('Empresas de la plataforma. Gestionarlas es cosa del superadministrador.')"
    >
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="create">
                {{ __('Nueva empresa') }}
            </flux:button>
        </x-slot:actions>
    </x-backoffice.heading>

    <flux:input
        wire:model.live.debounce.400ms="search"
        icon="magnifying-glass"
        class="sm:max-w-sm"
        :placeholder="__('Buscar por nombre, slug o email')"
        :label="__('Buscar empresas')"
        label:class="sr-only"
    />

    @if ($errorMessage)
        <x-backoffice.alert :message="$errorMessage" />
    @endif

    @if (empty($companies))
        @if (! $errorMessage)
            <x-backoffice.empty
                icon="building-office-2"
                :heading="__('No hay empresas que mostrar')"
                :text="filled($search)
                    ? __('Ninguna empresa coincide con la búsqueda.')
                    : __('Todavía no se ha dado de alta ninguna empresa.')"
            >
                <x-slot:actions>
                    <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Nueva empresa') }}</flux:button>
                </x-slot:actions>
            </x-backoffice.empty>
        @endif
    @else
        <div class="space-y-4">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Empresa') }}</flux:table.column>
                    <flux:table.column class="max-md:hidden">{{ __('Contacto') }}</flux:table.column>
                    <flux:table.column class="max-sm:hidden">{{ __('Teléfono') }}</flux:table.column>
                    <flux:table.column class="max-sm:hidden">{{ __('Estado') }}</flux:table.column>
                    <flux:table.column class="text-end">{{ __('Acciones') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($companies as $company)
                        <flux:table.row :key="$company['id']">
                            <flux:table.cell>
                                <span class="font-semibold text-gris-900 dark:text-blanco">{{ $company['name'] }}</span>
                                <span class="block text-sm text-gris-600 dark:text-azul-200">{{ $company['slug'] }}</span>

                                {{-- En móvil solo caben dos columnas sin empujar las
                                     acciones fuera de pantalla. --}}
                                <span class="mt-1 block sm:hidden">
                                    @if ($company['is_active'])
                                        <flux:badge rounded size="sm" class="!bg-ok-fondo !text-ok-fuerte">{{ __('Activa') }}</flux:badge>
                                    @else
                                        <flux:badge rounded size="sm" class="!bg-gris-050 !text-gris-600">{{ __('Inactiva') }}</flux:badge>
                                    @endif
                                </span>
                            </flux:table.cell>

                            <flux:table.cell class="max-md:hidden">{{ $company['contact_email'] ?: '—' }}</flux:table.cell>

                            <flux:table.cell class="whitespace-nowrap max-sm:hidden">
                                {{ $company['phone'] ?: '—' }}
                            </flux:table.cell>

                            <flux:table.cell class="max-sm:hidden">
                                @if ($company['is_active'])
                                    <flux:badge rounded size="sm" class="!bg-ok-fondo !text-ok-fuerte">{{ __('Activa') }}</flux:badge>
                                @else
                                    <flux:badge rounded size="sm" class="!bg-gris-050 !text-gris-600">{{ __('Inactiva') }}</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell class="text-end">
                                <div class="flex justify-end gap-1">
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="pencil-square"
                                        :label="__('Editar empresa')"
                                        wire:click="edit({{ $company['id'] }})"
                                    />
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="trash"
                                        :label="__('Eliminar empresa')"
                                        wire:click="confirmDelete({{ $company['id'] }})"
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

    <flux:modal name="company-form" class="w-full md:w-[32rem]">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $editing === null ? __('Nueva empresa') : __('Editar empresa') }}
                </flux:heading>
                <flux:text class="mt-1">
                    {{ __('El slug identifica a la empresa y no se puede repetir.') }}
                </flux:text>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('Nombre') }}</flux:label>
                    <flux:input wire:model.live.debounce.400ms="name" />
                    <flux:error name="name" />
                </flux:field>

                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('Slug') }}</flux:label>
                    <flux:input wire:model="slug" placeholder="transportes-del-norte" />
                    <flux:error name="slug" />
                </flux:field>

                <flux:field>
                    <flux:label badge="{{ __('Opcional') }}">{{ __('Email de contacto') }}</flux:label>
                    <flux:input type="email" wire:model="contact_email" />
                    <flux:error name="contact_email" />
                </flux:field>

                <flux:field>
                    <flux:label badge="{{ __('Opcional') }}">{{ __('Teléfono') }}</flux:label>
                    <flux:input wire:model="phone" />
                    <flux:error name="phone" />
                </flux:field>

                <flux:field variant="inline" class="sm:col-span-2">
                    <flux:switch wire:model="is_active" />
                    <flux:label>{{ __('Empresa activa') }}</flux:label>
                    <flux:error name="is_active" />
                </flux:field>
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
    </flux:modal>

    <flux:modal name="company-delete" class="w-full md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Eliminar empresa') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('Sus pedidos y sus usuarios no se borran: se quedan sin empresa asignada. No se puede deshacer.') }}
                </flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="destroy" wire:loading.attr="disabled" wire:target="destroy">
                    {{ __('Eliminar') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
