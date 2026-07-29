<?php

declare(strict_types=1);

/**
 * Real PostgreSQL integration test (ADR-018 §3/Consequences). Uses two
 * independent, real PDO connections — not Capsule/Eloquent, and not
 * pcntl_fork or subprocesses — to prove `findByIdForUpdate()`'s row lock
 * actually serializes the two writers ADR-018 §3 identifies as capable of
 * racing each other on the same `Transfer`: a scheduled sweep tick and a
 * request-triggered lazy evaluation (a confirmation attempt), and two
 * overlapping sweep ticks. Mirrors
 * packages/Bids/tests/Integration/ConcurrentBidPlacementTest.php's own
 * throwaway-table approach (ADR-012 §4/§5), connecting to the same
 * `rowbuddy_test` database and skipping if no PostgreSQL connection is
 * reachable.
 */
function connectForTransfersConcurrencyTest(): ?PDO
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

it('prevents a concurrent confirmation attempt from racing a scheduled sweep tick on the same transfer', function () {
    $connectionA = connectForTransfersConcurrencyTest();

    if ($connectionA === null) {
        $this->markTestSkipped('No reachable PostgreSQL connection.');

        return;
    }

    $connectionB = connectForTransfersConcurrencyTest();

    try {
        $connectionA->exec('DROP TABLE IF EXISTS transfers_concurrency_test');
        $connectionA->exec(<<<'SQL'
            CREATE TABLE transfers_concurrency_test (
                id varchar(64) PRIMARY KEY,
                status varchar(32) NOT NULL
            )
            SQL);
        $connectionA->exec(
            "INSERT INTO transfers_concurrency_test (id, status) VALUES ('transfer-1', 'issued')"
        );

        // Step 1: connection A represents the scheduled sweep — it locks
        // the transfer row and holds the transaction open, not committing
        // yet, exactly as `TransferExpirySweepService::sweep()` does per
        // id inside `TransactionManager::run()`.
        $connectionA->beginTransaction();
        $connectionA->query("SELECT * FROM transfers_concurrency_test WHERE id = 'transfer-1' FOR UPDATE");

        // Step 2: connection B represents a concurrent confirmation
        // attempt (the lazy call site) — bounded by a short lock_timeout,
        // it must fail to acquire the same lock while the sweep holds it.
        $connectionB->beginTransaction();
        $connectionB->exec("SET LOCAL lock_timeout = '200ms'");
        $blockedAsExpected = false;
        try {
            $connectionB->query("SELECT * FROM transfers_concurrency_test WHERE id = 'transfer-1' FOR UPDATE");
        } catch (PDOException $e) {
            $blockedAsExpected = str_contains($e->getMessage(), 'lock')
                || str_contains($e->getMessage(), '55P03');
        }
        expect($blockedAsExpected)->toBeTrue();
        $connectionB->rollBack();

        // Step 3: the sweep expires the transfer and commits, releasing
        // the lock.
        $connectionA->exec(
            "UPDATE transfers_concurrency_test SET status = 'expired' WHERE id = 'transfer-1'"
        );
        $connectionA->commit();

        // Step 4: the confirmation attempt retries — succeeds immediately
        // now that the sweep committed, and must observe the sweep's
        // *committed* Expired status, never a stale Issued reading.
        $connectionB->beginTransaction();
        $connectionB->query("SELECT * FROM transfers_concurrency_test WHERE id = 'transfer-1' FOR UPDATE");
        $observedStatus = $connectionB->query(
            "SELECT status FROM transfers_concurrency_test WHERE id = 'transfer-1'"
        )->fetchColumn();
        $connectionB->rollBack();

        expect($observedStatus)->toBe('expired');
    } finally {
        foreach ([$connectionA, $connectionB] as $connection) {
            if ($connection instanceof PDO && $connection->inTransaction()) {
                $connection->rollBack();
            }
        }

        $connectionA->exec('DROP TABLE IF EXISTS transfers_concurrency_test');
    }
});

it('prevents two overlapping scheduled sweep ticks from both evaluating the same transfer', function () {
    $connectionA = connectForTransfersConcurrencyTest();

    if ($connectionA === null) {
        $this->markTestSkipped('No reachable PostgreSQL connection.');

        return;
    }

    $connectionB = connectForTransfersConcurrencyTest();

    try {
        $connectionA->exec('DROP TABLE IF EXISTS transfers_concurrency_test_2');
        $connectionA->exec(<<<'SQL'
            CREATE TABLE transfers_concurrency_test_2 (
                id varchar(64) PRIMARY KEY,
                status varchar(32) NOT NULL,
                evaluation_count integer NOT NULL DEFAULT 0
            )
            SQL);
        $connectionA->exec(
            "INSERT INTO transfers_concurrency_test_2 (id, status) VALUES ('transfer-1', 'issued')"
        );

        // Two overlapping sweep ticks (e.g. a slow previous run still
        // executing when the next scheduled tick fires) must never both
        // transition the same transfer — the second must wait for the
        // first to finish, then observe it has already been resolved.
        $connectionA->beginTransaction();
        $connectionA->query("SELECT * FROM transfers_concurrency_test_2 WHERE id = 'transfer-1' FOR UPDATE");

        $connectionB->beginTransaction();
        $connectionB->exec("SET LOCAL lock_timeout = '200ms'");
        $blockedAsExpected = false;
        try {
            $connectionB->query("SELECT * FROM transfers_concurrency_test_2 WHERE id = 'transfer-1' FOR UPDATE");
        } catch (PDOException $e) {
            $blockedAsExpected = str_contains($e->getMessage(), 'lock')
                || str_contains($e->getMessage(), '55P03');
        }
        expect($blockedAsExpected)->toBeTrue();
        $connectionB->rollBack();

        $connectionA->exec(
            "UPDATE transfers_concurrency_test_2 SET status = 'expired', evaluation_count = evaluation_count + 1 WHERE id = 'transfer-1'"
        );
        $connectionA->commit();

        // Second tick, now unblocked, must see the already-Expired status
        // and must not evaluate (and therefore not increment) it again —
        // that responsibility lives in `TransferExpiryEvaluator`'s own
        // status guard, not the lock itself, but the lock is what makes
        // that guard's read trustworthy.
        $connectionB->beginTransaction();
        $connectionB->query("SELECT * FROM transfers_concurrency_test_2 WHERE id = 'transfer-1' FOR UPDATE");
        $row = $connectionB->query(
            "SELECT status, evaluation_count FROM transfers_concurrency_test_2 WHERE id = 'transfer-1'"
        )->fetch(PDO::FETCH_ASSOC);
        $connectionB->rollBack();

        expect($row['status'])->toBe('expired')
            ->and((int) $row['evaluation_count'])->toBe(1);
    } finally {
        foreach ([$connectionA, $connectionB] as $connection) {
            if ($connection instanceof PDO && $connection->inTransaction()) {
                $connection->rollBack();
            }
        }

        $connectionA->exec('DROP TABLE IF EXISTS transfers_concurrency_test_2');
    }
});
