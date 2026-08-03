<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersBidResponses;
use App\Http\Requests\PlaceBidRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use RowBuddy\Bids\Application\IdempotentBidPlacementService;
use RowBuddy\Bids\Exceptions\AuctionNotOpenForBidding;
use RowBuddy\Bids\Exceptions\BidderAccountSuspended;
use RowBuddy\Bids\Exceptions\BidIdempotencyKeyReused;
use RowBuddy\Bids\Exceptions\BidPlacementInProgress;
use RowBuddy\Bids\Exceptions\BidTooLow;
use RowBuddy\Bids\Exceptions\CurrencyMismatch;
use RowBuddy\Bids\Exceptions\SellerCannotBidOnOwnAuction;
use RowBuddy\SharedKernel\Exceptions\NotFoundException;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * The authenticated bid-placement endpoint (Phase 9, ADR-027 Architecture
 * Refinements §2). bidderId is derived exclusively from the authenticated
 * user — never accepted from request input. Duplicate-safety for HTTP
 * retries is delegated entirely to {@see IdempotentBidPlacementService};
 * this controller never calls BidService directly.
 */
final class PlaceBidController extends Controller
{
    use RendersBidResponses;

    public function __invoke(PlaceBidRequest $request, string $auctionId, IdempotentBidPlacementService $service): JsonResponse
    {
        $idempotencyKey = trim((string) $request->header('Idempotency-Key'));

        if ($idempotencyKey === '') {
            return $this->idempotencyKeyRequired();
        }

        $bidderId = (string) $request->user()->id;
        $amount = new Money(
            $request->integer('amount_minor_units'),
            new Currency($request->string('currency')->toString()),
        );

        try {
            $bid = $service->place($idempotencyKey, (string) Str::uuid(), $auctionId, $bidderId, $amount);
        } catch (NotFoundException) {
            return $this->notFound();
        } catch (AuctionNotOpenForBidding) {
            return $this->rejected('auction_not_open');
        } catch (SellerCannotBidOnOwnAuction) {
            return $this->rejected('seller_cannot_bid');
        } catch (BidTooLow) {
            return $this->rejected('bid_too_low');
        } catch (CurrencyMismatch) {
            return $this->rejected('currency_mismatch');
        } catch (BidderAccountSuspended) {
            return $this->rejected('account_suspended', 403);
        } catch (BidIdempotencyKeyReused) {
            return $this->rejected('idempotency_key_reused');
        } catch (BidPlacementInProgress) {
            return $this->placementInProgress();
        }

        return response()->json(['data' => $this->toResponse($bid)], 201);
    }
}
