import { useMutation } from '@tanstack/react-query';

import { fileDispute } from '@/api/disputes';
import { FileDisputeRequest } from '@/types/disputes';

/**
 * No automatic retry (TanStack Query's mutation default, left
 * unoverridden): filing has no Idempotency-Key contract — a retried
 * request after a successful-but-lost response would hit
 * DisputeAlreadyExistsForTransfer (409), not a safe replay.
 */
export function useFileDispute(transferId: string) {
  return useMutation({
    mutationFn: (payload: FileDisputeRequest) => fileDispute(transferId, payload),
  });
}
