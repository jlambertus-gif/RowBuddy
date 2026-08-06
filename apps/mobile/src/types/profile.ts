/**
 * Mirrors ShowProfileController/UpdateProfileController's response
 * shape exactly. Mobile is the first-ever writer of language/
 * country_code/currency/timezone (ADR-028 Decision 4) — all four are
 * optional on the request since an update may touch only a subset.
 */

export interface Profile {
  name: string;
  email: string;
  language: string | null;
  country_code: string | null;
  currency: string | null;
  timezone: string | null;
}

export interface UpdateProfileRequest {
  name: string;
  email: string;
  language?: string;
  country_code?: string;
  currency?: string;
  timezone?: string;
}
