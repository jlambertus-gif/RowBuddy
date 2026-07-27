<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Contracts;

use RowBuddy\QueuePresence\Exceptions\DuplicateActivePresenceSession;
use RowBuddy\QueuePresence\PresenceSession;

/**
 * Domain-facing persistence port. Deliberately expresses no ORM/storage
 * concept — implementations (e.g. an Eloquent adapter) translate between
 * whatever storage technology backs them and the {@see PresenceSession}
 * aggregate, never the reverse.
 */
interface PresenceSessionRepository
{
    /**
     * @throws DuplicateActivePresenceSession if the seller already has
     *                                        another active session for this queue
     */
    public function save(PresenceSession $session): void;

    public function findById(string $id): ?PresenceSession;

    /**
     * The most recently started session for this seller and queue,
     * regardless of its Active/Ended status — or null if none exists at
     * all. Feeds cross-module presence-verification reads (e.g. the
     * Auctions module's SellerPresenceVerification adapter); this
     * QueuePresence package remains unaware of any such caller.
     */
    public function findLatestBySellerAndQueue(string $sellerId, string $queueId): ?PresenceSession;
}
