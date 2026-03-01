<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { diagramPage } from '@/actions/App/Http/Controllers/ConnectionController';
import ConnectionDiagramCanvas from '@/components/diagram/ConnectionDiagramCanvas.vue';
import ConnectionDetailDialog from '@/components/connections/ConnectionDetailDialog.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import RealtimeToastContainer from '@/components/notifications/RealtimeToastContainer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import AppLayout from '@/layouts/AppLayout.vue';
import { useRealtimeUpdates } from '@/composables/useRealtimeUpdates';
import { debounce } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import type {
    DiagramFilters,
    HierarchicalFilterOptions,
    FlowNodeData,
    FlowEdgeData,
    CableTypeOption,
    PortTypeValue,
    DiagramDeviceNode,
    DiagramConnectionEdge,
} from '@/types/connections';
import {
    Filter,
    Network,
    RefreshCw,
    X,
    Server,
    Cable,
    Info,
    PanelLeftClose,
    PanelLeft,
} from 'lucide-vue-next';

interface DeviceTypeOption {
    value: number;
    label: string;
}

interface PortTypeOption {
    value: string;
    label: string;
}

interface Props {
    filterOptions: HierarchicalFilterOptions;
    deviceTypes: DeviceTypeOption[];
    portTypeOptions: PortTypeOption[];
    cableTypeOptions: CableTypeOption[];
    initialFilters: DiagramFilters;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Connection Diagram',
        href: '/connections/diagram/page',
    },
];

// Local filter state
const selectedDatacenterId = ref<number | null>(props.initialFilters.datacenter_id);
const selectedRoomId = ref<number | null>(props.initialFilters.room_id);
const selectedRowId = ref<number | null>(props.initialFilters.row_id);
const selectedRackId = ref<number | null>(props.initialFilters.rack_id);
const selectedDeviceId = ref<number | null>(props.initialFilters.device_id);
const selectedDeviceTypeId = ref<number | null>(props.initialFilters.device_type_id);
const selectedPortType = ref<PortTypeValue | null>(props.initialFilters.port_type);
const selectedVerified = ref<boolean | null>(props.initialFilters.verified);

// UI state
const showFilters = ref(true);
const showMobileFilters = ref(false);
const isFilterChanging = ref(false);

// Modal state
const showDeviceModal = ref(false);
const showConnectionModal = ref(false);
const selectedDevice = ref<DiagramDeviceNode | null>(null);
const selectedConnection = ref<DiagramConnectionEdge | null>(null);

// Diagram canvas ref
const diagramCanvasRef = ref<InstanceType<typeof ConnectionDiagramCanvas> | null>(null);

// Real-time updates integration
const {
    pendingUpdates,
    dismissUpdate,
    clearUpdates,
    onDataChange,
} = useRealtimeUpdates(selectedDatacenterId.value);

// Register handler for connection changes
onDataChange('connection', (data) => {
    // Toast will be automatically shown via pendingUpdates
    console.log('Connection changed:', data);
});

// Handle toast dismissal
function handleDismissUpdate(id: string): void {
    dismissUpdate(id);
}

// Handle toast refresh
function handleRefresh(): void {
    clearUpdates();
    router.reload();
}

// Handle clear all updates
function handleClearAll(): void {
    clearUpdates();
}

// Filtered options based on parent selections
const filteredRooms = computed(() => {
    if (!selectedDatacenterId.value) return [];
    return props.filterOptions.rooms.filter(
        (room) => room.datacenter_id === selectedDatacenterId.value,
    );
});

const filteredRows = computed(() => {
    if (!selectedRoomId.value) return [];
    return props.filterOptions.rows.filter(
        (row) => row.room_id === selectedRoomId.value,
    );
});

const filteredRacks = computed(() => {
    if (!selectedRowId.value) return [];
    return props.filterOptions.racks.filter(
        (rack) => rack.row_id === selectedRowId.value,
    );
});

// Current filter values for the diagram
const currentFilters = computed<Partial<DiagramFilters>>(() => ({
    datacenter_id: selectedDatacenterId.value,
    room_id: selectedRoomId.value,
    row_id: selectedRowId.value,
    rack_id: selectedRackId.value,
    device_id: selectedDeviceId.value,
    device_type_id: selectedDeviceTypeId.value,
    port_type: selectedPortType.value,
    verified: selectedVerified.value,
}));

