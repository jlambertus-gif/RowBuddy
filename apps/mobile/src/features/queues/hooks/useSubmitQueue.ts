import { useMutation } from '@tanstack/react-query';

import { submitQueue } from '@/api/queues';
import { SubmitQueueRequest } from '@/types/queues';

/**
 * No automatic retry (TanStack Query's mutation default, left
 * unoverridden): queue submission has no Idempotency-Key contract —
 * a retried request after a successful-but-lost response would create
 * a second, duplicate pending queue rather than a safe replay.
 */
export function useSubmitQueue() {
  return useMutation({
    mutationFn: (payload: SubmitQueueRequest) => submitQueue(payload),
  });
}
