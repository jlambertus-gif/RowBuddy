<?php

declare(strict_types=1);

use RowBuddy\Notifications\Exceptions\NotificationRecipientUnresolved;
use RowBuddy\Notifications\Listeners\SendTransferConfirmedNotification;
use RowBuddy\Notifications\Mail\TransferConfirmedMail;
use RowBuddy\Notifications\Support\NotificationDeliveryPipeline;
use RowBuddy\Notifications\Tests\Fakes\FakeMailer;
use RowBuddy\Notifications\Tests\Fakes\FakeRecipientContactLookup;
use RowBuddy\Notifications\Tests\Fakes\FakeRecipientLocalePreferenceLookup;
use RowBuddy\Notifications\Tests\Fakes\FakeTransferParticipantLookup;
use RowBuddy\Notifications\Tests\Fakes\InMemoryNotificationDeliveryLedger;
use RowBuddy\Notifications\ValueObjects\TransferParticipantSnapshot;
use RowBuddy\SharedKernel\Support\FrozenClock;
use RowBuddy\Transfers\Events\TransferConfirmed;

it('sends independently to both buyer and seller, resolved via the participant lookup', function () {
    $transferParticipants = new FakeTransferParticipantLookup;
    $transferParticipants->snapshots['transfer-1'] = new TransferParticipantSnapshot('transfer-1', '101', '102');
    $contacts = new FakeRecipientContactLookup;
    $contacts->emails['101'] = 'buyer@example.com';
    $contacts->emails['102'] = 'seller@example.com';
    $mailer = new FakeMailer;
    $pipeline = new NotificationDeliveryPipeline(new InMemoryNotificationDeliveryLedger, $contacts, new FakeRecipientLocalePreferenceLookup, $mailer);
    $listener = new SendTransferConfirmedNotification($transferParticipants, $pipeline);

    $listener->handle(new TransferConfirmed(new FrozenClock, 'transfer-1', 'auction-1', 'bid-1'));

    expect($mailer->sent)->toHaveCount(2)
        ->and($mailer->sent[0]['mailable'])->toBeInstanceOf(TransferConfirmedMail::class)
        ->and(array_column($mailer->sent, 'to'))->toEqualCanonicalizing(['buyer@example.com', 'seller@example.com']);
});

it('throws when no matching transfer is found', function () {
    $pipeline = new NotificationDeliveryPipeline(new InMemoryNotificationDeliveryLedger, new FakeRecipientContactLookup, new FakeRecipientLocalePreferenceLookup, new FakeMailer);
    $listener = new SendTransferConfirmedNotification(new FakeTransferParticipantLookup, $pipeline);

    expect(fn () => $listener->handle(new TransferConfirmed(new FrozenClock, 'transfer-missing', 'auction-1', 'bid-1')))
        ->toThrow(NotificationRecipientUnresolved::class);
});
