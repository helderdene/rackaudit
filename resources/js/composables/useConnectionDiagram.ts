import { computed, ref, type Ref } from 'vue';
import axios from 'axios';
import type { Node, Edge, XYPosition } from '@vue-flow/core';
import type {
    DiagramData,
    DiagramNode,
    DiagramConnectionEdge,
    DiagramFilters,
    DiagramAggregationLevel,
    FlowNodeData,
    FlowEdgeData,
    PortDrillDownData,
} from '@/types/connections';
import { isDeviceNode } from '@/types/connections';

/**
 * Default filter state
 */
const defaultFilters: DiagramFilters = {
    datacenter_id: null,
    room_id: null,
    row_id: null,
    rack_id: null,
    device_id: null,
    device_type_id: null,
    port_type: null,
    verified: null,
};

/**
 * Get edge stroke color based on cable type and color
 */
function getEdgeColor(edge: DiagramConnectionEdge): string {
    // Use cable color if specified
    if (edge.cable_color) {
        const colorMap: Record<string, string> = {
            blue: '#3b82f6',
            yellow: '#eab308',
            green: '#22c55e',
            red: '#ef4444',
            orange: '#f97316',
            purple: '#a855f7',
            gray: '#6b7280',
            black: '#1f2937',
            white: '#f3f4f6',
        };
        return colorMap[edge.cable_color.toLowerCase()] || '#6b7280';
    }

    // Default colors based on cable type (port type)
    if (!edge.cable_type) return '#6b7280';

    if (edge.cable_type.startsWith('cat')) {
        return '#3b82f6'; // Blue for ethernet
    }
    if (edge.cable_type.startsWith('fiber')) {
        return '#f97316'; // Orange for fiber
    }
    if (edge.cable_type.startsWith('power')) {
        return '#ef4444'; // Red for power
    }

    return '#6b7280'; // Gray default
}

/**
 * Get device type icon class
 */
function getDeviceTypeIcon(deviceType: string | null): string {
    if (!deviceType) return 'Server';

    const typeMap: Record<string, string> = {
        server: 'Server',
        switch: 'Network',
        router: 'Router',
        storage: 'HardDrive',
        pdu: 'Zap',
        firewall: 'Shield',
        'patch panel': 'LayoutGrid',
    };

    const lowerType = deviceType.toLowerCase();
    for (const [key, icon] of Object.entries(typeMap)) {
        if (lowerType.includes(key)) {
            return icon;
        }
    }

    return 'Server';
}

/**
 * Hierarchical layout algorithm that minimizes edge crossings.
 * Places nodes in columns based on their connectivity patterns (sources left, sinks right).
 * Uses barycenter heuristic to order nodes within columns.
 */
