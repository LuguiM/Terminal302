<?php

namespace App\Http\Requests\OperadorEmpleado;

use Illuminate\Foundation\Http\FormRequest;

class ToggleOperadorEmpleadoStatusRequest extends FormRequest
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
            'motivo_desactivacion' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
