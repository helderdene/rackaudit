/**
 * TypeScript interfaces for Room, Row, Rack, PDU, and Device data structures
 * Used by Room UI components in the Room/Zone Management feature
 */

/**
 * Minimal datacenter reference for breadcrumbs and navigation
 */
export interface DatacenterReference {
    id: number;
    name: string;
}

/**
 * Minimal room reference for breadcrumbs and navigation
 */
export interface RoomReference {
    id: number;
    name: string;
}

/**
 * Minimal row reference for breadcrumbs and navigation
 */
export interface RowReference {
    id: number;
    name: string;
}

/**
 * Minimal rack reference for breadcrumbs and navigation
 */
export interface RackReference {
    id: number;
    name: string;
}

/**
 * Room data interface with all fields including type label
 */
export interface RoomData {
    id: number;
    name: string;
    description: string | null;
    square_footage: number | null;
    type: string | null;
    type_label: string | null;
    row_count?: number;
    pdu_count?: number;
    created_at?: string;
    updated_at?: string;
}

/**
 * Room type option for dropdown selection
 */
export interface RoomTypeOption {
    value: string;
    label: string;
}

/**
 * Row data interface with orientation and status labels
 */
export interface RowData {
    id: number;
    name: string;
    position: number;
    orientation: string | null;
    orientation_label: string | null;
    status: string | null;
    status_label: string | null;
    pdu_count?: number;
    created_at?: string;
    updated_at?: string;
}

/**
 * Rack data interface with U-height and status labels
 * Extended with enhancement fields for rack page
 */
export interface RackData {
    id: number;
    name: string;
    position: number;
    u_height: number | null;
    u_height_label: string | null;
    serial_number: string | null;
    status: string | null;
    status_label: string | null;
    pdu_count?: number;
    pdu_ids?: number[];
    /** Rack manufacturer name */
    manufacturer?: string | null;
    /** Rack model number */
    model?: string | null;
    /** Rack depth dimensions (e.g., "1070mm") */
    depth?: string | null;
    /** Date when rack was installed (YYYY-MM-DD format) */
    installation_date?: string | null;
    /** Additional location context notes */
    location_notes?: string | null;
    /** Custom key-value specifications */
    specs?: Record<string, string | number> | null;
    created_at?: string;
    updated_at?: string;
}

/**
 * Option interface for dropdown selections (orientation, status, phase)
 */
export interface SelectOption {
    value: string;
    label: string;
}

/**
 * PDU option for rack assignment multi-select
 */
export interface PduOption {
    id: number;
    name: string;
    model: string | null;
}

/**
 * PDU data interface with assignment level indicator
 */
export interface PduData {
    id: number;
    name: string;
    model: string | null;
    manufacturer: string | null;
    total_capacity_kw: number | null;
    voltage: number | null;
    phase: string | null;
    phase_label: string | null;
    circuit_count: number;
    status: string | null;
    status_label: string | null;
    room_id: number | null;
    row_id: number | null;
    assignment_level: string;
    created_at?: string;
    updated_at?: string;
}

/**
 * Pagination link for navigation
 */
export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

/**
 * Paginated rooms response structure
 */