function calculateHierarchicalLayout(
    nodes: DiagramNode[],
    edges: DiagramConnectionEdge[],
): Map<number, XYPosition> {
    const positions = new Map<number, XYPosition>();

    if (nodes.length === 0) return positions;

    const nodeWidth = 220;
    const nodeHeight = 140;
    const horizontalGap = 300;
    const verticalGap = 160;
    const padding = 60;

    // For very small graphs, use simple grid
    if (nodes.length <= 3) {
        nodes.forEach((node, index) => {
            positions.set(node.id, {
                x: padding + index * (nodeWidth + horizontalGap),
                y: padding,
            });
        });
        return positions;
    }

    // Build adjacency information
    const outgoing = new Map<number, Set<number>>();
    const incoming = new Map<number, Set<number>>();
    const nodeIds = new Set(nodes.map(n => n.id));

    nodes.forEach(node => {
        outgoing.set(node.id, new Set());
        incoming.set(node.id, new Set());
    });

    edges.forEach(edge => {
        // Only consider edges where both nodes exist in our node set
        if (nodeIds.has(edge.source_device_id) && nodeIds.has(edge.destination_device_id)) {
            outgoing.get(edge.source_device_id)?.add(edge.destination_device_id);
            incoming.get(edge.destination_device_id)?.add(edge.source_device_id);
        }
    });

    // Calculate net flow for each node (positive = more outgoing = source-like)
    const netFlow = new Map<number, number>();
    nodes.forEach(node => {
        const out = outgoing.get(node.id)?.size || 0;
        const inc = incoming.get(node.id)?.size || 0;
        netFlow.set(node.id, out - inc);
    });

    // Assign nodes to layers based on net flow
    // Layer 0 = strong sources (net flow > 0)
    // Layer 1 = neutral nodes (net flow = 0)
    // Layer 2 = strong sinks (net flow < 0)
    const layers: number[][] = [[], [], []];

    nodes.forEach(node => {
        const flow = netFlow.get(node.id) || 0;
        if (flow > 0) {
            layers[0].push(node.id);
        } else if (flow < 0) {
            layers[2].push(node.id);
        } else {
            layers[1].push(node.id);
        }
    });

    // Handle edge case: if all nodes are in one layer, split them evenly
    const nonEmptyLayers = layers.filter(l => l.length > 0);
    if (nonEmptyLayers.length === 1 && nodes.length > 3) {
        const allNodes = nonEmptyLayers[0];
        layers[0] = allNodes.slice(0, Math.ceil(allNodes.length / 2));
        layers[1] = [];
        layers[2] = allNodes.slice(Math.ceil(allNodes.length / 2));
    }

    // Barycenter heuristic: order nodes within each layer to minimize crossings
    // For each node, calculate the average position of its neighbors in adjacent layers
    function orderLayerByBarycenter(layerIndex: number): void {
        const layer = layers[layerIndex];
        if (layer.length <= 1) return;

        // Get positions of neighbors in other layers
        const barycenters = new Map<number, number>();

        layer.forEach(nodeId => {
            const neighbors: number[] = [];

            // Check all other layers for connected nodes
            layers.forEach((otherLayer, otherIdx) => {
                if (otherIdx === layerIndex) return;
                otherLayer.forEach((otherNodeId, position) => {
                    if (outgoing.get(nodeId)?.has(otherNodeId) ||
                        incoming.get(nodeId)?.has(otherNodeId)) {
                        neighbors.push(position);
                    }
                });
            });

            // Calculate barycenter (average position of neighbors)
            if (neighbors.length > 0) {
                const sum = neighbors.reduce((a, b) => a + b, 0);
                barycenters.set(nodeId, sum / neighbors.length);
            } else {
                // If no neighbors, keep original position
                barycenters.set(nodeId, layer.indexOf(nodeId));
            }
        });

        // Sort layer by barycenter values
        layer.sort((a, b) => (barycenters.get(a) || 0) - (barycenters.get(b) || 0));
    }

    // Run barycenter ordering multiple times for better results
    for (let iteration = 0; iteration < 4; iteration++) {
        // Alternate direction for better convergence
        if (iteration % 2 === 0) {
            orderLayerByBarycenter(0);
            orderLayerByBarycenter(1);
            orderLayerByBarycenter(2);
        } else {
            orderLayerByBarycenter(2);
            orderLayerByBarycenter(1);
            orderLayerByBarycenter(0);
        }
    }

    // Calculate x positions for each layer (distribute horizontally)
    const layerXPositions: number[] = [];
    let currentX = padding;
    const usedLayers = layers.filter(l => l.length > 0);

    usedLayers.forEach((_, idx) => {
        layerXPositions.push(currentX);
        currentX += nodeWidth + horizontalGap;
    });

    // Center the layout if we have fewer than 3 used layers
    if (usedLayers.length < 3) {
        const totalWidth = usedLayers.length * (nodeWidth + horizontalGap) - horizontalGap;
        const offsetX = Math.max(0, (600 - totalWidth) / 2);
        layerXPositions.forEach((_, idx) => {
            layerXPositions[idx] += offsetX;
        });
    }

    // Assign positions to nodes
    let usedLayerIndex = 0;
    layers.forEach((layer, layerIdx) => {
        if (layer.length === 0) return;

        const x = layerXPositions[usedLayerIndex];
        usedLayerIndex++;

        // Calculate total height needed for this layer
        const totalHeight = layer.length * nodeHeight + (layer.length - 1) * (verticalGap - nodeHeight);

        // Start y position (centered vertically)
        const startY = Math.max(padding, (500 - totalHeight) / 2);

        layer.forEach((nodeId, nodeIdx) => {
            positions.set(nodeId, {
                x,
                y: startY + nodeIdx * verticalGap,
            });
        });
    });

    // Fine-tune with a few iterations of vertical adjustment to reduce crossings further
    for (let iteration = 0; iteration < 3; iteration++) {
        usedLayerIndex = 0;
        layers.forEach((layer, layerIdx) => {
            if (layer.length === 0) return;
            usedLayerIndex++;

            layer.forEach(nodeId => {
                const pos = positions.get(nodeId)!;
                const neighborYs: number[] = [];

                // Collect y positions of all connected nodes
                outgoing.get(nodeId)?.forEach(targetId => {
                    const targetPos = positions.get(targetId);
                    if (targetPos) neighborYs.push(targetPos.y);
                });
                incoming.get(nodeId)?.forEach(sourceId => {
                    const sourcePos = positions.get(sourceId);
                    if (sourcePos) neighborYs.push(sourcePos.y);
                });

                if (neighborYs.length > 0) {
                    // Move toward the average y of neighbors (with damping)
                    const avgY = neighborYs.reduce((a, b) => a + b, 0) / neighborYs.length;
                    pos.y = pos.y * 0.6 + avgY * 0.4;
                }
            });
        });

        // After adjustment, re-sort within layers to prevent overlap
        usedLayerIndex = 0;
        layers.forEach((layer) => {
            if (layer.length === 0) return;
            usedLayerIndex++;

            // Sort by current y position
            layer.sort((a, b) => (positions.get(a)?.y || 0) - (positions.get(b)?.y || 0));

            // Ensure minimum spacing
            let lastY = padding;
            layer.forEach(nodeId => {
                const pos = positions.get(nodeId)!;
                if (pos.y < lastY) {
                    pos.y = lastY;
                }
                lastY = pos.y + verticalGap;
            });
        });
    }

    // Final normalization: ensure all positions start from padding
    let minX = Infinity, minY = Infinity;
    positions.forEach(pos => {
        minX = Math.min(minX, pos.x);
        minY = Math.min(minY, pos.y);
    });

    const offsetX = padding - minX;
    const offsetY = padding - minY;
    positions.forEach(pos => {
        pos.x += offsetX;
        pos.y += offsetY;
    });

    return positions;
}

