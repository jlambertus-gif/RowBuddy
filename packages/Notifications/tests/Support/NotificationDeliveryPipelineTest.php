<?php

declare(strict_types=1);

use Illuminate\Mail\Mailable;
use RowBuddy\Notifications\Exceptions\NotificationRecipientUnresolved;
use RowBuddy\Notifications\Support\NotificationDeliveryPipeline;
use RowBuddy\Notifications\Tests\Fakes\FakeDeviceTokenRepository;
use RowBuddy\Notifications\Tests\Fakes\FakeMailer;
use RowBuddy\Notifications\Tests\Fakes\FakePushNotificationSender;
use RowBuddy\Notifications\Tests\Fakes\FakeRecipientContactLookup;
use RowBuddy\Notifications\Tests\Fakes\FakeRecipientLocalePreferenceLookup;
use RowBuddy\Notifications\Tests\Fakes\InMemoryNotificationDeliveryLedger;
use RowBuddy\Notifications\ValueObjects\NotificationType;
use RowBuddy\Notifications\ValueObjects\PushContent;
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
    $pipeline = new NotificationDeliveryPipeline($ledger, $contacts, new FakeRecipientLocalePreferenceLookup, $mailer, new FakeDeviceTokenRepository, new FakePushNotificationSender);

    $pipeline->deliver('event-1', '101', NotificationType::TransferIssued, fn (string $language) => new TestOnlyMailable($language));

    expect($mailer->sent)->toHaveCount(1)
        ->and($mailer->sent[0]['to'])->toBe('user@example.com')
        ->and($ledger->alreadyDelivered('event-1', '101', NotificationType::TransferIssued))->toBeTrue();
});

it('does not deliver a second time for the same logical identity', function () {
    $contacts = new FakeRecipientContactLookup;
    $contacts->emails['101'] = 'user@example.com';
    $mailer = new FakeMailer;
    $pipeline = new NotificationDeliveryPipeline(new InMemoryNotificationDeliveryLedger, $contacts, new FakeRecipientLocalePreferenceLookup, $mailer, new FakeDeviceTokenRepository, new FakePushNotificationSender);

    $pipeline->deliver('event-1', '101', NotificationType::TransferIssued, fn (string $language) => new TestOnlyMailable($language));
    $pipeline->deliver('event-1', '101', NotificationType::TransferIssued, fn (string $language) => new TestOnlyMailable($language));

    expect($mailer->sent)->toHaveCount(1);
});

it('delivers independently per recipient for the same domain event id', function () {
    $contacts = new FakeRecipientContactLookup;
    $contacts->emails['101'] = 'buyer@example.com';
    $contacts->emails['102'] = 'seller@example.com';
    $mailer = new FakeMailer;
    $pipeline = new NotificationDeliveryPipeline(new InMemoryNotificationDeliveryLedger, $contacts, new FakeRecipientLocalePreferenceLookup, $mailer, new FakeDeviceTokenRepository, new FakePushNotificationSender);

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
    $pipeline = new NotificationDeliveryPipeline(new InMemoryNotificationDeliveryLedger, $contacts, $localePreferences, $mailer, new FakeDeviceTokenRepository, new FakePushNotificationSender);

    $pipeline->deliver('event-1', '101', NotificationType::TransferIssued, fn (string $language) => new TestOnlyMailable($language));

    expect($mailer->sent[0]['mailable']->language)->toBe('es')
        ->and($mailer->sent[0]['mailable']->locale)->toBe('es');
});

it('falls back to English when no locale preference is stored', function () {
    $contacts = new FakeRecipientContactLookup;
    $contacts->emails['101'] = 'user@example.com';
    $mailer = new FakeMailer;
    $pipeline = new NotificationDeliveryPipeline(new InMemoryNotificationDeliveryLedger, $contacts, new FakeRecipientLocalePreferenceLookup, $mailer, new FakeDeviceTokenRepository, new FakePushNotificationSender);

    $pipeline->deliver('event-1', '101', NotificationType::TransferIssued, fn (string $language) => new TestOnlyMailable($language));

    expect($mailer->sent[0]['mailable']->language)->toBe('en');
});

it('throws when the recipient has no email on file, and records no delivery', function () {
    $ledger = new InMemoryNotificationDeliveryLedger;
    $pipeline = new NotificationDeliveryPipeline($ledger, new FakeRecipientContactLookup, new FakeRecipientLocalePreferenceLookup, new FakeMailer, new FakeDeviceTokenRepository, new FakePushNotificationSender);

    expect(fn () => $pipeline->deliver('event-1', '101', NotificationType::TransferIssued, fn (string $language) => new TestOnlyMailable($language)))
        ->toThrow(NotificationRecipientUnresolved::class);

    expect($ledger->alreadyDelivered('event-1', '101', NotificationType::TransferIssued))->toBeFalse();
});

