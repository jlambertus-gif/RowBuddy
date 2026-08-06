import { apiFetch } from '@/api/client';
import { Dispute, FileDisputeRequest } from '@/types/disputes';

/**
 * Mobile Sprint 4 (ADR-028 §3). The first HTTP surface
 * DisputeFilingService has ever had. Dispute resolution remains
 * exclusively the existing admin-only web surface — no mobile
 * equivalent exists or is added here.
 */

export function fileDispute(
  transferId: string,
  payload: FileDisputeRequest,
): Promise<{ data: Dispute }> {
  return apiFetch(`/api/v1/transfers/${transferId}/disputes`, { method: 'POST', body: payload });
}

export function fetchDispute(disputeId: string): Promise<{ data: Dispute }> {
  return apiFetch(`/api/v1/disputes/${disputeId}`);
}
