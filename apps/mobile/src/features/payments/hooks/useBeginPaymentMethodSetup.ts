import { useMutation } from '@tanstack/react-query';

import { beginPaymentMethodSetup } from '@/api/payments';

/**
 * Modeled as a mutation (not a query) since it's a POST that creates
 * real server/Stripe-side state (a SetupIntent) as a side effect — the
 * screen triggers it once itself, on mount, mirroring web's own
 * useEffect-driven `axios.post('/buyer-payment-methods/setup-intent')`.
 */
export function useBeginPaymentMethodSetup() {
  return useMutation({
    mutationFn: () => beginPaymentMethodSetup().then((response) => response.data),
  });
}