// --- deliverPush() (ADR-028 Decision 6) ---

it('sends to every registered device and records delivery under the push channel', function () {
    $deviceTokens = new FakeDeviceTokenRepository;
    $deviceTokens->tokensByUserId['101'] = ['ExponentPushToken[aaa]', 'ExponentPushToken[bbb]'];
    $pushSender = new FakePushNotificationSender;
    $ledger = new InMemoryNotificationDeliveryLedger;
    $pipeline = new NotificationDeliveryPipeline($ledger, new FakeRecipientContactLookup, new FakeRecipientLocalePreferenceLookup, new FakeMailer, $deviceTokens, $pushSender);

    $pipeline->deliverPush('event-1', '101', NotificationType::TransferIssued, fn (string $language) => new PushContent('Title', 'Body'));

    expect($pushSender->sent)->toHaveCount(1)
        ->and($pushSender->sent[0]['tokens'])->toBe(['ExponentPushToken[aaa]', 'ExponentPushToken[bbb]'])
        ->and($ledger->alreadyDelivered('event-1', '101', NotificationType::TransferIssued, 'push'))->toBeTrue();
});

it('does not send a second time once push delivery is already recorded', function () {
    $deviceTokens = new FakeDeviceTokenRepository;
    $deviceTokens->tokensByUserId['101'] = ['ExponentPushToken[aaa]'];
    $pushSender = new FakePushNotificationSender;
    $pipeline = new NotificationDeliveryPipeline(new InMemoryNotificationDeliveryLedger, new FakeRecipientContactLookup, new FakeRecipientLocalePreferenceLookup, new FakeMailer, $deviceTokens, $pushSender);

    $pipeline->deliverPush('event-1', '101', NotificationType::TransferIssued, fn (string $language) => new PushContent('Title', 'Body'));
    $pipeline->deliverPush('event-1', '101', NotificationType::TransferIssued, fn (string $language) => new PushContent('Title', 'Body'));

    expect($pushSender->sent)->toHaveCount(1);
});

it('never sends and records nothing when the recipient has no registered device — the expected common case, not a failure', function () {
    $pushSender = new FakePushNotificationSender;
    $ledger = new InMemoryNotificationDeliveryLedger;
    $pipeline = new NotificationDeliveryPipeline($ledger, new FakeRecipientContactLookup, new FakeRecipientLocalePreferenceLookup, new FakeMailer, new FakeDeviceTokenRepository, $pushSender);

    $pipeline->deliverPush('event-1', '101', NotificationType::TransferIssued, fn (string $language) => new PushContent('Title', 'Body'));

    expect($pushSender->sent)->toHaveCount(0)
        ->and($ledger->alreadyDelivered('event-1', '101', NotificationType::TransferIssued, 'push'))->toBeFalse();
});

it('resolves the recipient language and passes it to the push content factory', function () {
    $deviceTokens = new FakeDeviceTokenRepository;
    $deviceTokens->tokensByUserId['101'] = ['ExponentPushToken[aaa]'];
    $localePreferences = new FakeRecipientLocalePreferenceLookup;
    $localePreferences->snapshots['101'] = new RecipientLocalePreferenceSnapshot('es', null, null, null);
    $pushSender = new FakePushNotificationSender;
    $pipeline = new NotificationDeliveryPipeline(new InMemoryNotificationDeliveryLedger, new FakeRecipientContactLookup, $localePreferences, new FakeMailer, $deviceTokens, $pushSender);

    $pipeline->deliverPush('event-1', '101', NotificationType::TransferIssued, fn (string $language) => new PushContent("Title in {$language}", 'Body'));

    expect($pushSender->sent[0]['content']->title)->toBe('Title in es');
});

it('tracks push delivery independently of email for the same (event, recipient, type)', function () {
    $contacts = new FakeRecipientContactLookup;
    $contacts->emails['101'] = 'user@example.com';
    $deviceTokens = new FakeDeviceTokenRepository;
    $deviceTokens->tokensByUserId['101'] = ['ExponentPushToken[aaa]'];
    $mailer = new FakeMailer;
    $pushSender = new FakePushNotificationSender;
    $ledger = new InMemoryNotificationDeliveryLedger;
    $pipeline = new NotificationDeliveryPipeline($ledger, $contacts, new FakeRecipientLocalePreferenceLookup, $mailer, $deviceTokens, $pushSender);

    $pipeline->deliver('event-1', '101', NotificationType::TransferIssued, fn (string $language) => new TestOnlyMailable($language));
    $pipeline->deliverPush('event-1', '101', NotificationType::TransferIssued, fn (string $language) => new PushContent('Title', 'Body'));

    expect($mailer->sent)->toHaveCount(1)
        ->and($pushSender->sent)->toHaveCount(1);
});
