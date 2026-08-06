import { apiFetch } from '@/api/client';
import {
  ConfirmTransferAsBuyerRequest,
  ConfirmTransferAsSellerRequest,
  QrToken,
  Transfer,
  TransferConfirmed,
} from '@/types/transfers';

/**
 * All four functions below are Sanctum-guarded mirrors of the existing
 * web session-guarded /transfers* routes (ADR-028 Sprint 3) — every
 * controller they call is reused verbatim.
 */

export function fetchTransfer(transferId: string): Promise<{ data: Transfer }> {
  return apiFetch(`/api/v1/transfers/${transferId}`);
}

/** Buyer-only; re-readable any number of times within the transfer's own window. */
export function revealQrToken(transferId: string): Promise<{ data: QrToken }> {
  return apiFetch(`/api/v1/transfers/${transferId}/qr-token`);
}

export function confirmTransferAsSeller(
  transferId: string,
  payload: ConfirmTransferAsSellerRequest,
): Promise<{ data: TransferConfirmed }> {
  return apiFetch(`/api/v1/transfers/${transferId}/confirm-as-seller`, {
    method: 'POST',
    body: payload,
  });
}

export function confirmTransferAsBuyer(
  transferId: string,
  payload: ConfirmTransferAsBuyerRequest,
): Promise<{ data: TransferConfirmed }> {
  return apiFetch(`/api/v1/transfers/${transferId}/confirm-as-buyer`, {
    method: 'POST',
    body: payload,
  });
}
