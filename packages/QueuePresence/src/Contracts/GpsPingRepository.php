<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Contracts;

use DateTimeImmutable;
use RowBuddy\QueuePresence\Application\ConfidenceRecomputer;
use RowBuddy\QueuePresence\ValueObjects\GpsPingRecord;

/**
 * Domain-facing persistence port for recorded GPS pings.
 */
interface GpsPingRepository
{
    public function record(GpsPingRecord $ping): void;

    /**
     * The smallest (best) accuracy, in meters, among pings recorded
     * within the queue's geofence for this session — or null if none
     * exist. Feeds ADR-008's ConfidenceScorer via
     * {@see ConfidenceRecomputer}.
     */
    public function bestAccuracyWithinGeofence(string $presenceSessionId): ?float;

    /**
     * The most recent within-geofence ping's recorded-at timestamp for
     * this session, or null if none exists. Feeds cross-module
     * presence-verification reads (e.g. Auctions' live-proximity policy,
     * ADR-010 §3) — this QueuePresence package remains unaware of any
     * such caller or of what "stale" means to it.
     */
    public function latestWithinGeofencePingAt(string $presenceSessionId): ?DateTimeImmutable;
}
