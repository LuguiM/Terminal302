<?php

namespace App\Http\Requests\Bus;

use App\Models\TipoBus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('placa')) {
            $this->merge([
                'placa' => mb_strtoupper(trim((string) $this->input('placa'))),
            ]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ruta_id' => ['required', 'integer', Rule::exists('rutas', 'id')],
            'placa' => [
                'required',
                'string',
                'max:50',
                Rule::unique('buses', 'placa'),
                $this->salvadoranPublicTransportPlateRule(),
            ],
            'marca' => ['required', 'string', 'max:100'],
            'nombre_unidad' => ['nullable', 'string', 'max:100'],
            'capacidad' => ['required', 'integer', 'min:1'],
            'tipo_bus_id' => ['required', 'integer', Rule::exists('tipo_buses', 'id')],
            'operador_id' => ['prohibited'],
            'estado_id' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ruta_id.exists' => 'La ruta seleccionada no existe.',
            'tipo_bus_id.exists' => 'El tipo de bus seleccionado no existe.',
        ];
    }

    private function salvadoranPublicTransportPlateRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_scalar($value)) {
                return;
            }

            $tipoBus = TipoBus::query()->find($this->integer('tipo_bus_id'));

            if (! $tipoBus) {
                return;
            }

            $expectedPrefix = $this->expectedPlatePrefix((string) $tipoBus->nombre);

            if (! $expectedPrefix) {
                $fail('El tipo de servicio seleccionado no tiene una validacion de placa configurada.');

                return;
            }

            $plate = mb_strtoupper(trim((string) $value));

            if (! preg_match("/^{$expectedPrefix}-[A-Z0-9]{3,6}$/", $plate)) {
                $fail("La placa debe tener formato {$expectedPrefix}- seguido de 3 a 6 caracteres alfanumericos.");
            }
        };
    }

    private function expectedPlatePrefix(string $tipoBusNombre): ?string
    {
        $normalizedName = mb_strtolower($tipoBusNombre);

        return match ($normalizedName) {
            'bus', 'autobus' => 'AB',
            'microbus', 'coaster' => 'MB',
            default => null,
        };
    }
}
