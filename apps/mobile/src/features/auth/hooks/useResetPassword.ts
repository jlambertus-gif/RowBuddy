import { useMutation } from '@tanstack/react-query';

import { resetPassword } from '@/api/auth';
import { ResetPasswordRequest } from '@/types/auth';

/**
 * Not treated as safely auto-retryable — reset-password has no
 * documented idempotency contract on the backend (ADR-028 §9),
 * unlike bid placement's Idempotency-Key header. A failed attempt
 * always surfaces a manual retry to the user.
 */
export function useResetPassword() {
  return useMutation({
    retry: false,
    mutationFn: (payload: ResetPasswordRequest) => resetPassword(payload),
  });
}
