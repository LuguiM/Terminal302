<?php

namespace App\Http\Requests\Ticket;

use Illuminate\Foundation\Http\FormRequest;

class ValidarTicketRequest extends FormRequest
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
            'codigo_ticket' => ['required', 'string', 'max:255'],
            'observacion' => ['nullable', 'string', 'max:1000'],
            'ticket_id' => ['prohibited'],
            'validador_id' => ['prohibited'],
        ];
    }
}
