<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use RowBuddy\Notifications\Support\MoneyFormatter;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * ADR-025 §6 (`AuctionWon` → winner). Self-contained per ADR-025 §9:
 * exactly three fields, each individually named and rendered here — the
 * auction reference, the locale-formatted winning amount, and a fixed
 * next-step instruction. No raw event payload is ever passed to this
 * class or serialized into the email.
 *
 * Rendered in the recipient's own resolved language (ADR-025 §10) — the
 * caller must set `->locale($language)` on this instance before sending
 * so every `__()` call below resolves in that locale, and must pass the
 * same `$language` into the constructor so {@see MoneyFormatter} applies
 * matching number-formatting conventions.
 */
final class AuctionWonMail extends Mailable
{
    public function __construct(
        private readonly string $auctionReference,
        private readonly Money $winningAmount,
        private readonly string $language,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('notifications.auction_won.subject'));
    }

    public function content(): Content
    {
        $body = sprintf(
            '<p>%s</p><p>%s</p><p>%s</p>',
            e(__('notifications.auction_won.greeting')),
            e(__('notifications.auction_won.body', [
                'reference' => $this->auctionReference,
                'amount' => MoneyFormatter::format($this->winningAmount, $this->language),
            ])),
            e(__('notifications.auction_won.next_step')),
        );

        return (new Content)->htmlString($body);
    }
}
