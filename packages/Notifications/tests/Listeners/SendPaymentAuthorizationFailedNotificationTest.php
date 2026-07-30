<?php

declare(strict_types=1);

use RowBuddy\Notifications\Exceptions\NotificationRecipientUnresolved;
use RowBuddy\Notifications\Listeners\SendPaymentAuthorizationFailedNotification;
use RowBuddy\Notifications\Mail\PaymentAuthorizationFailedMail;
use RowBuddy\Notifications\Support\NotificationDeliveryPipeline;
use RowBuddy\Notifications\Tests\Fakes\FakeMailer;
use RowBuddy\Notifications\Tests\Fakes\FakeRecipientContactLookup;
use RowBuddy\Notifications\Tests\Fakes\FakeRecipientLocalePreferenceLookup;
use RowBuddy\Notifications\Tests\Fakes\FakeWinningBidderLookup;
use RowBuddy\Notifications\Tests\Fakes\InMemoryNotificationDeliveryLedger;
use RowBuddy\Payments\Events\PaymentAuthorizationFailed;
use RowBuddy\SharedKernel\Support\FrozenClock;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;

it('sends the notification to the resolvable buyer', function () {
    $bidders = new FakeWinningBidderLookup;
    $bidders->bidders['bid-1'] = '101';
    $contacts = new FakeRecipientContactLookup;
    $contacts->emails['101'] = 'buyer@example.com';
    $mailer = new FakeMailer;
    $pipeline = new NotificationDeliveryPipeline(new InMemoryNotificationDeliveryLedger, $contacts, new FakeRecipientLocalePreferenceLookup, $mailer);
    $listener = new SendPaymentAuthorizationFailedNotification($bidders, $pipeline);

    $event = new PaymentAuthorizationFailed(new FrozenClock, 'pi-1', 'auction-1', 'bid-1', new Money(11000, new Currency('USD')), 'card_declined');
    $listener->handle($event);

    expect($mailer->sent)->toHaveCount(1)
        ->and($mailer->sent[0]['to'])->toBe('buyer@example.com')
        ->and($mailer->sent[0]['mailable'])->toBeInstanceOf(PaymentAuthorizationFailedMail::class);
});

it('does not send a second time once already delivered', function () {
    $bidders = new FakeWinningBidderLookup;
    $bidders->bidders['bid-1'] = '101';
    $contacts = new FakeRecipientContactLookup;
    $contacts->emails['101'] = 'buyer@example.com';
    $mailer = new FakeMailer;
    $pipeline = new NotificationDeliveryPipeline(new InMemoryNotificationDeliveryLedger, $contacts, new FakeRecipientLocalePreferenceLookup, $mailer);
    $listener = new SendPaymentAuthorizationFailedNotification($bidders, $pipeline);
    $event = new PaymentAuthorizationFailed(new FrozenClock, 'pi-1', 'auction-1', 'bid-1', new Money(11000, new Currency('USD')), 'card_declined');

    $listener->handle($event);
    $listener->handle($event);

    expect($mailer->sent)->toHaveCount(1);
});

it('throws when no bidder can be resolved', function () {
    $pipeline = new NotificationDeliveryPipeline(new InMemoryNotificationDeliveryLedger, new FakeRecipientContactLookup, new FakeRecipientLocalePreferenceLookup, new FakeMailer);
    $listener = new SendPaymentAuthorizationFailedNotification(new FakeWinningBidderLookup, $pipeline);
    $event = new PaymentAuthorizationFailed(new FrozenClock, 'pi-1', 'auction-1', 'bid-missing', new Money(11000, new Currency('USD')), 'card_declined');

    expect(fn () => $listener->handle($event))->toThrow(NotificationRecipientUnresolved::class);
});
