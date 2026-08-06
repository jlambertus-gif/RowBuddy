/**
 * Mirrors AuctionPublicSnapshotAssembler's allowlisted shape exactly
 * (apps/web/app/Support/AuctionPublicSnapshotAssembler.php) — the same
 * shape used by both GET /auctions/{id} and the auctions.{id} Reverb
 * channel's snapshot.updated event, so one type covers both sources.
 */

export interface Money {
  amount_minor_units: number;
  currency: string;
}

export type AuctionStatus = 'open' | 'closing' | 'won' | 'expired' | 'cancelled';

export interface AuctionSnapshot {
  id: string;
  status: AuctionStatus;
  current_price: Money;
  minimum_next_amount: Money | null;
  closes_at: string;
  bid_count: number;
}

export interface PlaceBidRequest {
  amount_minor_units: number;
  currency: string;
}

export interface PlacedBid {
  id: string;
  auction_id: string;
  amount: Money;
  placed_at: string;
}
