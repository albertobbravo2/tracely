<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vínculo entre un envío y la cuenta del usuario final (pivote `shipment_user`).
 *
 * `store` y `destroy` los usa el propio cliente para añadir o quitar de su
 * lista un envío cuyo número de seguimiento conoce, y solo tocan su propio
 * vínculo. No los usa el agente al dar de alta el pedido: en el alta la pivote
 * no se toca, porque en ese momento aún no se sabe qué cuenta —si es que hay
 * alguna— acabará siguiendo el envío.
 *
 * `index` y `detach`, en cambio, son de backoffice: miran y deshacen los
 * vínculos de cualquiera, así que van con `permission:` como el resto de la
 * API de gestión.
 */
class ShipmentUserController extends Controller
{
    /**
     * Los usuarios que siguen este envío.
     *
     * De backoffice, no del cliente: sirve para ver quién tiene el pedido en su
     * lista, no para consultar la propia.
     */
    public function index(Shipment $shipment): JsonResponse
    {
        return response()->json($shipment->users()->paginate(15));
    }

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

    /**
     * Deshacer el vínculo entre este envío y una cuenta cualquiera.
     *
     * Es el gemelo de backoffice de `destroy`: aquí el usuario llega por la
     * URL en vez de salir del token, porque quien lo usa no está quitándose a
     * sí mismo de la lista, sino administrando la de otro. Solo se borra la
     * fila de la pivote: ni el envío ni la cuenta se tocan.
     */
    public function detach(Shipment $shipment, User $user): JsonResponse
    {
        $shipment->users()->detach($user->id);

        return response()->json(status: 204);
    }
}
