<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vínculo entre un envío y la cuenta del usuario final (pivote `shipment_user`).
 *
 * Lo usa el propio cliente para añadir a su lista un envío cuyo número de
 * seguimiento conoce. No lo usa el agente al dar de alta el pedido: en `store`
 * la pivote no se toca, porque en ese momento aún no se sabe qué cuenta —si es
 * que hay alguna— acabará siguiendo el envío.
 */
class ShipmentUserController extends Controller
{
    /**
     * Vincular con el envío la cuenta de quien hace la petición.
     */
    public function store(Request $request, Shipment $shipment): JsonResponse
    {
        // El id sale del token, nunca del payload: aceptarlo del cliente
        // dejaría vincular cuentas ajenas a un envío.
        $shipment->users()->syncWithoutDetaching([$request->user()->id]);

        return response()->json($shipment, 201);
    }

    /**
     * Desvincular del envío la cuenta de quien hace la petición.
     *
     * Solo deshace su propio vínculo: ni el envío, ni la cuenta, ni los
     * vínculos de otros usuarios se ven afectados.
     */
    public function destroy(Request $request, Shipment $shipment): JsonResponse
    {
        $shipment->users()->detach($request->user()->id);

        return response()->json(status: 204);
    }
}
