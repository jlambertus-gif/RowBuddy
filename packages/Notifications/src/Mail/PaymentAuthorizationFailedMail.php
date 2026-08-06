<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use RowBuddy\Notifications\Support\MoneyFormatter;
use RowBuddy\Notifications\ValueObjects\PushContent;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * ADR-025 §6 (`PaymentAuthorizationFailed` → buyer). Self-contained per
 * ADR-025 §9: reports the user-facing consequence and next step only —
 * never the underlying gateway failure detail (e.g. a raw Stripe decline
 * reason/code), which this class never even receives.
 */
final class PaymentAuthorizationFailedMail extends Mailable
{
    public function __construct(
        private readonly string $auctionReference,
        private readonly Money $amount,
        private readonly string $language,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('notifications.payment_authorization_failed.subject'));
    }

    public function content(): Content
    {
        $body = sprintf(
            '<p>%s</p><p>%s</p><p>%s</p>',
            e(__('notifications.payment_authorization_failed.greeting')),
            e(__('notifications.payment_authorization_failed.body', [
                'reference' => $this->auctionReference,
                'amount' => MoneyFormatter::format($this->amount, $this->language),
            ])),
            e(__('notifications.payment_authorization_failed.next_step')),
        );

        return (new Content)->htmlString($body);
    }

    /** See the identical note on AuctionWonMail::toPushContent(). */
    public function toPushContent(string $language): PushContent
    {
        return new PushContent(
            title: __('notifications.payment_authorization_failed.subject', [], $language),
            body: __('notifications.payment_authorization_failed.body', [
                'reference' => $this->auctionReference,
                'amount' => MoneyFormatter::format($this->amount, $language),
            ], $language),
        );
    }
}
