<?php

declare(strict_types=1);

use RowBuddy\Notifications\Listeners\SendTransferIssuedNotification;
use RowBuddy\Notifications\Mail\TransferIssuedMail;
use RowBuddy\Notifications\Support\NotificationDeliveryPipeline;
use RowBuddy\Notifications\Tests\Fakes\FakeMailer;
use RowBuddy\Notifications\Tests\Fakes\FakeRecipientContactLookup;
use RowBuddy\Notifications\Tests\Fakes\FakeRecipientLocalePreferenceLookup;
use RowBuddy\Notifications\Tests\Fakes\InMemoryNotificationDeliveryLedger;
use RowBuddy\SharedKernel\Support\FrozenClock;
use RowBuddy\Transfers\Events\TransferIssued;

it('sends independently to both buyer and seller', function () {
    $contacts = new FakeRecipientContactLookup;
    $contacts->emails['101'] = 'buyer@example.com';
    $contacts->emails['102'] = 'seller@example.com';
    $mailer = new FakeMailer;
    $pipeline = new NotificationDeliveryPipeline(new InMemoryNotificationDeliveryLedger, $contacts, new FakeRecipientLocalePreferenceLookup, $mailer);
    $listener = new SendTransferIssuedNotification($pipeline);

    $event = new TransferIssued(new FrozenClock, 'transfer-1', 'auction-1', 'bid-1', '102', '101');
    $listener->handle($event);

    expect($mailer->sent)->toHaveCount(2)
        ->and($mailer->sent[0]['mailable'])->toBeInstanceOf(TransferIssuedMail::class)
        ->and(array_column($mailer->sent, 'to'))->toEqualCanonicalizing(['buyer@example.com', 'seller@example.com']);
});

it('does not send a second time to either party once delivered', function () {
    $contacts = new FakeRecipientContactLookup;
    $contacts->emails['101'] = 'buyer@example.com';
    $contacts->emails['102'] = 'seller@example.com';
    $mailer = new FakeMailer;
    $pipeline = new NotificationDeliveryPipeline(new InMemoryNotificationDeliveryLedger, $contacts, new FakeRecipientLocalePreferenceLookup, $mailer);
    $listener = new SendTransferIssuedNotification($pipeline);
    $event = new TransferIssued(new FrozenClock, 'transfer-1', 'auction-1', 'bid-1', '102', '101');

    $listener->handle($event);
    $listener->handle($event);

    expect($mailer->sent)->toHaveCount(2);
});
