<?php

namespace App\Http\Requests\Ruta;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRutaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ruta' => ['required', 'string', 'max:50', Rule::unique('rutas', 'ruta')],
            'denominacion' => ['required', 'string', 'max:255'],
            'tarifa' => ['required', 'numeric', 'min:0'],
            'estado_id' => ['prohibited'],
        ];
    }
}
