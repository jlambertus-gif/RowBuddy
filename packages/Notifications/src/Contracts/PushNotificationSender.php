<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Contracts;

use RowBuddy\Notifications\ValueObjects\PushContent;

/**
 * The provider-facing push port (ADR-028 Decision 6: Expo's push
 * service). Deliberately expresses no provider/transport concept —
 * implementations translate to whatever push API backs them, never the
 * reverse. Takes every token for one recipient in a single call: Expo's
 * own API accepts a batch of messages in one request, and a user may
 * have more than one registered device.
 */
interface PushNotificationSender
{
    /**
     * @param  list<string>  $expoPushTokens
     */
    public function sendToTokens(array $expoPushTokens, PushContent $content): void;
}
