<?php

declare(strict_types=1);

use RowBuddy\Auctions\Events\AuctionWon;
use RowBuddy\Notifications\Exceptions\NotificationRecipientUnresolved;
use RowBuddy\Notifications\Listeners\SendAuctionWonNotification;
use RowBuddy\Notifications\Mail\AuctionWonMail;
use RowBuddy\Notifications\Support\NotificationDeliveryPipeline;
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

function makeAuctionWonFixtures(): array
{
    $contacts = new FakeRecipientContactLookup;
    $localePreferences = new FakeRecipientLocalePreferenceLookup;
    $ledger = new InMemoryNotificationDeliveryLedger;
    $mailer = new FakeMailer;
    $pipeline = new NotificationDeliveryPipeline($ledger, $contacts, $localePreferences, $mailer);

    return compact('contacts', 'localePreferences', 'ledger', 'mailer', 'pipeline');
}

it('sends the notification and records delivery for a resolvable winner', function () {
    ['contacts' => $contacts, 'localePreferences' => $localePreferences, 'ledger' => $ledger, 'mailer' => $mailer, 'pipeline' => $pipeline] = makeAuctionWonFixtures();
    $bidders = new FakeWinningBidderLookup;
    $bidders->bidders['bid-1'] = '101';
    $contacts->emails['101'] = 'winner@example.com';

    $listener = new SendAuctionWonNotification($bidders, $pipeline);
    $event = new AuctionWon(new FrozenClock, 'auction-1', 'bid-1', new Money(15000, new Currency('USD')));

    $listener->handle($event);

    expect($mailer->sent)->toHaveCount(1)
        ->and($mailer->sent[0]['to'])->toBe('winner@example.com')
        ->and($mailer->sent[0]['mailable'])->toBeInstanceOf(AuctionWonMail::class)
        ->and($ledger->alreadyDelivered('auction-1', '101', NotificationType::AuctionWon))->toBeTrue();
});

it('does not send a second time once delivery is already recorded', function () {
    ['contacts' => $contacts, 'pipeline' => $pipeline, 'mailer' => $mailer] = makeAuctionWonFixtures();
    $bidders = new FakeWinningBidderLookup;
    $bidders->bidders['bid-1'] = '101';
    $contacts->emails['101'] = 'winner@example.com';
    $event = new AuctionWon(new FrozenClock, 'auction-1', 'bid-1', new Money(15000, new Currency('USD')));

    $listener = new SendAuctionWonNotification($bidders, $pipeline);
    $listener->handle($event);
    $listener->handle($event);

    expect($mailer->sent)->toHaveCount(1);
});

it('resolves the recipient language from the locale preference lookup when present', function () {
    ['contacts' => $contacts, 'localePreferences' => $localePreferences, 'pipeline' => $pipeline, 'mailer' => $mailer] = makeAuctionWonFixtures();
    $bidders = new FakeWinningBidderLookup;
    $bidders->bidders['bid-1'] = '101';
    $contacts->emails['101'] = 'winner@example.com';
    $localePreferences->snapshots['101'] = new RecipientLocalePreferenceSnapshot('es', null, null, null);
    $event = new AuctionWon(new FrozenClock, 'auction-1', 'bid-1', new Money(15000, new Currency('USD')));

    (new SendAuctionWonNotification($bidders, $pipeline))->handle($event);

    expect($mailer->sent[0]['mailable']->locale)->toBe('es');
});

it('falls back to English when no locale preference is stored', function () {
    ['contacts' => $contacts, 'pipeline' => $pipeline, 'mailer' => $mailer] = makeAuctionWonFixtures();
    $bidders = new FakeWinningBidderLookup;
    $bidders->bidders['bid-1'] = '101';
    $contacts->emails['101'] = 'winner@example.com';
    $event = new AuctionWon(new FrozenClock, 'auction-1', 'bid-1', new Money(15000, new Currency('USD')));

    (new SendAuctionWonNotification($bidders, $pipeline))->handle($event);

    expect($mailer->sent[0]['mailable']->locale)->toBe('en');
});

it('throws when no bidder can be resolved for the winning bid', function () {
    ['pipeline' => $pipeline] = makeAuctionWonFixtures();
    $event = new AuctionWon(new FrozenClock, 'auction-1', 'bid-missing', new Money(15000, new Currency('USD')));

    $listener = new SendAuctionWonNotification(new FakeWinningBidderLookup, $pipeline);

    expect(fn () => $listener->handle($event))->toThrow(NotificationRecipientUnresolved::class);
});

it('throws when the resolved recipient has no email on file', function () {
    ['pipeline' => $pipeline] = makeAuctionWonFixtures();
    $bidders = new FakeWinningBidderLookup;
    $bidders->bidders['bid-1'] = '101';
    $event = new AuctionWon(new FrozenClock, 'auction-1', 'bid-1', new Money(15000, new Currency('USD')));

    $listener = new SendAuctionWonNotification($bidders, $pipeline);

    expect(fn () => $listener->handle($event))->toThrow(NotificationRecipientUnresolved::class);
});
