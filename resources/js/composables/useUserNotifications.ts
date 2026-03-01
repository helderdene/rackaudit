import { onUnmounted, ref, shallowRef } from 'vue';
import type Echo from 'laravel-echo';
import type { Channel } from 'laravel-echo';

/**
 * Notification payload received from the broadcast event.
 */
export interface BroadcastNotification {
    id: string;
    type: string;
    message: string;
    audit_id?: number;
    audit_name?: string;
    datacenter_id?: number;
    datacenter_name?: string;
    read: boolean;
    created_at: string;
    relative_time: string;
    [key: string]: unknown;
}

/**
 * Callback type for notification handlers.
 */
export type NotificationCallback = (notification: BroadcastNotification) => void;

/**
 * Composable for subscribing to user-specific notification channel via Laravel Echo.
 *
 * Enables real-time notification delivery by subscribing to a private channel
 * specific to the authenticated user.
 *
 * @example
 * ```ts
 * const { onNotification, isConnected, cleanup } = useUserNotifications(userId);
 *
 * onNotification((notification) => {
 *   console.log('New notification:', notification);
 *   // Increment unread count, add to list, etc.
 * });
 * ```
 */
export function useUserNotifications(userId: number | null) {
    const channel = shallowRef<Channel | null>(null);
    const isConnected = ref(false);
    const handlers: NotificationCallback[] = [];

    /**
     * Get the Echo instance from the window object.
     */
    function getEcho(): Echo<'reverb'> | null {
        if (typeof window !== 'undefined' && window.Echo) {
            return window.Echo;
        }
        return null;
    }

    /**
     * Subscribe to the user's private notification channel.
     */
    function subscribe(): void {
        if (!userId) {
            return;
        }

        const echo = getEcho();
        if (!echo) {
            console.warn('Laravel Echo is not available. Real-time notifications will not work.');
            return;
        }

        try {
            channel.value = echo.private(`user.${userId}`);
            isConnected.value = true;

            // Listen for notification.created events
            channel.value.listen('.notification.created', (data: { notification: BroadcastNotification; timestamp: string }) => {
                handleNotification(data.notification);
            });
        } catch (error) {
            console.error('Failed to subscribe to user notification channel:', error);
            isConnected.value = false;
        }
    }

    /**
     * Handle incoming notification from broadcast.
     */
    function handleNotification(notification: BroadcastNotification): void {
        // Notify all registered handlers
        for (const handler of handlers) {
            handler(notification);
        }
    }

    /**
     * Register a callback for new notifications.
     *
     * @param callback - Function to call when a notification is received
     */
    function onNotification(callback: NotificationCallback): void {
        handlers.push(callback);
    }

    /**
     * Clean up the channel subscription.
     * Stops listening to all events and leaves the channel.
     */
    function cleanup(): void {
        const echo = getEcho();

        if (channel.value && userId) {
            try {
                channel.value.stopListening('.notification.created');
            } catch {
                // Ignore errors when stopping listeners
            }

            try {
                echo?.leave(`user.${userId}`);
            } catch {
                // Ignore errors when leaving channel
            }

            channel.value = null;
            isConnected.value = false;
        }
    }

    // Subscribe on composable creation if userId is provided
    if (userId) {
        subscribe();
    }

    // Cleanup on component unmount
    onUnmounted(() => {
        cleanup();
    });

    return {
        /**
         * Whether the Echo channel is currently connected.
         */
        isConnected,

        /**
         * Register a callback for new notifications.
         */
        onNotification,

        /**
         * Manually subscribe to the channel (called automatically on creation).
         */
        subscribe,

        /**
         * Manually cleanup the channel subscription.
         */
        cleanup,
    };
}
