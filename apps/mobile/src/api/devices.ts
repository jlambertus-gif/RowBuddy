import { apiFetch } from '@/api/client';
import { RegisterDeviceTokenRequest } from '@/types/devices';

/**
 * Mobile Sprint 4 (ADR-028 Decision 6). Registering an already-known
 * token again is a safe, idempotent refresh, not a duplicate — see
 * EloquentDeviceTokenRepository::registerOrRefresh()'s upsert-by-token
 * behavior.
 */
export function registerDeviceToken(payload: RegisterDeviceTokenRequest): Promise<null> {
  return apiFetch('/api/v1/devices', { method: 'POST', body: payload });
}
