import { useQuery } from '@tanstack/react-query';

import { fetchTransferRatings } from '@/api/ratings';

import { ratingQueryKeys } from './queryKeys';

export function useTransferRatings(transferId: string, enabled: boolean) {
  return useQuery({
    queryKey: ratingQueryKeys.transferRatings(transferId),
    queryFn: () => fetchTransferRatings(transferId).then((response) => response.data),
    enabled,
  });
}
