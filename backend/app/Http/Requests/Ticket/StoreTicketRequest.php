<?php

namespace App\Http\Requests\Ticket;

use App\Models\Estado;
use App\Models\TipoEnvio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
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
            'venta_horario_id' => ['required', 'integer'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'tipo_envio_id' => ['required', 'integer', Rule::exists('tipo_envios', 'id')],
            'correo_destino' => ['nullable', 'email', 'max:255'],
            'telefono_destino' => ['nullable', 'string', 'max:50'],
            'tipo_entrega' => ['prohibited'],
            'vendedor_id' => ['prohibited'],
            'estado_id' => ['prohibited'],
            'es_sobreventa' => ['prohibited'],
            'codigo_ticket' => ['prohibited'],
            'ticket_plantilla_id' => ['prohibited'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->filled('tipo_envio_id')) {
                return;
            }

            $tipoEnvio = TipoEnvio::query()->find($this->integer('tipo_envio_id'));

            if (! $tipoEnvio) {
                return;
            }

            $activeStatus = Estado::activo();

            if (! $activeStatus) {
                $validator->errors()->add('tipo_envio_id', 'No se encontro el estado requerido: activo.');

                return;
            }

            if ((int) $tipoEnvio->estado_id !== (int) $activeStatus->id) {
                $validator->errors()->add('tipo_envio_id', 'El tipo de envio seleccionado no esta activo.');

                return;
            }

            if ($tipoEnvio->isDigital() && ! $this->filled('correo_destino')) {
                $validator->errors()->add('correo_destino', 'El correo destino es obligatorio cuando el tipo de envio es digital.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cantidad.min' => 'La cantidad debe ser mayor a 0.',
            'tipo_envio_id.exists' => 'El tipo de envio seleccionado no existe.',
        ];
    }
}
