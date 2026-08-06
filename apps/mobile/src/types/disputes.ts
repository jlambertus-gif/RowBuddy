import { Money } from '@/types/auctions';

/**
 * Mirrors RendersDisputeFilingResponses::toResponse() exactly
 * (apps/web/app/Http/Controllers/Concerns) — never exposes
 * resolutionNotes or evidenceFoundFraudulent, both admin-only concerns
 * outside this sprint's no-administration boundary.
 */

export type DisputeStatus = 'opened' | 'resolved';

export type DisputeResolutionOutcome =
  'release_to_seller' | 'refund_to_buyer' | 'split' | 'cancelled';

export interface Dispute {
  id: string;
  transfer_id: string;
  reason: string;
  status: DisputeStatus;
  opened_at: string;
  resolution_outcome: DisputeResolutionOutcome | null;
  refund_amount: Money | null;
  resolved_at: string | null;
}

export interface FileDisputeRequest {
  reason: string;
}
