<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InitialUserCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public const PURPOSE_INITIAL = 'initial';
    public const PURPOSE_RESET = 'reset';

    public function __construct(
        public User $user,
        public string $temporaryPassword,
        public string $purpose = self::PURPOSE_INITIAL,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->purpose === self::PURPOSE_RESET
                ? 'Contrasena restablecida en Terminal302'
                : 'Credenciales de acceso a Terminal302',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.initial-user-credentials',
        );
    }
}
