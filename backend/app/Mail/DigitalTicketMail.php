<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DigitalTicketMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu ticket digital de Terminal302',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.digital-ticket',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! $this->ticket->ticket_image_path) {
            return [];
        }

        return [
            Attachment::fromStorageDisk(config('filesystems.default'), $this->ticket->ticket_image_path)
                ->as($this->ticket->codigo_ticket.'.png')
                ->withMime('image/png'),
        ];
    }
}
