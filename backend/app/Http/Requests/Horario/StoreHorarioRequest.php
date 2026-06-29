<?php

namespace App\Http\Requests\Horario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHorarioRequest extends FormRequest
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
            'bus_id' => ['required', 'integer', Rule::exists('buses', 'id')],
            'dia_id' => ['required', 'integer', Rule::exists('dias', 'id')],
            'hora_salida' => ['required', 'date_format:H:i'],
            'sobreventa_permitida' => ['required', 'boolean'],
            'estado_id' => ['prohibited'],
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
            'bus_id.exists' => 'El bus seleccionado no existe.',
            'dia_id.exists' => 'El dia seleccionado no existe.',
            'hora_salida.date_format' => 'La hora de salida debe tener formato HH:mm.',
        ];
    }
}
