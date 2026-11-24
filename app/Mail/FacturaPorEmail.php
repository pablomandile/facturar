<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Comprobante;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Una factura autorizada, con su PDF adjunto. La arma y la manda solo
 * `EnviadorDeFacturas`.
 *
 * Sale desde la casilla de la app (con el nombre del emisor, para que el
 * cliente sepa de quién es) y las respuestas van al email de la cuenta.
 */
class FacturaPorEmail extends Mailable
{
    public function __construct(
        public readonly Comprobante $comprobante,
        public readonly string $asunto,
        public readonly string $texto,
        public readonly string $pdf,
        public readonly string $nombreDelPdf,
        public readonly string $responderA,
    ) {}

    public function envelope(): Envelope
    {
        $razonSocial = $this->comprobante->emisor->razon_social;

        return new Envelope(
            from: new Address((string) config('mail.from.address'), "{$razonSocial} vía ".config('app.name')),
            replyTo: [new Address($this->responderA, $razonSocial)],
            subject: $this->asunto,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.factura',
            text: 'mail.factura-texto',
            with: ['texto' => $this->texto],
        );
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->pdf, $this->nombreDelPdf)->withMime('application/pdf'),
        ];
    }
}
