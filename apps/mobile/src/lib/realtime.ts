import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Connects to the same Reverb server the web app already uses,
 * subscribing to the identical public `auctions.{id}` channel — no
 * backend change (architecture review §1/§6). `Pusher` must be
 * injected explicitly via the `Pusher` option: laravel-echo's own
 * connector otherwise falls back to `window.Pusher`, which doesn't
 * exist in React Native.
 *
 * Config is overridable via EXPO_PUBLIC_REVERB_* for non-local
 * environments. Defaults match this repo's own local docker-compose
 * setup. Android emulators cannot resolve "localhost" as the host
 * machine — override EXPO_PUBLIC_REVERB_HOST to 10.0.2.2 there.
 */
let echoInstance: Echo<'reverb'> | null = null;

function getEcho(): Echo<'reverb'> {
  if (!echoInstance) {
    echoInstance = new Echo<'reverb'>({
      broadcaster: 'reverb',
      Pusher,
      key: process.env.EXPO_PUBLIC_REVERB_APP_KEY ?? '43vsggynk3jze2sohqfq',
      wsHost: process.env.EXPO_PUBLIC_REVERB_HOST ?? 'localhost',
      wsPort: Number(process.env.EXPO_PUBLIC_REVERB_PORT ?? 8080),
      wssPort: Number(process.env.EXPO_PUBLIC_REVERB_PORT ?? 8080),
      forceTLS: (process.env.EXPO_PUBLIC_REVERB_SCHEME ?? 'http') === 'https',
      enabledTransports: ['ws', 'wss'],
    });
  }

  return echoInstance;
}

/**
 * Subscribes to one auction's public snapshot updates. Returns an
 * unsubscribe function the caller must invoke on cleanup (e.g. a
 * `useEffect` teardown) — leaving channels open leaks socket
 * subscriptions across screens.
 */
export function subscribeToAuctionSnapshot<T>(
  auctionId: string,
  onSnapshot: (snapshot: T) => void,
): () => void {
  const channelName = `auctions.${auctionId}`;
  getEcho().channel(channelName).listen('.snapshot.updated', onSnapshot);

  return () => {
    getEcho().leaveChannel(channelName);
  };
}
