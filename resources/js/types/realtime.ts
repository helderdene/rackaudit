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
 * Structure of a real-time update notification for display in toast components.
 */
export interface RealtimeUpdate {
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
    /**
     * Whether this update represents a conflict with the current entity being edited.
     */
    isConflict?: boolean;
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
