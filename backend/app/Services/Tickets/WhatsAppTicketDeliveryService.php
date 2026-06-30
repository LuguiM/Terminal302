<?php

namespace App\Services\Tickets;

use Illuminate\Support\Collection;

class WhatsAppTicketDeliveryService
{
    /**
     * @param  Collection<int, \App\Models\Ticket>  $tickets
     */
    public function prepare(string $telefonoDestino, Collection $tickets): void
    {
        // Placeholder para futura integracion con WhatsApp Business API.
        // Por ahora no realiza llamadas externas ni persiste estado adicional.
    }
}
