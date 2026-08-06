<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Support;

use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Mailable;
use RowBuddy\Notifications\Contracts\DeviceTokenRepository;
use RowBuddy\Notifications\Contracts\NotificationDeliveryLedger;
use RowBuddy\Notifications\Contracts\PushNotificationSender;
use RowBuddy\Notifications\Contracts\RecipientContactLookup;
use RowBuddy\Notifications\Contracts\RecipientLocalePreferenceLookup;
use RowBuddy\Notifications\Exceptions\NotificationRecipientUnresolved;
use RowBuddy\Notifications\ValueObjects\NotificationType;
use RowBuddy\Notifications\ValueObjects\PushContent;

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
        private readonly DeviceTokenRepository $deviceTokens,
        private readonly PushNotificationSender $pushSender,
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

    /**
     * The second channel (ADR-028 Decision 6) on the exact same eight
     * (event, recipient) pairs `deliver()` already handles — extended
     * by, not parallel to, the existing machinery, including the
     * identical locale-resolution step (ADR-028 Decision 6: "renders
     * from the recipient's own stored users.language, identically to
     * email"). Unlike email, a recipient with no registered device is
     * the expected common case, not a failure: this returns silently
     * rather than throwing NotificationRecipientUnresolved, since
     * retrying could never conjure a device token into existence, and
     * not every user has ever installed the mobile app.
     *
     * @param  callable(string $language): PushContent  $pushContentFactory
     */
    public function deliverPush(
        string $domainEventId,
        string $recipientId,
        NotificationType $type,
        callable $pushContentFactory,
    ): void {
        if ($this->ledger->alreadyDelivered($domainEventId, $recipientId, $type, channel: 'push')) {
            return;
        }

        $tokens = $this->deviceTokens->findTokensByUserId($recipientId);

        if ($tokens === []) {
            return;
        }

        $language = $this->localePreferences->findByRecipientId($recipientId)?->language ?? 'en';

        $this->pushSender->sendToTokens($tokens, $pushContentFactory($language));

        $this->ledger->recordDelivered($domainEventId, $recipientId, $type, channel: 'push');
    }
}
