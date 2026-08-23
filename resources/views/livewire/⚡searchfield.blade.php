<?php

use App\Enums\ShipmentStatus;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    #[Validate('required|string|min:4|max:40')]
    public string $tracking_number = '';

    /** @var array<string, mixed>|null */
    public ?array $shipment = null;

    /**
     * ¿El envío mostrado ya está en la lista del usuario logueado?
     *
     * Siempre false para un invitado: el botón de vincular solo existe bajo
     * `@auth`, y la pivote es de un usuario concreto.
     */
    public bool $linked = false;

    public ?string $errorMessage = null;

    /**
     * Consultar la API interna de envíos con el número de guía introducido.
     */
    public function search(): void
    {
        $this->validate();

        $this->shipment = null;
        $this->linked = false;
        $this->errorMessage = null;

        $path = route('shipments.show', ['shipment' => trim($this->tracking_number)], absolute: false);

        $response = $this->callApi('get', $path);

        if ($response === null) {
            $this->errorMessage = __('No pudimos conectar con el servicio de rastreo. Inténtalo de nuevo en unos segundos.');

            return;
        }

        if ($response->status() === 404) {
            $this->errorMessage = __('No encontramos ningún envío con ese número de guía.');

            return;
        }

        if ($response->failed()) {
            $this->errorMessage = __('El servicio de rastreo no está disponible ahora mismo.');

            return;
        }

        $this->shipment = $response->json();
        $this->linked = $this->isLinked();

        $this->dispatch('shipment-found', shipment: $this->shipment);
    }

    /**
     * Añadir el envío mostrado a la lista del usuario logueado.
     */
    public function link(): void
    {
        $this->toggleLink(link: true);
    }

    /**
     * Quitar el envío mostrado de la lista del usuario logueado.
     */
    public function unlink(): void
    {
        $this->toggleLink(link: false);
    }

    /**
     * El vínculo se crea y se borra a través de la API (`shipments.users.*`),
     * no tocando la pivote desde aquí: mismo motivo que la búsqueda, que la
     * regla de qué se puede vincular viva en un solo sitio.
     */
    private function toggleLink(bool $link): void
    {
        $trackingNumber = $this->shipment['tracking_number'] ?? null;

        if (! auth()->check() || ! is_string($trackingNumber)) {
            return;
        }

        $this->errorMessage = null;

        $path = route(
            $link ? 'shipments.users.store' : 'shipments.users.destroy',
            ['shipment' => $trackingNumber],
            absolute: false,
        );

        $response = $this->callApi($link ? 'post' : 'delete', $path, 'shipment:write');

        if ($response === null) {
            $this->errorMessage = __('No pudimos conectar con el servicio de rastreo. Inténtalo de nuevo en unos segundos.');

            return;
        }

        if ($response->failed()) {
            $this->errorMessage = $link
                ? __('No pudimos vincular el envío a tu cuenta.')
                : __('No pudimos quitar el envío de tu cuenta.');

            return;
        }

        $this->linked = $link;
    }

    /**
     * Nodos del timeline, uno por evento del historial.
     *
     * Solo la respuesta autenticada trae `histories`. Sin ese campo devolvemos
     * un array vacío y la vista no pinta nada de la línea de tiempo, ni siquiera
     * el separador. Eso cubre también al usuario logueado cuyo envío todavía no
     * tiene ningún evento registrado.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function timeline(): array
    {
        $histories = $this->shipment['histories'] ?? [];

        if ($histories === []) {
            return [];
        }

        $lastKey = array_key_last($histories);

        return collect($histories)
            ->map(fn (array $event, int|string $key) => $this->node(
                status: $event['status'] ?? null,
                current: $key === $lastKey,
                description: $event['description'] ?? null,
                location: $event['location'] ?? null,
                recordedAt: $event['recorded_at'] ?? null,
            ))
            ->values()
            ->all();
    }

    /**
     * Badge de estado de la tarjeta: etiqueta y clases salen del mismo `status`
     * del envío, para que no puedan acabar describiendo estados distintos.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function statusBadge(): array
    {
        $status = $this->shipment['status'] ?? null;

        return [
            ...$this->styles($status),
            'label' => ShipmentStatus::tryFrom((string) $status)?->label() ?? __('Estado desconocido'),
        ];
    }

    /**
     * Fecha de entrega estimada ya formateada, o null si no viene.
     */
    #[Computed]
    public function estimatedDelivery(): ?string
    {
        $date = $this->shipment['estimated_delivery_date'] ?? null;

        // locale('es') explícito: APP_LOCALE es 'en' pero toda la interfaz está en
        // español, y sin esto las fechas saldrían como "9 Aug 2026".
        return $date ? Carbon::parse($date)->locale('es')->translatedFormat('j M Y') : null;
    }

    /**
     * Llamar a nuestra propia API desde el servidor.
     *
     * La petición no arrastra la sesión del navegador: para la API seríamos un
     * invitado y solo devolvería los datos públicos. Si hay usuario logueado le
     * acuñamos un token de un minuto para que lo reconozca, y lo borramos en
     * `finally` para que no quede vivo si la conexión falla.
     *
     * Devuelve null si no se pudo conectar; el mensaje lo decide quien llama.
     */
    private function callApi(string $method, string $path, string $ability = 'shipment:read'): ?Response
    {
        $pending = Http::acceptJson()
            ->timeout(10)
            ->baseUrl(config('services.internal_api.url'));

        $token = auth()->user()?->createToken('searchfield', [$ability], now()->addMinute());

        if ($token) {
            $pending->withToken($token->plainTextToken);
        }

        try {
            return $pending->send($method, $path);
        } catch (ConnectionException) {
            return null;
        } finally {
            $token?->accessToken->delete();
        }
    }

    /**
     * ¿Este envío ya está vinculado a la cuenta de quien mira?
     *
     * Esto sí se resuelve con Eloquent y no por la API: no son datos del envío
     * recortados según la sesión —de eso va el patrón de llamar a la API—, sino
     * la pivote del propio usuario, y `users.myshipments` viene paginado, así
     * que no sirve para responderlo de un vistazo.
     */
    private function isLinked(): bool
    {
        $user = auth()->user();
        $trackingNumber = $this->shipment['tracking_number'] ?? null;

        if (! $user instanceof User || ! is_string($trackingNumber)) {
            return false;
        }

        return $user->shipments()
            ->where('tracking_number', $trackingNumber)
            ->exists();
    }

    /**
     * Un nodo del timeline, ya resuelto a texto y clases para la vista.
     *
     * @return array<string, mixed>
     */
    private function node(
        ?string $status,
        bool $current,
        ?string $description = null,
        ?string $location = null,
        ?string $recordedAt = null,
    ): array {
        return [
            'title' => ShipmentStatus::tryFrom((string) $status)?->label() ?? __('Estado desconocido'),
            'description' => $description,
            'meta' => collect([
                $location,
                $recordedAt ? Carbon::parse($recordedAt)->locale('es')->translatedFormat('j M Y · H:i') : null,
            ])->filter()->join(' · ') ?: null,
            'current' => $current,
            'styles' => $this->styles($status),
        ];
    }

    /**
     * Estado → familia de la paleta. Es una decisión visual, por eso vive en el
     * componente y no en el enum de dominio.
     *
     * @return array<string, string>
     */
    private function styles(?string $status): array
    {
        return match (ShipmentStatus::tryFrom((string) $status)) {
            ShipmentStatus::Entregado => [
                'dot' => 'bg-ok',
                'title' => 'text-ok-fuerte dark:text-ok-claro',
                'badge' => '!bg-ok-fondo !text-ok-fuerte',
            ],
            ShipmentStatus::EnTransito, ShipmentStatus::EnAduana => [
                'dot' => 'bg-azul-600',
                'title' => 'text-azul-600 dark:text-azul-200',
                'badge' => '!bg-azul-050 !text-azul-800',
            ],
            ShipmentStatus::Incidencia => [
                'dot' => 'bg-alerta',
                'title' => 'text-alerta-fuerte dark:text-alerta-claro',
                'badge' => '!bg-alerta-fondo !text-alerta-fuerte',
            ],
            // Cubre `pendiente` y cualquier estado que no reconozcamos.
            default => [
                'dot' => 'bg-gris-400',
                'title' => 'text-gris-900 dark:text-azul-100',
                'badge' => '!bg-gris-050 !text-gris-600',
            ],
        };
    }
};
?>

