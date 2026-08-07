import { useQuery } from '@tanstack/react-query';

import { fetchAccountStanding } from '@/api/accountStanding';

export function useAccountStanding() {
  return useQuery({
    queryKey: ['account-standing'],
    queryFn: () => fetchAccountStanding().then((response) => response.data),
  });
}
