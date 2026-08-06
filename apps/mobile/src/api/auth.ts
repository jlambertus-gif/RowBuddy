import { apiFetch } from '@/api/client';
import {
  AlreadyVerifiedResponse,
  ApiUser,
  AuthSession,
  ForgotPasswordRequest,
  LoginRequest,
  RegisterRequest,
  ResetPasswordRequest,
  ResetResponse,
  SentResponse,
} from '@/types/auth';

export function register(payload: RegisterRequest): Promise<{ data: AuthSession }> {
  return apiFetch('/api/v1/auth/register', {
    method: 'POST',
    body: payload,
    authenticated: false,
  });
}

export function login(payload: LoginRequest): Promise<{ data: AuthSession }> {
  return apiFetch('/api/v1/auth/login', { method: 'POST', body: payload, authenticated: false });
}

/**
 * `expoPushToken` is optional and backward compatible (ADR-028 Decision
 * 6) — passed only when a device token was actually registered on this
 * device, so the backend can remove it: "a token is removed on logout...
 * never pushed to once its owning session's token has been revoked."
 */
export function logout(expoPushToken?: string): Promise<null> {
  return apiFetch('/api/v1/auth/logout', {
    method: 'POST',
    body: expoPushToken ? { expo_push_token: expoPushToken } : {},
  });
}

export function forgotPassword(payload: ForgotPasswordRequest): Promise<{ data: SentResponse }> {
  return apiFetch('/api/v1/auth/forgot-password', {
    method: 'POST',
    body: payload,
    authenticated: false,
  });
}

export function resetPassword(payload: ResetPasswordRequest): Promise<{ data: ResetResponse }> {
  return apiFetch('/api/v1/auth/reset-password', {
    method: 'POST',
    body: payload,
    authenticated: false,
  });
}

export function resendEmailVerification(): Promise<{
  data: SentResponse | AlreadyVerifiedResponse;
}> {
  return apiFetch('/api/v1/auth/email/verification-notification', { method: 'POST' });
}

export function fetchCurrentUser(): Promise<{ data: ApiUser }> {
  return apiFetch('/api/v1/me');
}
