import { useQuery } from '@tanstack/react-query';

import { revealQrToken } from '@/api/transfers';

/**
 * Buyer-only, on-demand reveal (matches web's "reveal code" button —
 * never auto-fetched). `enabled: false` plus the caller's own `refetch()`
 * models a one-shot imperative read, not a background-refreshed query.
 */
export function useRevealQrToken(transferId: string) {
  return useQuery({
    queryKey: ['transfers', transferId, 'qr-token'],
    queryFn: () => revealQrToken(transferId).then((response) => response.data.qr_token),
    enabled: false,
  });
}
