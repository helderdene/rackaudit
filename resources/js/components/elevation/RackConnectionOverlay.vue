<script setup lang="ts">
import { computed, ref, onMounted, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import { diagramPage } from '@/actions/App/Http/Controllers/ConnectionController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { Cable, ExternalLink, Loader2 } from 'lucide-vue-next';
import type { PlaceholderDevice, RackFace } from '@/types/rooms';
import type {
    DiagramConnectionEdge,
    CableTypeValue,
} from '@/types/connections';

interface ConnectionData {
    id: string;
    source_device_id: number;
    destination_device_id: number;
    cable_type: CableTypeValue | null;
    cable_color: string | null;
    connection_count: number;
}

interface Props {
    /** Rack ID to fetch connections for */
    rackId: number;
    /** Total rack height in U */
    uHeight: number;
    /** Devices placed on the rack */
    devices: PlaceholderDevice[];
    /** Height of a single U slot in pixels */
    slotHeight?: number;
    /** Which face of the rack is being viewed */
    face: RackFace;
}

const props = withDefaults(defineProps<Props>(), {
    slotHeight: 28,
});

// State
const connections = ref<ConnectionData[]>([]);
const isLoading = ref(false);
const error = ref<string | null>(null);
const hoveredConnection = ref<string | null>(null);

// Build a map of device ID to device data for quick lookup
const deviceMap = computed(() => {
    const map = new Map<number, PlaceholderDevice>();
    for (const device of props.devices) {
        const deviceId = parseInt(device.id, 10);
        map.set(deviceId, device);
    }
    return map;
});

// Filter devices on this face
const faceDevices = computed(() => {
    return props.devices.filter((d) => d.face === props.face);
});

// Build a map of device ID to start U for position calculation
const devicePositionMap = computed(() => {
    const map = new Map<number, { startU: number; height: number }>();
    for (const device of faceDevices.value) {
        if (device.start_u !== undefined) {
            const deviceId = parseInt(device.id, 10);
            map.set(deviceId, {
                startU: device.start_u,
                height: device.u_size,
            });
        }
    }
    return map;
});

// Get connections that involve devices on this face
const faceConnections = computed(() => {
    return connections.value.filter((conn) => {
        const sourceOnFace = devicePositionMap.value.has(conn.source_device_id);
        const destOnFace = devicePositionMap.value.has(conn.destination_device_id);
        // Include connection if at least one device is on this face
        return sourceOnFace || destOnFace;
    });
});

/**
 * Calculate Y position for a device center in SVG coordinates
 * The rack is rendered with highest U at top, U1 at bottom
 */
function getDeviceCenterY(deviceId: number): number | null {
    const position = devicePositionMap.value.get(deviceId);
    if (!position) return null;

    // Calculate position from bottom
    // startU is the bottom U position of the device
    // We need to account for: total height, slot height, and device height
    const slotGap = 4; // gap-1 = 0.25rem = 4px
    const totalHeight = props.uHeight * (props.slotHeight + slotGap);

    // Device center Y from the top of the container
    const deviceTopU = position.startU + position.height - 1;
    const deviceBottomFromTop = props.uHeight - deviceTopU;
    const deviceCenterFromTop = deviceBottomFromTop + (position.height / 2);

    return deviceCenterFromTop * (props.slotHeight + slotGap);
}

/**
 * Get stroke color based on cable type and color with dark mode support
 * Uses brighter colors for dark mode visibility
 */
function getConnectionColor(conn: ConnectionData): string {
    // Check if we're in dark mode
    const isDark = document.documentElement.classList.contains('dark');

    if (conn.cable_color) {
        const colorMap: Record<string, { light: string; dark: string }> = {
            blue: { light: '#3b82f6', dark: '#60a5fa' },
            yellow: { light: '#eab308', dark: '#facc15' },
            green: { light: '#22c55e', dark: '#4ade80' },
            red: { light: '#ef4444', dark: '#f87171' },
            orange: { light: '#f97316', dark: '#fb923c' },
            purple: { light: '#a855f7', dark: '#c084fc' },
            gray: { light: '#6b7280', dark: '#9ca3af' },
            black: { light: '#1f2937', dark: '#6b7280' },
            white: { light: '#d1d5db', dark: '#f3f4f6' },
        };
        const normalizedColor = conn.cable_color.toLowerCase();
        if (colorMap[normalizedColor]) {
            return isDark ? colorMap[normalizedColor].dark : colorMap[normalizedColor].light;
        }
        return isDark ? '#9ca3af' : '#6b7280';
    }

    if (!conn.cable_type) return isDark ? '#9ca3af' : '#6b7280';

    if (conn.cable_type.startsWith('cat')) {
        return isDark ? '#60a5fa' : '#3b82f6'; // Blue for ethernet
    }
    if (conn.cable_type.startsWith('fiber')) {
        return isDark ? '#fb923c' : '#f97316'; // Orange for fiber
    }
    if (conn.cable_type.startsWith('power')) {
        return isDark ? '#f87171' : '#ef4444'; // Red for power
    }

    return isDark ? '#9ca3af' : '#6b7280';
}

/**
 * Fetch connections for this rack
 */
async function fetchConnections() {
    isLoading.value = true;
    error.value = null;

    try {
        const response = await axios.get<{ data: { edges: DiagramConnectionEdge[] } }>(
            '/connections/diagram',
            { params: { rack_id: props.rackId } },
        );

        connections.value = response.data.data.edges.map((edge) => ({
            id: edge.id,
            source_device_id: edge.source_device_id,
            destination_device_id: edge.destination_device_id,
            cable_type: edge.cable_type,
            cable_color: edge.cable_color,
            connection_count: edge.connection_count,
        }));
    } catch {
        error.value = 'Failed to load connections';
    } finally {
        isLoading.value = false;
    }
}

// Compute SVG dimensions
const svgWidth = 24; // Width of the connection line area
const totalHeight = computed(() => {
    const slotGap = 4;
    return props.uHeight * (props.slotHeight + slotGap);
});

// Get device name from ID
function getDeviceName(deviceId: number): string {
    const device = deviceMap.value.get(deviceId);
    return device?.name || `Device ${deviceId}`;
}

// Handle mouse events
function handleConnectionHover(connId: string) {
    hoveredConnection.value = connId;
}

function handleConnectionLeave() {
    hoveredConnection.value = null;
}

// Connection diagram URL for this rack
const connectionDiagramUrl = computed(() => diagramPage.url({ query: { rack_id: props.rackId } }));

// Fetch on mount and when rack ID changes
onMounted(fetchConnections);
watch(() => props.rackId, fetchConnections);
</script>

<template>
    <div class="flex gap-2">
        <!-- Connection lines overlay -->
        <div class="relative shrink-0" :style="{ width: `${svgWidth}px`, height: `${totalHeight}px` }">
            <!-- Loading state -->
            <div v-if="isLoading" class="absolute inset-0 flex items-center justify-center">
                <Loader2 class="size-4 animate-spin text-muted-foreground dark:text-slate-400" />
            </div>

            <!-- Error state -->
            <div v-else-if="error" class="absolute inset-0 flex items-center justify-center">
                <span class="text-xs text-destructive dark:text-red-400">!</span>
            </div>

            <!-- SVG Connection lines -->
            <svg
                v-else
                :width="svgWidth"
                :height="totalHeight"
                class="absolute inset-0"
            >
                <template v-for="conn in faceConnections" :key="conn.id">
                    <!-- Only draw lines for connections where both devices are on this face -->
                    <template v-if="getDeviceCenterY(conn.source_device_id) !== null && getDeviceCenterY(conn.destination_device_id) !== null">
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <line
                                        :x1="4"
                                        :y1="getDeviceCenterY(conn.source_device_id)!"
                                        :x2="4"
                                        :y2="getDeviceCenterY(conn.destination_device_id)!"
                                        :stroke="getConnectionColor(conn)"
                                        :stroke-width="hoveredConnection === conn.id ? 4 : 2"
                                        stroke-linecap="round"
                                        class="cursor-pointer transition-all"
                                        @mouseenter="handleConnectionHover(conn.id)"
                                        @mouseleave="handleConnectionLeave"
                                    />
                                </TooltipTrigger>
                                <TooltipContent side="left" class="max-w-[200px] dark:bg-slate-800 dark:border-slate-600">
                                    <div class="space-y-1 text-xs">
                                        <div class="font-medium dark:text-slate-100">Connection</div>
                                        <div class="dark:text-slate-300">{{ getDeviceName(conn.source_device_id) }}</div>
                                        <div class="text-muted-foreground dark:text-slate-400">to</div>
                                        <div class="dark:text-slate-300">{{ getDeviceName(conn.destination_device_id) }}</div>
                                        <Badge v-if="conn.connection_count > 1" variant="secondary" class="text-[10px]">
                                            {{ conn.connection_count }} links
                                        </Badge>
                                    </div>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>

                        <!-- Connection dots at device positions -->
                        <circle
                            :cx="4"
                            :cy="getDeviceCenterY(conn.source_device_id)!"
                            r="3"
                            :fill="getConnectionColor(conn)"
                            class="pointer-events-none"
                        />
                        <circle
                            :cx="4"
                            :cy="getDeviceCenterY(conn.destination_device_id)!"
                            r="3"
                            :fill="getConnectionColor(conn)"
                            class="pointer-events-none"
                        />
                    </template>

                    <!-- Show indicator for external connections (device not on this face) -->
                    <template v-else>
                        <template v-if="getDeviceCenterY(conn.source_device_id) !== null">
                            <circle
                                :cx="4"
                                :cy="getDeviceCenterY(conn.source_device_id)!"
                                r="4"
                                :fill="getConnectionColor(conn)"
                                fill-opacity="0.5"
                                :stroke="getConnectionColor(conn)"
                                stroke-width="1"
                                class="pointer-events-none"
                            />
                            <line
                                :x1="4"
                                :y1="getDeviceCenterY(conn.source_device_id)! - 4"
                                :x2="svgWidth - 4"
                                :y2="getDeviceCenterY(conn.source_device_id)! - 8"
                                :stroke="getConnectionColor(conn)"
                                stroke-width="1"
                                stroke-dasharray="2 2"
                                class="pointer-events-none"
                            />
                        </template>
                        <template v-if="getDeviceCenterY(conn.destination_device_id) !== null">
                            <circle
                                :cx="4"
                                :cy="getDeviceCenterY(conn.destination_device_id)!"
                                r="4"
                                :fill="getConnectionColor(conn)"
                                fill-opacity="0.5"
                                :stroke="getConnectionColor(conn)"
                                stroke-width="1"
                                class="pointer-events-none"
                            />
                            <line
                                :x1="4"
                                :y1="getDeviceCenterY(conn.destination_device_id)! - 4"
                                :x2="svgWidth - 4"
                                :y2="getDeviceCenterY(conn.destination_device_id)! - 8"
                                :stroke="getConnectionColor(conn)"
                                stroke-width="1"
                                stroke-dasharray="2 2"
                                class="pointer-events-none"
                            />
                        </template>
                    </template>
                </template>
            </svg>
        </div>

        <!-- Connection legend/info with dark mode -->
        <div v-if="faceConnections.length > 0" class="flex flex-col gap-1 pt-1">
            <Link :href="connectionDiagramUrl">
                <Button variant="ghost" size="sm" class="h-6 gap-1 text-xs dark:hover:bg-slate-700">
                    <Cable class="size-3" />
                    {{ faceConnections.length }}
                    <ExternalLink class="size-3" />
                </Button>
            </Link>
        </div>

        <!-- Skeleton loading state with dark mode -->
        <div v-if="isLoading" class="flex flex-col gap-1">
            <Skeleton class="h-4 w-16 dark:bg-slate-700" />
            <Skeleton class="h-4 w-12 dark:bg-slate-700" />
        </div>
    </div>
</template>
