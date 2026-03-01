/**
 * TypeScript interfaces for Connection data structures
 * Used by Connection UI components in the Port Connections feature
 */

/**
 * Re-export port type values for use in connection utilities
 * These are duplicated here to avoid circular dependencies with ports.ts
 */
export type PortTypeValue = 'ethernet' | 'fiber' | 'power';
export type PortStatusValue =
    | 'available'
    | 'connected'
    | 'reserved'
    | 'disabled';

/**
 * Cable type enum values matching backend CableType enum
 */
export type CableTypeValue =
    | 'cat5e'
    | 'cat6'
    | 'cat6a' // Ethernet cables
    | 'fiber_sm'
    | 'fiber_mm' // Fiber cables
    | 'power_c13'
    | 'power_c14'
    | 'power_c19'
    | 'power_c20'; // Power cables

/**
 * Cable type option for dropdown selection
 */
export interface CableTypeOption {
    /** The cable type value matching backend enum */
    value: CableTypeValue;
    /** Human-readable label for display */
    label: string;
    /** Port types this cable is compatible with */
    port_types: PortTypeValue[];
}

/**
 * Port info within a connection (subset of PortData from ConnectionResource)
 */
export interface ConnectionPortInfo {
    /** Port unique identifier */
    id: number;
    /** Port label (e.g., "eth0", "port1") */
    label: string;
    /** Port type value */
    type: PortTypeValue;
    /** Human-readable port type label */
    type_label: string;
    /** Port direction value */
    direction: string;
    /** Human-readable direction label */
    direction_label: string;
    /** Device information */
    device: {
        id: number;
        name: string;
        asset_tag: string | null;
    } | null;
}

/**
 * Logical path step for patch panel connections
 */
export interface LogicalPathStep {
    /** Port unique identifier */
    id: number;
    /** Port label */
    label: string;
    /** Device ID the port belongs to */
    device_id: number;
    /** Device name for display */
    device_name: string | null;
}

/**
 * Connection data interface with all connection fields from ConnectionResource
 */
export interface ConnectionData {
    /** Unique identifier for the connection */
    id: number;

    /** Cable properties */
    cable_type: CableTypeValue | null;
    cable_type_label: string | null;
    cable_length: number | null;
    cable_color: string | null;
    path_notes: string | null;

    /** Logical path array for patch panel connections */
    logical_path?: LogicalPathStep[];

    /** Timestamps */
    created_at: string | null;
    updated_at: string | null;
}

/**
 * Connection with source and destination port relations
 * Extends ConnectionData with the full port information
 */
export interface ConnectionWithPorts extends ConnectionData {
    /** Source port with device information */
    source_port: ConnectionPortInfo;
    /** Destination port with device information */
    destination_port: ConnectionPortInfo;
}

/**
 * Hierarchical filter option extending the pattern from BulkExport/Create.vue
 * with additional device and rack references
 */
export interface HierarchicalFilterOption {
    /** Unique identifier */
    value: number;
    /** Display label */
    label: string;
    /** Parent datacenter ID (for rooms) */
    datacenter_id?: number;
    /** Parent room ID (for rows) */
    room_id?: number;
    /** Parent row ID (for racks) */
    row_id?: number;
    /** Parent rack ID (for devices) */
    rack_id?: number;
    /** Parent device ID (for ports) */
    device_id?: number;
}

/**
 * Available port option for the port selector dropdown
 * Used in the hierarchical port selector to display available ports
 */
export interface AvailablePortOption {
    /** Port unique identifier */
    id: number;
    /** Port label for display */
    label: string;
    /** Device name the port belongs to */
    device_name: string;
    /** Device ID the port belongs to */
    device_id: number;
    /** Port type value */
    type: PortTypeValue;
    /** Port status value */
    status: PortStatusValue;
    /** Port subtype label for display */
    subtype_label?: string;
}

/**
 * Filter options structure for hierarchical port selection
 */
export interface HierarchicalFilterOptions {
    datacenters: HierarchicalFilterOption[];
    rooms: HierarchicalFilterOption[];
    rows: HierarchicalFilterOption[];
    racks: HierarchicalFilterOption[];
    devices?: HierarchicalFilterOption[];
}

/**
 * All cable type options with their port type mappings
 */
export const CABLE_TYPE_OPTIONS: CableTypeOption[] = [
    // Ethernet cables
    { value: 'cat5e', label: 'Cat5e', port_types: ['ethernet'] },
    { value: 'cat6', label: 'Cat6', port_types: ['ethernet'] },
    { value: 'cat6a', label: 'Cat6a', port_types: ['ethernet'] },
    // Fiber cables
    { value: 'fiber_sm', label: 'Fiber SM', port_types: ['fiber'] },
    { value: 'fiber_mm', label: 'Fiber MM', port_types: ['fiber'] },
    // Power cables
    { value: 'power_c13', label: 'C13', port_types: ['power'] },
    { value: 'power_c14', label: 'C14', port_types: ['power'] },
    { value: 'power_c19', label: 'C19', port_types: ['power'] },
    { value: 'power_c20', label: 'C20', port_types: ['power'] },
];

