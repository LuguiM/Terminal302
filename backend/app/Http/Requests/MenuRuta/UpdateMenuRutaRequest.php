<?php

namespace App\Http\Requests\MenuRuta;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMenuRutaRequest extends FormRequest
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
            'titulo' => ['sometimes', 'required', 'string', 'max:255'],
            'ruta' => ['sometimes', 'nullable', 'string', 'max:255'],
            'orden' => ['sometimes', 'required', 'numeric'],
            'icono' => ['nullable', 'string', 'max:100'],
            'visible' => ['sometimes', 'boolean'],
            'requiere_autenticacion' => ['sometimes', 'boolean'],
            'dependencia' => ['nullable', 'integer', Rule::exists('menu_rutas', 'id')],
            'role_id' => ['sometimes', 'required', 'integer', Rule::exists('roles', 'id')],
            'base_url' => ['nullable', 'url', 'max:255'],
            'estado_id' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role_id.exists' => 'El rol seleccionado no existe.',
            'dependencia.exists' => 'La dependencia seleccionada no existe.',
            'base_url.url' => 'La URL base no es valida.',
        ];
    }
}