export interface PaginatedRooms {
    data: RoomData[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

/**
 * Filter interface for room search
 */
export interface RoomFilters {
    search: string;
}

/**
 * Device width options for rack placement
 * - 'full': Device occupies the full width of the rack slot
 * - 'half-left': Device occupies the left half of the rack slot
 * - 'half-right': Device occupies the right half of the rack slot
 */
export type DeviceWidth = 'full' | 'half-left' | 'half-right';

/**
 * Rack face options for device placement
 * - 'front': Device is placed on the front of the rack
 * - 'rear': Device is placed on the rear of the rack
 */
export type RackFace = 'front' | 'rear';

/**
 * Device type option for dropdown selection
 */
export interface DeviceTypeOption {
    id: number;
    name: string;
    default_u_size: number;
}

/**
 * Lifecycle status option for dropdown selection
 */
export interface LifecycleStatusOption {
    value: string;
    label: string;
}

/**
 * Warranty status for device
 * - 'active': Warranty is currently valid
 * - 'expired': Warranty has expired
 * - 'none': No warranty information
 */
export type WarrantyStatus = 'active' | 'expired' | 'none';

/**
 * Device data interface for the device management system
 * Represents a physical datacenter device/asset with all fields
 */
export interface DeviceData {
    /** Unique identifier for the device */
    id: number;
    /** Display name of the device */
    name: string;
    /** Auto-generated asset tag (immutable) */
    asset_tag: string;
    /** Serial number of the device */
    serial_number: string | null;
    /** Manufacturer of the device */
    manufacturer: string | null;
    /** Model number/name of the device */
    model: string | null;

    /** Lifecycle status value */
    lifecycle_status: string | null;
    /** Human-readable lifecycle status label */
    lifecycle_status_label: string | null;

    /** Number of rack units the device occupies (1-48) */
    u_height: number;
    /** Depth type: standard, deep, shallow */
    depth: string | null;
    /** Human-readable depth label */
    depth_label: string | null;
    /** Width type: full, half_left, half_right */
    width_type: string | null;
    /** Human-readable width type label */
    width_type_label: string | null;
    /** Rack face: front, rear */
    rack_face: string | null;
    /** Human-readable rack face label */
    rack_face_label: string | null;

    /** Starting U position (lowest U-number occupied), null if unplaced */
    start_u: number | null;

    /** Purchase date in YYYY-MM-DD format */
    purchase_date: string | null;
    /** Warranty start date in YYYY-MM-DD format */
    warranty_start_date: string | null;
    /** Warranty end date in YYYY-MM-DD format */
    warranty_end_date: string | null;
    /** Calculated warranty status */
    warranty_status: WarrantyStatus;

    /** Flexible key-value specifications */
    specs: Record<string, string> | null;

    /** Additional notes */
    notes: string | null;

    /** Device type relationship */
    device_type: DeviceTypeOption | null;

    /** Rack placement relationship */
    rack: RackReference | null;

    /** Timestamps */
    created_at?: string;
    updated_at?: string;
}

/**
 * Paginated devices response structure
 */
export interface PaginatedDevices {
    data: DeviceData[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

/**
 * Filter interface for device search
 */
export interface DeviceFilters {
    search: string;
    lifecycle_status: string;
}

/**
 * Placeholder device interface for the elevation system
 * Represents a device that can be placed in a rack.
 * This is a temporary structure until the Device model is implemented.
 */
export interface PlaceholderDevice {
    /** Unique identifier for the device */
    id: string;
    /** Display name of the device */
    name: string;
    /** Device type (e.g., 'server', 'switch', 'storage') */
    type: string;
    /** Number of rack units the device occupies (1U, 2U, 4U, etc.) */
    u_size: number;
    /** Width of the device in the rack slot */
    width: DeviceWidth;
    /** Starting U position (lowest U-number occupied), undefined if unplaced */
    start_u?: number;
    /** Which face of the rack the device is mounted on, undefined if unplaced */
    face?: RackFace;
}

/**
 * Device position interface for tracking device placement in a rack
 * Used when placing or moving a device to a specific position
 */
export interface DevicePosition {
    /** The device ID being positioned */
    device_id: string;
    /** Starting U position (lowest U-number occupied) */
    start_u: number;
    /** Which face of the rack the device is mounted on */
    face: RackFace;
    /** Width of the device in the rack slot */
    width: DeviceWidth;
}

/**
 * Rack elevation state interface for managing device placement
 * Used by the useRackElevation composable to track state
 */
export interface RackElevationState {
    /** Devices that have been placed in the rack */
    placedDevices: PlaceholderDevice[];
    /** Devices that are available but not yet placed */
    unplacedDevices: PlaceholderDevice[];
    /** The device currently being dragged, null if no drag in progress */
    draggedDevice: PlaceholderDevice | null;
}

/**
 * Utilization statistics for rack capacity display
 * Used to show how much of the rack is being used
 */
export interface UtilizationStats {
    /** Total rack units available in the rack */
    totalU: number;
    /** Total rack units currently occupied */
    usedU: number;
    /** Total rack units available for use */
    availableU: number;
    /** Percentage of rack capacity in use (0-100) */
    utilizationPercent: number;
    /** Rack units used on the front face (optional, for separate tracking) */
    frontUsedU?: number;
    /** Rack units used on the rear face (optional, for separate tracking) */
    rearUsedU?: number;
}

/**
 * Power metrics for rack capacity display
 * Shows total power consumption vs PDU capacity
 */
export interface PowerMetrics {
    /** Total power draw from all devices in watts */
    totalPowerDraw: number;
    /** Total PDU capacity in watts */
    pduCapacity: number;
    /** Percentage of power capacity in use (0-100) */
    powerUtilizationPercent: number;
}

/**
 * Device summary for rack device list table
 * Minimal device info for display in rack show page
 */
export interface RackDevice {
    /** Unique identifier for the device */
    id: number;
    /** Display name of the device */
    name: string;
    /** Device type name (e.g., 'Server', 'Switch', 'Storage') */
    type: string;
    /** Starting U position in the rack */
    start_u: number;
    /** Number of rack units the device occupies */
    u_height: number;
    /** Lifecycle status value (e.g., 'deployed', 'maintenance') */
    lifecycle_status: string | null;
    /** Human-readable lifecycle status label */
    lifecycle_status_label: string | null;
}
