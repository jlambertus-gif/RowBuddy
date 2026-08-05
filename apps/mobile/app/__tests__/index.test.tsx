import '@/i18n';

import { render, screen } from '@testing-library/react-native';

import SprintZeroPlaceholder from '../index';

describe('Sprint 0 placeholder route', () => {
  it('renders the translated app name and placeholder copy', async () => {
    await render(<SprintZeroPlaceholder />);

    expect(screen.getByText('RowBuddy')).toBeVisible();
    expect(screen.getByText('RowBuddy Mobile — Sprint 0 scaffold')).toBeVisible();
  });
});
