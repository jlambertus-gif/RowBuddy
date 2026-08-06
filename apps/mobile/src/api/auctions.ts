import { apiFetch } from '@/api/client';
import { AuctionSnapshot, PlaceBidRequest, PlacedBid } from '@/types/auctions';

/**
 * GET /auctions/{id} — public, unauthenticated, unversioned (reused
 * exactly as-is; see the note in src/api/client.ts).
 */
export function fetchAuction(auctionId: string): Promise<{ data: AuctionSnapshot }> {
  return apiFetch(`/auctions/${auctionId}`, { authenticated: false });
}

/**
 * POST /api/v1/auctions/{id}/bids — the one endpoint that genuinely
 * needed a new, Sanctum-guarded mirror (ADR-028 Sprint 2): the existing
 * web route only accepts the session guard, which a native client has
 * no way to present. `idempotencyKey` must be a fresh value per user
 * gesture — the caller decides that, not this function.
 */
export function placeBid(
  auctionId: string,
  payload: PlaceBidRequest,
  idempotencyKey: string,
): Promise<{ data: PlacedBid }> {
  return apiFetch(`/api/v1/auctions/${auctionId}/bids`, {
    method: 'POST',
    body: payload,
    headers: { 'Idempotency-Key': idempotencyKey },
  });
}
