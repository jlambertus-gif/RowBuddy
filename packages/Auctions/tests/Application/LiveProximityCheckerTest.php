<?php

declare(strict_types=1);

use RowBuddy\Auctions\Application\LiveProximityChecker;
use RowBuddy\Auctions\Auction;
use RowBuddy\Auctions\Contracts\SellerPresenceVerification;
use RowBuddy\Auctions\Events\AuctionCancelled;
use RowBuddy\Auctions\Events\AuctionProximityAtRisk;
use RowBuddy\Auctions\Events\AuctionProximityRestored;
use RowBuddy\Auctions\Tests\Fakes\InMemoryAuctionRepository;
use RowBuddy\Auctions\Tests\Fakes\InMemorySellerPresenceVerification;
use RowBuddy\Auctions\Tests\Fakes\RecordingDomainEventPublisher;
use RowBuddy\Auctions\ValueObjects\AuctionStatus;
use RowBuddy\Auctions\ValueObjects\ConfidenceTier;
use RowBuddy\Auctions\ValueObjects\PresenceVerificationSnapshot;
use RowBuddy\SharedKernel\Support\FrozenClock;

/**
 * This entire suite runs without Laravel, without a database, and without
 * Eloquent — the same in-memory-fake style as AuctionServiceTest. Every
 * scenario here is invoked directly against the checker, the same way
 * ADR-011 requires it to be invoked in production: by a command acting on
 * an already-existing active auction, never by a plain read.
 */
function makeChecker(
    InMemoryAuctionRepository $auctions,
    SellerPresenceVerification $presenceVerification,
    RecordingDomainEventPublisher $events,
    DateTimeImmutable $now,
): LiveProximityChecker {
    return new LiveProximityChecker($auctions, $presenceVerification, $events, new FrozenClock($now));
}

it('does nothing when the ping is fresh and the auction was never at risk', function () {
    $auctions = new InMemoryAuctionRepository;
    $presenceVerification = new InMemorySellerPresenceVerification;
    $events = new RecordingDomainEventPublisher;
    $now = new DateTimeImmutable('2026-09-16 10:00:00');

    $auction = Auction::open('auction-1', 'queue-1', 'seller-1', 'session-1', usd(1000), new FrozenClock($now));
    $auction->releaseEvents();
    $presenceVerification->stub('seller-1', 'queue-1', aSnapshot(
        ConfidenceTier::EvidenceVerified,
        180,
        true,
        $now->modify('-5 minutes'),
    ));

    $checker = makeChecker($auctions, $presenceVerification, $events, $now);
    $result = $checker->check($auction);

    expect($result->status())->toBe(AuctionStatus::Open)
        ->and($result->proximityAtRiskSince())->toBeNull()
        ->and($events->published)->toBe([]);
});

it('flags an auction at risk on first stale detection', function () {
    $auctions = new InMemoryAuctionRepository;
    $presenceVerification = new InMemorySellerPresenceVerification;
    $events = new RecordingDomainEventPublisher;
    $now = new DateTimeImmutable('2026-09-16 10:00:00');

    $auction = Auction::open('auction-2', 'queue-1', 'seller-1', 'session-2', usd(1000), new FrozenClock($now));
    $auction->releaseEvents();
    $presenceVerification->stub('seller-1', 'queue-1', aSnapshot(
        ConfidenceTier::EvidenceVerified,
        180,
        true,
        $now->modify('-20 minutes'),
    ));

    $checker = makeChecker($auctions, $presenceVerification, $events, $now);
    $result = $checker->check($auction);

    expect($result->status())->toBe(AuctionStatus::Open)
        ->and($result->proximityAtRiskSince())->toEqual($now)
        ->and($events->published)->toHaveCount(1)
        ->and($events->published[0])->toBeInstanceOf(AuctionProximityAtRisk::class)
        ->and($auctions->findById('auction-2'))->not->toBeNull();
});

it('does not extend the at-risk window on a repeated stale check within the grace period', function () {
    $auctions = new InMemoryAuctionRepository;
    $presenceVerification = new InMemorySellerPresenceVerification;
    $events = new RecordingDomainEventPublisher;
    $flaggedAt = new DateTimeImmutable('2026-09-16 10:00:00');

    $auction = Auction::open('auction-3', 'queue-1', 'seller-1', 'session-3', usd(1000), new FrozenClock($flaggedAt));
    $auction->releaseEvents();
    $presenceVerification->stub('seller-1', 'queue-1', aSnapshot(
        ConfidenceTier::EvidenceVerified,
        180,
        true,
        $flaggedAt->modify('-20 minutes'),
    ));

    makeChecker($auctions, $presenceVerification, $events, $flaggedAt)->check($auction);
    expect($auction->proximityAtRiskSince())->toEqual($flaggedAt);

    $secondCheckAt = $flaggedAt->modify('+5 minutes');
    makeChecker($auctions, $presenceVerification, $events, $secondCheckAt)->check($auction);

    expect($auction->status())->toBe(AuctionStatus::Open)
        ->and($auction->proximityAtRiskSince())->toEqual($flaggedAt)
        ->and($events->published)->toHaveCount(1);
});

