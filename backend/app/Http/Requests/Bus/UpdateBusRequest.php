<?php

namespace App\Http\Requests\Bus;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusRequest extends FormRequest
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
        $busId = $this->route('bus');

        return [
            'ruta_id' => ['required', 'integer', Rule::exists('rutas', 'id')],
            'placa' => ['required', 'string', 'max:50', Rule::unique('buses', 'placa')->ignore($busId)],
            'marca' => ['required', 'string', 'max:100'],
            'nombre_unidad' => ['nullable', 'string', 'max:100'],
            'capacidad' => ['required', 'integer', 'min:1'],
            'tipo_bus_id' => ['required', 'integer', Rule::exists('tipo_buses', 'id')],
            'operador_id' => ['prohibited'],
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
            'tipo_bus_id.exists' => 'El tipo de bus seleccionado no existe.',
        ];
    }
}
