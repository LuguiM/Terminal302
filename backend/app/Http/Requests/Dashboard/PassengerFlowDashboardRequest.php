<?php

namespace App\Http\Requests\Dashboard;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PassengerFlowDashboardRequest extends FormRequest
{
    private const OPERATION_TIMEZONE = 'America/El_Salvador';

    private const MAX_RANGE_DAYS = 366;

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
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'fecha_desde' => ['nullable', 'date_format:Y-m-d'],
            'fecha_hasta' => ['nullable', 'date_format:Y-m-d'],
            'mes' => ['nullable', 'date_format:Y-m'],
            'ruta_id' => ['nullable', 'integer', 'exists:rutas,id'],
            'operador_id' => ['nullable', 'integer', 'exists:operadores,id'],
            'bus_id' => ['nullable', 'integer', 'exists:buses,id'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $hasDate = $this->filled('fecha');
                $hasMonth = $this->filled('mes');
                $hasRangeStart = $this->filled('fecha_desde');
                $hasRangeEnd = $this->filled('fecha_hasta');
                $hasRange = $hasRangeStart || $hasRangeEnd;
                $selectedModes = collect([$hasDate, $hasMonth, $hasRange])
                    ->filter()
                    ->count();

                if ($selectedModes > 1) {
                    $validator->errors()->add('fecha', 'Solo se permite un filtro temporal: fecha, mes o rango de fechas.');
                }

                if ($hasRangeStart xor $hasRangeEnd) {
                    $validator->errors()->add('fecha_desde', 'Debe enviar fecha_desde y fecha_hasta para filtrar por rango.');
                }

                if ($hasRangeStart && $hasRangeEnd) {
                    $start = CarbonImmutable::createFromFormat('Y-m-d', $this->string('fecha_desde')->toString(), self::OPERATION_TIMEZONE);
                    $end = CarbonImmutable::createFromFormat('Y-m-d', $this->string('fecha_hasta')->toString(), self::OPERATION_TIMEZONE);

                    if ($start && $end && $start->greaterThan($end)) {
                        $validator->errors()->add('fecha_hasta', 'La fecha final debe ser igual o posterior a la fecha inicial.');
                    }

                    if ($start && $end && $start->diffInDays($end) + 1 > self::MAX_RANGE_DAYS) {
                        $validator->errors()->add('fecha_hasta', 'El rango no puede superar 366 dias.');
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardFilters(): array
    {
        $mode = 'fecha';
        $start = CarbonImmutable::now(self::OPERATION_TIMEZONE)->startOfDay();
        $end = $start;

        if ($this->filled('fecha')) {
            $start = CarbonImmutable::createFromFormat('Y-m-d', $this->string('fecha')->toString(), self::OPERATION_TIMEZONE)->startOfDay();
            $end = $start;
        } elseif ($this->filled('mes')) {
            $mode = 'mes';
            $start = CarbonImmutable::createFromFormat('Y-m', $this->string('mes')->toString(), self::OPERATION_TIMEZONE)->startOfMonth();
            $end = $start->endOfMonth()->startOfDay();
        } elseif ($this->filled('fecha_desde') && $this->filled('fecha_hasta')) {
            $mode = 'rango';
            $start = CarbonImmutable::createFromFormat('Y-m-d', $this->string('fecha_desde')->toString(), self::OPERATION_TIMEZONE)->startOfDay();
            $end = CarbonImmutable::createFromFormat('Y-m-d', $this->string('fecha_hasta')->toString(), self::OPERATION_TIMEZONE)->startOfDay();
        }

        return [
            'mode' => $mode,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'ruta_id' => $this->filled('ruta_id') ? $this->integer('ruta_id') : null,
            'operador_id' => $this->filled('operador_id') ? $this->integer('operador_id') : null,
            'bus_id' => $this->filled('bus_id') ? $this->integer('bus_id') : null,
        ];
    }
}
