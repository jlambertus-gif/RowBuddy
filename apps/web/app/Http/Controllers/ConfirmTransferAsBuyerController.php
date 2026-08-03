<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersTransferResponses;
use App\Http\Requests\ConfirmTransferAsBuyerRequest;
use Illuminate\Http\JsonResponse;
use RowBuddy\SharedKernel\Exceptions\NotFoundException;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;
use RowBuddy\Transfers\Application\TransferConfirmationService;
use RowBuddy\Transfers\Exceptions\ConfirmationOutsideGeofence;
use RowBuddy\Transfers\Exceptions\IllegalStateTransition;
use RowBuddy\Transfers\Exceptions\TransferAccessDenied;

/**
 * The buyer's half of the QR confirmation flow (Phase 9, ADR-027 Sprint
 * 3, ADR-027 Architecture Refinements §5). requestingUserId is derived
 * exclusively from the authenticated user — never accepted from request
 * input, nor is any sellerId/buyerId.
 */
final class ConfirmTransferAsBuyerController extends Controller
{
    use RendersTransferResponses;

    public function __invoke(ConfirmTransferAsBuyerRequest $request, TransferConfirmationService $service, string $transferId): JsonResponse
    {
        $requestingUserId = (string) $request->user()->id;
        $geo = new GeoPoint($request->float('latitude'), $request->float('longitude'));

        try {
            $service->confirmByBuyer($transferId, $requestingUserId, $geo);
        } catch (NotFoundException) {
            return $this->notFound();
        } catch (TransferAccessDenied) {
            return $this->accessDenied();
        } catch (ConfirmationOutsideGeofence) {
            return $this->outsideGeofence();
        } catch (IllegalStateTransition) {
            return $this->illegalState();
        }

        return response()->json(['data' => ['confirmed' => true]], 200);
    }
}
