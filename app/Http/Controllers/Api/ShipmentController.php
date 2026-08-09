<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShipmentRequest;
use App\Http\Requests\UpdateShipmentRequest;
use App\Models\Shipment;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Shipment::paginate(15));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreShipmentRequest $request)
    {
        //
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
    public function update(UpdateShipmentRequest $request, Shipment $shipment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Shipment $shipment)
    {
        //
    }
}
