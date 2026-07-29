<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Application;

use RowBuddy\Transfers\Contracts\DomainEventPublisher;
use RowBuddy\Transfers\Contracts\TransactionManager;
use RowBuddy\Transfers\Contracts\TransferRepository;

/**
 * The scheduled sweep (ADR-018 §3) — the only mechanism capable of
 * detecting a `Transfer` nobody ever touches again ("total silence", per
 * the ADR's "Alternatives considered"). Enumerates every still-`Issued`
 * transfer id with an unlocked read, then evaluates each individually
 * under its own row lock and its own transaction — one slow or failed
 * transfer never blocks or aborts evaluation of the rest, and no lock is
 * held any longer than a single transfer's own evaluation requires.
 *
 * Shares `TransferExpiryEvaluator` with the lazy, confirmation-time call
 * site (`TransferConfirmationService`) — this is Option D's whole point:
 * one piece of logic, two invocation sites, not two implementations.
 */
final class TransferExpirySweepService
{
    public function __construct(
        private readonly TransferRepository $transfers,
        private readonly TransferExpiryEvaluator $evaluator,
        private readonly TransactionManager $transactions,
        private readonly DomainEventPublisher $events,
    ) {}

    public function sweep(): int
    {
        $evaluated = 0;

        foreach ($this->transfers->findIssuedTransferIds() as $transferId) {
            $events = $this->transactions->run(function () use ($transferId) {
                $transfer = $this->transfers->findByIdForUpdate($transferId);

                if ($transfer === null) {
                    return [];
                }

                $this->evaluator->evaluate($transfer);
                $this->transfers->save($transfer);

                return $transfer->releaseEvents();
            });

            foreach ($events as $event) {
                $this->events->publish($event);
            }

            $evaluated++;
        }

        return $evaluated;
    }
}
