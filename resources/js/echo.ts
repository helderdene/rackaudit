import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Laravel Echo Bootstrap Configuration
 *
 * This file initializes Laravel Echo with either Reverb (local development) or
 * Pusher (production) configuration. Echo is used for real-time WebSocket
 * communication to receive broadcast events from the Laravel backend.
 *
 * Environment Detection:
 * - If VITE_PUSHER_APP_CLUSTER is set, uses Pusher (production)
 * - Otherwise, uses Reverb (local development)
 *
 * The Echo instance is attached to the window object for global access in components.
 */

// Make Pusher available globally (required by Laravel Echo)
declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: Echo<'reverb'> | Echo<'pusher'>;
    }
}

window.Pusher = Pusher;

/**
 * Determine if we should use Pusher (production) or Reverb (development).
 * Pusher cluster being set indicates production environment.
 */
const usePusher = !!import.meta.env.VITE_PUSHER_APP_CLUSTER;

/**
 * Initialize Laravel Echo with appropriate broadcaster configuration.
 * Private channels use CSRF authentication via the /broadcasting/auth endpoint.
 */
if (usePusher) {
    // Production: Pusher configuration
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: import.meta.env.VITE_PUSHER_APP_KEY,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
        forceTLS: true,
        authEndpoint: '/broadcasting/auth',
    });
} else {
    // Development: Reverb configuration
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
    });
}

export default window.Echo;
