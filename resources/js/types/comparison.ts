/**
 * TypeScript interfaces for Connection Comparison data structures
 * Used by Comparison UI components for the Expected vs Actual Comparison feature
 */

import type { CableTypeValue } from './connections';

/**
 * Discrepancy type values matching backend DiscrepancyType enum
 */
export type DiscrepancyTypeValue =
    | 'matched'
    | 'missing'
    | 'unexpected'
    | 'mismatched'
    | 'conflicting';

/**
 * Device info within a comparison result
 */
export interface ComparisonDeviceInfo {
    /** Device unique identifier */
    id: number;
    /** Device display name */
    name: string;
    /** Device asset tag */
    asset_tag: string | null;
}

/**
 * Port info within a comparison result
 */
export interface ComparisonPortInfo {
    /** Port unique identifier */
    id: number;
    /** Port label */
    label: string;
    /** Port type value */
    type: string | null;
    /** Human-readable port type label */
    type_label: string | null;
}

/**
 * Expected connection data within a comparison result
 */
export interface ComparisonExpectedConnection {
    /** Expected connection ID */
    id: number;
    /** Cable type from expected connection */
    cable_type: CableTypeValue | null;
    /** Human-readable cable type label */
    cable_type_label: string | null;
    /** Implementation file ID */
    implementation_file_id: number;
}

/**
 * Actual connection data within a comparison result
 */
export interface ComparisonActualConnection {
    /** Connection ID */
    id: number;
    /** Cable type from actual connection */
    cable_type: CableTypeValue | null;
    /** Human-readable cable type label */
    cable_type_label: string | null;
    /** Cable length in meters */
    cable_length: number | null;
}

/**
 * Acknowledgment data for a discrepancy
 * Matches the structure returned by ComparisonResultResource::transformAcknowledgment()
 */
export interface DiscrepancyAcknowledgmentData {
    /** Acknowledgment ID */
    id: number;
    /** User ID who acknowledged */
    acknowledged_by: number;
    /** User name who acknowledged */
    acknowledged_by_name: string | null;
    /** Acknowledgment timestamp in ISO 8601 format */
    acknowledged_at: string | null;
    /** Optional notes */
    notes: string | null;
}

/**
 * Conflict info for conflicting discrepancies
 */
export interface ConflictInfo {
    /** Source port ID that has conflicts */
    source_port_id: number;
    /** Array of conflicting file expectations */
    conflicting_files: {
        /** Implementation file ID */
        file_id: number;
        /** Implementation file name */
        file_name: string;
        /** Expected destination port ID */
        dest_port_id: number;
        /** Expected destination port label */
        dest_port_label: string;
    }[];
}

/**
 * Comparison result data from API
 * Matches the structure returned by ComparisonResultResource
 */
export interface ComparisonResultData {
    /** Discrepancy type value */
    discrepancy_type: DiscrepancyTypeValue;
    /** Human-readable discrepancy type label */
    discrepancy_type_label: string;
    /** Expected connection data (nullable for unexpected connections) */
    expected_connection: ComparisonExpectedConnection | null;
    /** Actual connection data (nullable for missing connections) */
    actual_connection: ComparisonActualConnection | null;
    /** Source device info */
    source_device: ComparisonDeviceInfo | null;
    /** Source port info */
    source_port: ComparisonPortInfo | null;
    /** Destination device info (expected) */
    dest_device: ComparisonDeviceInfo | null;
    /** Destination port info (expected) */
    dest_port: ComparisonPortInfo | null;
    /** Actual destination port info (for mismatched) */
    actual_dest_port: ComparisonPortInfo | null;
    /** Whether this discrepancy has been acknowledged */
    is_acknowledged: boolean;
    /** Acknowledgment data if acknowledged */
    acknowledgment: DiscrepancyAcknowledgmentData | null;
    /** Conflict info for conflicting discrepancies */
    conflict_info: ConflictInfo | null;
}

/**
 * Comparison statistics data
 */
export interface ComparisonStatistics {
    /** Total number of comparisons */
    total: number;
    /** Number of matched connections */
    matched: number;
    /** Number of missing connections */
    missing: number;
    /** Number of unexpected connections */
    unexpected: number;
    /** Number of mismatched connections */
    mismatched: number;
    /** Number of conflicting connections */
    conflicting: number;
    /** Number of acknowledged discrepancies */
    acknowledged: number;
}

/**
 * Pagination metadata for comparison results
 */
export interface ComparisonPagination {
    /** Total number of results */
    total: number;
    /** Current offset */
    offset: number;
    /** Page size limit */
    limit: number;
}

/**
 * Filter options for comparison view
 */
export interface ComparisonFilterOptions {
    /** Available devices for filtering */
    devices: {
        id: number;
        name: string;
    }[];
    /** Available racks for filtering */
    racks: {
        id: number;
        name: string;
    }[];
}

/**
 * Get display color class for a discrepancy type
 */
export function getDiscrepancyColorClass(type: DiscrepancyTypeValue): string {
    switch (type) {
        case 'matched':
            return 'text-green-600 dark:text-green-400';
        case 'missing':
            return 'text-red-600 dark:text-red-400';
        case 'unexpected':
            return 'text-orange-600 dark:text-orange-400';
        case 'mismatched':
            return 'text-amber-600 dark:text-amber-400';
        case 'conflicting':
            return 'text-purple-600 dark:text-purple-400';
        default:
            return 'text-muted-foreground';
    }
}

/**
 * Get border color class for a discrepancy type (for row styling)
 */
export function getDiscrepancyBorderClass(type: DiscrepancyTypeValue): string {
    switch (type) {
        case 'matched':
            return 'border-l-green-500';
        case 'missing':
            return 'border-l-red-500';
        case 'unexpected':
            return 'border-l-orange-500';
        case 'mismatched':
            return 'border-l-amber-500';
        case 'conflicting':
            return 'border-l-purple-500';
        default:
            return 'border-l-gray-500';
    }
}

/**
 * Get badge variant for a discrepancy type
 */
export function getDiscrepancyBadgeVariant(
    type: DiscrepancyTypeValue,
): 'default' | 'secondary' | 'outline' | 'destructive' {
    switch (type) {
        case 'matched':
            return 'default';
        case 'missing':
            return 'destructive';
        case 'unexpected':
            return 'outline';
        case 'mismatched':
            return 'secondary';
        case 'conflicting':
            return 'outline';
        default:
            return 'outline';
    }
}
