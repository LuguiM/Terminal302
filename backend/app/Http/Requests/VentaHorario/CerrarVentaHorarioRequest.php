<?php

namespace App\Http\Requests\VentaHorario;

use Illuminate\Foundation\Http\FormRequest;

class CerrarVentaHorarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'motivo_cierre' => ['nullable', 'string', 'max:1000'],
            'cerrada_por' => ['prohibited'],
            'fecha_cierre' => ['prohibited'],
            'venta_cerrada' => ['prohibited'],
            'total_tickets_vendidos' => ['prohibited'],
            'total_tickets_sobreventa' => ['prohibited'],
            'estado_id' => ['prohibited'],
        ];
    }
}
