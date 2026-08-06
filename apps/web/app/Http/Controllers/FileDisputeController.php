<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersDisputeFilingResponses;
use App\Http\Requests\FileDisputeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use RowBuddy\Disputes\Application\DisputeFilingService;
use RowBuddy\Disputes\Exceptions\DisputeAlreadyExistsForTransfer;
use RowBuddy\Disputes\Exceptions\DisputeFilingNotAuthorized;
use RowBuddy\Disputes\Exceptions\DisputeFilingWindowElapsed;
use RowBuddy\Disputes\Exceptions\TransferNotEligibleForDispute;

/**
 * Mobile Sprint 4 (ADR-028 §3). The first HTTP surface
 * DisputeFilingService has ever had — the existing admin routes
 * (`admin.disputes.*`) are resolution-only and untouched by this file.
 * requestingUserId (the filing buyer) is derived exclusively from the
 * authenticated user; DisputeFilingService itself rejects anyone who
 * isn't actually this transfer's buyer.
 */
final class FileDisputeController extends Controller
{
    use RendersDisputeFilingResponses;

    public function __invoke(FileDisputeRequest $request, DisputeFilingService $service, string $transferId): JsonResponse
    {
        $buyerId = (string) $request->user()->id;

        try {
            $dispute = $service->file(
                (string) Str::uuid(),
                $transferId,
                $buyerId,
                $request->string('reason')->toString(),
            );
        } catch (DisputeAlreadyExistsForTransfer) {
            return $this->alreadyExists();
        } catch (TransferNotEligibleForDispute) {
            return $this->notFound();
        } catch (DisputeFilingNotAuthorized) {
            return $this->notAuthorized();
        } catch (DisputeFilingWindowElapsed) {
            return $this->windowElapsed();
        }

        return response()->json(['data' => $this->toResponse($dispute)], 201);
    }
}
