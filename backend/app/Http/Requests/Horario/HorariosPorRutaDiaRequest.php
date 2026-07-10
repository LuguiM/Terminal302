<?php

namespace App\Http\Requests\Horario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HorariosPorRutaDiaRequest extends FormRequest
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
            'ruta_id' => ['required', 'integer', Rule::exists('rutas', 'id')],
            'dia_id' => ['required', 'integer', Rule::exists('dias', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ruta_id.exists' => 'La ruta seleccionada no existe.',
            'dia_id.exists' => 'El dia seleccionado no existe.',
        ];
    }
}
