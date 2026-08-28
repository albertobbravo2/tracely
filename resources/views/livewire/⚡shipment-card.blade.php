<?php

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Tarjeta de un envío: guía, estado, ruta y su línea de tiempo, con el botón
 * de vincular/eliminar dentro.
 *
 * Es un componente Livewire y no un Blade anónimo precisamente por ese botón:
 * así el botón viaja con la tarjeta y funciona en cualquier pantalla que la
 * pinte, sin que esa pantalla tenga que declarar `link()`/`unlink()` por su
 * cuenta. Cuando esos métodos vivían en ⚡searchfield, el botón solo existía
 * en el buscador.
 */
new class extends Component
{
    /**
     * El envío tal cual lo devuelve la API, no un modelo: las pantallas que
     * montan esta tarjeta reciben el dato por HTTP.
     *
     * @var array<string, mixed>
     */
    public array $shipment = [];

    public bool $linked = false;

    public ?string $errorMessage = null;

    /**
     * `linked` se puede dar hecho desde fuera: el dashboard lista justo los
     * envíos vinculados, así que ya sabe la respuesta y se ahorra una consulta
     * por tarjeta. Si no llega, la tarjeta lo resuelve ella misma, así que una
     * pantalla que no lo pase sigue pintando el botón correcto.
     */
    public function mount(?bool $linked = null): void
    {
        $this->linked = $linked ?? $this->isLinked();
    }

    /**
     * Añadir este envío a la lista del usuario logueado.
     */
    public function link(): void
    {
        $this->toggleLink(link: true);
    }

    /**
     * Quitar este envío de la lista del usuario logueado.
     */
    public function unlink(): void
    {
        $this->toggleLink(link: false);
    }

    /**
     * El vínculo se crea y se borra a través de la API (`shipments.users.*`),
     * no tocando la pivote desde aquí: la regla de a quién se vincula un envío
     * —el id sale del token, nunca del payload— vive en un solo sitio.
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

        $response = $this->callApi($link ? 'post' : 'delete', $path);

        if ($response === null) {
            $this->errorMessage = __('No pudimos conectar con el servicio. Inténtalo de nuevo en unos segundos.');

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
     * Fecha de entrega estimada ya formateada, o null si no viene.
     */
    #[Computed]
    public function estimatedDelivery(): ?string
    {
        $date = $this->shipment['estimated_delivery_date'] ?? null;

        // locale('es') explícito: APP_LOCALE es 'en' pero la interfaz está en
        // español, y sin esto la fecha saldría como "9 Aug 2026".
        return $date ? Carbon::parse((string) $date)->locale('es')->translatedFormat('j M Y') : null;
    }

    /**
     * Llamar a nuestra propia API desde el servidor.
     *
     * La petición no arrastra la sesión del navegador, así que para la API
     * seríamos un invitado: se acuña un token de un minuto para que reconozca
     * al usuario y se borra en `finally`, para que no quede vivo si la conexión
     * falla.
     *
     * Devuelve null si no se pudo conectar; el mensaje lo decide quien llama.
     */
    private function callApi(string $method, string $path): ?Response
    {
        $pending = Http::acceptJson()
            ->timeout(10)
            ->baseUrl(config('services.internal_api.url'));

        $token = auth()->user()?->createToken('shipment-card', ['shipment:write'], now()->addMinute());

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
};
?>

<div class="rounded-2xl border border-gris-200 bg-blanco p-6 shadow-sm sm:p-8 dark:border-azul-800 dark:bg-azul-900">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:text class="text-gris-600 dark:text-azul-100">{{ __('Guía') }}</flux:text>

            {{-- El estado va con la guía, no en la esquina: describe al envío,
                 igual que el número, y arriba a la derecha solo quedaba apilado
                 sobre un botón con el que no tiene nada que ver. --}}
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <flux:heading size="lg" class="text-gris-900 dark:text-blanco">
                    {{ $shipment['tracking_number'] ?? '—' }}
                </flux:heading>

                <x-backoffice.status-badge :status="$shipment['status'] ?? null" />
            </div>
        </div>

        <div class="shrink-0">
            {{-- Solo con sesión: la pivote `shipment_user` cuelga de una cuenta
                 concreta, y un invitado no tiene ninguna. --}}
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

    @if ($errorMessage)
        <div class="mt-4 rounded-xl border border-gris-200 bg-gris-050 px-4 py-3 dark:border-azul-800 dark:bg-azul-900">
            <flux:text class="text-gris-600 dark:text-azul-100">{{ $errorMessage }}</flux:text>
        </div>
    @endif

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div>
            <flux:text class="text-gris-600 dark:text-azul-100">{{ __('Origen') }}</flux:text>
            <p class="font-semibold text-gris-900 dark:text-blanco">{{ $shipment['origin'] ?? '—' }}</p>
        </div>
        <div>
            <flux:text class="text-gris-600 dark:text-azul-100">{{ __('Destino') }}</flux:text>
            <p class="font-semibold text-gris-900 dark:text-blanco">{{ $shipment['destination'] ?? '—' }}</p>
        </div>
        <div>
            <flux:text class="text-gris-600 dark:text-azul-100">{{ __('Entrega estimada') }}</flux:text>
            <p class="font-semibold text-ok dark:text-ok-claro">{{ $this->estimatedDelivery ?? '—' }}</p>
        </div>
    </div>

    {{-- El histórico solo llega en la respuesta autenticada de la API: sin él
         no se pinta nada aquí abajo, tampoco el separador. --}}
    @if ($shipment['histories'] ?? [])
        <flux:separator class="mt-6" />

        <x-shipment-timeline :histories="$shipment['histories']" class="mt-6" />
    @endif
</div>
