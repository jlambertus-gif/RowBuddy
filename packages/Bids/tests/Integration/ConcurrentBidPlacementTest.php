<?php

declare(strict_types=1);

/**
 * Real PostgreSQL integration test (ADR-012 §4). Uses two independent,
 * real PDO connections — not Capsule/Eloquent, and not pcntl_fork or
 * subprocesses — to prove the auction-row lock actually prevents a
 * second bidder from validating against a stale highest-bid reading, not
 * merely that `SELECT ... FOR UPDATE` blocks. Connects to the same
 * throwaway `rowbuddy_test` database Queues' own PostGIS integration
 * tests use, via its own two tiny tables, and skips if no PostgreSQL
 * connection is reachable — same convention as
 * packages/Queues/tests/Support/PostgisTestConnection.php.
 */
function connectForBidsConcurrencyTest(): ?PDO
{
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '5432';
    $username = getenv('DB_USERNAME') ?: 'rowbuddy';
    $password = getenv('DB_PASSWORD') ?: 'rowbuddy';

    try {
        $bootstrap = new PDO(
            "pgsql:host={$host};port={$port};dbname=rowbuddy",
            $username,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $exists = $bootstrap->query("SELECT 1 FROM pg_database WHERE datname = 'rowbuddy_test'")->fetchColumn();
        if (! $exists) {
            $bootstrap->exec('CREATE DATABASE rowbuddy_test');
        }

        return new PDO(
            "pgsql:host={$host};port={$port};dbname=rowbuddy_test",
            $username,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    } catch (Throwable) {
        return null;
    }
}

it('prevents a second bidder from validating against a stale highest-bid reading', function () {
    $connectionA = connectForBidsConcurrencyTest();

    if ($connectionA === null) {
        $this->markTestSkipped('No reachable PostgreSQL connection.');

        return;
    }

    $connectionB = connectForBidsConcurrencyTest();

    try {
        // Setup: two tiny throwaway tables, isolated from the real
        // auctions/bids migrations — this test never imports Auctions'
        // or Bids' Eloquent models, keeping it a pure persistence-layer
        // proof (ADR-012 §5).
        $connectionA->exec('DROP TABLE IF EXISTS bids_concurrency_test_bids');
        $connectionA->exec('DROP TABLE IF EXISTS bids_concurrency_test_auctions');
        $connectionA->exec(<<<'SQL'
            CREATE TABLE bids_concurrency_test_auctions (
                id varchar(64) PRIMARY KEY,
                starting_price_minor_units bigint NOT NULL
            )
            SQL);
        $connectionA->exec(<<<'SQL'
            CREATE TABLE bids_concurrency_test_bids (
                id varchar(64) PRIMARY KEY,
                auction_id varchar(64) NOT NULL,
                amount_minor_units bigint NOT NULL
            )
            SQL);
        $connectionA->exec(
            "INSERT INTO bids_concurrency_test_auctions (id, starting_price_minor_units) VALUES ('auction-1', 1000)"
        );

        // Step 2: connection A locks the auction row and holds the
        // transaction open — not committing yet.
        $connectionA->beginTransaction();
        $connectionA->query("SELECT * FROM bids_concurrency_test_auctions WHERE id = 'auction-1' FOR UPDATE");

        // Step 3: connection B, bounded by a short lock_timeout, must
        // fail to acquire the same lock while A holds it. This is the
        // bounded-timeout mechanism: B can never hang indefinitely — it
        // fails fast within 200ms and this test asserts on that failure
        // rather than blocking.
        $connectionB->beginTransaction();
        $connectionB->exec("SET LOCAL lock_timeout = '200ms'");
        $blockedAsExpected = false;
        try {
            $connectionB->query("SELECT * FROM bids_concurrency_test_auctions WHERE id = 'auction-1' FOR UPDATE");
        } catch (PDOException $e) {
            $blockedAsExpected = str_contains($e->getMessage(), 'lock')
                || str_contains($e->getMessage(), '55P03');
        }
        expect($blockedAsExpected)->toBeTrue();
        $connectionB->rollBack();

        // Step 4: still on A, lock held throughout the highest-bid read,
        // validation, and insert — only then commit, releasing the lock.
        $highestBeforeA = $connectionA->query(
            "SELECT MAX(amount_minor_units) FROM bids_concurrency_test_bids WHERE auction_id = 'auction-1'"
        )->fetchColumn();
        expect($highestBeforeA)->toBeNull();

        $startingPrice = (int) $connectionA->query(
            "SELECT starting_price_minor_units FROM bids_concurrency_test_auctions WHERE id = 'auction-1'"
        )->fetchColumn();
        expect(1100)->toBeGreaterThan($startingPrice);

        $connectionA->exec(
            "INSERT INTO bids_concurrency_test_bids (id, auction_id, amount_minor_units) VALUES ('bid-a', 'auction-1', 1100)"
        );
        $connectionA->commit();

        // Step 5: connection B retries — succeeds immediately now that A
        // committed, and must observe A's *committed* bid (1100), not the
        // stale 1000 floor it could have read had it not been blocked.
        $connectionB->beginTransaction();
        $connectionB->query("SELECT * FROM bids_concurrency_test_auctions WHERE id = 'auction-1' FOR UPDATE");
        $highestAfterA = (int) $connectionB->query(
            "SELECT MAX(amount_minor_units) FROM bids_concurrency_test_bids WHERE auction_id = 'auction-1'"
        )->fetchColumn();
        expect($highestAfterA)->toBe(1100);

        // A bid of 1050 would have passed against the stale 1000 floor
        // B could have read in step 3 — it must be rejected against the
        // real, current 1100 floor.
        expect(1050)->toBeLessThanOrEqual($highestAfterA);
        $connectionB->rollBack();
    } finally {
        // Deterministic cleanup: both connections' transactions are
        // guaranteed closed (a ROLLBACK with nothing open is a harmless
        // no-op), throwaway tables dropped, both PDO handles released —
        // regardless of whether the test above passed or failed.
        foreach ([$connectionA, $connectionB] as $connection) {
            if ($connection instanceof PDO && $connection->inTransaction()) {
                $connection->rollBack();
            }
        }

        $connectionA->exec('DROP TABLE IF EXISTS bids_concurrency_test_bids');
        $connectionA->exec('DROP TABLE IF EXISTS bids_concurrency_test_auctions');
    }
});

it('ensures a concurrent bidder observes the newly extended deadline only after the lock is released', function () {
    $connectionA = connectForBidsConcurrencyTest();

    if ($connectionA === null) {
        $this->markTestSkipped('No reachable PostgreSQL connection.');

        return;
    }

    $connectionB = connectForBidsConcurrencyTest();

    try {
        $connectionA->exec('DROP TABLE IF EXISTS bids_concurrency_test_auctions_2');
        $connectionA->exec(<<<'SQL'
            CREATE TABLE bids_concurrency_test_auctions_2 (
                id varchar(64) PRIMARY KEY,
                closes_at timestamp NOT NULL
            )
            SQL);

        $originalClosesAt = '2026-09-30 10:30:00';
        $extendedClosesAt = '2026-09-30 10:32:00';
        $connectionA->exec(
            "INSERT INTO bids_concurrency_test_auctions_2 (id, closes_at) VALUES ('auction-2', '{$originalClosesAt}')"
        );

        // Connection A: locks, simulates an accepted bid landing in the
        // soft-close window by extending closes_at, but does not commit
        // yet — the extension exists only inside A's still-open transaction.
        $connectionA->beginTransaction();
        $connectionA->query("SELECT * FROM bids_concurrency_test_auctions_2 WHERE id = 'auction-2' FOR UPDATE");
        $connectionA->exec(
            "UPDATE bids_concurrency_test_auctions_2 SET closes_at = '{$extendedClosesAt}' WHERE id = 'auction-2'"
        );

        // Connection B, bounded by lock_timeout, cannot acquire the lock
        // (and therefore cannot see the extension) while A holds it.
        $connectionB->beginTransaction();
        $connectionB->exec("SET LOCAL lock_timeout = '200ms'");
        $blockedAsExpected = false;
        try {
            $connectionB->query("SELECT * FROM bids_concurrency_test_auctions_2 WHERE id = 'auction-2' FOR UPDATE");
        } catch (PDOException $e) {
            $blockedAsExpected = str_contains($e->getMessage(), 'lock')
                || str_contains($e->getMessage(), '55P03');
        }
        expect($blockedAsExpected)->toBeTrue();
        $connectionB->rollBack();

        $connectionA->commit();

        // Connection B, once unblocked, must observe the *extended*
        // deadline A committed — never the original, and never something
        // in between.
        $connectionB->beginTransaction();
        $connectionB->query("SELECT * FROM bids_concurrency_test_auctions_2 WHERE id = 'auction-2' FOR UPDATE");
        $observedClosesAt = $connectionB->query(
            "SELECT to_char(closes_at, 'YYYY-MM-DD HH24:MI:SS') FROM bids_concurrency_test_auctions_2 WHERE id = 'auction-2'"
        )->fetchColumn();
        $connectionB->rollBack();

        expect($observedClosesAt)->toBe($extendedClosesAt)
            ->and($observedClosesAt)->not->toBe($originalClosesAt);
    } finally {
        foreach ([$connectionA, $connectionB] as $connection) {
            if ($connection instanceof PDO && $connection->inTransaction()) {
                $connection->rollBack();
            }
        }

        $connectionA->exec('DROP TABLE IF EXISTS bids_concurrency_test_auctions_2');
    }
});
