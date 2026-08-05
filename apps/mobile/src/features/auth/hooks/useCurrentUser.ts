import { useQuery } from '@tanstack/react-query';

import { fetchCurrentUser } from '@/api/auth';

import { authQueryKeys } from './queryKeys';

/**
 * Authenticated identity bootstrap (ADR-028 Sprint 1 scope). Disabled
 * until the caller knows a token exists — see app/_layout.tsx's own
 * splash/token-bootstrap logic, which decides whether to enable this at
 * all before ever rendering an authenticated screen.
 */
export function useCurrentUser(enabled: boolean) {
  return useQuery({
    queryKey: authQueryKeys.currentUser,
    queryFn: async () => (await fetchCurrentUser()).data,
    enabled,
    retry: false,
  });
}
