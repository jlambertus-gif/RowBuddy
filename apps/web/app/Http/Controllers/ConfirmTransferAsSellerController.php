<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersTransferResponses;
use App\Http\Requests\ConfirmTransferAsSellerRequest;
use Illuminate\Http\JsonResponse;
use RowBuddy\SharedKernel\Exceptions\NotFoundException;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;
use RowBuddy\Transfers\Application\TransferConfirmationService;
use RowBuddy\Transfers\Exceptions\ConfirmationOutsideGeofence;
use RowBuddy\Transfers\Exceptions\IllegalStateTransition;
use RowBuddy\Transfers\Exceptions\InvalidQrToken;
use RowBuddy\Transfers\Exceptions\TransferAccessDenied;

/**
 * The seller's half of the QR confirmation flow (Phase 9, ADR-027 Sprint
 * 3, ADR-027 Architecture Refinements §5). requestingUserId is derived
 * exclusively from the authenticated user — never accepted from request
 * input, nor is any sellerId/buyerId. Exposes existing domain behavior
 * through a thin boundary; invents no second confirmation protocol.
 * Replay-safe by the existing domain guard alone: a second confirmation
 * attempt from the legitimate seller is rejected as an illegal state
 * transition (already confirmed), never silently re-applied.
 */
final class ConfirmTransferAsSellerController extends Controller
{
    use RendersTransferResponses;

    public function __invoke(ConfirmTransferAsSellerRequest $request, TransferConfirmationService $service, string $transferId): JsonResponse
    {
        $requestingUserId = (string) $request->user()->id;
        $geo = new GeoPoint($request->float('latitude'), $request->float('longitude'));

        try {
            $service->confirmBySeller($transferId, $requestingUserId, $request->string('qr_token')->toString(), $geo);
        } catch (NotFoundException) {
            return $this->notFound();
        } catch (TransferAccessDenied) {
            return $this->accessDenied();
        } catch (InvalidQrToken) {
            return $this->invalidQrToken();
        } catch (ConfirmationOutsideGeofence) {
            return $this->outsideGeofence();
        } catch (IllegalStateTransition) {
            return $this->illegalState();
        }

        return response()->json(['data' => ['confirmed' => true]], 200);
    }
}
