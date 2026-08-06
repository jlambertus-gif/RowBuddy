import { useMutation, useQueryClient } from '@tanstack/react-query';

import { submitRating } from '@/api/ratings';
import { SubmitRatingRequest } from '@/types/ratings';

import { ratingQueryKeys } from './queryKeys';

/**
 * No automatic retry (TanStack Query's mutation default, left
 * unoverridden): rating submission has no Idempotency-Key contract — a
 * retried request after a successful-but-lost response would hit
 * RatingAlreadyExistsForTransferAndRater (409), not a safe replay.
 */
export function useSubmitRating(transferId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: SubmitRatingRequest) => submitRating(transferId, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ratingQueryKeys.transferRatings(transferId) });
    },
  });
}
