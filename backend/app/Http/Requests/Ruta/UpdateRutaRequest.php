<?php

namespace App\Http\Requests\Ruta;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRutaRequest extends FormRequest
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
        $rutaId = $this->route('ruta');

        return [
            'ruta' => ['required', 'string', 'max:50', Rule::unique('rutas', 'ruta')->ignore($rutaId)],
            'denominacion' => ['required', 'string', 'max:255'],
            'tarifa' => ['required', 'numeric', 'min:0'],
            'estado_id' => ['prohibited'],
        ];
    }
}
