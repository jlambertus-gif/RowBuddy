import { useMutation, useQueryClient } from '@tanstack/react-query';

import { logout } from '@/api/auth';
import { clearAuthToken } from '@/lib/authToken';
import { clearStoredPushToken, getStoredPushToken } from '@/lib/pushToken';

export function useLogout() {
  const queryClient = useQueryClient();

  return useMutation({
    retry: false,
    mutationFn: async () => {
      try {
        const expoPushToken = await getStoredPushToken();
        await logout(expoPushToken ?? undefined);
      } finally {
        // Always clear all local state, even if the network request
        // itself failed — an unreachable backend must never leave the
        // app looking logged in, holding a stale push token, or (on a
        // shared device where a second account logs in without the app
        // fully restarting) showing the previous user's cached profile,
        // transfers, or ratings from TanStack Query's in-memory cache.
        await clearAuthToken();
        await clearStoredPushToken();
        queryClient.clear();
      }
    },
  });
}
