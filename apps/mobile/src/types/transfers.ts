/**
 * Mirrors RendersTransferResponses::toResponse()'s allowlisted shape
 * exactly (apps/web/app/Http/Controllers/Concerns) — never exposes the
 * QR token hash, a raw storage reference, or the other participant's
 * identity beyond whether they've confirmed yet.
 */

export type TransferStatus = 'issued' | 'confirmed' | 'expired' | 'cancelled';
export type TransferRole = 'seller' | 'buyer';

export interface Transfer {
  id: string;
  auction_id: string;
  status: TransferStatus;
  role: TransferRole;
  seller_confirmed: boolean;
  buyer_confirmed: boolean;
  confirmed_at: string | null;
  expires_at: string;
}

export interface QrToken {
  qr_token: string;
}

export interface ConfirmTransferAsSellerRequest {
  qr_token: string;
  latitude: number;
  longitude: number;
}

export interface ConfirmTransferAsBuyerRequest {
  latitude: number;
  longitude: number;
}

export interface TransferConfirmed {
  confirmed: true;
}
