import '@/i18n';

import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import * as ExpoRouter from 'expo-router';
import React from 'react';

import { useLogin } from '@/features/auth/hooks/useLogin';

import Login from '../login';

jest.mock('expo-router', () => {
  // eslint-disable-next-line @typescript-eslint/no-require-imports -- jest.mock factories can't reference top-level imports; an inline require is the documented workaround.
  const { Text } = require('react-native');
  return {
    router: { replace: jest.fn() },
    Link: ({ children }: { children: React.ReactNode }) => <Text>{children}</Text>,
  };
});

jest.mock('@/features/auth/hooks/useLogin');

describe('Login screen', () => {
  afterEach(() => {
    jest.clearAllMocks();
  });

  it('submits the entered credentials and navigates to /home for a verified user', async () => {
    const mutate = jest.fn((_vars, options) => {
      options?.onSuccess?.({ user: { email_verified_at: '2026-01-01T00:00:00Z' }, token: 'x' });
    });
    (useLogin as jest.Mock).mockReturnValue({ mutate, isPending: false, error: null });

    await render(<Login />);

    await fireEvent.changeText(screen.getByTestId('login-email'), 'buyer@example.com');
    await fireEvent.changeText(screen.getByTestId('login-password'), 'a-password');
    await fireEvent.press(screen.getByTestId('login-submit'));

    expect(mutate).toHaveBeenCalledWith(
      { email: 'buyer@example.com', password: 'a-password' },
      expect.anything(),
    );
    await waitFor(() => expect(ExpoRouter.router.replace).toHaveBeenCalledWith('/home'));
  });

  it('routes an unverified user to the verify-email screen instead of home', async () => {
    const mutate = jest.fn((_vars, options) => {
      options?.onSuccess?.({ user: { email_verified_at: null }, token: 'x' });
    });
    (useLogin as jest.Mock).mockReturnValue({ mutate, isPending: false, error: null });

    await render(<Login />);
    await fireEvent.press(screen.getByTestId('login-submit'));

    await waitFor(() =>
      expect(ExpoRouter.router.replace).toHaveBeenCalledWith('/(auth)/verify-email'),
    );
  });

  it('shows the submitting label while the mutation is pending', async () => {
    (useLogin as jest.Mock).mockReturnValue({ mutate: jest.fn(), isPending: true, error: null });

    await render(<Login />);

    expect(screen.getByText('Logging in...')).toBeVisible();
  });
});
