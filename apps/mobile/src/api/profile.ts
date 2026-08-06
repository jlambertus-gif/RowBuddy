import { apiFetch } from '@/api/client';
import { Profile, UpdateProfileRequest } from '@/types/profile';

/**
 * Mobile Sprint 4 (ADR-028 §3/§4). Deliberately separate from
 * GET /api/v1/me (Sprint 1) — see ShowProfileController's own doc
 * comment for why the two response shapes are kept distinct.
 */

export function fetchProfile(): Promise<{ data: Profile }> {
  return apiFetch('/api/v1/profile');
}

export function updateProfile(payload: UpdateProfileRequest): Promise<{ data: Profile }> {
  return apiFetch('/api/v1/profile', { method: 'PUT', body: payload });
}
