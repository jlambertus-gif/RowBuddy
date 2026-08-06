import { useMutation } from '@tanstack/react-query';

import { completePaymentMethodSetup } from '@/api/payments';

/**
 * No automatic retry (TanStack Query's mutation default, left
 * unoverridden): completing setup has no Idempotency-Key contract
 * either — Sprint 3 constraint #10.
 */
export function useCompletePaymentMethodSetup() {
  return useMutation({
    mutationFn: (setupIntentId: string) =>
      completePaymentMethodSetup(setupIntentId).then((response) => response.data),
  });
}
