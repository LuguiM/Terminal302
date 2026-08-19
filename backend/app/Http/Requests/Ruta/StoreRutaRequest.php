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

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->exists('ruta') && is_string($this->input('ruta'))) {
            $normalized['ruta'] = mb_strtoupper(trim($this->input('ruta')));
        }

        if ($this->exists('denominacion') && is_string($this->input('denominacion'))) {
            $normalized['denominacion'] = mb_convert_case(
                trim($this->input('denominacion')),
                MB_CASE_TITLE,
                'UTF-8',
            );
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ruta' => [
                'required',
                'string',
                'max:50',
                'regex:/^\d+(?:-?[A-Z0-9])?$/',
                Rule::unique('rutas', 'ruta'),
            ],
            'denominacion' => ['required', 'string', 'max:255'],
            'tarifa' => ['required', 'numeric', 'min:0'],
            'estado_id' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ruta.regex' => 'El codigo de ruta debe contener numeros y puede finalizar con una letra o un numero, con guion opcional (ejemplo: 302-B).',
        ];
    }
}