/**
 * Composable for managing connection diagram state and interactions.
 * Handles fetching diagram data, managing nodes/edges, and user interactions.
 */
export function useConnectionDiagram(initialFilters?: Partial<DiagramFilters>) {
    // State management
    const diagramData: Ref<DiagramData | null> = ref(null);
    const nodes: Ref<Node<FlowNodeData>[]> = ref([]);
    const edges: Ref<Edge<FlowEdgeData>[]> = ref([]);
    const filters: Ref<DiagramFilters> = ref({
        ...defaultFilters,
        ...initialFilters,
    });
    const selectedNodeId: Ref<number | null> = ref(null);
    const selectedEdgeId: Ref<string | null> = ref(null);
    const isLoading: Ref<boolean> = ref(false);
    const error: Ref<string | null> = ref(null);
    const nodePositions: Ref<Map<number, XYPosition>> = ref(new Map());
    const aggregationLevel: Ref<DiagramAggregationLevel> = ref('rack');

    // Port drill-down state - supports multiple expanded nodes (device nodes only)
    const expandedNodeIds: Ref<Set<number>> = ref(new Set());
    const expandedNodePortsMap: Ref<Map<number, PortDrillDownData[]>> = ref(new Map());
    const isLoadingPorts: Ref<boolean> = ref(false);

    /**
     * Get currently selected node data
     */
    const selectedNode = computed<DiagramNode | null>(() => {
        if (!selectedNodeId.value || !diagramData.value) return null;
        return (
            diagramData.value.nodes.find((n) => n.id === selectedNodeId.value) ||
            null
        );
    });

    /**
     * Get currently selected edge data
     */
    const selectedEdge = computed<DiagramConnectionEdge | null>(() => {
        if (!selectedEdgeId.value || !diagramData.value) return null;
        return (
            diagramData.value.edges.find((e) => e.id === selectedEdgeId.value) ||
            null
        );
    });

    /**
     * Build query parameters from current filters
     */
    function buildQueryParams(): Record<string, string | number> {
        const params: Record<string, string | number> = {};

        if (filters.value.datacenter_id) {
            params.datacenter_id = filters.value.datacenter_id;
        }
        if (filters.value.room_id) {
            params.room_id = filters.value.room_id;
        }
        if (filters.value.row_id) {
            params.row_id = filters.value.row_id;
        }
        if (filters.value.rack_id) {
            params.rack_id = filters.value.rack_id;
        }
        if (filters.value.device_id) {
            params.device_id = filters.value.device_id;
        }
        if (filters.value.device_type_id) {
            params.device_type_id = filters.value.device_type_id;
        }
        if (filters.value.port_type) {
            params.port_type = filters.value.port_type;
        }
        if (filters.value.verified !== null) {
            params.verified = filters.value.verified ? 1 : 0;
        }

        return params;
    }

    /**
     * Transform API data into Vue Flow nodes
     */
    function transformToNodes(data: DiagramData): Node<FlowNodeData>[] {
        // Calculate positions using hierarchical layout to minimize edge crossings
        const positions = calculateHierarchicalLayout(data.nodes, data.edges);

        return data.nodes.map((node) => {
            const existingPosition = nodePositions.value.get(node.id);
            const calculatedPosition = positions.get(node.id) || { x: 0, y: 0 };
            const position = existingPosition || calculatedPosition;

            // Save position for future reference
            nodePositions.value.set(node.id, position);

            // Only device nodes can be expanded
            const canExpand = isDeviceNode(node);
            const isExpanded = canExpand && expandedNodeIds.value.has(node.id);

            return {
                id: String(node.id),
                type: 'device', // Using same component for both rack and device nodes
                position,
                data: {
                    ...node,
                    selected: selectedNodeId.value === node.id,
                    hovered: false,
                    expanded: isExpanded,
                    ports: isExpanded
                        ? expandedNodePortsMap.value.get(node.id)
                        : undefined,
                } as FlowNodeData,
                draggable: true,
            };
        });
    }

    /**
     * Transform API data into Vue Flow edges
     */
    function transformToEdges(data: DiagramData): Edge<FlowEdgeData>[] {
        return data.edges.map((edge) => {
            const strokeColor = getEdgeColor(edge);
            const strokeDasharray = edge.verified ? undefined : '5 5';

            return {
                id: edge.id,
                type: 'connection',
                source: String(edge.source_device_id),
                target: String(edge.destination_device_id),
                data: {
                    ...edge,
                    selected: selectedEdgeId.value === edge.id,
                    hovered: false,
                },
                style: {
                    stroke: strokeColor,
                    strokeWidth: edge.has_discrepancy ? 3 : 2,
                    strokeDasharray,
                },
                animated: edge.has_discrepancy,
            };
        });
    }

    /**
     * Generate port-to-port edges for expanded nodes
     */
    function generatePortEdges(): Edge<FlowEdgeData>[] {
        const portEdges: Edge<FlowEdgeData>[] = [];

        // Iterate through all expanded nodes
        expandedNodeIds.value.forEach((deviceId) => {
            const ports = expandedNodePortsMap.value.get(deviceId);
            if (!ports) return;

            // For each port with a connection, create an edge
            ports.forEach((port) => {
                if (!port.connection) return;

                const remoteDeviceId = port.connection.remote_device?.id;
                if (!remoteDeviceId) return;

                // Create unique edge ID for port-to-port connection
                const edgeId = `port-${port.id}-${port.connection.id}`;

                // Determine if the remote device is also expanded
                const remoteIsExpanded = expandedNodeIds.value.has(remoteDeviceId);

                // Get cable color from connection
                const cableColor = port.connection.cable_color || 'gray';
                const colorMap: Record<string, string> = {
                    blue: '#3b82f6',
                    yellow: '#eab308',
                    green: '#22c55e',
                    red: '#ef4444',
                    orange: '#f97316',
                    purple: '#a855f7',
                    gray: '#6b7280',
                    black: '#1f2937',
                    white: '#f3f4f6',
                };
                const strokeColor = colorMap[cableColor.toLowerCase()] || '#6b7280';

                portEdges.push({
                    id: edgeId,
                    type: 'smoothstep',
                    source: String(deviceId),
                    sourceHandle: `port-${port.id}`,
                    target: String(remoteDeviceId),
                    targetHandle: remoteIsExpanded ? `port-${port.connection.remote_port?.id}` : undefined,
                    data: {
                        id: edgeId,
                        source_device_id: deviceId,
                        destination_device_id: remoteDeviceId,
                        cable_type: port.connection.cable_type || null,
                        cable_color: cableColor,
                        verified: port.connection.verified ?? true,
                        has_discrepancy: false,
                        connection_count: 1,
                        isPortEdge: true,
                        sourcePortLabel: port.label,
                        targetPortLabel: port.connection.remote_port?.label,
                    } as FlowEdgeData,
                    style: {
                        stroke: strokeColor,
                        strokeWidth: 2,
                        strokeDasharray: port.connection.verified ? undefined : '5 5',
                    },
                    animated: false,
                });
            });
        });

        return portEdges;
    }

    /**
     * Update edges to include port-level connections
     */
    function updateEdgesWithPortConnections(): void {
        if (!diagramData.value) return;

        // Get base device-to-device edges
        const baseEdges = transformToEdges(diagramData.value);

        // Filter out device edges where both devices are expanded (show port edges instead)
        const filteredBaseEdges = baseEdges.filter((edge) => {
            const sourceExpanded = expandedNodeIds.value.has(edge.data?.source_device_id || 0);
            const targetExpanded = expandedNodeIds.value.has(edge.data?.destination_device_id || 0);
            // Hide device edge if source is expanded (we'll show port edges instead)
            return !sourceExpanded;
        });

        // Generate port-to-port edges
        const portEdges = generatePortEdges();

        // Combine edges
        edges.value = [...filteredBaseEdges, ...portEdges];
    }

    /**
     * Fetch diagram data from API
     */
    async function fetchDiagramData(): Promise<void> {
        isLoading.value = true;
        error.value = null;

        try {
            const params = buildQueryParams();
            const response = await axios.get<{ data: DiagramData }>(
                '/connections/diagram',
                { params },
            );

            diagramData.value = response.data.data;
            aggregationLevel.value = response.data.data.aggregation_level;

            // Clear expanded nodes when aggregation level changes
            // (port drill-down only applies to device nodes)
            if (response.data.data.aggregation_level === 'rack') {
                expandedNodeIds.value.clear();
                expandedNodePortsMap.value.clear();
            }

            // Transform data for Vue Flow
            nodes.value = transformToNodes(response.data.data);
            edges.value = transformToEdges(response.data.data);
        } catch (err: unknown) {
            const axiosError = err as { response?: { data?: { message?: string } } };
            error.value =
                axiosError.response?.data?.message ||
                'Failed to load diagram data';
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * Fetch port drill-down data for a specific device
     * Note: We only update the data property to avoid resetting positions
     */
    async function fetchPortDrillDown(deviceId: number): Promise<void> {
        isLoadingPorts.value = true;

        try {
            const response = await axios.get<{ data: PortDrillDownData[] }>(
                `/devices/${deviceId}/ports/diagram`,
            );

            // Add to expanded nodes set and store ports
            expandedNodeIds.value.add(deviceId);
            expandedNodePortsMap.value.set(deviceId, response.data.data);

            // Update only the target node with port data - preserve position by only modifying data
            nodes.value.forEach((node) => {
                if (node.data.id === deviceId) {
                    node.data = {
                        ...node.data,
                        expanded: true,
                        ports: response.data.data,
                    };
                }
            });

            // Update edges to show port-level connections
            updateEdgesWithPortConnections();
        } catch (err: unknown) {
            const axiosError = err as { response?: { data?: { message?: string } } };
            error.value =
                axiosError.response?.data?.message ||
                'Failed to load port data';
        } finally {
            isLoadingPorts.value = false;
        }
    }

    /**
     * Toggle port drill-down expansion for a node
     * Note: We only update the data property to avoid resetting positions
     */
    async function toggleNodeExpansion(nodeId: number): Promise<void> {
        if (expandedNodeIds.value.has(nodeId)) {
            // Collapse this specific node - preserve position by only modifying data
            expandedNodeIds.value.delete(nodeId);
            expandedNodePortsMap.value.delete(nodeId);

            nodes.value.forEach((node) => {
                if (node.data.id === nodeId) {
                    node.data = {
                        ...node.data,
                        expanded: false,
                        ports: undefined,
                    };
                }
            });

            // Update edges to remove port-level connections for this node
            updateEdgesWithPortConnections();
        } else {
            // Expand (fetch port data)
            await fetchPortDrillDown(nodeId);
        }
    }

    /**
     * Collapse all expanded nodes
     * Note: We only update the data property to avoid resetting positions
     */
    function collapseAllNodes(): void {
        expandedNodeIds.value.clear();
        expandedNodePortsMap.value.clear();

        nodes.value.forEach((node) => {
            node.data = {
                ...node.data,
                expanded: false,
                ports: undefined,
            };
        });
    }

    /**
     * Update filters and refetch data
     */
    async function setFilters(newFilters: Partial<DiagramFilters>): Promise<void> {
        filters.value = { ...filters.value, ...newFilters };
        await fetchDiagramData();
    }

    /**
     * Clear all filters and refetch data
     */
    async function clearFilters(): Promise<void> {
        filters.value = { ...defaultFilters };
        await fetchDiagramData();
    }

    /**
     * Handle node selection
     * Note: We only update the data.selected property to avoid resetting positions
     */
    function selectNode(nodeId: number | null): void {
        selectedNodeId.value = nodeId;
        selectedEdgeId.value = null;

        // Update node selection state - preserve position by only modifying data
        nodes.value.forEach((node) => {
            node.data = {
                ...node.data,
                selected: node.data.id === nodeId,
            };
        });

        // Update edge selection state
        edges.value.forEach((edge) => {
            edge.data = {
                ...edge.data,
                selected: false,
            };
        });
    }

    /**
     * Handle edge selection
     * Note: We only update the data.selected property to avoid resetting positions
     */
    function selectEdge(edgeId: string | null): void {
        selectedEdgeId.value = edgeId;
        selectedNodeId.value = null;

        // Update node selection state - preserve position by only modifying data
        nodes.value.forEach((node) => {
            node.data = {
                ...node.data,
                selected: false,
            };
        });

        // Update edge selection state
        edges.value.forEach((edge) => {
            edge.data = {
                ...edge.data,
                selected: edge.id === edgeId,
            };
        });
    }

    /**
     * Clear selection
     * Note: We only update the data.selected property to avoid resetting positions
     */
    function clearSelection(): void {
        selectedNodeId.value = null;
        selectedEdgeId.value = null;

        nodes.value.forEach((node) => {
            node.data = {
                ...node.data,
                selected: false,
            };
        });

        edges.value.forEach((edge) => {
            edge.data = {
                ...edge.data,
                selected: false,
            };
        });
    }

    /**
     * Update node position after drag
     */
    function updateNodePosition(nodeId: string, position: XYPosition): void {
        const numericId = parseInt(nodeId, 10);
        nodePositions.value.set(numericId, position);
    }

    /**
     * Reset layout to recalculate positions
     */
    function resetLayout(): void {
        nodePositions.value.clear();

        if (diagramData.value) {
            nodes.value = transformToNodes(diagramData.value);
        }
    }

    /**
     * Get device type icon for a node
     */
    function getNodeIcon(deviceType: string | null): string {
        return getDeviceTypeIcon(deviceType);
    }

    /**
     * Get color for an edge based on cable properties
     */
    function getEdgeStrokeColor(edge: DiagramConnectionEdge): string {
        return getEdgeColor(edge);
    }

    return {
        // State
        diagramData,
        nodes,
        edges,
        filters,
        selectedNodeId,
        selectedEdgeId,
        selectedNode,
        selectedEdge,
        isLoading,
        error,
        aggregationLevel,

        // Port drill-down state
        expandedNodeIds,
        expandedNodePortsMap,
        isLoadingPorts,

        // Methods
        fetchDiagramData,
        setFilters,
        clearFilters,
        selectNode,
        selectEdge,
        clearSelection,
        updateNodePosition,
        resetLayout,
        getNodeIcon,
        getEdgeStrokeColor,

        // Port drill-down methods
        fetchPortDrillDown,
        toggleNodeExpansion,
        collapseAllNodes,
    };
}
