<?php

namespace App\Services;

use App\Models\Ticket;

class PublicTicketLookupService
{
    public function findByCode(string $codigoTicket): ?Ticket
    {
        return Ticket::query()
            ->with([
                'estado',
                'tipoEnvio',
                'ventaHorario.horario.ruta',
                'ventaHorario.horario.operador',
                'ventaHorario.horario.dia',
            ])
            ->where('codigo_ticket', $codigoTicket)
            ->first();
    }

    public function hasRequiredRelations(Ticket $ticket): bool
    {
        return (bool) (
            $ticket->estado
            && $ticket->tipoEnvio
            && $ticket->ventaHorario
            && $ticket->ventaHorario->horario
            && $ticket->ventaHorario->horario->ruta
            && $ticket->ventaHorario->horario->operador
            && $ticket->ventaHorario->horario->dia
        );
    }
}
