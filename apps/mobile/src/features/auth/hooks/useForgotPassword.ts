import { useMutation } from '@tanstack/react-query';

import { forgotPassword } from '@/api/auth';
import { ForgotPasswordRequest } from '@/types/auth';

export function useForgotPassword() {
  return useMutation({
    retry: false,
    mutationFn: (payload: ForgotPasswordRequest) => forgotPassword(payload),
  });
}
