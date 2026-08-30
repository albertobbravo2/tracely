<?php

namespace App\Http\Controllers\Api;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\ShipmentHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShipmentHistoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $histories = ShipmentHistory::query()
            // El listado del backoffice muestra el número de guía, no el id:
            // sin el eager load cada fila sería una consulta aparte.
            ->with('shipment:id,tracking_number')
            ->when(
                $request->integer('shipment_id'),
                fn ($query, $shipmentId) => $query->where('shipment_id', $shipmentId),
            )
            ->orderBy('recorded_at')
            ->paginate(15);

        return response()->json($histories);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shipment_id' => ['required', 'integer', 'exists:shipments,id'],
            'status' => ['required', Rule::enum(ShipmentStatus::class)],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'recorded_at' => ['required', 'date'],
        ]);

        return response()->json(ShipmentHistory::create($data), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(ShipmentHistory $shipmentHistory): JsonResponse
    {
        return response()->json($shipmentHistory);
    }

    /**
     * Update the specified resource in storage.
     *
     * Corregir un evento mal registrado (una ubicación equivocada, una fecha
     * mal tecleada). No se permite moverlo a otro envío: para eso se borra y
     * se crea donde corresponda.
     */
    public function update(Request $request, ShipmentHistory $shipmentHistory): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', 'required', Rule::enum(ShipmentStatus::class)],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'recorded_at' => ['sometimes', 'required', 'date'],
        ]);

        $shipmentHistory->update($data);

        return response()->json($shipmentHistory);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ShipmentHistory $shipmentHistory): JsonResponse
    {
        $shipmentHistory->delete();

        return response()->json(status: 204);
    }
}
