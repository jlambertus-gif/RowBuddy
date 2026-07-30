<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * ADR-025 §6 (`TransferExpired` → buyer and seller). Content must remain
 * symmetric and blame-free (ADR-025 Consequences) — mirrors ADR-018's
 * no-fault posture; this class never attributes fault for either party.
 */
final class TransferExpiredMail extends Mailable
{
    public function __construct(
        private readonly string $transferReference,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('notifications.transfer_expired.subject'));
    }

    public function content(): Content
    {
        $body = sprintf(
            '<p>%s</p><p>%s</p><p>%s</p>',
            e(__('notifications.transfer_expired.greeting')),
            e(__('notifications.transfer_expired.body', ['reference' => $this->transferReference])),
            e(__('notifications.transfer_expired.next_step')),
        );

        return (new Content)->htmlString($body);
    }
}
