import '@/i18n';

import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { ApiError } from '@/api/client';
import { useSubmitQueue } from '@/features/queues/hooks/useSubmitQueue';
import { getCurrentCoordinates, LocationPermissionDeniedError } from '@/lib/location';

import SubmitQueue from '../submit';

jest.mock('@/features/queues/hooks/useSubmitQueue');
jest.mock('@/lib/location');

describe('Submit queue screen', () => {
  afterEach(() => {
    jest.clearAllMocks();
  });

  it('disables the submit button until every required field is filled', async () => {
    (useSubmitQueue as jest.Mock).mockReturnValue({
      mutate: jest.fn(),
      isPending: false,
      isSuccess: false,
    });

    await render(<SubmitQueue />);

    expect(screen.getByTestId('submit-queue-button')).toBeDisabled();

    await fireEvent.changeText(screen.getByTestId('queue-category-input'), 'concert');
    await fireEvent.changeText(screen.getByTestId('queue-country-input'), 'us');
    await fireEvent.changeText(screen.getByTestId('queue-latitude-input'), '32.7157');
    await fireEvent.changeText(screen.getByTestId('queue-longitude-input'), '-117.1611');
    await fireEvent.changeText(screen.getByTestId('queue-radius-input'), '200');

    expect(screen.getByTestId('submit-queue-button')).not.toBeDisabled();
  });

  it('submits the form with the jurisdiction country uppercased', async () => {
    const mutate = jest.fn();
    (useSubmitQueue as jest.Mock).mockReturnValue({ mutate, isPending: false, isSuccess: false });

    await render(<SubmitQueue />);
    await fireEvent.changeText(screen.getByTestId('queue-category-input'), 'concert');
    await fireEvent.changeText(screen.getByTestId('queue-country-input'), 'us');
    await fireEvent.changeText(screen.getByTestId('queue-latitude-input'), '32.7157');
    await fireEvent.changeText(screen.getByTestId('queue-longitude-input'), '-117.1611');
    await fireEvent.changeText(screen.getByTestId('queue-radius-input'), '200');
    await fireEvent.press(screen.getByTestId('submit-queue-button'));

    expect(mutate).toHaveBeenCalledWith(
      {
        category: 'concert',
        jurisdiction_country: 'US',
        latitude: 32.7157,
        longitude: -117.1611,
        radius_meters: 200,
      },
      expect.anything(),
    );
  });

  it('fills latitude/longitude from the current location on request', async () => {
    (useSubmitQueue as jest.Mock).mockReturnValue({
      mutate: jest.fn(),
      isPending: false,
      isSuccess: false,
    });
    (getCurrentCoordinates as jest.Mock).mockResolvedValue({
      latitude: 40.7128,
      longitude: -74.006,
    });

    await render(<SubmitQueue />);
    await fireEvent.press(screen.getByTestId('use-current-location-button'));

    await waitFor(() =>
      expect(screen.getByTestId('queue-latitude-input').props.value).toBe('40.7128'),
    );
    expect(screen.getByTestId('queue-longitude-input').props.value).toBe('-74.006');
  });

  it('shows a location error without blocking manual entry when location fails', async () => {
    (useSubmitQueue as jest.Mock).mockReturnValue({
      mutate: jest.fn(),
      isPending: false,
      isSuccess: false,
    });
    (getCurrentCoordinates as jest.Mock).mockRejectedValue(new LocationPermissionDeniedError());

    await render(<SubmitQueue />);
    await fireEvent.press(screen.getByTestId('use-current-location-button'));

    await waitFor(() =>
      expect(
        screen.getByText("We couldn't determine your location. You can still enter it manually."),
      ).toBeVisible(),
    );
  });

  it('shows the success message once submitted', async () => {
    (useSubmitQueue as jest.Mock).mockReturnValue({
      mutate: jest.fn(),
      isPending: false,
      isSuccess: true,
    });

    await render(<SubmitQueue />);

    expect(screen.getByText('Your queue has been submitted for approval.')).toBeVisible();
  });

  it('shows the backend error message when submission fails', async () => {
    const mutate = jest.fn((_, options) =>
      options?.onError?.(new ApiError('This category is restricted.', 422, null)),
    );
    (useSubmitQueue as jest.Mock).mockReturnValue({ mutate, isPending: false, isSuccess: false });

    await render(<SubmitQueue />);
    await fireEvent.changeText(screen.getByTestId('queue-category-input'), 'medical_emergency');
    await fireEvent.changeText(screen.getByTestId('queue-country-input'), 'us');
    await fireEvent.changeText(screen.getByTestId('queue-latitude-input'), '32.7157');
    await fireEvent.changeText(screen.getByTestId('queue-longitude-input'), '-117.1611');
    await fireEvent.changeText(screen.getByTestId('queue-radius-input'), '200');
    await fireEvent.press(screen.getByTestId('submit-queue-button'));

    await waitFor(() => expect(screen.getByText('This category is restricted.')).toBeVisible());
  });
});
