<?php

declare(strict_types=1);

use RowBuddy\Auctions\Application\AuctionService;
use RowBuddy\Auctions\Application\FixedAuctionDurationPolicy;
use RowBuddy\Auctions\Contracts\SellerPresenceVerification;
use RowBuddy\Auctions\Events\AuctionOpened;
use RowBuddy\Auctions\Exceptions\InsufficientConfidenceTier;
use RowBuddy\Auctions\Exceptions\PresenceSessionAlreadyConsumed;
use RowBuddy\Auctions\Tests\Fakes\InMemoryAuctionRepository;
use RowBuddy\Auctions\Tests\Fakes\InMemorySellerPresenceVerification;
use RowBuddy\Auctions\Tests\Fakes\RecordingDomainEventPublisher;
use RowBuddy\Auctions\ValueObjects\AuctionStatus;
use RowBuddy\Auctions\ValueObjects\ConfidenceTier;
use RowBuddy\Auctions\ValueObjects\PresenceVerificationSnapshot;
use RowBuddy\SharedKernel\Support\FrozenClock;

/**
 * This entire suite runs without Laravel, without a database, and without
 * Eloquent: every collaborator is a plain in-memory fake implementing the
 * same domain-facing interface the real adapters implement — the same
 * style as QueuePresence's PresenceSessionServiceTest.
 */
function aSnapshot(
    ConfidenceTier $tier,
    int $points = 0,
    bool $sessionActive = true,
    ?DateTimeImmutable $lastWithinGeofencePingAt = null,
): PresenceVerificationSnapshot {
    return new PresenceVerificationSnapshot(
        $tier,
        $points,
        new DateTimeImmutable('2026-09-10 09:00:00'),
        $sessionActive,
        $lastWithinGeofencePingAt,
    );
}

function makeAuctionService(
    InMemoryAuctionRepository $auctions,
    InMemorySellerPresenceVerification $presenceVerification,
    RecordingDomainEventPublisher $events,
): AuctionService {
    return new AuctionService($auctions, $presenceVerification, new FixedAuctionDurationPolicy(30 * 60), $events, new FrozenClock);
}

it('opens an auction when the seller is Evidence Verified for the queue', function () {
    $auctions = new InMemoryAuctionRepository;
    $presenceVerification = new InMemorySellerPresenceVerification;
    $presenceVerification->stub('seller-1', 'queue-1', aSnapshot(ConfidenceTier::EvidenceVerified, 180));
    $events = new RecordingDomainEventPublisher;

    $service = makeAuctionService($auctions, $presenceVerification, $events);

    $auction = $service->open('auction-1', 'queue-1', 'seller-1', 'session-1', usd(1000));

    expect($auction->status())->toBe(AuctionStatus::Open)
        ->and($auctions->findById('auction-1'))->not->toBeNull()
        ->and($events->published)->toHaveCount(1)
        ->and($events->published[0])->toBeInstanceOf(AuctionOpened::class);
});

it('computes closesAt from the injected AuctionDurationPolicy', function () {
    $auctions = new InMemoryAuctionRepository;
    $presenceVerification = new InMemorySellerPresenceVerification;
    $presenceVerification->stub('seller-1', 'queue-1', aSnapshot(ConfidenceTier::EvidenceVerified, 180));
    $events = new RecordingDomainEventPublisher;
    $clock = new FrozenClock(new DateTimeImmutable('2026-09-30 10:00:00'));

    $service = new AuctionService($auctions, $presenceVerification, new FixedAuctionDurationPolicy(30 * 60), $events, $clock);

    $auction = $service->open('auction-8', 'queue-1', 'seller-1', 'session-8', usd(1000));

    expect($auction->closesAt())->toEqual(new DateTimeImmutable('2026-09-30 10:30:00'));
});

it('fails closed when no verification record exists at all', function () {
    $auctions = new InMemoryAuctionRepository;
    $presenceVerification = new InMemorySellerPresenceVerification;
    $events = new RecordingDomainEventPublisher;

    $service = makeAuctionService($auctions, $presenceVerification, $events);

    expect(fn () => $service->open('auction-2', 'queue-1', 'seller-1', 'session-2', usd(1000)))
        ->toThrow(InsufficientConfidenceTier::class);

    expect($auctions->saved)->toBe([])
        ->and($events->published)->toBe([]);
});

it('fails closed when the seller is only Location Verified', function () {
    $auctions = new InMemoryAuctionRepository;
    $presenceVerification = new InMemorySellerPresenceVerification;
    $presenceVerification->stub('seller-1', 'queue-1', aSnapshot(ConfidenceTier::LocationVerified, 80));
    $events = new RecordingDomainEventPublisher;

    $service = makeAuctionService($auctions, $presenceVerification, $events);

    expect(fn () => $service->open('auction-3', 'queue-1', 'seller-1', 'session-3', usd(1000)))
        ->toThrow(InsufficientConfidenceTier::class);

    expect($auctions->saved)->toBe([]);
});

it('fails closed when the seller is Unverified', function () {
    $auctions = new InMemoryAuctionRepository;
    $presenceVerification = new InMemorySellerPresenceVerification;
    $presenceVerification->stub('seller-1', 'queue-1', aSnapshot(ConfidenceTier::Unverified, 0));
    $events = new RecordingDomainEventPublisher;

    $service = makeAuctionService($auctions, $presenceVerification, $events);

    expect(fn () => $service->open('auction-4', 'queue-1', 'seller-1', 'session-4', usd(1000)))
        ->toThrow(InsufficientConfidenceTier::class);
});

it('propagates the repository exception when the presence session already backs another auction', function () {
    $auctions = new InMemoryAuctionRepository;
    $presenceVerification = new InMemorySellerPresenceVerification;
    $presenceVerification->stub('seller-1', 'queue-1', aSnapshot(ConfidenceTier::EvidenceVerified, 180));
    $presenceVerification->stub('seller-2', 'queue-1', aSnapshot(ConfidenceTier::EvidenceVerified, 180));
    $events = new RecordingDomainEventPublisher;

    $service = makeAuctionService($auctions, $presenceVerification, $events);
    $service->open('auction-5', 'queue-1', 'seller-1', 'session-shared', usd(1000));

    expect(fn () => $service->open('auction-6', 'queue-1', 'seller-2', 'session-shared', usd(1000)))
        ->toThrow(PresenceSessionAlreadyConsumed::class);
});

it('does not swallow an unexpected error from the verification adapter', function () {
    $auctions = new InMemoryAuctionRepository;
    $events = new RecordingDomainEventPublisher;

    $failingVerification = new class implements SellerPresenceVerification
    {
        public function verificationFor(string $sellerId, string $queueId): ?PresenceVerificationSnapshot
        {
            throw new RuntimeException('adapter unavailable');
        }
    };

    $service = new AuctionService($auctions, $failingVerification, new FixedAuctionDurationPolicy(30 * 60), $events, new FrozenClock);

    expect(fn () => $service->open('auction-7', 'queue-1', 'seller-1', 'session-7', usd(1000)))
        ->toThrow(RuntimeException::class, 'adapter unavailable');

    expect($auctions->saved)->toBe([]);
});
