import { useQuery } from '@tanstack/react-query';

import { fetchProfile } from '@/api/profile';

import { profileQueryKeys } from './queryKeys';

export function useProfile() {
  return useQuery({
    queryKey: profileQueryKeys.profile,
    queryFn: () => fetchProfile().then((response) => response.data),
  });
}
