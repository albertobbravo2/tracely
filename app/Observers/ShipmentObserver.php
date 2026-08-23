<?php

namespace App\Observers;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Dos responsabilidades sobre el ciclo de vida del envío:
 *
 * 1. Crear el primer evento del historial cuando el alta lo trae consigo
 *    (`Shipment::$initialHistory`), que es como lo manda el formulario de
 *    backoffice. Sin ese dato no se inventa nada.
 * 2. Mantener en Redis una copia de los envíos ya entregados: es el único
 *    estado que ya no cambia, así que sirve para responder `shipments.show`
 *    sin tocar Postgres. Cualquier otro estado se cachea. y se limpia si el
 *    envío deja de estar "entregado" o se borra, para no servir datos viejos.
 */
class ShipmentObserver
{
    private const CACHE_TTL_DAYS = 1;

    /**
     * Alta del envío: si viene con un primer evento de historial, se crea aquí.
     *
     * Se comprueba el dato, no el origen de la petición: un alta por API que no
     * mande `history` —y las factories de los tests— siguen creando el envío sin
     * historial, exactamente igual que antes.
     *
     * Corre antes que `saved()`, así que un envío que nazca ya "entregado" se
     * cachea con este evento ya dentro.
     */
    public function created(Shipment $shipment): void
    {
        if ($shipment->initialHistory === null) {
            return;
        }

        $shipment->histories()->create($shipment->initialHistory);
    }

    /**
     * Se dispara tanto en alta como en actualización: cubre el envío que
     * nace ya entregado y el que transiciona a entregado más tarde.
     */
    public function saved(Shipment $shipment): void
    {
        if ($shipment->status !== ShipmentStatus::Entregado) {
            $this->forget($shipment->tracking_number);

            return;
        }

        $this->remember($shipment);
    }

    public function deleted(Shipment $shipment): void
    {
        $this->forget($shipment->tracking_number);
    }

    /**
     * Un fallo de Redis nunca debe impedir guardar el envío: si la caché no
     * responde, se registra y se sigue sin ella.
     */
    private function remember(Shipment $shipment): void
    {
        try {
            Cache::store('redis')->put(
                self::cacheKey($shipment->tracking_number),
                $shipment->load(['histories' => fn ($query) => $query->orderBy('recorded_at')])->toArray(),
                now()->addDays(self::CACHE_TTL_DAYS),
            );
        } catch (\Throwable $e) {
            Log::warning('No se pudo cachear el envío en Redis.', [
                'tracking_number' => $shipment->tracking_number,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function forget(string $trackingNumber): void
    {
        try {
            Cache::store('redis')->forget(self::cacheKey($trackingNumber));
        } catch (\Throwable $e) {
            Log::warning('No se pudo limpiar la caché de Redis del envío.', [
                'tracking_number' => $trackingNumber,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function cacheKey(string $trackingNumber): string
    {
        return "shipment:{$trackingNumber}";
    }
}
