<?php

use App\Enums\ShipmentStatus;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

new class extends Component
{
    /** @var list<array<string, mixed>> */
    public array $shipments = [];

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->load();
    }

    /**
     * Consultar la API interna por los envíos vinculados a la cuenta de
     * quien ve el dashboard. Mismo patrón que ⚡searchfield: llamada por HTTP
     * a nuestra propia API en vez de a Eloquent directamente, para heredar
     * de ahí las reglas de visibilidad en vez de reimplementarlas aquí.
     */
    private function load(): void
    {
        $this->errorMessage = null;

        $path = route('users.myshipments', absolute: false);

        $pending = Http::acceptJson()
            ->timeout(10)
            ->baseUrl(config('services.internal_api.url'));

        // La llamada sale del servidor, sin la sesión del navegador: le
        // acuñamos un token de un minuto para que la API reconozca al
        // usuario y devuelva su propia lista.
        $token = auth()->user()->createToken('my-shipments', ['shipment:read'], now()->addMinute());

        try {
            $response = $pending->withToken($token->plainTextToken)->get($path);
        } catch (ConnectionException) {
            $this->errorMessage = __('No pudimos conectar con el servicio de pedidos. Inténtalo de nuevo en unos segundos.');

            return;
        } finally {
            $token->accessToken->delete();
        }

        if ($response->failed()) {
            $this->errorMessage = __('No pudimos cargar tus pedidos ahora mismo.');

            return;
        }

        $this->shipments = $response->json('data') ?? [];
    }

    /**
     * Etiqueta y clases del badge de estado. Misma paleta que ⚡searchfield:
     * `ok` entregado, `alerta` incidencia, `azul` en tránsito/aduana,
     * `gris` pendiente o cualquier estado sin reconocer.
     *
     * @return array<string, string>
     */
    private function statusBadge(?string $status): array
    {
        return match (ShipmentStatus::tryFrom((string) $status)) {
            ShipmentStatus::Entregado => ['badge' => '!bg-ok-fondo !text-ok-fuerte'],
            ShipmentStatus::EnTransito, ShipmentStatus::EnAduana => ['badge' => '!bg-azul-050 !text-azul-800'],
            ShipmentStatus::Incidencia => ['badge' => '!bg-alerta-fondo !text-alerta-fuerte'],
            default => ['badge' => '!bg-gris-050 !text-gris-600'],
        } + [
            'label' => ShipmentStatus::tryFrom((string) $status)?->label() ?? __('Estado desconocido'),
        ];
    }
};
?>

<div>
    <flux:heading size="lg" class="text-gris-900 dark:text-blanco">{{ __('Mis pedidos') }}</flux:heading>

    @if ($errorMessage)
        <div class="mt-4 rounded-xl border border-gris-200 bg-gris-050 px-4 py-3 dark:border-azul-800 dark:bg-azul-900">
            <flux:text class="text-gris-600 dark:text-azul-100">{{ $errorMessage }}</flux:text>
        </div>
    @elseif (empty($shipments))
        <div class="mt-4 rounded-xl border border-gris-200 bg-gris-050 px-4 py-3 dark:border-azul-800 dark:bg-azul-900">
            <flux:text class="text-gris-600 dark:text-azul-100">{{ __('Todavía no tienes ningún pedido vinculado.') }}</flux:text>
        </div>
    @else
        <flux:table class="mt-4">
            <flux:table.columns>
                <flux:table.column>{{ __('Guía') }}</flux:table.column>
                <flux:table.column>{{ __('Destino') }}</flux:table.column>
                <flux:table.column>{{ __('Estado') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($shipments as $shipment)
                    <flux:table.row :key="$shipment['id']">
                        <flux:table.cell class="font-semibold text-gris-900 dark:text-blanco">
                            {{ $shipment['tracking_number'] }}
                        </flux:table.cell>
                        <flux:table.cell>{{ $shipment['destination'] }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge rounded size="sm" class="{{ $this->statusBadge($shipment['status'])['badge'] }}">
                                {{ $this->statusBadge($shipment['status'])['label'] }}
                            </flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
