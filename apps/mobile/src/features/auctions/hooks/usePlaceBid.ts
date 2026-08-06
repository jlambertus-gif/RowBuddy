import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useRef } from 'react';

import { placeBid } from '@/api/auctions';
import { generateIdempotencyKey } from '@/lib/idempotencyKey';
import { PlaceBidRequest } from '@/types/auctions';

import { auctionQueryKeys } from './queryKeys';

/**
 * Bid placement is the one mutation in this app that IS safe to retry
 * automatically: its Idempotency-Key contract is server-verified (a 409
 * is explicitly documented as safe to retry with the same key —
 * architecture review §9). The key is generated once in `onMutate`
 * (which TanStack Query calls a single time per `mutate()` call, not
 * per retry attempt) and read from a ref inside `mutationFn` — generating
 * it directly inside `mutationFn` would mint a fresh key on every retry,
 * defeating the entire point of the header.
 */
export function usePlaceBid(auctionId: string) {
  const queryClient = useQueryClient();
  const idempotencyKeyRef = useRef('');

  return useMutation({
    retry: 2,
    onMutate: () => {
      idempotencyKeyRef.current = generateIdempotencyKey();
    },
    mutationFn: (payload: PlaceBidRequest) =>
      placeBid(auctionId, payload, idempotencyKeyRef.current),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: auctionQueryKeys.auction(auctionId) });
    },
  });
}