/**
 * Get cable type options filtered by port type
 *
 * @param portType - The port type to filter cable options for
 * @returns Array of CableTypeOption compatible with the given port type
 *
 * @example
 * getCableTypesForPortType('ethernet') // Returns Cat5e, Cat6, Cat6a options
 * getCableTypesForPortType('fiber') // Returns Fiber SM, Fiber MM options
 * getCableTypesForPortType('power') // Returns C13, C14, C19, C20 options
 */
export function getCableTypesForPortType(
    portType: PortTypeValue,
): CableTypeOption[] {
    return CABLE_TYPE_OPTIONS.filter((option) =>
        option.port_types.includes(portType),
    );
}

/**
 * Get the default cable type for a given port type
 * Returns the first compatible cable type option
 *
 * @param portType - The port type to get default cable type for
 * @returns The default CableTypeOption or undefined if none found
 */
export function getDefaultCableTypeForPortType(
    portType: PortTypeValue,
): CableTypeOption | undefined {
    return getCableTypesForPortType(portType)[0];
}

/**
 * Check if a cable type is valid for a given port type
 *
 * @param cableType - The cable type value to check
 * @param portType - The port type to validate against
 * @returns true if the cable type is valid for the port type
 */
export function isValidCableTypeForPort(
    cableType: CableTypeValue,
    portType: PortTypeValue,
): boolean {
    const option = CABLE_TYPE_OPTIONS.find((opt) => opt.value === cableType);
    return option ? option.port_types.includes(portType) : false;
}

// =============================================================================
// Connection Diagram Types
// =============================================================================

/** Aggregation level for diagram visualization */
export type DiagramAggregationLevel = 'rack' | 'device';

/** Node type discriminator */
export type DiagramNodeType = 'rack' | 'device';

/**
 * Rack node data for connection diagram visualization.
 * Represents a rack as a node in the graph with aggregated connection data.
 */
export interface DiagramRackNode {
    /** Unique rack identifier */
    id: number;
    /** Rack display name */
    name: string;
    /** Node type discriminator */
    node_type: 'rack';
    /** Row ID where rack is located */
    row_id: number | null;
    /** Row name */
    row_name: string | null;
    /** Room ID */
    room_id: number | null;
    /** Room name */
    room_name: string | null;
    /** Datacenter ID */
    datacenter_id: number | null;
    /** Datacenter name */
    datacenter_name: string | null;
    /** Rack height in U */
    u_height: number | null;
    /** Number of devices with connections in this rack */
    device_count: number;
    /** Number of active connections */
    connection_count: number;
}

/**
 * Device node data for connection diagram visualization.
 * Represents a device as a node in the graph with aggregated connection data.
 */
export interface DiagramDeviceNode {
    /** Unique device identifier */
    id: number;
    /** Device display name */
    name: string;
    /** Node type discriminator */
    node_type: 'device';
    /** Device asset tag */
    asset_tag: string | null;
    /** Device type name (e.g., "Server", "Switch") */
    device_type: string | null;
    /** Device type ID for filtering */
    device_type_id: number | null;
    /** Rack ID where device is located */
    rack_id: number | null;
    /** Total number of ports on the device */
    port_count: number;
    /** Number of active connections */
    connection_count: number;
}

/**
 * Union type for diagram nodes (can be either rack or device)
 */
export type DiagramNode = DiagramRackNode | DiagramDeviceNode;

/**
 * Connection edge data for diagram visualization.
 * Represents connections between two devices (may aggregate multiple connections).
 */
export interface DiagramConnectionEdge {
    /** Unique edge identifier (format: "sourceId-destId") */
    id: string;
    /** Source device ID */
    source_device_id: number;
    /** Destination device ID */
    destination_device_id: number;
    /** Primary cable type for this edge */
    cable_type: CableTypeValue | null;
    /** Primary cable color for this edge */
    cable_color: string | null;
    /** Whether all connections in this edge are verified */
    verified: boolean;
    /** Whether any connection has audit discrepancies */
    has_discrepancy: boolean;
    /** Number of individual connections between these devices */
    connection_count: number;
}

/**
 * Connection diagram API response format
 */
export interface DiagramData {
    /** Nodes for the diagram (can be racks or devices based on aggregation level) */
    nodes: DiagramNode[];
    /** Connection edges for the diagram */
    edges: DiagramConnectionEdge[];
    /** Current aggregation level */
    aggregation_level: DiagramAggregationLevel;
}

/**
 * Vue Flow node position
 */
export interface NodePosition {
    x: number;
    y: number;
}

/**
 * Vue Flow node data with rack or device information
 */
export interface FlowNodeData extends DiagramNode {
    /** Whether the node is currently selected */
    selected?: boolean;
    /** Whether the node is being hovered */
    hovered?: boolean;
    /** Whether the node is expanded to show ports (device nodes only) */
    expanded?: boolean;
    /** Port data when node is expanded (device nodes only) */
    ports?: PortDrillDownData[];
}

/**
 * Type guard to check if a node is a rack node
 */
