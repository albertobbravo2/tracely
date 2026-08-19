<?php

namespace App\Http\Requests;

use App\Enums\ShipmentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShipmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * La autorización vive en la ruta (`permission:crear pedido`), igual que en
     * el resto de la API: aquí solo se valida la forma de los datos.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * `sender_id` no se acepta del cliente a propósito: es quien registra el
     * pedido, y lo pone el controlador con el usuario autenticado. Aceptarlo
     * aquí dejaría dar de alta envíos a nombre de otro usuario.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tracking_number' => ['required', 'string', 'max:255', 'unique:shipments,tracking_number'],
            'receiver_name' => ['required', 'string', 'max:255'],
            'origin' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'string', 'max:255'],
            'estimated_delivery_date' => ['required', 'date'],
            // Opcional: la columna ya tiene "pendiente" como valor por defecto.
            'status' => ['sometimes', 'required', Rule::enum(ShipmentStatus::class)],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
        ];
    }
}
