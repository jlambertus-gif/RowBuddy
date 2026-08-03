<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Exceptions\MissingBuyerPaymentMethod;
use App\Support\AuctionParticipantResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;
use RowBuddy\Auctions\Events\AuctionWon;
use RowBuddy\Payments\Application\AuctionWinAuthorizationService;
use RowBuddy\Payments\Contracts\BuyerPaymentMethodRepository;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * The first real `AuctionWon` -> `AuctionWinAuthorizationService` wiring
 * (Phase 9, ADR-027 Sprint 3 Decision 2) — this capability has existed
 * since Phase 4 with no caller wiring it to a real event listener at all
 * until now. Additive composition-root wiring, not a replacement of any
 * existing direct-invocation orchestration (none existed for this).
 *
 * Resolves the winning buyer's saved payment method itself; if none
 * exists, fails loudly via {@see MissingBuyerPaymentMethod} rather than
 * silently skipping or inventing a fallback — `AuctionWinAuthorizationService`
 * itself is called only with a real, already-verified Stripe PaymentMethod
 * reference, exactly as it always has been.
 *
 * Queued so authorization never runs on the request thread that
 * committed `AuctionWon`'s own transaction — after-commit, mirroring
 * `BroadcastAuctionSnapshot`'s posture. Idempotent via
 * `AuctionWinAuthorizationService::handle()`'s own `findByAuctionId()`
 * short-circuit plus the Stripe-side deterministic idempotency key it
 * already derives from `auctionId` — a duplicate `AuctionWon` delivery
 * never authorizes twice.
 */
final class TriggerAuctionWinAuthorization implements ShouldQueue
{
    public function __construct(
        private readonly AuctionParticipantResolver $participants,
        private readonly BuyerPaymentMethodRepository $paymentMethods,
        private readonly AuctionWinAuthorizationService $authorizationService,
    ) {}

    public function handle(AuctionWon $event): void
    {
        $payload = $event->payload();
        $auctionId = (string) $payload['auction_id'];
        $winningBidId = (string) $payload['winning_bid_id'];

        $resolved = $this->participants->resolve($auctionId, $winningBidId);
        $paymentMethod = $this->paymentMethods->findByBuyerId($resolved->buyerId);

        if ($paymentMethod === null) {
            throw MissingBuyerPaymentMethod::forBuyer($resolved->buyerId, $auctionId);
        }

        $this->authorizationService->handle(
            (string) Str::uuid(),
            $auctionId,
            $winningBidId,
            $resolved->sellerId,
            $resolved->buyerId,
            new Money((int) $payload['winning_amount_minor_units'], new Currency((string) $payload['winning_amount_currency'])),
            $paymentMethod->stripePaymentMethodId,
        );
    }
}
