<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * ADR-025 §6 (`TransferConfirmed` → buyer and seller). Included
 * deliberately despite both parties being physically present at
 * confirmation (ADR-025 §6's own rationale) — a durable, transactional
 * confirmation record independent of the in-person handoff itself.
 */
final class TransferConfirmedMail extends Mailable
{
    public function __construct(
        private readonly string $transferReference,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('notifications.transfer_confirmed.subject'));
    }

    public function content(): Content
    {
        $body = sprintf(
            '<p>%s</p><p>%s</p><p>%s</p>',
            e(__('notifications.transfer_confirmed.greeting')),
            e(__('notifications.transfer_confirmed.body', ['reference' => $this->transferReference])),
            e(__('notifications.transfer_confirmed.next_step')),
        );

        return (new Content)->htmlString($body);
    }
}
