import { useMutation, useQueryClient } from '@tanstack/react-query';

import { confirmTransferAsBuyer } from '@/api/transfers';
import { ConfirmTransferAsBuyerRequest } from '@/types/transfers';

import { transferQueryKeys } from './queryKeys';

/** See the identical no-automatic-retry note in useConfirmTransferAsSeller. */
export function useConfirmTransferAsBuyer(transferId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: ConfirmTransferAsBuyerRequest) =>
      confirmTransferAsBuyer(transferId, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: transferQueryKeys.transfer(transferId) });
    },
  });
}
