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
}
