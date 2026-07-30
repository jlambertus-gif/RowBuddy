<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * ADR-025 §6 (`DisputeOpened` → seller). Surfaces only that a dispute
 * exists and that a response is expected — never the buyer's filed
 * `reason` text, which is not in ADR-025 §9's allowed field list.
 */
final class DisputeOpenedMail extends Mailable
{
    public function __construct(
        private readonly string $disputeReference,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('notifications.dispute_opened.subject'));
    }

    public function content(): Content
    {
        $body = sprintf(
            '<p>%s</p><p>%s</p><p>%s</p>',
            e(__('notifications.dispute_opened.greeting')),
            e(__('notifications.dispute_opened.body', ['reference' => $this->disputeReference])),
            e(__('notifications.dispute_opened.next_step')),
        );

        return (new Content)->htmlString($body);
    }
}