export function isRackNode(node: DiagramNode | FlowNodeData): node is DiagramRackNode {
    return node.node_type === 'rack';
}

/**
 * Type guard to check if a node is a device node
 */
export function isDeviceNode(node: DiagramNode | FlowNodeData): node is DiagramDeviceNode {
    return node.node_type === 'device';
}

/**
 * Vue Flow edge data with connection information
 */
export interface FlowEdgeData extends DiagramConnectionEdge {
    /** Whether the edge is currently selected */
    selected?: boolean;
    /** Whether the edge is being hovered */
    hovered?: boolean;
    /** Whether this is a port-to-port edge (vs device-to-device) */
    isPortEdge?: boolean;
    /** Source port label for port edges */
    sourcePortLabel?: string;
    /** Target port label for port edges */
    targetPortLabel?: string;
}

/**
 * Diagram filter state
 */
export interface DiagramFilters {
    /** Selected datacenter ID */
    datacenter_id: number | null;
    /** Selected room ID */
    room_id: number | null;
    /** Selected row ID */
    row_id: number | null;
    /** Selected rack ID */
    rack_id: number | null;
    /** Selected device ID for filtering */
    device_id: number | null;
    /** Selected device type ID */
    device_type_id: number | null;
    /** Selected port type filter */
    port_type: PortTypeValue | null;
    /** Filter for verified/unverified connections */
    verified: boolean | null;
}

/**
 * Get the port type category based on cable type
 */
export function getPortTypeFromCableType(
    cableType: CableTypeValue | null,
): PortTypeValue | null {
    if (!cableType) return null;

    const option = CABLE_TYPE_OPTIONS.find((opt) => opt.value === cableType);
    return option?.port_types[0] ?? null;
}

// =============================================================================
// Port Drill-Down Types
// =============================================================================

/**
 * Remote device info for port connections
 */
export interface RemoteDeviceInfo {
    /** Device unique identifier */
    id: number;
    /** Device display name */
    name: string;
    /** Device asset tag */
    asset_tag: string | null;
}

/**
 * Remote port info for port connections
 */
export interface RemotePortInfo {
    /** Port unique identifier */
    id: number;
    /** Port label */
    label: string;
    /** Port type value */
    type: PortTypeValue | null;
}

/**
 * Port connection details for drill-down view
 */
export interface PortConnectionDetails {
    /** Connection unique identifier */
    id: number;
    /** Cable type value */
    cable_type: CableTypeValue | null;
    /** Human-readable cable type label */
    cable_type_label: string | null;
    /** Cable color */
    cable_color: string | null;
    /** Cable length in meters */
    cable_length: number | null;
    /** Path notes/description */
    path_notes: string | null;
    /** Remote port information */
    remote_port: RemotePortInfo | null;
    /** Remote device information */
    remote_device: RemoteDeviceInfo | null;
}

/**
 * Port data for drill-down visualization
 * Returned by GET /devices/{device}/ports/diagram endpoint
 */
export interface PortDrillDownData {
    /** Port unique identifier */
    id: number;
    /** Port label (e.g., "eth0", "Gi0/1") */
    label: string;
    /** Port type value */
    type: PortTypeValue | null;
    /** Human-readable type label */
    type_label: string | null;
    /** Port subtype value */
    subtype: string | null;
    /** Human-readable subtype label */
    subtype_label: string | null;
    /** Port status value */
    status: PortStatusValue | null;
    /** Human-readable status label */
    status_label: string | null;
    /** Port direction value */
    direction: string | null;
    /** Human-readable direction label */
    direction_label: string | null;
    /** Paired port ID (for patch panels) */
    paired_port_id: number | null;
    /** Paired port label */
    paired_port_label: string | null;
    /** Connection details if connected */
    connection: PortConnectionDetails | null;
}

/**
 * Get status color class based on port status
 */
export function getPortStatusColor(status: PortStatusValue | null): string {
    switch (status) {
        case 'available':
            return 'bg-green-500';
        case 'connected':
            return 'bg-blue-500';
        case 'reserved':
            return 'bg-yellow-500';
        case 'disabled':
            return 'bg-gray-400';
        default:
            return 'bg-gray-300';
    }
}

/**
 * Get status text color class based on port status
 */
export function getPortStatusTextColor(status: PortStatusValue | null): string {
    switch (status) {
        case 'available':
            return 'text-green-600 dark:text-green-400';
        case 'connected':
            return 'text-blue-600 dark:text-blue-400';
        case 'reserved':
            return 'text-yellow-600 dark:text-yellow-400';
        case 'disabled':
            return 'text-gray-500 dark:text-gray-400';
        default:
            return 'text-gray-400';
    }
}

/**
 * Get status badge variant based on port status
 */
export function getPortStatusBadgeVariant(
    status: PortStatusValue | null,
): 'default' | 'secondary' | 'outline' | 'destructive' {
    switch (status) {
        case 'available':
            return 'default';
        case 'connected':
            return 'secondary';
        case 'reserved':
            return 'outline';
        case 'disabled':
            return 'destructive';
        default:
            return 'outline';
    }
}
