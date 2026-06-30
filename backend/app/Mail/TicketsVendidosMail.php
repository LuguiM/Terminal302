<?php

namespace App\Mail;

use App\Models\VentaHorario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class TicketsVendidosMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, \App\Models\Ticket>  $tickets
     */
    public function __construct(
        public Collection $tickets,
        public VentaHorario $ventaHorario,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tickets emitidos en Terminal302',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tickets-vendidos',
        );
    }
}
