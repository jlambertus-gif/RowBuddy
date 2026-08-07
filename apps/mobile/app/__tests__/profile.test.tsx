import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { ApiError } from '@/api/client';
import { useLogout } from '@/features/auth/hooks/useLogout';
import { useNotificationPermissionStatus } from '@/features/notifications/hooks/useNotificationPermissionStatus';
import { useRegisterPushNotifications } from '@/features/notifications/hooks/useRegisterPushNotifications';
import { useAccountStanding } from '@/features/profile/hooks/useAccountStanding';
import { useProfile } from '@/features/profile/hooks/useProfile';
import { useUpdateProfile } from '@/features/profile/hooks/useUpdateProfile';
import i18n from '@/i18n';
import { NotificationPermissionDeniedError } from '@/lib/pushNotifications';

import Profile from '../profile';

jest.mock('expo-router', () => ({
  router: { push: jest.fn(), replace: jest.fn() },
}));
jest.mock('@/features/profile/hooks/useProfile');
jest.mock('@/features/profile/hooks/useUpdateProfile');
jest.mock('@/features/profile/hooks/useAccountStanding');
jest.mock('@/features/auth/hooks/useLogout');
jest.mock('@/features/notifications/hooks/useNotificationPermissionStatus');
jest.mock('@/features/notifications/hooks/useRegisterPushNotifications');

function baseProfile(overrides: Record<string, unknown> = {}) {
  return {
    name: 'Ada Lovelace',
    email: 'ada@example.test',
    language: 'en',
    country_code: 'US',
    currency: 'USD',
    timezone: 'America/Los_Angeles',
    ...overrides,
  };
}

