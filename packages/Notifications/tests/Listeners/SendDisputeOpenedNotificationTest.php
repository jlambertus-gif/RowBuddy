<?php

declare(strict_types=1);

use RowBuddy\Disputes\Events\DisputeOpened;
use RowBuddy\Notifications\Listeners\SendDisputeOpenedNotification;
use RowBuddy\Notifications\Mail\DisputeOpenedMail;
use RowBuddy\Notifications\Support\NotificationDeliveryPipeline;
use RowBuddy\Notifications\Tests\Fakes\FakeMailer;
use RowBuddy\Notifications\Tests\Fakes\FakeRecipientContactLookup;
use RowBuddy\Notifications\Tests\Fakes\FakeRecipientLocalePreferenceLookup;
use RowBuddy\Notifications\Tests\Fakes\InMemoryNotificationDeliveryLedger;
use RowBuddy\SharedKernel\Support\FrozenClock;

it('sends the notification to the seller only', function () {
    $contacts = new FakeRecipientContactLookup;
    $contacts->emails['102'] = 'seller@example.com';
    $mailer = new FakeMailer;
    $pipeline = new NotificationDeliveryPipeline(new InMemoryNotificationDeliveryLedger, $contacts, new FakeRecipientLocalePreferenceLookup, $mailer);
    $listener = new SendDisputeOpenedNotification($pipeline);

    $event = new DisputeOpened(new FrozenClock, 'dispute-1', 'transfer-1', 'auction-1', '101', '102', 'not as described');
    $listener->handle($event);

    expect($mailer->sent)->toHaveCount(1)
        ->and($mailer->sent[0]['to'])->toBe('seller@example.com')
        ->and($mailer->sent[0]['mailable'])->toBeInstanceOf(DisputeOpenedMail::class);
});

it('does not send a second time once already delivered', function () {
    $contacts = new FakeRecipientContactLookup;
    $contacts->emails['102'] = 'seller@example.com';
    $mailer = new FakeMailer;
    $pipeline = new NotificationDeliveryPipeline(new InMemoryNotificationDeliveryLedger, $contacts, new FakeRecipientLocalePreferenceLookup, $mailer);
    $listener = new SendDisputeOpenedNotification($pipeline);
    $event = new DisputeOpened(new FrozenClock, 'dispute-1', 'transfer-1', 'auction-1', '101', '102', 'not as described');

    $listener->handle($event);
    $listener->handle($event);

    expect($mailer->sent)->toHaveCount(1);
});
