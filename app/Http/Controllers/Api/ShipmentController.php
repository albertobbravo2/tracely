<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShipmentRequest;
use App\Http\Requests\UpdateShipmentRequest;
use App\Models\Shipment;
use App\Observers\ShipmentObserver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ShipmentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * Los tres filtros son opcionales: sin ninguno devuelve la lista completa,
     * igual que antes. `ilike` es de Postgres, que es la base de este proyecto.
     */
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();

        $shipments = Shipment::query()
            ->when(
                $request->string('search')->trim()->value(),
                fn ($query, string $search) => $query->where(
                    fn ($query) => $query->where('tracking_number', 'ilike', "%{$search}%")
                        ->orWhere('receiver_name', 'ilike', "%{$search}%")
                        ->orWhere('origin', 'ilike', "%{$search}%")
                        ->orWhere('destination', 'ilike', "%{$search}%"),
                ),
            )
            ->when(
                $request->string('status')->value(),
                fn ($query, string $status) => $query->where('status', $status),
            )
            // Quien no es superadministrador solo ve los pedidos de su propia
            // empresa: el filtro `company_id` de la petición se ignora para
            // ellos, así no pueden asomarse a otra pidiéndola por parámetro.
            ->when(
                ! $actor->hasRole('superadministrador'),
                fn ($query) => $query->where('company_id', $actor->company_id),
                fn ($query) => $query->when(
                    $request->integer('company_id'),
                    fn ($query, int $companyId) => $query->where('company_id', $companyId),
                ),
            )
            ->latest('id')
            ->paginate(15);

        return response()->json($shipments);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreShipmentRequest $request): JsonResponse
    {
        $shipment = new Shipment([
            // `history` no es una columna del envío: se excluye del fill y viaja
            // aparte hasta el observer.
            ...$request->safe()->except('history'),
            // Quien registra el pedido es siempre quien hace la petición.
            'sender_id' => $request->user()->id,
            // Si no se indica empresa, hereda la de quien lo registra; así un
            // agente no tiene que mandar su propio company_id en cada alta.
            'company_id' => $request->validated('company_id') ?? $request->user()->company_id,
        ]);

        // El primer evento del historial lo crea `ShipmentObserver::created()`,
        // que solo ve el modelo: por eso el dato se cuelga aquí antes de guardar.
        $shipment->initialHistory = $request->validated('history');

        $shipment->save();

        return response()->json($shipment, 201);
    }

    /**
     * Display the specified resource.
     *
     * `$shipment` llega como string (ver comentario en routes/api.php): primero
     * se busca en Redis y solo se cae a Postgres si no hay nada cacheado. Solo
     * los envíos "entregado" llegan a estar en caché (los escribe
     * `ShipmentObserver`), así que un miss aquí es normal para el resto.
     */
    public function show(Request $request, string $shipment): JsonResponse
    {
        $cached = $this->fromCache($shipment);

        if ($cached !== null) {
            // Mismo recorte público/privado que la rama de base de datos: la
            // caché guarda el envío completo, y aquí se decide cuánto de eso
            // le llega a la respuesta según haya o no sesión.
            return response()->json(
                $request->user('sanctum') ? $cached : Arr::only($cached, [
                    'tracking_number',
                    'status',
                    'origin',
                    'destination',
                    'estimated_delivery_date',
                ])
            );
        }

        $model = Shipment::where('tracking_number', $shipment)->firstOrFail();

        // Sin token válido solo se exponen los datos públicos de seguimiento,
        // sin histórico ni datos del remitente/destinatario.
        if (! $request->user('sanctum')) {
            return response()->json($model->only([
                'tracking_number',
                'status',
                'origin',
                'destination',
                'estimated_delivery_date',
            ]));
        }

        // Los documentos viajan con el mismo criterio que el histórico: solo en
        // la rama autenticada. La rama pública de arriba es una lista blanca de
        // campos, así que añadirlos aquí no los expone a quien no tiene sesión.
        // Lo que sí sigue protegido por `permission:ver documento` es la
        // descarga (`documents.download`): esto solo enseña qué hay adjunto.
        return response()->json(
            $model->load([
                'histories' => fn ($query) => $query->orderBy('recorded_at'),
                'documents' => fn ($query) => $query->orderBy('id'),
            ])
        );
    }

    /**
     * Un fallo de Redis nunca debe tumbar la consulta pública: si la caché no
     * responde, se registra y se sigue a Postgres como si hubiera sido un miss.
     *
     * @return array<string, mixed>|null
     */
    private function fromCache(string $trackingNumber): ?array
    {
        try {
            return Cache::store('redis')->get(ShipmentObserver::cacheKey($trackingNumber));
        } catch (\Throwable $e) {
            Log::warning('No se pudo leer la caché de Redis del envío.', [
                'tracking_number' => $trackingNumber,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateShipmentRequest $request, Shipment $shipment): JsonResponse
    {
        $shipment->update($request->validated());

        return response()->json($shipment);
    }

    /**
     * Remove the specified resource from storage.
     *
     * El historial y los vínculos con usuarios caen en cascada por FK. Los
     * documentos también, pero sus ficheros en disco no: ver DocumentController.
     */
    public function destroy(Shipment $shipment): JsonResponse
    {
        $shipment->delete();

        return response()->json(status: 204);
    }

    /**
     * Llevar a la pantalla de detalle de un pedido a partir de su guía.
     *
     * No devuelve JSON como el resto del controlador: es la única acción de
     * cara al navegador, y solo redirige. Que el pedido exista o no lo resuelve
     * la pantalla de destino, que ya pide el dato a `shipments.show`.
     */
}
