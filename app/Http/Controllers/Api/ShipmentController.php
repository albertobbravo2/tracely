<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShipmentRequest;
use App\Http\Requests\UpdateShipmentRequest;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            ->when(
                $request->integer('company_id'),
                fn ($query, int $companyId) => $query->where('company_id', $companyId),
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
        $shipment = Shipment::create([
            ...$request->validated(),
            // Quien registra el pedido es siempre quien hace la petición.
            'sender_id' => $request->user()->id,
            // Si no se indica empresa, hereda la de quien lo registra; así un
            // agente no tiene que mandar su propio company_id en cada alta.
            'company_id' => $request->validated('company_id') ?? $request->user()->company_id,
        ]);

        return response()->json($shipment, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Shipment $shipment)
    {
        // Sin token válido solo se exponen los datos públicos de seguimiento,
        // sin histórico ni datos del remitente/destinatario.
        if (! $request->user('sanctum')) {
            return response()->json($shipment->only([
                'tracking_number',
                'status',
                'origin',
                'destination',
                'estimated_delivery_date',
            ]));
        }

        return response()->json(
            $shipment->load(['histories' => fn ($query) => $query->orderBy('recorded_at')])
        );
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
}
