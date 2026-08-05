/**
 * Hand-written types mirroring the backend's actual /api/v1/auth/* and
 * /api/v1/me response shapes (ADR-028 Decision 1 — no OpenAPI spec
 * exists to generate from). Keep these in sync with
 * apps/web/app/Http/Controllers/Concerns/RendersApiAuthResponses.php
 * and the individual Api*Controller classes.
 */

export interface ApiUser {
  id: number;
  name: string;
  email: string;
  email_verified_at: string | null;
}

export interface AuthSession {
  user: ApiUser;
  token: string;
}

export interface RegisterRequest {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export interface LoginRequest {
  email: string;
  password: string;
}

export interface ForgotPasswordRequest {
  email: string;
}

export interface ResetPasswordRequest {
  token: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export interface SentResponse {
  sent: boolean;
}

export interface AlreadyVerifiedResponse {
  already_verified: boolean;
}

export interface ResetResponse {
  reset: boolean;
}

/**
 * Matches Laravel's default JSON validation-exception shape exactly —
 * this app never overrides it for the mobile API surface (ADR-028).
 */
export interface ApiValidationError {
  message: string;
  errors: Record<string, string[]>;
}
