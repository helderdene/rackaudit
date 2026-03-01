/**
 * TypeScript interfaces for Port data structures
 * Used by Port UI components in the Port Management feature
 */

import type { ConnectionWithPorts } from './connections';

/**
 * Port type enum values
 */
export type PortTypeValue = 'ethernet' | 'fiber' | 'power';

/**
 * Port subtype enum values grouped by parent type
 * - Ethernet: gbe1, gbe10, gbe25, gbe40, gbe100
 * - Fiber: lc, sc, mpo
 * - Power: c13, c14, c19, c20
 */
export type PortSubtypeValue =
    | 'gbe1'
    | 'gbe10'
    | 'gbe25'
    | 'gbe40'
    | 'gbe100' // Ethernet
    | 'lc'
    | 'sc'
    | 'mpo' // Fiber
    | 'c13'
    | 'c14'
    | 'c19'
    | 'c20'; // Power

/**
 * Port status enum values
 */
export type PortStatusValue =
    | 'available'
    | 'connected'
    | 'reserved'
    | 'disabled';

/**
 * Port direction enum values
 * - Network (Ethernet/Fiber): uplink, downlink, bidirectional
 * - Power: input, output
 */
export type PortDirectionValue =
    | 'uplink'
    | 'downlink'
    | 'bidirectional'
    | 'input'
    | 'output';

/**
 * Port visual face enum values
 */
export type PortVisualFaceValue = 'front' | 'rear';

/**
 * Port data interface with all fields from PortResource
 */
export interface PortData {
    /** Unique identifier for the port */
    id: number;
    /** ID of the parent device */
    device_id: number;
    /** User-defined text label (e.g., "eth0", "port1", "PSU-A") */
    label: string;

    /** Port type value */
    type: PortTypeValue;
    /** Human-readable port type label */
    type_label: string;
    /** Port subtype value */
    subtype: PortSubtypeValue;
    /** Human-readable port subtype label */
    subtype_label: string;

    /** Port status value */
    status: PortStatusValue;
    /** Human-readable port status label */
    status_label: string;

    /** Port direction value */
    direction: PortDirectionValue;
    /** Human-readable port direction label */
    direction_label: string;

    /** Optional slot/module number for modular devices */
    position_slot: number | null;
    /** Optional physical grid position row for patch panels */
    position_row: number | null;
    /** Optional physical grid position column for patch panels */
    position_column: number | null;

    /** Visual X coordinate as percentage (0-100) for device face positioning */
    visual_x: number | null;
    /** Visual Y coordinate as percentage (0-100) for device face positioning */
    visual_y: number | null;
    /** Visual face indicator (front/rear) */
    visual_face: PortVisualFaceValue | null;
    /** Human-readable visual face label */
    visual_face_label: string | null;

    /** Timestamps */
    created_at?: string;
    updated_at?: string;

    /** Connection data when the port is connected (optional, loaded from backend) */
    connection?: ConnectionWithPorts;

    /** Remote device name for display when port is connected (optional convenience field) */
    remote_device_name?: string;

    /** Remote port label for display when port is connected (optional convenience field) */
    remote_port_label?: string;
}

/**
 * Port type option for dropdown selection
 */
export interface PortTypeOption {
    value: PortTypeValue;
    label: string;
}

/**
 * Port subtype option for dropdown selection with parent type reference
 */
export interface PortSubtypeOption {
    value: PortSubtypeValue;
    label: string;
    type: PortTypeValue;
}

/**
 * Port status option for dropdown selection
 */
export interface PortStatusOption {
    value: PortStatusValue;
    label: string;
}

/**
 * Port direction option for dropdown selection with valid types reference
 */
export interface PortDirectionOption {
    value: PortDirectionValue;
    label: string;
    types: PortTypeValue[];
}

/**
 * Port visual face option for dropdown selection
 */
export interface PortVisualFaceOption {
    value: PortVisualFaceValue;
    label: string;
}

/**
 * Port enum options for form dropdowns
 */
export interface PortEnumOptions {
    typeOptions: PortTypeOption[];
    subtypeOptions: PortSubtypeOption[];
    statusOptions: PortStatusOption[];
    directionOptions: PortDirectionOption[];
    visualFaceOptions: PortVisualFaceOption[];
}
