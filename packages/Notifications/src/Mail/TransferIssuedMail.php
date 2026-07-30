<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * ADR-025 §6 (`TransferIssued` → buyer and seller). Self-contained per
 * ADR-025 §9: the transfer reference and a next-step instruction only.
 */
final class TransferIssuedMail extends Mailable
{
    public function __construct(
        private readonly string $transferReference,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('notifications.transfer_issued.subject'));
    }

    public function content(): Content
    {
        $body = sprintf(
            '<p>%s</p><p>%s</p><p>%s</p>',
            e(__('notifications.transfer_issued.greeting')),
            e(__('notifications.transfer_issued.body', ['reference' => $this->transferReference])),
            e(__('notifications.transfer_issued.next_step')),
        );

        return (new Content)->htmlString($body);
    }
}
