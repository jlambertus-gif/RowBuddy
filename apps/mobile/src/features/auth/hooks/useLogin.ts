import { useMutation, useQueryClient } from '@tanstack/react-query';

import { login } from '@/api/auth';
import { setAuthToken } from '@/lib/authToken';
import { LoginRequest } from '@/types/auth';

import { authQueryKeys } from './queryKeys';

export function useLogin() {
  const queryClient = useQueryClient();

  return useMutation({
    // No automatic retry (ADR-028 Decision 7/§9) — a failed login has no
    // documented idempotency contract, unlike bid placement.
    retry: false,
    mutationFn: async (payload: LoginRequest) => {
      const { data } = await login(payload);
      await setAuthToken(data.token);

      return data;
    },
    onSuccess: (data) => {
      queryClient.setQueryData(authQueryKeys.currentUser, data.user);
    },
  });
}
