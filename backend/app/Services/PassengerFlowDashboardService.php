<?php

namespace App\Services;

use App\Models\Operador;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class PassengerFlowDashboardService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function admin(array $filters): array
    {
        return $this->buildDashboard($filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function operador(array $filters, Operador $operador): array
    {
        return $this->buildDashboard($filters, $operador->id);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildDashboard(array $filters, ?int $operatorScopeId = null): array
    {
        $summary = $this->summary($filters, $operatorScopeId);

        return [
            'filtros' => $this->responseFilters($filters),
            'resumen' => $summary,
            'series' => [
                'por_dia' => $this->dailySeries($filters, $operatorScopeId),
            ],
            'rankings' => [
                'rutas' => $this->routeRanking($filters, $operatorScopeId),
                'horarios' => $this->scheduleRanking($filters, $operatorScopeId),
                'operadores' => $operatorScopeId ? [] : $this->operatorRanking($filters),
                'buses' => $operatorScopeId ? $this->busRanking($filters, $operatorScopeId) : [],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function summary(array $filters, ?int $operatorScopeId): array
    {
        $ticketTotals = $this->ticketsQuery($filters, $operatorScopeId)
            ->selectRaw('COUNT(tickets.id) as tickets_vendidos')
            ->selectRaw('COUNT(validaciones.id) as tickets_validados')
            ->selectRaw('COALESCE(SUM(CASE WHEN tickets.es_sobreventa THEN 1 ELSE 0 END), 0) as tickets_sobreventa')
            ->first();

        $saleTotals = $this->salesQuery($filters, $operatorScopeId)
            ->selectRaw('COUNT(DISTINCT ventas_horarios.id) as salidas_operadas')
            ->selectRaw('COALESCE(AVG(CASE WHEN buses.capacidad > 0 THEN (ventas_horarios.total_tickets_vendidos::decimal / buses.capacidad) * 100 ELSE 0 END), 0) as ocupacion_promedio')
            ->first();

        $sold = (int) ($ticketTotals->tickets_vendidos ?? 0);
        $validated = (int) ($ticketTotals->tickets_validados ?? 0);

        return [
            'tickets_vendidos' => $sold,
            'tickets_validados' => $validated,
            'porcentaje_validacion' => $sold > 0 ? round(($validated / $sold) * 100, 2) : 0.0,
            'tickets_sobreventa' => (int) ($ticketTotals->tickets_sobreventa ?? 0),
            'salidas_operadas' => (int) ($saleTotals->salidas_operadas ?? 0),
            'ocupacion_promedio' => round((float) ($saleTotals->ocupacion_promedio ?? 0), 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function dailySeries(array $filters, ?int $operatorScopeId): array
    {
        $rows = $this->ticketsQuery($filters, $operatorScopeId)
            ->selectRaw('ventas_horarios.fecha_operacion as fecha')
            ->selectRaw('COUNT(tickets.id) as tickets_vendidos')
            ->selectRaw('COUNT(validaciones.id) as tickets_validados')
            ->selectRaw('COALESCE(SUM(CASE WHEN tickets.es_sobreventa THEN 1 ELSE 0 END), 0) as tickets_sobreventa')
            ->groupBy('ventas_horarios.fecha_operacion')
            ->orderBy('ventas_horarios.fecha_operacion')
            ->get()
            ->keyBy(fn (object $row): string => CarbonImmutable::parse($row->fecha)->toDateString());

        return collect(CarbonPeriod::create($filters['start_date'], $filters['end_date']))
            ->map(function ($date) use ($rows): array {
                $key = CarbonImmutable::parse($date)->toDateString();
                $row = $rows->get($key);

                return [
                    'fecha' => $key,
                    'tickets_vendidos' => (int) ($row->tickets_vendidos ?? 0),
                    'tickets_validados' => (int) ($row->tickets_validados ?? 0),
                    'tickets_sobreventa' => (int) ($row->tickets_sobreventa ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function routeRanking(array $filters, ?int $operatorScopeId): array
    {
        return $this->ticketsQuery($filters, $operatorScopeId)
            ->selectRaw('rutas.id as ruta_id, rutas.ruta, rutas.denominacion')
            ->selectRaw('COUNT(tickets.id) as tickets_vendidos')
            ->selectRaw('COUNT(validaciones.id) as tickets_validados')
            ->selectRaw('COALESCE(SUM(CASE WHEN tickets.es_sobreventa THEN 1 ELSE 0 END), 0) as tickets_sobreventa')
            ->groupBy('rutas.id', 'rutas.ruta', 'rutas.denominacion')
            ->orderByDesc('tickets_vendidos')
            ->orderBy('rutas.ruta')
            ->limit(10)
            ->get()
            ->map(fn (object $row): array => [
                'ruta_id' => (int) $row->ruta_id,
                'ruta' => $row->ruta,
                'denominacion' => $row->denominacion,
                'tickets_vendidos' => (int) $row->tickets_vendidos,
                'tickets_validados' => (int) $row->tickets_validados,
                'tickets_sobreventa' => (int) $row->tickets_sobreventa,
                'porcentaje_validacion' => (int) $row->tickets_vendidos > 0
                    ? round(((int) $row->tickets_validados / (int) $row->tickets_vendidos) * 100, 2)
                    : 0.0,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function scheduleRanking(array $filters, ?int $operatorScopeId): array
    {
        return $this->ticketsQuery($filters, $operatorScopeId)
            ->selectRaw('horarios.id as horario_id, horarios.hora_salida, rutas.ruta, rutas.denominacion, operadores.nombre_comercial, buses.placa')
            ->selectRaw('COUNT(tickets.id) as tickets_vendidos')
            ->selectRaw('COUNT(validaciones.id) as tickets_validados')
            ->selectRaw('COALESCE(SUM(CASE WHEN tickets.es_sobreventa THEN 1 ELSE 0 END), 0) as tickets_sobreventa')
            ->groupBy('horarios.id', 'horarios.hora_salida', 'rutas.ruta', 'rutas.denominacion', 'operadores.nombre_comercial', 'buses.placa')
            ->orderByDesc('tickets_vendidos')
            ->orderBy('horarios.hora_salida')
            ->limit(10)
            ->get()
            ->map(fn (object $row): array => [
                'horario_id' => (int) $row->horario_id,
                'ruta' => $row->ruta,
                'denominacion' => $row->denominacion,
                'operador' => $row->nombre_comercial,
                'bus' => $row->placa,
                'hora_salida' => substr((string) $row->hora_salida, 0, 5),
                'tickets_vendidos' => (int) $row->tickets_vendidos,
                'tickets_validados' => (int) $row->tickets_validados,
                'tickets_sobreventa' => (int) $row->tickets_sobreventa,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function operatorRanking(array $filters): array
    {
        return $this->ticketsQuery($filters)
            ->selectRaw('operadores.id as operador_id, operadores.nombre_comercial')
            ->selectRaw('COUNT(tickets.id) as tickets_vendidos')
            ->selectRaw('COUNT(validaciones.id) as tickets_validados')
            ->selectRaw('COALESCE(SUM(CASE WHEN tickets.es_sobreventa THEN 1 ELSE 0 END), 0) as tickets_sobreventa')
            ->groupBy('operadores.id', 'operadores.nombre_comercial')
            ->orderByDesc('tickets_vendidos')
            ->orderBy('operadores.nombre_comercial')
            ->limit(10)
            ->get()
            ->map(fn (object $row): array => [
                'operador_id' => (int) $row->operador_id,
                'nombre_comercial' => $row->nombre_comercial,
                'tickets_vendidos' => (int) $row->tickets_vendidos,
                'tickets_validados' => (int) $row->tickets_validados,
                'tickets_sobreventa' => (int) $row->tickets_sobreventa,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function busRanking(array $filters, int $operatorScopeId): array
    {
        return $this->ticketsQuery($filters, $operatorScopeId)
            ->selectRaw('buses.id as bus_id, buses.placa, buses.marca, buses.nombre_unidad')
            ->selectRaw('COUNT(tickets.id) as tickets_vendidos')
            ->selectRaw('COUNT(validaciones.id) as tickets_validados')
            ->selectRaw('COALESCE(SUM(CASE WHEN tickets.es_sobreventa THEN 1 ELSE 0 END), 0) as tickets_sobreventa')
            ->groupBy('buses.id', 'buses.placa', 'buses.marca', 'buses.nombre_unidad')
            ->orderByDesc('tickets_vendidos')
            ->orderBy('buses.placa')
            ->limit(10)
            ->get()
            ->map(fn (object $row): array => [
                'bus_id' => (int) $row->bus_id,
                'placa' => $row->placa,
                'marca' => $row->marca,
                'nombre_unidad' => $row->nombre_unidad,
                'tickets_vendidos' => (int) $row->tickets_vendidos,
                'tickets_validados' => (int) $row->tickets_validados,
                'tickets_sobreventa' => (int) $row->tickets_sobreventa,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function ticketsQuery(array $filters, ?int $operatorScopeId = null): Builder
    {
        return DB::table('tickets')
            ->join('ventas_horarios', 'tickets.venta_horario_id', '=', 'ventas_horarios.id')
            ->join('horarios', 'ventas_horarios.horario_id', '=', 'horarios.id')
            ->join('rutas', 'horarios.ruta_id', '=', 'rutas.id')
            ->join('operadores', 'horarios.operador_id', '=', 'operadores.id')
            ->join('buses', 'horarios.bus_id', '=', 'buses.id')
            ->leftJoin('validaciones', 'validaciones.ticket_id', '=', 'tickets.id')
            ->whereBetween('ventas_horarios.fecha_operacion', [$filters['start_date'], $filters['end_date']])
            ->when($filters['ruta_id'] ?? null, fn (Builder $query, int $rutaId) => $query->where('horarios.ruta_id', $rutaId))
            ->when($operatorScopeId !== null, fn (Builder $query) => $query->where('horarios.operador_id', $operatorScopeId))
            ->when($operatorScopeId === null && ($filters['operador_id'] ?? null), fn (Builder $query) => $query->where('horarios.operador_id', $filters['operador_id']))
            ->when($operatorScopeId !== null && ($filters['bus_id'] ?? null), fn (Builder $query) => $query->where('horarios.bus_id', $filters['bus_id']));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function salesQuery(array $filters, ?int $operatorScopeId = null): Builder
    {
        return DB::table('ventas_horarios')
            ->join('horarios', 'ventas_horarios.horario_id', '=', 'horarios.id')
            ->join('buses', 'horarios.bus_id', '=', 'buses.id')
            ->whereBetween('ventas_horarios.fecha_operacion', [$filters['start_date'], $filters['end_date']])
            ->when($filters['ruta_id'] ?? null, fn (Builder $query, int $rutaId) => $query->where('horarios.ruta_id', $rutaId))
            ->when($operatorScopeId !== null, fn (Builder $query) => $query->where('horarios.operador_id', $operatorScopeId))
            ->when($operatorScopeId === null && ($filters['operador_id'] ?? null), fn (Builder $query) => $query->where('horarios.operador_id', $filters['operador_id']))
            ->when($operatorScopeId !== null && ($filters['bus_id'] ?? null), fn (Builder $query) => $query->where('horarios.bus_id', $filters['bus_id']));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function responseFilters(array $filters): array
    {
        return [
            'fecha_desde' => $filters['start_date'],
            'fecha_hasta' => $filters['end_date'],
            'modo' => $filters['mode'],
            'ruta_id' => $filters['ruta_id'],
            'operador_id' => $filters['operador_id'],
            'bus_id' => $filters['bus_id'],
        ];
    }
}
