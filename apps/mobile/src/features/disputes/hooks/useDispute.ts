import { useQuery } from '@tanstack/react-query';

import { fetchDispute } from '@/api/disputes';

import { disputeQueryKeys } from './queryKeys';

export function useDispute(disputeId: string) {
  return useQuery({
    queryKey: disputeQueryKeys.dispute(disputeId),
    queryFn: () => fetchDispute(disputeId).then((response) => response.data),
  });
}
