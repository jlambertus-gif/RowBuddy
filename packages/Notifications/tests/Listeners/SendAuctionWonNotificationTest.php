<?php

declare(strict_types=1);

use RowBuddy\Auctions\Events\AuctionWon;
use RowBuddy\Notifications\Exceptions\NotificationRecipientUnresolved;
use RowBuddy\Notifications\Listeners\SendAuctionWonNotification;
use RowBuddy\Notifications\Mail\AuctionWonMail;
use RowBuddy\Notifications\Tests\Fakes\FakeMailer;
use RowBuddy\Notifications\Tests\Fakes\FakeRecipientContactLookup;
use RowBuddy\Notifications\Tests\Fakes\FakeRecipientLocalePreferenceLookup;
use RowBuddy\Notifications\Tests\Fakes\FakeWinningBidderLookup;
use RowBuddy\Notifications\Tests\Fakes\InMemoryNotificationDeliveryLedger;
use RowBuddy\Notifications\ValueObjects\NotificationType;
use RowBuddy\Notifications\ValueObjects\RecipientLocalePreferenceSnapshot;
use RowBuddy\SharedKernel\Support\FrozenClock;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;

function makeListener(
    FakeWinningBidderLookup $bidders,
    FakeRecipientContactLookup $contacts,
    FakeRecipientLocalePreferenceLookup $localePreferences,
    InMemoryNotificationDeliveryLedger $ledger,
    FakeMailer $mailer,
): SendAuctionWonNotification {
    return new SendAuctionWonNotification($bidders, $contacts, $localePreferences, $ledger, $mailer);
}

it('sends the notification and records delivery for a resolvable winner', function () {
    $bidders = new FakeWinningBidderLookup;
    $bidders->bidders['bid-1'] = '101';
    $contacts = new FakeRecipientContactLookup;
    $contacts->emails['101'] = 'winner@example.com';
    $localePreferences = new FakeRecipientLocalePreferenceLookup;
    $ledger = new InMemoryNotificationDeliveryLedger;
    $mailer = new FakeMailer;

    $listener = makeListener($bidders, $contacts, $localePreferences, $ledger, $mailer);
    $event = new AuctionWon(new FrozenClock, 'auction-1', 'bid-1', new Money(15000, new Currency('USD')));

    $listener->handle($event);

    expect($mailer->sent)->toHaveCount(1)
        ->and($mailer->sent[0]['to'])->toBe('winner@example.com')
        ->and($mailer->sent[0]['mailable'])->toBeInstanceOf(AuctionWonMail::class)
        ->and($ledger->alreadyDelivered('auction-1', '101', NotificationType::AuctionWon))->toBeTrue();
});

it('does not send a second time once delivery is already recorded', function () {
    $bidders = new FakeWinningBidderLookup;
    $bidders->bidders['bid-1'] = '101';
    $contacts = new FakeRecipientContactLookup;
    $contacts->emails['101'] = 'winner@example.com';
    $localePreferences = new FakeRecipientLocalePreferenceLookup;
    $ledger = new InMemoryNotificationDeliveryLedger;
    $mailer = new FakeMailer;
    $event = new AuctionWon(new FrozenClock, 'auction-1', 'bid-1', new Money(15000, new Currency('USD')));

    makeListener($bidders, $contacts, $localePreferences, $ledger, $mailer)->handle($event);
    makeListener($bidders, $contacts, $localePreferences, $ledger, $mailer)->handle($event);

    expect($mailer->sent)->toHaveCount(1);
});

it('resolves the recipient language from the locale preference lookup when present', function () {
    $bidders = new FakeWinningBidderLookup;
    $bidders->bidders['bid-1'] = '101';
    $contacts = new FakeRecipientContactLookup;
    $contacts->emails['101'] = 'winner@example.com';
    $localePreferences = new FakeRecipientLocalePreferenceLookup;
    $localePreferences->snapshots['101'] = new RecipientLocalePreferenceSnapshot('es', null, null, null);
    $ledger = new InMemoryNotificationDeliveryLedger;
    $mailer = new FakeMailer;
    $event = new AuctionWon(new FrozenClock, 'auction-1', 'bid-1', new Money(15000, new Currency('USD')));

    makeListener($bidders, $contacts, $localePreferences, $ledger, $mailer)->handle($event);

    expect($mailer->sent[0]['mailable']->locale)->toBe('es');
});

it('falls back to English when no locale preference is stored', function () {
    $bidders = new FakeWinningBidderLookup;
    $bidders->bidders['bid-1'] = '101';
    $contacts = new FakeRecipientContactLookup;
    $contacts->emails['101'] = 'winner@example.com';
    $ledger = new InMemoryNotificationDeliveryLedger;
    $mailer = new FakeMailer;
    $event = new AuctionWon(new FrozenClock, 'auction-1', 'bid-1', new Money(15000, new Currency('USD')));

    makeListener($bidders, $contacts, new FakeRecipientLocalePreferenceLookup, $ledger, $mailer)->handle($event);

    expect($mailer->sent[0]['mailable']->locale)->toBe('en');
});

it('throws when no bidder can be resolved for the winning bid', function () {
    $event = new AuctionWon(new FrozenClock, 'auction-1', 'bid-missing', new Money(15000, new Currency('USD')));

    $listener = makeListener(
        new FakeWinningBidderLookup,
        new FakeRecipientContactLookup,
        new FakeRecipientLocalePreferenceLookup,
        new InMemoryNotificationDeliveryLedger,
        new FakeMailer,
    );

    expect(fn () => $listener->handle($event))->toThrow(NotificationRecipientUnresolved::class);
});

it('throws when the resolved recipient has no email on file', function () {
    $bidders = new FakeWinningBidderLookup;
    $bidders->bidders['bid-1'] = '101';
    $event = new AuctionWon(new FrozenClock, 'auction-1', 'bid-1', new Money(15000, new Currency('USD')));

    $listener = makeListener(
        $bidders,
        new FakeRecipientContactLookup,
        new FakeRecipientLocalePreferenceLookup,
        new InMemoryNotificationDeliveryLedger,
        new FakeMailer,
    );

    expect(fn () => $listener->handle($event))->toThrow(NotificationRecipientUnresolved::class);
});