describe('Profile screen', () => {
  beforeEach(() => {
    (useAccountStanding as jest.Mock).mockReturnValue({ data: { state: 'active' } });
  });

  afterEach(() => {
    jest.clearAllMocks();
    void i18n.changeLanguage('en');
  });

  it('pre-fills the form with the loaded profile', async () => {
    (useProfile as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: false,
      data: baseProfile(),
    });
    (useUpdateProfile as jest.Mock).mockReturnValue({
      mutate: jest.fn(),
      isPending: false,
      isSuccess: false,
    });
    (useLogout as jest.Mock).mockReturnValue({ mutate: jest.fn(), isPending: false });
    (useNotificationPermissionStatus as jest.Mock).mockReturnValue({ data: false });
    (useRegisterPushNotifications as jest.Mock).mockReturnValue({
      mutateAsync: jest.fn(),
      isPending: false,
    });

    await render(<Profile />);

    expect(screen.getByDisplayValue('Ada Lovelace')).toBeVisible();
    expect(screen.getByDisplayValue('ada@example.test')).toBeVisible();
  });

  it('switches the active UI language immediately when a language option is pressed', async () => {
    (useProfile as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: false,
      data: baseProfile({ language: 'en' }),
    });
    (useUpdateProfile as jest.Mock).mockReturnValue({
      mutate: jest.fn(),
      isPending: false,
      isSuccess: false,
    });
    (useLogout as jest.Mock).mockReturnValue({ mutate: jest.fn(), isPending: false });
    (useNotificationPermissionStatus as jest.Mock).mockReturnValue({ data: false });
    (useRegisterPushNotifications as jest.Mock).mockReturnValue({
      mutateAsync: jest.fn(),
      isPending: false,
    });

    await render(<Profile />);
    await fireEvent.press(screen.getByTestId('language-option-es'));

    await waitFor(() => expect(i18n.language).toBe('es'));
  });

  it('saves the form, including locale preferences, via useUpdateProfile', async () => {
    const mutate = jest.fn();
    (useProfile as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: false,
      data: baseProfile(),
    });
    (useUpdateProfile as jest.Mock).mockReturnValue({ mutate, isPending: false, isSuccess: false });
    (useLogout as jest.Mock).mockReturnValue({ mutate: jest.fn(), isPending: false });
    (useNotificationPermissionStatus as jest.Mock).mockReturnValue({ data: false });
    (useRegisterPushNotifications as jest.Mock).mockReturnValue({
      mutateAsync: jest.fn(),
      isPending: false,
    });

    await render(<Profile />);
    await fireEvent.press(screen.getByTestId('save-profile-button'));

    expect(mutate).toHaveBeenCalledWith(
      {
        name: 'Ada Lovelace',
        email: 'ada@example.test',
        language: 'en',
        country_code: 'US',
        currency: 'USD',
        timezone: 'America/Los_Angeles',
      },
      expect.anything(),
    );
  });

  it('shows an "enabled" state instead of a button once push notifications are already granted', async () => {
    (useProfile as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: false,
      data: baseProfile(),
    });
    (useUpdateProfile as jest.Mock).mockReturnValue({
      mutate: jest.fn(),
      isPending: false,
      isSuccess: false,
    });
    (useLogout as jest.Mock).mockReturnValue({ mutate: jest.fn(), isPending: false });
    (useNotificationPermissionStatus as jest.Mock).mockReturnValue({ data: true });
    (useRegisterPushNotifications as jest.Mock).mockReturnValue({
      mutateAsync: jest.fn(),
      isPending: false,
    });

    await render(<Profile />);

    expect(screen.queryByTestId('enable-notifications-button')).toBeNull();
    expect(screen.getByText('Enabled on this device.')).toBeVisible();
  });

  it('requests push permission when the enable button is pressed, showing a denial message if refused', async () => {
    const mutateAsync = jest.fn().mockRejectedValue(new NotificationPermissionDeniedError());
    (useProfile as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: false,
      data: baseProfile(),
    });
    (useUpdateProfile as jest.Mock).mockReturnValue({
      mutate: jest.fn(),
      isPending: false,
      isSuccess: false,
    });
    (useLogout as jest.Mock).mockReturnValue({ mutate: jest.fn(), isPending: false });
    (useNotificationPermissionStatus as jest.Mock).mockReturnValue({ data: false });
    (useRegisterPushNotifications as jest.Mock).mockReturnValue({ mutateAsync, isPending: false });

    await render(<Profile />);
    await fireEvent.press(screen.getByTestId('enable-notifications-button'));

    await waitFor(() =>
      expect(
        screen.getByText(
          'Notification permission was denied. You can enable it from your device settings.',
        ),
      ).toBeVisible(),
    );
  });

  it('logs out and navigates to login', async () => {
    const mutate = jest.fn((_, options) => options?.onSettled?.());
    (useProfile as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: false,
      data: baseProfile(),
    });
    (useUpdateProfile as jest.Mock).mockReturnValue({
      mutate: jest.fn(),
      isPending: false,
      isSuccess: false,
    });
    (useLogout as jest.Mock).mockReturnValue({ mutate, isPending: false });
    (useNotificationPermissionStatus as jest.Mock).mockReturnValue({ data: false });
    (useRegisterPushNotifications as jest.Mock).mockReturnValue({
      mutateAsync: jest.fn(),
      isPending: false,
    });
    const { router } = jest.requireMock('expo-router');

    await render(<Profile />);
    await fireEvent.press(screen.getByTestId('profile-logout-button'));

    expect(router.replace).toHaveBeenCalledWith('/(auth)/login');
  });

  it('shows the backend error message when saving fails', async () => {
    const mutate = jest.fn((_, options) =>
      options?.onError?.(new ApiError('Unsupported language.', 422, null)),
    );
    (useProfile as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: false,
      data: baseProfile(),
    });
    (useUpdateProfile as jest.Mock).mockReturnValue({ mutate, isPending: false, isSuccess: false });
    (useLogout as jest.Mock).mockReturnValue({ mutate: jest.fn(), isPending: false });
    (useNotificationPermissionStatus as jest.Mock).mockReturnValue({ data: false });
    (useRegisterPushNotifications as jest.Mock).mockReturnValue({
      mutateAsync: jest.fn(),
      isPending: false,
    });

    await render(<Profile />);
    await fireEvent.press(screen.getByTestId('save-profile-button'));

    await waitFor(() => expect(screen.getByText('Unsupported language.')).toBeVisible());
  });

  it('shows an active account status', async () => {
    (useProfile as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: false,
      data: baseProfile(),
    });
    (useUpdateProfile as jest.Mock).mockReturnValue({
      mutate: jest.fn(),
      isPending: false,
      isSuccess: false,
    });
    (useLogout as jest.Mock).mockReturnValue({ mutate: jest.fn(), isPending: false });
    (useNotificationPermissionStatus as jest.Mock).mockReturnValue({ data: false });
    (useRegisterPushNotifications as jest.Mock).mockReturnValue({
      mutateAsync: jest.fn(),
      isPending: false,
    });

    await render(<Profile />);

    expect(screen.getByTestId('account-standing')).toBeVisible();
    expect(screen.getByText('Active')).toBeVisible();
  });

  it('shows a suspended account status warning', async () => {
    (useAccountStanding as jest.Mock).mockReturnValue({ data: { state: 'suspended' } });
    (useProfile as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: false,
      data: baseProfile(),
    });
    (useUpdateProfile as jest.Mock).mockReturnValue({
      mutate: jest.fn(),
      isPending: false,
      isSuccess: false,
    });
    (useLogout as jest.Mock).mockReturnValue({ mutate: jest.fn(), isPending: false });
    (useNotificationPermissionStatus as jest.Mock).mockReturnValue({ data: false });
    (useRegisterPushNotifications as jest.Mock).mockReturnValue({
      mutateAsync: jest.fn(),
      isPending: false,
    });

    await render(<Profile />);

    expect(screen.getByText('Suspended — some actions may be unavailable')).toBeVisible();
  });
});
