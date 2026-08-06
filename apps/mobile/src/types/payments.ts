/**
 * Mirrors BeginBuyerPaymentMethodSetupController/
 * CompleteBuyerPaymentMethodSetupController's response shapes exactly
 * (apps/web/app/Http/Controllers). Deliberately never carries a Stripe
 * Customer or PaymentMethod reference — the backend never returns one
 * (ADR-027 Architecture Refinements §4).
 */

export interface SetupIntentDraft {
  client_secret: string;
  publishable_key: string;
}

export interface SavedPaymentMethod {
  saved: true;
  saved_at: string;
}
