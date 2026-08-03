import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

let echoInstance = null;

/**
 * Lazily constructs a single Echo/Reverb client, only when a page actually
 * needs a live channel (Phase 9, ADR-027 Architecture Refinements §3) —
 * most pages never subscribe to anything, so most visitors never pay for
 * a WebSocket connection.
 */
export function getEcho() {
    if (echoInstance) {
        return echoInstance;
    }

    echoInstance = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    return echoInstance;
}
