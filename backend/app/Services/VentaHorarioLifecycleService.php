<?php

namespace App\Services;

use App\Models\Estado;
use App\Models\VentaHorario;
use Carbon\CarbonImmutable;

class VentaHorarioLifecycleService
{
    private const OPERATION_TIMEZONE = 'America/El_Salvador';
    private const EXPIRED_REASON = 'Hora de salida alcanzada.';

    public function closeExpiredForToday(?Estado $activeStatus = null, ?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now(self::OPERATION_TIMEZONE);
        $activeStatus ??= Estado::activo();

        if (! $activeStatus) {
            return 0;
        }

        $expiredSales = VentaHorario::query()
            ->with('horario')
            ->where('fecha_operacion', $now->toDateString())
            ->where('venta_cerrada', false)
            ->where('estado_id', $activeStatus->id)
            ->whereHas('horario', fn ($query) => $query
                ->where('estado_id', $activeStatus->id)
                ->whereHas('dia', fn ($dayQuery) => $dayQuery->where('orden', $now->dayOfWeekIso))
                ->where('hora_salida', '<', $now->format('H:i')))
            ->get();

        $expiredSales->each(fn (VentaHorario $ventaHorario) => $this->closeExpired($ventaHorario, null, $now));

        return $expiredSales->count();
    }

    public function isExpiredForToday(VentaHorario $ventaHorario, ?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now(self::OPERATION_TIMEZONE);

        if ($ventaHorario->fecha_operacion?->toDateString() !== $now->toDateString()) {
            return false;
        }

        $horario = $ventaHorario->horario;

        if (! $horario) {
            return false;
        }

        return (int) $horario->dia?->orden === $now->dayOfWeekIso
            && $this->minutesFromTime((string) $horario->hora_salida) < $this->minutesFromTime($now->format('H:i'));
    }

    public function closeExpired(VentaHorario $ventaHorario, ?int $closedBy = null, ?CarbonImmutable $now = null): void
    {
        $now ??= CarbonImmutable::now(self::OPERATION_TIMEZONE);

        if ($ventaHorario->venta_cerrada) {
            return;
        }

        $ventaHorario->forceFill([
            'venta_cerrada' => true,
            'cerrada_por' => $closedBy,
            'fecha_cierre' => $now,
            'motivo_cierre' => self::EXPIRED_REASON,
        ])->save();
    }

    private function minutesFromTime(string $time): int
    {
        $parts = explode(':', $time);

        return ((int) ($parts[0] ?? 0) * 60) + (int) ($parts[1] ?? 0);
    }
}
