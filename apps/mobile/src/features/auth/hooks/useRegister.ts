import { useMutation, useQueryClient } from '@tanstack/react-query';

import { register } from '@/api/auth';
import { setAuthToken } from '@/lib/authToken';
import { RegisterRequest } from '@/types/auth';

import { authQueryKeys } from './queryKeys';

export function useRegister() {
  const queryClient = useQueryClient();

  return useMutation({
    retry: false,
    mutationFn: async (payload: RegisterRequest) => {
      const { data } = await register(payload);
      await setAuthToken(data.token);

      return data;
    },
    onSuccess: (data) => {
      queryClient.setQueryData(authQueryKeys.currentUser, data.user);
    },
  });
}
