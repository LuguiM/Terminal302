<?php

namespace App\Http\Requests\Horario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BusesPorRutaOperadorRequest extends FormRequest
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
            'operador_id' => ['required', 'integer', Rule::exists('operadores', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ruta_id.exists' => 'La ruta seleccionada no existe.',
            'operador_id.exists' => 'El operador seleccionado no existe.',
        ];
    }
}
