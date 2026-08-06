import { apiFetch } from '@/api/client';
import { SubmitRatingRequest, TransferRatings } from '@/types/ratings';

/**
 * Mobile Sprint 4 (ADR-028 §3). The first HTTP surface either endpoint
 * has ever had, on any client — no web route to mirror.
 */

export function submitRating(
  transferId: string,
  payload: SubmitRatingRequest,
): Promise<{
  data: {
    id: string;
    transfer_id: string;
    score: number;
    comment: string | null;
    submitted_at: string;
  };
}> {
  return apiFetch(`/api/v1/transfers/${transferId}/ratings`, { method: 'POST', body: payload });
}

export function fetchTransferRatings(transferId: string): Promise<{ data: TransferRatings }> {
  return apiFetch(`/api/v1/transfers/${transferId}/ratings`);
}
