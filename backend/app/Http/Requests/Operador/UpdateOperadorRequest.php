<?php

namespace App\Http\Requests\Operador;

use App\Models\TipoOperador;
use App\Models\Operador;
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
        $routeOperador = $this->route('operador');
        $operadorId = $routeOperador instanceof Operador ? $routeOperador->id : $routeOperador;

        return [
            'tipo_operador_id' => ['required', 'integer', Rule::exists('tipo_operadores', 'id')],
            'nombre_comercial' => ['required', 'string', 'max:255'],
            'razon_social' => ['nullable', 'string', 'max:255'],
            'representante_legal' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'telefono_opcional' => ['nullable', 'string', 'max:50'],
            'correo_administrativo' => ['nullable', 'email', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'nit' => ['nullable', 'string', 'regex:/^\d{4}-\d{6}-\d{3}-\d$/', Rule::unique('operadores', 'nit')->ignore($operadorId)],
            'dui' => ['nullable', 'string', 'regex:/^\d{8}-\d$/', Rule::unique('operadores', 'dui')->ignore($operadorId)],
            'estado_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'motivo_desactivacion' => ['prohibited'],
            'nombre' => ['prohibited'],
            'documento' => ['prohibited'],
            'correo' => ['prohibited'],
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
                $this->validateEmpresa($validator);
                return;
            }

            if ($tipoNombre === 'persona') {
                $this->validatePersona($validator);
                return;
            }

            $validator->errors()->add('tipo_operador_id', 'El tipo de operador no es valido para este registro.');
        });
    }

    private function validateEmpresa(Validator $validator): void
    {
        if (blank($this->input('razon_social'))) {
            $validator->errors()->add('razon_social', 'La razon social es obligatoria para operadores de tipo empresa.');
        }

        if (blank($this->input('representante_legal'))) {
            $validator->errors()->add('representante_legal', 'El representante legal es obligatorio para operadores de tipo empresa.');
        }

        if (blank($this->input('nit'))) {
            $validator->errors()->add('nit', 'El NIT es obligatorio para operadores de tipo empresa.');
        }
    }

    private function validatePersona(Validator $validator): void
    {
        if (blank($this->input('dui'))) {
            $validator->errors()->add('dui', 'El DUI es obligatorio para operadores de tipo persona.');
        }

        if (blank($this->input('telefono'))) {
            $validator->errors()->add('telefono', 'El telefono es obligatorio para operadores de tipo persona.');
        }
    }
}
