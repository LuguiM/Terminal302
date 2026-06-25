<?php

namespace App\Http\Requests\Operador;

use App\Models\TipoOperador;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateOperadorRequest extends FormRequest
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
            'tipo_operador_id' => ['required', 'integer', Rule::exists('tipo_operadores', 'id')],
            'nombre' => ['required', 'string', 'max:255'],
            'razon_social' => ['nullable', 'string', 'max:255'],
            'representante_legal' => ['nullable', 'string', 'max:255'],
            'documento' => ['nullable', 'string', 'max:255'],
            'telefono' => ['required', 'string', 'max:50'],
            'correo' => ['required', 'email', 'max:255'],
            'direccion' => ['required', 'string', 'max:255'],
            'estado_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'motivo_desactivacion' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('tipo_operador_id')) {
                return;
            }

            $tipoOperador = TipoOperador::query()->find($this->integer('tipo_operador_id'));
            $tipoNombre = mb_strtolower((string) $tipoOperador?->nombre);

            if ($tipoNombre === 'empresa') {
                if (blank($this->input('razon_social'))) {
                    $validator->errors()->add('razon_social', 'La razon social es obligatoria para operadores de tipo empresa.');
                }

                if (blank($this->input('representante_legal'))) {
                    $validator->errors()->add('representante_legal', 'El representante legal es obligatorio para operadores de tipo empresa.');
                }

                return;
            }

            if ($tipoNombre !== 'persona') {
                $validator->errors()->add('tipo_operador_id', 'El tipo de operador no es valido para este registro.');
            }
        });
    }
}
