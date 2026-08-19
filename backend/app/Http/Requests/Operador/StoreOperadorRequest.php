<?php

namespace App\Http\Requests\Operador;

use App\Models\TipoOperador;
use App\Rules\ValidDui;
use App\Rules\ValidNit;
use App\Rules\ValidSalvadoranPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOperadorRequest extends FormRequest
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
            'nombre_comercial' => ['required', 'string', 'max:255'],
            'razon_social' => ['nullable', 'string', 'max:255'],
            'representante_legal' => ['nullable', 'string', 'max:255'],
            'telefono' => ['required', 'string', new ValidSalvadoranPhone],
            'telefono_opcional' => ['nullable', 'string', new ValidSalvadoranPhone],
            'correo_administrativo' => ['nullable', 'email', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'nit' => ['nullable', 'string', new ValidNit, Rule::unique('operadores', 'nit')],
            'dui' => ['nullable', 'string', new ValidDui, Rule::unique('operadores', 'dui')],
            'estado_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'motivo_desactivacion' => ['prohibited'],
            'nombre' => ['prohibited'],
            'documento' => ['prohibited'],
            'correo' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $fields = [
            'nombre_comercial', 'razon_social', 'representante_legal', 'telefono',
            'telefono_opcional', 'correo_administrativo', 'direccion', 'nit', 'dui',
        ];
        $normalized = [];

        foreach ($fields as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $normalized[$field] = trim($this->input($field));
            }
        }

        $this->merge($normalized);
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

        foreach (['dui', 'telefono_opcional'] as $field) {
            if ($this->filled($field)) {
                $validator->errors()->add($field, 'Este campo no corresponde a un operador de tipo empresa.');
            }
        }
    }

    private function validatePersona(Validator $validator): void
    {
        if (blank($this->input('dui'))) {
            $validator->errors()->add('dui', 'El DUI es obligatorio para operadores de tipo persona.');
        }

        foreach (['razon_social', 'representante_legal', 'nit', 'correo_administrativo', 'direccion'] as $field) {
            if ($this->filled($field)) {
                $validator->errors()->add($field, 'Este campo no corresponde a un operador de tipo persona.');
            }
        }
    }
}
