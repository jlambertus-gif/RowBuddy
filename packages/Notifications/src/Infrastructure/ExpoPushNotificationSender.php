<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Infrastructure;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RowBuddy\Notifications\Contracts\PushNotificationSender;
use RowBuddy\Notifications\ValueObjects\PushContent;

/**
 * Expo's push notification service (ADR-028 Decision 6) — brokers both
 * APNs and FCM, consistent with Decision 1's managed-workflow choice.
 * One HTTP request per call, carrying every token as Expo's own batch
 * format ({@link https://docs.expo.dev/push-notifications/sending-notifications/#push-tickets}) —
 * a single network failure surfaces as a thrown exception, letting the
 * calling queued listener's own retry/backoff handle it (ADR-025 §11: no
 * bespoke failure tracking beyond Laravel's own queue infrastructure).
 * Per-token delivery failures Expo reports inside a 200 response (e.g. a
 * stale "DeviceNotRegistered" token) are not inspected here — pruning
 * dead tokens is a deferred cleanup concern, not this MVP's scope.
 */
final class ExpoPushNotificationSender implements PushNotificationSender
{
    private const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    public function sendToTokens(array $expoPushTokens, PushContent $content): void
    {
        if ($expoPushTokens === []) {
            return;
        }

        $messages = array_map(
            static fn (string $token): array => [
                'to' => $token,
                'title' => $content->title,
                'body' => $content->body,
            ],
            $expoPushTokens,
        );

        /** @var Response $response */
        $response = Http::post(self::ENDPOINT, $messages);
        $response->throw();
    }
}
