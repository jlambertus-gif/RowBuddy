/**
 * A fresh value per user gesture, sent as the Idempotency-Key header
 * bid placement requires. Only needs to be unique, not cryptographically
 * secure — avoids pulling in a UUID dependency for that alone.
 */
export function generateIdempotencyKey(): string {
  return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`;
}
