<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use RowBuddy\Disputes\ValueObjects\DisputeResolutionOutcome;
use RowBuddy\Notifications\Support\MoneyFormatter;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * ADR-025 §6 (`DisputeResolved` → buyer and seller). Reports only the
 * administrator's chosen outcome (ADR-021 §6) and the refund amount if
 * any — never `resolutionNotes` (unrestricted admin-authored free text)
 * or `evidenceFoundFraudulent` (an internal, inert observation, ADR-021
 * §8), neither of which is in ADR-025 §9's allowed field list.
 */
final class DisputeResolvedMail extends Mailable
{
    public function __construct(
        private readonly string $disputeReference,
        private readonly DisputeResolutionOutcome $outcome,
        private readonly ?Money $refundAmount,
        private readonly string $language,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('notifications.dispute_resolved.subject'));
    }

    public function content(): Content
    {
        $outcomeText = __("notifications.dispute_resolved.outcomes.{$this->outcome->value}");

        $lines = [
            e(__('notifications.dispute_resolved.greeting')),
            e(__('notifications.dispute_resolved.body', [
                'reference' => $this->disputeReference,
                'outcome' => $outcomeText,
            ])),
        ];

        if ($this->refundAmount !== null) {
            $lines[] = e(__('notifications.dispute_resolved.refund_note', [
                'amount' => MoneyFormatter::format($this->refundAmount, $this->language),
            ]));
        }

        $body = '<p>'.implode('</p><p>', $lines).'</p>';

        return (new Content)->htmlString($body);
    }
}
