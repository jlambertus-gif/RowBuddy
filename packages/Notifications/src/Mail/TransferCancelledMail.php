<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use RowBuddy\Notifications\ValueObjects\PushContent;

/**
 * ADR-025 §6 (`TransferCancelled` → buyer and seller). Content must
 * remain symmetric and blame-free (ADR-025 Consequences), mirroring
 * ADR-018's no-fault posture — this class deliberately never accepts or
 * surfaces the event's own internal `reason` field.
 */
final class TransferCancelledMail extends Mailable
{
    public function __construct(
        private readonly string $transferReference,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('notifications.transfer_cancelled.subject'));
    }

    public function content(): Content
    {
        $body = sprintf(
            '<p>%s</p><p>%s</p><p>%s</p>',
            e(__('notifications.transfer_cancelled.greeting')),
            e(__('notifications.transfer_cancelled.body', ['reference' => $this->transferReference])),
            e(__('notifications.transfer_cancelled.next_step')),
        );

        return (new Content)->htmlString($body);
    }

    /** See the identical note on AuctionWonMail::toPushContent(). */
    public function toPushContent(string $language): PushContent
    {
        return new PushContent(
            title: __('notifications.transfer_cancelled.subject', [], $language),
            body: __('notifications.transfer_cancelled.body', ['reference' => $this->transferReference], $language),
        );
    }
}
