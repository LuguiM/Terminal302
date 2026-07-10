<?php

namespace App\Http\Requests\OperadorEmpleado;

use App\Models\OperadorEmpleado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOperadorEmpleadoRequest extends FormRequest
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
        /** @var OperadorEmpleado|null $empleado */
        $empleado = $this->route('empleado');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($empleado?->user_id),
            ],
            'role_id' => ['prohibited'],
            'estado_id' => ['prohibited'],
            'password' => ['prohibited'],
            'must_change_password' => ['prohibited'],
        ];
    }
}
