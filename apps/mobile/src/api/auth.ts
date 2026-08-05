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
  return apiFetch('/auth/register', { method: 'POST', body: payload, authenticated: false });
}

export function login(payload: LoginRequest): Promise<{ data: AuthSession }> {
  return apiFetch('/auth/login', { method: 'POST', body: payload, authenticated: false });
}

export function logout(): Promise<null> {
  return apiFetch('/auth/logout', { method: 'POST' });
}

export function forgotPassword(payload: ForgotPasswordRequest): Promise<{ data: SentResponse }> {
  return apiFetch('/auth/forgot-password', {
    method: 'POST',
    body: payload,
    authenticated: false,
  });
}

export function resetPassword(payload: ResetPasswordRequest): Promise<{ data: ResetResponse }> {
  return apiFetch('/auth/reset-password', {
    method: 'POST',
    body: payload,
    authenticated: false,
  });
}

export function resendEmailVerification(): Promise<{
  data: SentResponse | AlreadyVerifiedResponse;
}> {
  return apiFetch('/auth/email/verification-notification', { method: 'POST' });
}

export function fetchCurrentUser(): Promise<{ data: ApiUser }> {
  return apiFetch('/me');
}
