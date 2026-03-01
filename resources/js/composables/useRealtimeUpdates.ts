import { onUnmounted, ref, shallowRef } from 'vue';
import type Echo from 'laravel-echo';
import type { Channel } from 'laravel-echo';

/**
 * Entity types that can receive real-time updates.
 */
export type EntityType =
    | 'connection'
    | 'device'
    | 'rack'
    | 'implementation_file'
    | 'finding'
    | 'audit';

/**
 * Structure of a pending update notification.
 */
export interface PendingUpdate {
    id: string;
    entityType: EntityType;
    entityId: number;
    action: string;
    user: {
        id: number;
        name: string;
    };
    timestamp: string;
    message?: string;
}

/**
 * Callback type for data change handlers.
 */
export type DataChangeCallback = (data: {
    entityId: number;
    action: string;
    user: { id: number; name: string };
    timestamp: string;
    [key: string]: unknown;
}) => void;

/**
 * Event handler registry entry.
 */
interface EventHandler {
    entityType: EntityType;
    callback: DataChangeCallback;
}

/**
 * Composable for subscribing to real-time datacenter updates via Laravel Echo.
 *
 * This composable manages WebSocket subscriptions to datacenter-scoped private channels,
 * allowing components to receive notifications when infrastructure data changes.
 *
 * @example
 * ```ts
 * const { onDataChange, hasUpdates, pendingUpdates, clearUpdates } = useRealtimeUpdates(datacenterId);
 *
 * onDataChange('connection', (data) => {
 *   console.log('Connection changed:', data);
 * });
 * ```
 */
export function useRealtimeUpdates(datacenterId: number | null) {
    const channel = shallowRef<Channel | null>(null);
    const hasUpdates = ref(false);
    const pendingUpdates = ref<PendingUpdate[]>([]);
    const isConnected = ref(false);
    const handlers: EventHandler[] = [];

    /**
     * Event name mapping for each entity type.
     * These match the broadcastAs() values from Laravel events.
     */
    const eventNames: Record<EntityType, string[]> = {
        connection: ['.connection.changed', '.connection.created', '.connection.deleted'],
        device: ['.device.changed', '.device.placed', '.device.moved', '.device.removed'],
        rack: ['.rack.changed', '.rack.created', '.rack.deleted'],
        implementation_file: ['.implementation_file.approved', '.implementation_file.rejected'],
        finding: ['.finding.resolved', '.finding.assigned', '.finding.changed'],
        audit: ['.audit.status_changed'],
    };

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
     * Subscribe to a datacenter channel for real-time updates.
     */
    function subscribe(): void {
        if (!datacenterId) {
            return;
        }

        const echo = getEcho();
        if (!echo) {
            console.warn('Laravel Echo is not available. Real-time updates will not work.');
            return;
        }

        try {
            channel.value = echo.private(`datacenter.${datacenterId}`);
            isConnected.value = true;

            // Listen for all registered entity types
            for (const [entityType, events] of Object.entries(eventNames)) {
                for (const eventName of events) {
                    channel.value.listen(eventName, (data: unknown) => {
                        handleEvent(entityType as EntityType, eventName, data);
                    });
                }
            }
        } catch (error) {
            console.error('Failed to subscribe to datacenter channel:', error);
            isConnected.value = false;
        }
    }

    /**
     * Handle incoming broadcast events.
     */
    function handleEvent(entityType: EntityType, eventName: string, data: unknown): void {
        const eventData = data as Record<string, unknown>;

        // Extract action from event name (e.g., '.connection.changed' -> 'changed')
        const action = eventName.split('.').pop() ?? 'changed';

        // Create pending update notification
        const update: PendingUpdate = {
            id: `${entityType}-${eventData.entityId ?? eventData.id ?? Date.now()}-${Date.now()}`,
            entityType,
            entityId: (eventData.entityId ?? eventData.id ?? 0) as number,
            action,
            user: (eventData.user ?? { id: 0, name: 'Unknown' }) as { id: number; name: string },
            timestamp: (eventData.timestamp ?? new Date().toISOString()) as string,
            message: eventData.message as string | undefined,
        };

        pendingUpdates.value.push(update);
        hasUpdates.value = true;

        // Notify registered handlers
        for (const handler of handlers) {
            if (handler.entityType === entityType) {
                handler.callback({
                    entityId: update.entityId,
                    action: update.action,
                    user: update.user,
                    timestamp: update.timestamp,
                    ...eventData,
                });
            }
        }
    }

    /**
     * Register a callback for data changes on a specific entity type.
     *
     * @param entityType - The type of entity to listen for (connection, device, rack, etc.)
     * @param callback - Function to call when an event is received
     */
    function onDataChange(entityType: EntityType, callback: DataChangeCallback): void {
        handlers.push({ entityType, callback });
    }

    /**
     * Clear all pending updates and reset the hasUpdates flag.
     */
    function clearUpdates(): void {
        pendingUpdates.value = [];
        hasUpdates.value = false;
    }

    /**
     * Remove a specific pending update by ID.
     */
    function dismissUpdate(updateId: string): void {
        pendingUpdates.value = pendingUpdates.value.filter((u) => u.id !== updateId);
        if (pendingUpdates.value.length === 0) {
            hasUpdates.value = false;
        }
    }

    /**
     * Clean up the channel subscription.
     * Stops listening to all events and leaves the channel.
     */
    function cleanup(): void {
        const echo = getEcho();

        if (channel.value && datacenterId) {
            // Stop listening to all events
            for (const events of Object.values(eventNames)) {
                for (const eventName of events) {
                    try {
                        channel.value.stopListening(eventName);
                    } catch {
                        // Ignore errors when stopping listeners
                    }
                }
            }

            // Leave the channel
            try {
                echo?.leave(`datacenter.${datacenterId}`);
            } catch {
                // Ignore errors when leaving channel
            }

            channel.value = null;
            isConnected.value = false;
        }
    }

    // Subscribe on composable creation if datacenterId is provided
    if (datacenterId) {
        subscribe();
    }

    // Cleanup on component unmount
    onUnmounted(() => {
        cleanup();
    });

    return {
        /**
         * Whether there are pending updates that haven't been acknowledged.
         */
        hasUpdates,

        /**
         * Array of pending update notifications.
         */
        pendingUpdates,

        /**
         * Whether the Echo channel is currently connected.
         */
        isConnected,

        /**
         * Register a callback for data changes on a specific entity type.
         */
        onDataChange,

        /**
         * Clear all pending updates.
         */
        clearUpdates,

        /**
         * Dismiss a specific pending update by ID.
         */
        dismissUpdate,

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
