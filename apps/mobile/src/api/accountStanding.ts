import { apiFetch } from '@/api/client';
import { AccountStanding } from '@/types/accountStanding';

/**
 * Mobile Sprint 6 (ADR-028 §3). The first HTTP surface — web or
 * mobile — for a user's own account standing.
 */
export function fetchAccountStanding(): Promise<{ data: AccountStanding }> {
  return apiFetch('/api/v1/account-standing');
}
