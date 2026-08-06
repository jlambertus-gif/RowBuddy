import { useMutation, useQueryClient } from '@tanstack/react-query';

import { confirmTransferAsSeller } from '@/api/transfers';
import { ConfirmTransferAsSellerRequest } from '@/types/transfers';

import { transferQueryKeys } from './queryKeys';

/**
 * No automatic retry (TanStack Query's mutation default, left
 * unoverridden deliberately): unlike bid placement, transfer
 * confirmation has no Idempotency-Key contract, so a retried request
 * after a successful-but-lost response would hit IllegalStateTransition
 * (422, "already confirmed"), not a safe replay — Sprint 3 constraint
 * #10.
 */
export function useConfirmTransferAsSeller(transferId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: ConfirmTransferAsSellerRequest) =>
      confirmTransferAsSeller(transferId, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: transferQueryKeys.transfer(transferId) });
    },
  });
}