// Check if any filters are active
const hasActiveFilters = computed(() => {
    return (
        selectedDatacenterId.value !== null ||
        selectedRoomId.value !== null ||
        selectedRowId.value !== null ||
        selectedRackId.value !== null ||
        selectedDeviceId.value !== null ||
        selectedDeviceTypeId.value !== null ||
        selectedPortType.value !== null ||
        selectedVerified.value !== null
    );
});

// Count active filters for badge display
const activeFilterCount = computed(() => {
    let count = 0;
    if (selectedDatacenterId.value !== null) count++;
    if (selectedRoomId.value !== null) count++;
    if (selectedRowId.value !== null) count++;
    if (selectedRackId.value !== null) count++;
    if (selectedDeviceId.value !== null) count++;
    if (selectedDeviceTypeId.value !== null) count++;
    if (selectedPortType.value !== null) count++;
    if (selectedVerified.value !== null) count++;
    return count;
});

// Reset cascading selections when parent changes
watch(selectedDatacenterId, () => {
    selectedRoomId.value = null;
    selectedRowId.value = null;
    selectedRackId.value = null;
});

watch(selectedRoomId, () => {
    selectedRowId.value = null;
    selectedRackId.value = null;
});

watch(selectedRowId, () => {
    selectedRackId.value = null;
});

// Debounced filter update
const debouncedUpdateDiagram = debounce(async () => {
    isFilterChanging.value = true;
    if (diagramCanvasRef.value) {
        await diagramCanvasRef.value.setFilters(currentFilters.value);
    }
    isFilterChanging.value = false;
}, 300);

// Watch for filter changes
watch(
    [
        selectedDatacenterId,
        selectedRoomId,
        selectedRowId,
        selectedRackId,
        selectedDeviceId,
        selectedDeviceTypeId,
        selectedPortType,
        selectedVerified,
    ],
    () => {
        debouncedUpdateDiagram();
    },
);

/**
 * Clear all filters
 */
function clearFilters() {
    selectedDatacenterId.value = null;
    selectedRoomId.value = null;
    selectedRowId.value = null;
    selectedRackId.value = null;
    selectedDeviceId.value = null;
    selectedDeviceTypeId.value = null;
    selectedPortType.value = null;
    selectedVerified.value = null;
}

/**
 * Handle node click - show device details modal
 */
function handleNodeClick(nodeId: number) {
    if (diagramCanvasRef.value) {
        const nodeData = (diagramCanvasRef.value as any)?.selectedNode?.value;
        if (nodeData) {
            selectedDevice.value = nodeData;
            showDeviceModal.value = true;
        }
    }
}

/**
 * Handle node selection - capture selected node data
 */
function handleNodeSelect(node: FlowNodeData | null) {
    if (node) {
        selectedDevice.value = node;
    }
}

/**
 * Handle edge click - show connection details modal
 */
function handleEdgeClick(edgeId: string) {
    if (diagramCanvasRef.value) {
        const edgeData = (diagramCanvasRef.value as any)?.selectedEdge?.value;
        if (edgeData) {
            selectedConnection.value = edgeData;
            showConnectionModal.value = true;
        }
    }
}

/**
 * Handle edge selection - capture selected edge data
 */
function handleEdgeSelect(edge: FlowEdgeData | null) {
    if (edge) {
        selectedConnection.value = edge;
    }
}

/**
 * Navigate to device details page
 */
function navigateToDevice(deviceId: number) {
    router.visit(`/devices/${deviceId}`);
}

// Select styling class with dark mode
const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 dark:bg-slate-800 dark:border-slate-600 dark:text-slate-100';
</script>

