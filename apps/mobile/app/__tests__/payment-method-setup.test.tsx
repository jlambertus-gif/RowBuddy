import '@/i18n';

import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { ApiError } from '@/api/client';
import { useBeginPaymentMethodSetup } from '@/features/payments/hooks/useBeginPaymentMethodSetup';
import { useCompletePaymentMethodSetup } from '@/features/payments/hooks/useCompletePaymentMethodSetup';

import PaymentMethodSetup from '../payment-method-setup';

jest.mock('@/features/payments/hooks/useBeginPaymentMethodSetup');
jest.mock('@/features/payments/hooks/useCompletePaymentMethodSetup');

const mockConfirmSetupIntent = jest.fn();
jest.mock('@stripe/stripe-react-native', () => ({
  StripeProvider: ({ children }: { children: React.ReactNode }) => children,
  CardField: 'CardField',
  useConfirmSetupIntent: () => ({ confirmSetupIntent: mockConfirmSetupIntent, loading: false }),
}));

describe('Payment method setup screen', () => {
  afterEach(() => {
    jest.clearAllMocks();
  });

  it('begins setup once on mount', async () => {
    const mutate = jest.fn();
    (useBeginPaymentMethodSetup as jest.Mock).mockReturnValue({
      mutate,
      isError: false,
      data: undefined,
    });
    (useCompletePaymentMethodSetup as jest.Mock).mockReturnValue({
      mutateAsync: jest.fn(),
      isPending: false,
      isSuccess: false,
    });

    await render(<PaymentMethodSetup />);

    expect(mutate).toHaveBeenCalledTimes(1);
  });

  it('mounts the card field once the SetupIntent draft arrives, disabling save until the card is complete', async () => {
    (useBeginPaymentMethodSetup as jest.Mock).mockReturnValue({
      mutate: jest.fn(),
      isError: false,
      data: { client_secret: 'seti_123_secret', publishable_key: 'pk_test_123' },
    });
    (useCompletePaymentMethodSetup as jest.Mock).mockReturnValue({
      mutateAsync: jest.fn(),
      isPending: false,
      isSuccess: false,
    });

    await render(<PaymentMethodSetup />);

    expect(screen.getByTestId('payment-card-field')).toBeVisible();
    expect(screen.getByTestId('save-card-button')).toBeDisabled();
  });

  it('confirms the SetupIntent client-side, then posts only the resulting id to the backend', async () => {
    mockConfirmSetupIntent.mockResolvedValue({ setupIntent: { id: 'seti_123' }, error: undefined });
    const mutateAsync = jest
      .fn()
      .mockResolvedValue({ saved: true, saved_at: '2026-01-01T00:00:00Z' });
    (useBeginPaymentMethodSetup as jest.Mock).mockReturnValue({
      mutate: jest.fn(),
      isError: false,
      data: { client_secret: 'seti_123_secret', publishable_key: 'pk_test_123' },
    });
    (useCompletePaymentMethodSetup as jest.Mock).mockReturnValue({
      mutateAsync,
      isPending: false,
      isSuccess: false,
    });

    await render(<PaymentMethodSetup />);

    await fireEvent(screen.getByTestId('payment-card-field'), 'onCardChange', { complete: true });
    await fireEvent.press(screen.getByTestId('save-card-button'));

    await waitFor(() =>
      expect(mockConfirmSetupIntent).toHaveBeenCalledWith('seti_123_secret', {
        paymentMethodType: 'Card',
      }),
    );
    expect(mutateAsync).toHaveBeenCalledWith('seti_123');
  });

  it('shows the Stripe client-side error and never calls the backend when confirmation fails', async () => {
    mockConfirmSetupIntent.mockResolvedValue({
      error: { message: 'Your card number is incorrect.' },
    });
    const mutateAsync = jest.fn();
    (useBeginPaymentMethodSetup as jest.Mock).mockReturnValue({
      mutate: jest.fn(),
      isError: false,
      data: { client_secret: 'seti_123_secret', publishable_key: 'pk_test_123' },
    });
    (useCompletePaymentMethodSetup as jest.Mock).mockReturnValue({
      mutateAsync,
      isPending: false,
      isSuccess: false,
    });

    await render(<PaymentMethodSetup />);

    await fireEvent(screen.getByTestId('payment-card-field'), 'onCardChange', { complete: true });
    await fireEvent.press(screen.getByTestId('save-card-button'));

    await waitFor(() => expect(screen.getByText('Your card number is incorrect.')).toBeVisible());
    expect(mutateAsync).not.toHaveBeenCalled();
  });

  it('shows the backend load error using the ApiError message when beginning setup fails', async () => {
    (useBeginPaymentMethodSetup as jest.Mock).mockReturnValue({
      mutate: jest.fn(),
      isError: true,
      error: new ApiError('Payment provider is temporarily unavailable.', 503, null),
      data: undefined,
    });
    (useCompletePaymentMethodSetup as jest.Mock).mockReturnValue({
      mutateAsync: jest.fn(),
      isPending: false,
      isSuccess: false,
    });

    await render(<PaymentMethodSetup />);

    expect(screen.getByText('Payment provider is temporarily unavailable.')).toBeVisible();
  });
});
