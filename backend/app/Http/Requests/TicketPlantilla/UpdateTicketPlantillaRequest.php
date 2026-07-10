<?php

namespace App\Http\Requests\TicketPlantilla;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketPlantillaRequest extends FormRequest
{
    private const LOCATION_FIELDS = [
        'qr_location',
        'precio_location',
        'fecha_hora_location',
        'asiento_location',
        'codigo_ticket_location',
        'ruta_location',
        'salida_location',
        'operador_location',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizedLocations());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $width = (int) config('ticket.ticket_template_width', 1000);
        $height = (int) config('ticket.ticket_template_height', 500);
        $maxSizeKb = (int) config('ticket.ticket_template_max_size_kb', 10240);

        $rules = [
            'nombre' => ['required', 'string', 'max:255'],
            'image' => [
                'nullable',
                'image',
                "max:{$maxSizeKb}",
                Rule::dimensions()
                    ->width($width)
                    ->height($height),
            ],
            'es_predeterminada' => ['nullable', 'boolean'],
            'estado_id' => ['prohibited'],
            'image_path' => ['prohibited'],
        ];

        foreach (self::LOCATION_FIELDS as $field) {
            $rules[$field] = [
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_array($value)) {
                        $fail("El campo {$attribute} debe contener JSON valido.");
                    }
                },
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $width = (int) config('ticket.ticket_template_width', 1000);
        $height = (int) config('ticket.ticket_template_height', 500);
        $maxSizeKb = (int) config('ticket.ticket_template_max_size_kb', 10240);
        $maxSizeMb = max(1, (int) ceil($maxSizeKb / 1024));

        return [
            'image.image' => 'El archivo debe ser una imagen valida.',
            'image.max' => "La imagen no debe superar {$maxSizeMb} MB.",
            'image.dimensions' => "La imagen debe tener dimensiones exactas de {$width}x{$height} px.",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedLocations(): array
    {
        $locations = [];

        foreach (self::LOCATION_FIELDS as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = $this->input($field);

            if ($value === null || is_array($value)) {
                $locations[$field] = $value;

                continue;
            }

            if (is_string($value)) {
                $decoded = json_decode($value, true);
                $locations[$field] = json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
            }
        }

        return $locations;
    }
}
