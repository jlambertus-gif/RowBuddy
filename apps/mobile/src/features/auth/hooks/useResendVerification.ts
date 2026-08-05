import { useMutation } from '@tanstack/react-query';

import { resendEmailVerification } from '@/api/auth';

export function useResendVerification() {
  return useMutation({
    retry: false,
    mutationFn: () => resendEmailVerification(),
  });
}
