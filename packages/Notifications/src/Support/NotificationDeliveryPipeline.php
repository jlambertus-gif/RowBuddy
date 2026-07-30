<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Support;

use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Mailable;
use RowBuddy\Notifications\Contracts\NotificationDeliveryLedger;
use RowBuddy\Notifications\Contracts\RecipientContactLookup;
use RowBuddy\Notifications\Contracts\RecipientLocalePreferenceLookup;
use RowBuddy\Notifications\Exceptions\NotificationRecipientUnresolved;
use RowBuddy\Notifications\ValueObjects\NotificationType;

/**
 * The idempotent-delivery-plus-locale-resolution sequence every listener
 * in this module needs (ADR-025 §7/§10): check the ledger, resolve the
 * recipient's email and language through their approved read ports
 * only, render in that language, send, then record delivery — extracted
 * here once all eight approved notification types needed the exact same
 * sequence, rather than duplicated eight times.
 */
final class NotificationDeliveryPipeline
{
    public function __construct(
        private readonly NotificationDeliveryLedger $ledger,
        private readonly RecipientContactLookup $contacts,
        private readonly RecipientLocalePreferenceLookup $localePreferences,
        private readonly Mailer $mailer,
    ) {}

    /**
     * @param  callable(string $language): Mailable  $mailableFactory
     *
     * @throws NotificationRecipientUnresolved
     */
    public function deliver(
        string $domainEventId,
        string $recipientId,
        NotificationType $type,
        callable $mailableFactory,
    ): void {
        if ($this->ledger->alreadyDelivered($domainEventId, $recipientId, $type)) {
            return;
        }

        $email = $this->contacts->findEmailById($recipientId);

        if ($email === null) {
            throw new NotificationRecipientUnresolved(
                "{$type->value} for event [{$domainEventId}]: no email on file for recipient [{$recipientId}]."
            );
        }

        $language = $this->localePreferences->findByRecipientId($recipientId)?->language ?? 'en';

        $mailable = $mailableFactory($language)->locale($language);

        $this->mailer->to($email)->send($mailable);

        $this->ledger->recordDelivered($domainEventId, $recipientId, $type);
    }
}