it('cancels once the grace period elapses following a stale flag', function () {
    $auctions = new InMemoryAuctionRepository;
    $presenceVerification = new InMemorySellerPresenceVerification;
    $events = new RecordingDomainEventPublisher;
    $flaggedAt = new DateTimeImmutable('2026-09-16 10:00:00');

    $auction = Auction::open('auction-4', 'queue-1', 'seller-1', 'session-4', usd(1000), new FrozenClock($flaggedAt));
    $auction->releaseEvents();
    $presenceVerification->stub('seller-1', 'queue-1', aSnapshot(
        ConfidenceTier::EvidenceVerified,
        180,
        true,
        $flaggedAt->modify('-20 minutes'),
    ));

    makeChecker($auctions, $presenceVerification, $events, $flaggedAt)->check($auction);

    $laterCheckAt = $flaggedAt->modify('+11 minutes');
    makeChecker($auctions, $presenceVerification, $events, $laterCheckAt)->check($auction);

    expect($auction->status())->toBe(AuctionStatus::Cancelled)
        ->and($events->published)->toHaveCount(2)
        ->and($events->published[1])->toBeInstanceOf(AuctionCancelled::class);
});

it('restores proximity once a fresh ping arrives after being at risk', function () {
    $auctions = new InMemoryAuctionRepository;
    $presenceVerification = new InMemorySellerPresenceVerification;
    $events = new RecordingDomainEventPublisher;
    $flaggedAt = new DateTimeImmutable('2026-09-16 10:00:00');

    $auction = Auction::open('auction-5', 'queue-1', 'seller-1', 'session-5', usd(1000), new FrozenClock($flaggedAt));
    $auction->releaseEvents();
    $presenceVerification->stub('seller-1', 'queue-1', aSnapshot(
        ConfidenceTier::EvidenceVerified,
        180,
        true,
        $flaggedAt->modify('-20 minutes'),
    ));
    makeChecker($auctions, $presenceVerification, $events, $flaggedAt)->check($auction);
    expect($auction->proximityAtRiskSince())->not->toBeNull();

    $restoreCheckAt = $flaggedAt->modify('+5 minutes');
    $presenceVerification->stub('seller-1', 'queue-1', aSnapshot(
        ConfidenceTier::EvidenceVerified,
        180,
        true,
        $restoreCheckAt->modify('-2 minutes'),
    ));
    makeChecker($auctions, $presenceVerification, $events, $restoreCheckAt)->check($auction);

    expect($auction->status())->toBe(AuctionStatus::Open)
        ->and($auction->proximityAtRiskSince())->toBeNull()
        ->and($events->published)->toHaveCount(2)
        ->and($events->published[1])->toBeInstanceOf(AuctionProximityRestored::class);
});

it('cancels immediately when the session is no longer active, with no prior at-risk flag needed', function () {
    $auctions = new InMemoryAuctionRepository;
    $presenceVerification = new InMemorySellerPresenceVerification;
    $events = new RecordingDomainEventPublisher;
    $now = new DateTimeImmutable('2026-09-16 10:00:00');

    $auction = Auction::open('auction-6', 'queue-1', 'seller-1', 'session-6', usd(1000), new FrozenClock($now));
    $auction->releaseEvents();
    $presenceVerification->stub('seller-1', 'queue-1', aSnapshot(
        ConfidenceTier::EvidenceVerified,
        180,
        false,
        $now->modify('-1 minute'),
    ));

    makeChecker($auctions, $presenceVerification, $events, $now)->check($auction);

    expect($auction->status())->toBe(AuctionStatus::Cancelled)
        ->and($events->published)->toHaveCount(1)
        ->and($events->published[0])->toBeInstanceOf(AuctionCancelled::class);
});

it('cancels immediately when no verification record exists at all', function () {
    $auctions = new InMemoryAuctionRepository;
    $presenceVerification = new InMemorySellerPresenceVerification;
    $events = new RecordingDomainEventPublisher;
    $now = new DateTimeImmutable('2026-09-16 10:00:00');

    $auction = Auction::open('auction-7', 'queue-1', 'seller-1', 'session-7', usd(1000), new FrozenClock($now));
    $auction->releaseEvents();

    makeChecker($auctions, $presenceVerification, $events, $now)->check($auction);

    expect($auction->status())->toBe(AuctionStatus::Cancelled)
        ->and($events->published)->toHaveCount(1);
});

it('does not query verification at all for an auction already in a terminal state', function () {
    $auctions = new InMemoryAuctionRepository;
    $events = new RecordingDomainEventPublisher;
    $now = new DateTimeImmutable('2026-09-16 10:00:00');

    $throwingVerification = new class implements SellerPresenceVerification
    {
        public function verificationFor(string $sellerId, string $queueId): ?PresenceVerificationSnapshot
        {
            throw new RuntimeException('should never be called for a terminal auction');
        }
    };

    foreach ([AuctionStatus::Won, AuctionStatus::Expired, AuctionStatus::Cancelled] as $terminalStatus) {
        $auction = Auction::fromPersistence(
            'auction-terminal-'.$terminalStatus->value,
            'queue-1',
            'seller-1',
            'session-terminal',
            usd(1000),
            $now,
            $terminalStatus,
            null,
            null,
        );

        $checker = makeChecker($auctions, $throwingVerification, $events, $now);
        $result = $checker->check($auction);

        expect($result->status())->toBe($terminalStatus);
    }

    expect($events->published)->toBe([]);
});