<div>
    <form wire:submit="search" class="mx-auto flex max-w-xl items-center gap-3">
        <flux:input
            wire:model="tracking_number"
            name="tracking_number"
            aria-label="{{ __('Número de guía') }}"
            placeholder="RY-4820-1174-MX"
            class="flex-1"
            class:input="!border-transparent !bg-blanco !text-gris-900 !shadow-none placeholder:!text-gris-400"
        />

        <flux:button
            type="submit"
            variant="primary"
            wire:loading.attr="disabled"
            wire:target="search"
            class="shrink-0 px-6 font-semibold [--color-accent-foreground:var(--color-white)] [--color-accent:var(--color-brand-navy)]"
        >
            <span wire:loading.remove wire:target="search">{{ __('Buscar') }}</span>
            <span wire:loading wire:target="search">{{ __('Buscando...') }}</span>
        </flux:button>
    </form>

    <div class="mx-auto mt-4 max-w-xl text-start">
        <flux:error name="tracking_number" />

        @if ($errorMessage)
            <div class="rounded-xl border border-gris-200 bg-gris-050 px-4 py-3 dark:border-azul-800 dark:bg-azul-900">
                <flux:text class="text-gris-600 dark:text-azul-100">{{ $errorMessage }}</flux:text>
            </div>
        @endif

        @if ($shipment)
            <div class="rounded-2xl border border-gris-200 bg-blanco p-6 shadow-sm sm:p-8 dark:border-azul-800 dark:bg-azul-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <flux:text class="text-gris-600 dark:text-azul-100">{{ __('Guía') }}</flux:text>
                        <flux:heading size="lg" class="text-gris-900 dark:text-blanco">
                            {{ $shipment['tracking_number'] }}
                        </flux:heading>
                    </div>

                    <div class="flex shrink-0 flex-col items-end gap-3">
                        <flux:badge rounded class="{{ $this->statusBadge['badge'] }}">
                            {{ $this->statusBadge['label'] }}
                        </flux:badge>

                        {{-- Solo con sesión: la pivote `shipment_user` cuelga de una
                             cuenta concreta, y un invitado no tiene ninguna. --}}
                        @auth
                            @if ($linked)
                                <flux:button
                                    size="sm"
                                    icon="minus-circle"
                                    wire:click="unlink"
                                    wire:loading.attr="disabled"
                                    wire:target="unlink"
                                >
                                    {{ __('Eliminar') }}
                                </flux:button>
                            @else
                                <flux:button
                                    size="sm"
                                    variant="primary"
                                    icon="plus-circle"
                                    wire:click="link"
                                    wire:loading.attr="disabled"
                                    wire:target="link"
                                    class="[--color-accent-foreground:var(--color-white)] [--color-accent:var(--color-brand-navy)]"
                                >
                                    {{ __('Vincular') }}
                                </flux:button>
                            @endif
                        @endauth
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <flux:text class="text-gris-600 dark:text-azul-100">{{ __('Origen') }}</flux:text>
                        <p class="font-semibold text-gris-900 dark:text-blanco">{{ $shipment['origin'] }}</p>
                    </div>
                    <div>
                        <flux:text class="text-gris-600 dark:text-azul-100">{{ __('Destino') }}</flux:text>
                        <p class="font-semibold text-gris-900 dark:text-blanco">{{ $shipment['destination'] }}</p>
                    </div>
                    <div>
                        <flux:text class="text-gris-600 dark:text-azul-100">{{ __('Entrega estimada') }}</flux:text>
                        <p class="font-semibold text-ok dark:text-ok-claro">{{ $this->estimatedDelivery ?? '—' }}</p>
                    </div>
                </div>

                {{-- El histórico solo llega en la respuesta autenticada: sin él no
                     se pinta nada aquí abajo, tampoco el separador. --}}
                @if ($this->timeline)
                    <flux:separator class="mt-6" />

                    <ol class="mt-6">
                        @foreach ($this->timeline as $event)
                            <li class="relative border-s-2 border-gris-200 ps-6 pb-8 last:border-transparent last:pb-0 dark:border-azul-800">
                                <span
                                    aria-hidden="true"
                                    @class([
                                        'absolute -start-[7px] top-1.5 size-3 rounded-full ring-4 ring-blanco dark:ring-azul-900',
                                        $event['styles']['dot'],
                                    ])
                                ></span>

                                <flux:heading
                                    size="sm"
                                    @class([
                                        $event['styles']['title'],
                                        'font-semibold' => $event['current'],
                                    ])
                                >
                                    {{ $event['title'] }}
                                </flux:heading>

                                @if ($event['description'])
                                    <flux:text class="mt-0.5 text-gris-900 dark:text-azul-100">
                                        {{ $event['description'] }}
                                    </flux:text>
                                @endif

                                @if ($event['meta'])
                                    <flux:text size="sm" class="mt-0.5 text-gris-600 dark:text-azul-200">
                                        {{ $event['meta'] }}
                                    </flux:text>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        @endif
    </div>
</div>
