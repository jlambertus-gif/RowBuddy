import { useMutation, useQueryClient } from '@tanstack/react-query';

import { logout } from '@/api/auth';
import { clearAuthToken } from '@/lib/authToken';
import { clearStoredPushToken, getStoredPushToken } from '@/lib/pushToken';

import { authQueryKeys } from './queryKeys';

export function useLogout() {
  const queryClient = useQueryClient();

  return useMutation({
    retry: false,
    mutationFn: async () => {
      try {
        const expoPushToken = await getStoredPushToken();
        await logout(expoPushToken ?? undefined);
      } finally {
        // Always clear both local values, even if the network request
        // itself failed — an unreachable backend must never leave the
        // app looking logged in or holding a stale push token.
        await clearAuthToken();
        await clearStoredPushToken();
      }
    },
    onSuccess: () => {
      queryClient.setQueryData(authQueryKeys.currentUser, null);
    },
  });
}
