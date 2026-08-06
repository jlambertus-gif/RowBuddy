import { apiFetch } from '@/api/client';
import { SavedPaymentMethod, SetupIntentDraft } from '@/types/payments';

/**
 * POST /api/v1/buyer-payment-methods/setup-intent — a Sanctum-guarded
 * mirror of the existing web session-guarded route (ADR-028 Sprint 3):
 * BeginBuyerPaymentMethodSetupController is reused verbatim.
 */
export function beginPaymentMethodSetup(): Promise<{ data: SetupIntentDraft }> {
  return apiFetch('/api/v1/buyer-payment-methods/setup-intent', { method: 'POST' });
}

/**
 * POST /api/v1/buyer-payment-methods — completes setup after the Stripe
 * React Native SDK has confirmed the SetupIntent entirely client-side;
 * only the resulting setup_intent id is ever sent here, never card data.
 */
export function completePaymentMethodSetup(
  setupIntentId: string,
): Promise<{ data: SavedPaymentMethod }> {
  return apiFetch('/api/v1/buyer-payment-methods', {
    method: 'POST',
    body: { setup_intent_id: setupIntentId },
  });
}
