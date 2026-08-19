<?php

namespace App\Http\Requests;

use App\Enums\ShipmentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShipmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * La autorización vive en la ruta (`permission:editar pedido`), igual que en
     * el resto de la API: aquí solo se valida la forma de los datos.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Todo es `sometimes` para permitir PATCH parciales. `sender_id` no se
     * puede cambiar: es el registro de quién dio de alta el envío.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tracking_number' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                // El binding de la ruta es por tracking_number, así que sin este
                // ignore() un PUT que reenvíe el mismo número chocaría consigo.
                Rule::unique('shipments', 'tracking_number')->ignore($this->route('shipment')),
            ],
            'receiver_name' => ['sometimes', 'required', 'string', 'max:255'],
            'origin' => ['sometimes', 'required', 'string', 'max:255'],
            'destination' => ['sometimes', 'required', 'string', 'max:255'],
            'estimated_delivery_date' => ['sometimes', 'required', 'date'],
            'status' => ['sometimes', 'required', Rule::enum(ShipmentStatus::class)],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
        ];
    }
}
