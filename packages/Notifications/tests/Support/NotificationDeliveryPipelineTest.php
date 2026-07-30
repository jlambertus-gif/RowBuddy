<?php

declare(strict_types=1);

use Illuminate\Mail\Mailable;
use RowBuddy\Notifications\Exceptions\NotificationRecipientUnresolved;
use RowBuddy\Notifications\Support\NotificationDeliveryPipeline;
use RowBuddy\Notifications\Tests\Fakes\FakeMailer;
use RowBuddy\Notifications\Tests\Fakes\FakeRecipientContactLookup;
use RowBuddy\Notifications\Tests\Fakes\FakeRecipientLocalePreferenceLookup;
use RowBuddy\Notifications\Tests\Fakes\InMemoryNotificationDeliveryLedger;
use RowBuddy\Notifications\ValueObjects\NotificationType;
use RowBuddy\Notifications\ValueObjects\RecipientLocalePreferenceSnapshot;

final class TestOnlyMailable extends Mailable
{
    public function __construct(public readonly string $language) {}
}

it('sends and records delivery when the recipient is fully resolvable', function () {
    $contacts = new FakeRecipientContactLookup;
    $contacts->emails['101'] = 'user@example.com';
    $ledger = new InMemoryNotificationDeliveryLedger;
    $mailer = new FakeMailer;
    $pipeline = new NotificationDeliveryPipeline($ledger, $contacts, new FakeRecipientLocalePreferenceLookup, $mailer);

    $pipeline->deliver('event-1', '101', NotificationType::TransferIssued, fn (string $language) => new TestOnlyMailable($language));

    expect($mailer->sent)->toHaveCount(1)
        ->and($mailer->sent[0]['to'])->toBe('user@example.com')
        ->and($ledger->alreadyDelivered('event-1', '101', NotificationType::TransferIssued))->toBeTrue();
});

it('does not deliver a second time for the same logical identity', function () {
    $contacts = new FakeRecipientContactLookup;
    $contacts->emails['101'] = 'user@example.com';
    $mailer = new FakeMailer;
    $pipeline = new NotificationDeliveryPipeline(new InMemoryNotificationDeliveryLedger, $contacts, new FakeRecipientLocalePreferenceLookup, $mailer);

    $pipeline->deliver('event-1', '101', NotificationType::TransferIssued, fn (string $language) => new TestOnlyMailable($language));
    $pipeline->deliver('event-1', '101', NotificationType::TransferIssued, fn (string $language) => new TestOnlyMailable($language));

    expect($mailer->sent)->toHaveCount(1);
});

it('delivers independently per recipient for the same domain event id', function () {
    $contacts = new FakeRecipientContactLookup;
    $contacts->emails['101'] = 'buyer@example.com';
    $contacts->emails['102'] = 'seller@example.com';
    $mailer = new FakeMailer;
    $pipeline = new NotificationDeliveryPipeline(new InMemoryNotificationDeliveryLedger, $contacts, new FakeRecipientLocalePreferenceLookup, $mailer);

    $pipeline->deliver('event-1', '101', NotificationType::TransferIssued, fn (string $language) => new TestOnlyMailable($language));
    $pipeline->deliver('event-1', '102', NotificationType::TransferIssued, fn (string $language) => new TestOnlyMailable($language));

    expect($mailer->sent)->toHaveCount(2);
});

it('resolves the stored language and passes it to the mailable factory and ->locale()', function () {
    $contacts = new FakeRecipientContactLookup;
    $contacts->emails['101'] = 'user@example.com';
    $localePreferences = new FakeRecipientLocalePreferenceLookup;
    $localePreferences->snapshots['101'] = new RecipientLocalePreferenceSnapshot('es', null, null, null);
    $mailer = new FakeMailer;
    $pipeline = new NotificationDeliveryPipeline(new InMemoryNotificationDeliveryLedger, $contacts, $localePreferences, $mailer);

    $pipeline->deliver('event-1', '101', NotificationType::TransferIssued, fn (string $language) => new TestOnlyMailable($language));

    expect($mailer->sent[0]['mailable']->language)->toBe('es')
        ->and($mailer->sent[0]['mailable']->locale)->toBe('es');
});

it('falls back to English when no locale preference is stored', function () {
    $contacts = new FakeRecipientContactLookup;
    $contacts->emails['101'] = 'user@example.com';
    $mailer = new FakeMailer;
    $pipeline = new NotificationDeliveryPipeline(new InMemoryNotificationDeliveryLedger, $contacts, new FakeRecipientLocalePreferenceLookup, $mailer);

    $pipeline->deliver('event-1', '101', NotificationType::TransferIssued, fn (string $language) => new TestOnlyMailable($language));

    expect($mailer->sent[0]['mailable']->language)->toBe('en');
});

it('throws when the recipient has no email on file, and records no delivery', function () {
    $ledger = new InMemoryNotificationDeliveryLedger;
    $pipeline = new NotificationDeliveryPipeline($ledger, new FakeRecipientContactLookup, new FakeRecipientLocalePreferenceLookup, new FakeMailer);

    expect(fn () => $pipeline->deliver('event-1', '101', NotificationType::TransferIssued, fn (string $language) => new TestOnlyMailable($language)))
        ->toThrow(NotificationRecipientUnresolved::class);

    expect($ledger->alreadyDelivered('event-1', '101', NotificationType::TransferIssued))->toBeFalse();
});
