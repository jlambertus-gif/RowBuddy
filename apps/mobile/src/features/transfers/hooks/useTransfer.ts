import { useQuery } from '@tanstack/react-query';

import { fetchTransfer } from '@/api/transfers';

import { transferQueryKeys } from './queryKeys';

/**
 * Matches web's Transfers/Show.jsx exactly: fetch on mount, refetch only
 * after this device's own confirmation succeeds (see useConfirmTransferAsSeller/
 * useConfirmTransferAsBuyer) — no Reverb subscription and no polling,
 * since the backend has no live channel for Transfers at all. If the
 * other participant confirms first, this screen only reflects that once
 * the user re-opens/refreshes it — an existing product gap on web
 * itself, not something mobile invents or is asked to fix this sprint.
 */
export function useTransfer(transferId: string) {
  return useQuery({
    queryKey: transferQueryKeys.transfer(transferId),
    queryFn: () => fetchTransfer(transferId).then((response) => response.data),
  });
}
