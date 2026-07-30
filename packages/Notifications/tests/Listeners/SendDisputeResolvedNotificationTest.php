<?php

declare(strict_types=1);

use RowBuddy\Disputes\Events\DisputeResolved;
use RowBuddy\Disputes\ValueObjects\DisputeResolutionOutcome;
use RowBuddy\Notifications\Exceptions\NotificationRecipientUnresolved;
use RowBuddy\Notifications\Listeners\SendDisputeResolvedNotification;
use RowBuddy\Notifications\Mail\DisputeResolvedMail;
use RowBuddy\Notifications\Support\NotificationDeliveryPipeline;
use RowBuddy\Notifications\Tests\Fakes\FakeDisputeParticipantLookup;
use RowBuddy\Notifications\Tests\Fakes\FakeMailer;
use RowBuddy\Notifications\Tests\Fakes\FakeRecipientContactLookup;
use RowBuddy\Notifications\Tests\Fakes\FakeRecipientLocalePreferenceLookup;
use RowBuddy\Notifications\Tests\Fakes\InMemoryNotificationDeliveryLedger;
use RowBuddy\Notifications\ValueObjects\DisputeParticipantSnapshot;
use RowBuddy\SharedKernel\Support\FrozenClock;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;

it('sends independently to both buyer and seller, resolved via the dispute participant lookup', function () {
    $disputeParticipants = new FakeDisputeParticipantLookup;
    $disputeParticipants->snapshots['dispute-1'] = new DisputeParticipantSnapshot('dispute-1', '101', '102');
    $contacts = new FakeRecipientContactLookup;
    $contacts->emails['101'] = 'buyer@example.com';
    $contacts->emails['102'] = 'seller@example.com';
    $mailer = new FakeMailer;
    $pipeline = new NotificationDeliveryPipeline(new InMemoryNotificationDeliveryLedger, $contacts, new FakeRecipientLocalePreferenceLookup, $mailer);
    $listener = new SendDisputeResolvedNotification($disputeParticipants, $pipeline);

    $event = new DisputeResolved(new FrozenClock, 'dispute-1', DisputeResolutionOutcome::RefundToBuyer, new Money(11000, new Currency('USD')), 'admin-1', 'buyer is correct', false);
    $listener->handle($event);

    expect($mailer->sent)->toHaveCount(2)
        ->and($mailer->sent[0]['mailable'])->toBeInstanceOf(DisputeResolvedMail::class)
        ->and(array_column($mailer->sent, 'to'))->toEqualCanonicalizing(['buyer@example.com', 'seller@example.com']);
});

it('carries no refund amount for an outcome that does not include one', function () {
    $disputeParticipants = new FakeDisputeParticipantLookup;
    $disputeParticipants->snapshots['dispute-1'] = new DisputeParticipantSnapshot('dispute-1', '101', '102');
    $contacts = new FakeRecipientContactLookup;
    $contacts->emails['101'] = 'buyer@example.com';
    $contacts->emails['102'] = 'seller@example.com';
    $mailer = new FakeMailer;
    $pipeline = new NotificationDeliveryPipeline(new InMemoryNotificationDeliveryLedger, $contacts, new FakeRecipientLocalePreferenceLookup, $mailer);
    $listener = new SendDisputeResolvedNotification($disputeParticipants, $pipeline);

    $event = new DisputeResolved(new FrozenClock, 'dispute-1', DisputeResolutionOutcome::ReleaseToSeller, null, 'admin-1', 'no issue found', false);
    $listener->handle($event);

    expect($mailer->sent)->toHaveCount(2);
});

it('throws when no matching dispute is found', function () {
    $pipeline = new NotificationDeliveryPipeline(new InMemoryNotificationDeliveryLedger, new FakeRecipientContactLookup, new FakeRecipientLocalePreferenceLookup, new FakeMailer);
    $listener = new SendDisputeResolvedNotification(new FakeDisputeParticipantLookup, $pipeline);

    $event = new DisputeResolved(new FrozenClock, 'dispute-missing', DisputeResolutionOutcome::Cancelled, null, 'admin-1', 'notes', false);

    expect(fn () => $listener->handle($event))->toThrow(NotificationRecipientUnresolved::class);
});
