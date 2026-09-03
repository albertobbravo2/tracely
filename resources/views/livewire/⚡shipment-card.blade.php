<?php

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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

    /** @var list<array<string, mixed>> */
    public array $documents = [];

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
        $this->documents = $this->loadDocuments();
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

    /**
     * ¿Quien mira puede ver los documentos del envío?
     *
     * Los tres roles del backoffice, igual que el permiso "ver documento" del
     * seeder (ver el comentario en RolesAndPermissionsSeeder): un cliente sin
     * rol no los ve nunca, se pinte esta tarjeta donde se pinte. Se comprueba
     * por rol y no con `can('ver documento')` porque esta tarjeta también la
     * monta ⚡searchfield para un invitado, y ahí no hay sesión sobre la que
     * evaluar el permiso.
     */
    private function canViewDocuments(): bool
    {
        return auth()->user()?->hasAnyRole(['agente', 'administrador', 'superadministrador']) ?? false;
    }

    /**
     * Documentos del envío, solo para quien tiene rol para verlos.
     *
     * Esta tarjeta la pinta tanto el buscador público como "Mis pedidos", así
     * que se comprueba el rol antes de llamar —igual que `loadCompanies()` en
     * el backoffice—: los documentos no son públicos como el seguimiento, y
     * sin `id` —la rama pública de `shipments.show` no lo trae— tampoco hay
     * por qué preguntarlos.
     *
     * @return list<array<string, mixed>>
     */
    private function loadDocuments(): array
    {
        $shipmentId = $this->shipment['id'] ?? null;

        if ($shipmentId === null || ! $this->canViewDocuments()) {
            return [];
        }

        $response = $this->callApi('get', route('documents.index', [
            'shipment_id' => (int) $shipmentId,
        ], absolute: false));

        if ($response === null || $response->failed()) {
            return [];
        }

        $rows = $response->json('data');

        return is_array($rows) ? array_values(array_filter($rows, is_array(...))) : [];
    }

    /**
     * Tono del distintivo de estado de un documento.
     *
     * Mismo criterio que ⚡documents.blade.php: el estado es texto libre en la
     * API —no hay enum detrás, a diferencia del de los envíos—, así que se
     * reconocen las raíces habituales en castellano y todo lo demás se queda
     * en gris.
     */
    public function statusTone(?string $status): string
    {
        $normalized = Str::lower(Str::ascii(trim((string) $status)));

        return match (true) {
            Str::contains($normalized, ['aprobad', 'validad', 'aceptad', 'verificad', 'conform']) => 'ok',
            Str::contains($normalized, ['rechazad', 'denegad', 'caducad', 'error', 'incidencia', 'invalid']) => 'alerta',
            Str::contains($normalized, ['pendiente', 'revis', 'proces', 'tramit', 'espera']) => 'azul',
            default => 'gris',
        };
    }

    /**
     * Estado del documento tal y como se pinta: con la inicial en mayúscula, y
     * con un texto propio cuando viene vacío en vez de un distintivo mudo.
     */
    public function statusLabel(?string $status): string
    {
        $status = trim((string) $status);

        return $status === '' ? __('Sin estado') : Str::ucfirst($status);
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

    {{-- `$documents` ya sale vacío si quien mira no tiene permiso para verlos
         —lo resuelve `loadDocuments()`—, así que aquí solo queda pintar lo que
         haya, igual que con el histórico. --}}
    @if ($documents)
        <flux:separator class="mt-6" />

        <div class="mt-6 space-y-3">
            <flux:text class="text-gris-600 dark:text-azul-100">{{ __('Documentos') }}</flux:text>

            <ul class="space-y-2">
                @foreach ($documents as $document)
                    <li class="flex items-center justify-between gap-3 rounded-xl border border-gris-200 px-3 py-2 dark:border-azul-800">
                        <div class="flex min-w-0 items-center gap-2">
                            <flux:icon name="paper-clip" variant="outline" class="size-4 shrink-0 text-gris-400 dark:text-azul-200" />

                            <div class="min-w-0">
                                <span class="block truncate font-semibold text-gris-900 dark:text-blanco">
                                    {{ $document['document_name'] ?? '—' }}
                                </span>

                                <x-backoffice.tone-badge :tone="$this->statusTone($document['status'] ?? null)" class="mt-0.5">
                                    {{ $this->statusLabel($document['status'] ?? null) }}
                                </x-backoffice.tone-badge>
                            </div>
                        </div>

                        {{-- Relativa y armada aquí, no con el `download_url` que
                             devuelve la API: esa URL sale con el host de dentro
                             del contenedor (ver la nota en ⚡documents.blade.php). --}}
                        <flux:button
                            size="sm"
                            variant="ghost"
                            icon="arrow-down-tray"
                            class="shrink-0"
                            :label="__('Descargar documento')"
                            :href="route('documents.download', ['document' => $document['id']], absolute: false)"
                        />
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
