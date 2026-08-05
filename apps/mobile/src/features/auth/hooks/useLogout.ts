import { useMutation, useQueryClient } from '@tanstack/react-query';

import { logout } from '@/api/auth';
import { clearAuthToken } from '@/lib/authToken';

import { authQueryKeys } from './queryKeys';

export function useLogout() {
  const queryClient = useQueryClient();

  return useMutation({
    retry: false,
    mutationFn: async () => {
      try {
        await logout();
      } finally {
        // Always clear the local token, even if the network request
        // itself failed — an unreachable backend must never leave the
        // app looking logged in.
        await clearAuthToken();
      }
    },
    onSuccess: () => {
      queryClient.setQueryData(authQueryKeys.currentUser, null);
    },
  });
}
