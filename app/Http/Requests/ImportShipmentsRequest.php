<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportShipmentsRequest extends FormRequest
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
     * Solo se valida el fichero: el contenido de cada fila lo valida el
     * controlador una a una, porque una fila mala no debe tumbar el import
     * entero —y un 422 aquí sí lo tumbaría.
     *
     * `txt` va junto a `csv` porque el tipo que detecta el servidor depende de
     * quién exportó el fichero: Excel y LibreOffice mandan `text/csv`, pero un
     * CSV escrito a mano llega como `text/plain` y sin esto se rechazaría.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => __('Elige un fichero CSV para importar.'),
            'file.mimes' => __('El fichero debe ser un CSV.'),
            'file.max' => __('El fichero no puede superar los 2 MB.'),
        ];
    }
}