<template>
    <Head title="Connection Diagram" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-2 sm:p-4">
            <!-- Header with responsive layout -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <HeadingSmall
                    title="Connection Diagram"
                    description="Interactive visualization of device connections across your infrastructure."
                    class="[&>p]:hidden sm:[&>p]:block"
                />

                <div class="flex items-center gap-2">
                    <!-- Mobile filter button (visible on small screens) -->
                    <Sheet v-model:open="showMobileFilters">
                        <SheetTrigger as-child>
                            <Button
                                variant="outline"
                                size="sm"
                                class="lg:hidden dark:border-slate-600 dark:hover:bg-slate-700"
                            >
                                <Filter class="mr-2 size-4" />
                                Filters
                                <Badge
                                    v-if="activeFilterCount > 0"
                                    variant="secondary"
                                    class="ml-2 size-5 p-0 text-xs"
                                >
                                    {{ activeFilterCount }}
                                </Badge>
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="left" class="w-80 overflow-y-auto dark:bg-slate-900 dark:border-slate-700">
                            <SheetHeader>
                                <SheetTitle class="flex items-center justify-between dark:text-slate-100">
                                    <span class="flex items-center gap-2">
                                        <Filter class="size-4" />
                                        Filters
                                    </span>
                                    <Button
                                        v-if="hasActiveFilters"
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 px-2 text-xs dark:hover:bg-slate-700"
                                        @click="clearFilters"
                                    >
                                        <X class="mr-1 size-3" />
                                        Clear
                                    </Button>
                                </SheetTitle>
                            </SheetHeader>
                            <!-- Mobile filter content (same as desktop) -->
                            <div class="mt-6 space-y-4">
                                <!-- Location Filters -->
                                <div class="space-y-3">
                                    <h4 class="text-sm font-medium text-muted-foreground dark:text-slate-400">Location</h4>

                                    <div class="grid gap-2">
                                        <Label for="mobile-filter-datacenter" class="text-xs dark:text-slate-300">Datacenter</Label>
                                        <select
                                            id="mobile-filter-datacenter"
                                            v-model="selectedDatacenterId"
                                            :class="selectClass"
                                        >
                                            <option :value="null">All Datacenters</option>
                                            <option
                                                v-for="dc in filterOptions.datacenters"
                                                :key="dc.value"
                                                :value="dc.value"
                                            >
                                                {{ dc.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="mobile-filter-room" class="text-xs dark:text-slate-300">Room</Label>
                                        <select
                                            id="mobile-filter-room"
                                            v-model="selectedRoomId"
                                            :disabled="!selectedDatacenterId || filteredRooms.length === 0"
                                            :class="selectClass"
                                        >
                                            <option :value="null">
                                                {{ selectedDatacenterId ? (filteredRooms.length > 0 ? 'All Rooms' : 'No rooms') : 'Select datacenter' }}
                                            </option>
                                            <option
                                                v-for="room in filteredRooms"
                                                :key="room.value"
                                                :value="room.value"
                                            >
                                                {{ room.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="mobile-filter-row" class="text-xs dark:text-slate-300">Row</Label>
                                        <select
                                            id="mobile-filter-row"
                                            v-model="selectedRowId"
                                            :disabled="!selectedRoomId || filteredRows.length === 0"
                                            :class="selectClass"
                                        >
                                            <option :value="null">
                                                {{ selectedRoomId ? (filteredRows.length > 0 ? 'All Rows' : 'No rows') : 'Select room' }}
                                            </option>
                                            <option
                                                v-for="row in filteredRows"
                                                :key="row.value"
                                                :value="row.value"
                                            >
                                                {{ row.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="mobile-filter-rack" class="text-xs dark:text-slate-300">Rack</Label>
                                        <select
                                            id="mobile-filter-rack"
                                            v-model="selectedRackId"
                                            :disabled="!selectedRowId || filteredRacks.length === 0"
                                            :class="selectClass"
                                        >
                                            <option :value="null">
                                                {{ selectedRowId ? (filteredRacks.length > 0 ? 'All Racks' : 'No racks') : 'Select row' }}
                                            </option>
                                            <option
                                                v-for="rack in filteredRacks"
                                                :key="rack.value"
                                                :value="rack.value"
                                            >
                                                {{ rack.label }}
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Connection Filters -->
                                <div class="space-y-3 border-t pt-4 dark:border-slate-700">
                                    <h4 class="text-sm font-medium text-muted-foreground dark:text-slate-400">Connection Type</h4>

                                    <div class="grid gap-2">
                                        <Label for="mobile-filter-port-type" class="text-xs dark:text-slate-300">Port Type</Label>
                                        <select
                                            id="mobile-filter-port-type"
                                            v-model="selectedPortType"
                                            :class="selectClass"
                                        >
                                            <option :value="null">All Types</option>
                                            <option
                                                v-for="type in portTypeOptions"
                                                :key="type.value"
                                                :value="type.value"
                                            >
                                                {{ type.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="mobile-filter-device-type" class="text-xs dark:text-slate-300">Device Type</Label>
                                        <select
                                            id="mobile-filter-device-type"
                                            v-model="selectedDeviceTypeId"
                                            :class="selectClass"
                                        >
                                            <option :value="null">All Device Types</option>
                                            <option
                                                v-for="type in deviceTypes"
                                                :key="type.value"
                                                :value="type.value"
                                            >
                                                {{ type.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="mobile-filter-verified" class="text-xs dark:text-slate-300">Status</Label>
                                        <select
                                            id="mobile-filter-verified"
                                            v-model="selectedVerified"
                                            :class="selectClass"
                                        >
                                            <option :value="null">All Statuses</option>
                                            <option :value="true">Verified</option>
                                            <option :value="false">Unverified</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </SheetContent>
                    </Sheet>

                    <!-- Desktop filter toggle button (hidden on small screens) -->
                    <Button
                        variant="outline"
                        size="sm"
                        class="hidden lg:flex dark:border-slate-600 dark:hover:bg-slate-700"
                        @click="showFilters = !showFilters"
                    >
                        <PanelLeft v-if="!showFilters" class="mr-2 size-4" />
                        <PanelLeftClose v-else class="mr-2 size-4" />
                        {{ showFilters ? 'Hide Filters' : 'Show Filters' }}
                    </Button>
                </div>
            </div>

            <!-- Main content area with responsive layout -->
            <div class="flex flex-1 gap-4" :class="{ 'lg:flex-row': showFilters }">
                <!-- Desktop filter sidebar (hidden on mobile, shown via Sheet) -->
                <Card
                    v-if="showFilters"
                    class="hidden w-64 shrink-0 lg:block xl:w-72 dark:bg-slate-800 dark:border-slate-700"
                >
                    <CardHeader class="pb-4">
                        <CardTitle class="flex items-center justify-between text-base dark:text-slate-100">
                            <span class="flex items-center gap-2">
                                <Filter class="size-4" />
                                Filters
                            </span>
                            <Button
                                v-if="hasActiveFilters"
                                variant="ghost"
                                size="sm"
                                class="h-7 px-2 text-xs dark:hover:bg-slate-700"
                                @click="clearFilters"
                            >
                                <X class="mr-1 size-3" />
                                Clear
                            </Button>
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <!-- Location Filters -->
                        <div class="space-y-3">
                            <h4 class="text-sm font-medium text-muted-foreground dark:text-slate-400">Location</h4>

                            <!-- Datacenter -->
                            <div class="grid gap-2">
                                <Label for="filter-datacenter" class="text-xs dark:text-slate-300">Datacenter</Label>
                                <select
                                    id="filter-datacenter"
                                    v-model="selectedDatacenterId"
                                    :class="selectClass"
                                >
                                    <option :value="null">All Datacenters</option>
                                    <option
                                        v-for="dc in filterOptions.datacenters"
                                        :key="dc.value"
                                        :value="dc.value"
                                    >
                                        {{ dc.label }}
                                    </option>
                                </select>
                            </div>

                            <!-- Room -->
                            <div class="grid gap-2">
                                <Label for="filter-room" class="text-xs dark:text-slate-300">Room</Label>
                                <select
                                    id="filter-room"
                                    v-model="selectedRoomId"
                                    :disabled="!selectedDatacenterId || filteredRooms.length === 0"
                                    :class="selectClass"
                                >
                                    <option :value="null">
                                        {{ selectedDatacenterId ? (filteredRooms.length > 0 ? 'All Rooms' : 'No rooms') : 'Select datacenter' }}
                                    </option>
                                    <option
                                        v-for="room in filteredRooms"
                                        :key="room.value"
                                        :value="room.value"
                                    >
                                        {{ room.label }}
                                    </option>
                                </select>
                            </div>

                            <!-- Row -->
                            <div class="grid gap-2">
                                <Label for="filter-row" class="text-xs dark:text-slate-300">Row</Label>
                                <select
                                    id="filter-row"
                                    v-model="selectedRowId"
                                    :disabled="!selectedRoomId || filteredRows.length === 0"
                                    :class="selectClass"
                                >
                                    <option :value="null">
                                        {{ selectedRoomId ? (filteredRows.length > 0 ? 'All Rows' : 'No rows') : 'Select room' }}
                                    </option>
                                    <option
                                        v-for="row in filteredRows"
                                        :key="row.value"
                                        :value="row.value"
                                    >
                                        {{ row.label }}
                                    </option>
                                </select>
                            </div>

                            <!-- Rack -->
                            <div class="grid gap-2">
                                <Label for="filter-rack" class="text-xs dark:text-slate-300">Rack</Label>
                                <select
                                    id="filter-rack"
                                    v-model="selectedRackId"
                                    :disabled="!selectedRowId || filteredRacks.length === 0"
                                    :class="selectClass"
                                >
                                    <option :value="null">
                                        {{ selectedRowId ? (filteredRacks.length > 0 ? 'All Racks' : 'No racks') : 'Select row' }}
                                    </option>
                                    <option
                                        v-for="rack in filteredRacks"
                                        :key="rack.value"
                                        :value="rack.value"
                                    >
                                        {{ rack.label }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Connection Filters -->
                        <div class="space-y-3 border-t pt-4 dark:border-slate-700">
                            <h4 class="text-sm font-medium text-muted-foreground dark:text-slate-400">Connection Type</h4>

                            <!-- Port Type -->
                            <div class="grid gap-2">
                                <Label for="filter-port-type" class="text-xs dark:text-slate-300">Port Type</Label>
                                <select
                                    id="filter-port-type"
                                    v-model="selectedPortType"
                                    :class="selectClass"
                                >
                                    <option :value="null">All Types</option>
                                    <option
                                        v-for="type in portTypeOptions"
                                        :key="type.value"
                                        :value="type.value"
                                    >
                                        {{ type.label }}
                                    </option>
                                </select>
                            </div>

                            <!-- Device Type -->
                            <div class="grid gap-2">
                                <Label for="filter-device-type" class="text-xs dark:text-slate-300">Device Type</Label>
                                <select
                                    id="filter-device-type"
                                    v-model="selectedDeviceTypeId"
                                    :class="selectClass"
                                >
                                    <option :value="null">All Device Types</option>
                                    <option
                                        v-for="type in deviceTypes"
                                        :key="type.value"
                                        :value="type.value"
                                    >
                                        {{ type.label }}
                                    </option>
                                </select>
                            </div>

                            <!-- Verified Status -->
                            <div class="grid gap-2">
                                <Label for="filter-verified" class="text-xs dark:text-slate-300">Status</Label>
                                <select
                                    id="filter-verified"
                                    v-model="selectedVerified"
                                    :class="selectClass"
                                >
                                    <option :value="null">All Statuses</option>
                                    <option :value="true">Verified</option>
                                    <option :value="false">Unverified</option>
                                </select>
                            </div>
                        </div>

                        <!-- Active Filters Summary -->
                        <div v-if="hasActiveFilters" class="border-t pt-4 dark:border-slate-700">
                            <div class="flex flex-wrap gap-1">
                                <Badge
                                    v-if="selectedDatacenterId"
                                    variant="secondary"
                                    class="text-xs"
                                >
                                    DC: {{ filterOptions.datacenters.find(d => d.value === selectedDatacenterId)?.label }}
                                </Badge>
                                <Badge
                                    v-if="selectedPortType"
                                    variant="secondary"
                                    class="text-xs"
                                >
                                    {{ portTypeOptions.find(p => p.value === selectedPortType)?.label }}
                                </Badge>
                                <Badge
                                    v-if="selectedDeviceTypeId"
                                    variant="secondary"
                                    class="text-xs"
                                >
                                    {{ deviceTypes.find(d => d.value === selectedDeviceTypeId)?.label }}
                                </Badge>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Diagram canvas with responsive min-height -->
                <div class="relative flex-1 min-h-[400px] sm:min-h-[500px]">
                    <!-- Loading overlay for filter changes -->
                    <div
                        v-if="isFilterChanging"
                        class="absolute inset-0 z-20 flex items-center justify-center bg-background/60 dark:bg-slate-900/60 backdrop-blur-sm rounded-lg"
                    >
                        <div class="flex items-center gap-2 text-sm text-muted-foreground dark:text-slate-400">
                            <RefreshCw class="size-4 animate-spin" />
                            Updating diagram...
                        </div>
                    </div>

                    <ConnectionDiagramCanvas
                        ref="diagramCanvasRef"
                        :initial-filters="currentFilters"
                        :show-minimap="true"
                        :show-controls="true"
                        class="h-full"
                        @node-click="handleNodeClick"
                        @node-select="handleNodeSelect"
                        @edge-click="handleEdgeClick"
                        @edge-select="handleEdgeSelect"
                    />
                </div>
            </div>
        </div>

        <!-- Device Detail Modal with dark mode -->
        <Dialog v-model:open="showDeviceModal">
            <DialogContent class="sm:max-w-md dark:bg-slate-800 dark:border-slate-700">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 dark:text-slate-100">
                        <Server class="size-5" />
                        Device Details
                    </DialogTitle>
                </DialogHeader>

                <div v-if="selectedDevice" class="space-y-4">
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-sm text-muted-foreground dark:text-slate-400">Name</span>
                            <span class="text-sm font-medium dark:text-slate-100">{{ selectedDevice.name }}</span>
                        </div>
                        <div v-if="selectedDevice.asset_tag" class="flex justify-between">
                            <span class="text-sm text-muted-foreground dark:text-slate-400">Asset Tag</span>
                            <span class="text-sm font-mono dark:text-slate-100">{{ selectedDevice.asset_tag }}</span>
                        </div>
                        <div v-if="selectedDevice.device_type" class="flex justify-between">
                            <span class="text-sm text-muted-foreground dark:text-slate-400">Type</span>
                            <Badge variant="secondary">{{ selectedDevice.device_type }}</Badge>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-muted-foreground dark:text-slate-400">Ports</span>
                            <span class="text-sm font-medium dark:text-slate-100">{{ selectedDevice.port_count }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-muted-foreground dark:text-slate-400">Connections</span>
                            <span class="text-sm font-medium dark:text-slate-100">{{ selectedDevice.connection_count }}</span>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 border-t pt-4 dark:border-slate-700">
                        <Button
                            variant="outline"
                            class="dark:border-slate-600 dark:hover:bg-slate-700"
                            @click="showDeviceModal = false"
                        >
                            Close
                        </Button>
                        <Button @click="navigateToDevice(selectedDevice.id)">
                            View Device
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Connection Detail Modal with dark mode -->
        <Dialog v-model:open="showConnectionModal">
            <DialogContent class="sm:max-w-md dark:bg-slate-800 dark:border-slate-700">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 dark:text-slate-100">
                        <Cable class="size-5" />
                        Connection Details
                    </DialogTitle>
                </DialogHeader>

                <div v-if="selectedConnection" class="space-y-4">
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-sm text-muted-foreground dark:text-slate-400">Cable Type</span>
                            <span class="text-sm font-medium dark:text-slate-100">
                                {{ selectedConnection.cable_type || 'Unknown' }}
                            </span>
                        </div>
                        <div v-if="selectedConnection.cable_color" class="flex justify-between items-center">
                            <span class="text-sm text-muted-foreground dark:text-slate-400">Cable Color</span>
                            <div class="flex items-center gap-2">
                                <span
                                    class="size-4 rounded-full border dark:border-slate-500"
                                    :style="{ backgroundColor: selectedConnection.cable_color }"
                                />
                                <span class="text-sm font-medium capitalize dark:text-slate-100">
                                    {{ selectedConnection.cable_color }}
                                </span>
                            </div>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-muted-foreground dark:text-slate-400">Status</span>
                            <Badge :variant="selectedConnection.verified ? 'default' : 'secondary'">
                                {{ selectedConnection.verified ? 'Verified' : 'Unverified' }}
                            </Badge>
                        </div>
                        <div v-if="selectedConnection.connection_count > 1" class="flex justify-between">
                            <span class="text-sm text-muted-foreground dark:text-slate-400">Connections</span>
                            <span class="text-sm font-medium dark:text-slate-100">{{ selectedConnection.connection_count }}</span>
                        </div>
                        <div v-if="selectedConnection.has_discrepancy" class="rounded-md bg-yellow-50 p-3 text-sm text-yellow-800 dark:bg-yellow-950/50 dark:text-yellow-200">
                            <Info class="mr-2 inline size-4" />
                            This connection has audit discrepancies
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 border-t pt-4 dark:border-slate-700">
                        <Button
                            variant="outline"
                            class="dark:border-slate-600 dark:hover:bg-slate-700"
                            @click="showConnectionModal = false"
                        >
                            Close
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Real-time Toast Container -->
        <RealtimeToastContainer
            :updates="pendingUpdates"
            @dismiss="handleDismissUpdate"
            @refresh="handleRefresh"
            @clear-all="handleClearAll"
        />
    </AppLayout>
</template>
