<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Tests\Fakes;

use RowBuddy\Notifications\Contracts\PushNotificationSender;
use RowBuddy\Notifications\ValueObjects\PushContent;

final class FakePushNotificationSender implements PushNotificationSender
{
    /** @var list<array{tokens: list<string>, content: PushContent}> */
    public array $sent = [];

    public function sendToTokens(array $expoPushTokens, PushContent $content): void
    {
        $this->sent[] = ['tokens' => $expoPushTokens, 'content' => $content];
    }
}
