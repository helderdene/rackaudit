<script setup lang="ts">
import { diagramPage } from '@/actions/App/Http/Controllers/ConnectionController';
import DatacenterController from '@/actions/App/Http/Controllers/DatacenterController';
import DeviceController from '@/actions/App/Http/Controllers/DeviceController';
import RackController from '@/actions/App/Http/Controllers/RackController';
import RoomController from '@/actions/App/Http/Controllers/RoomController';
import RowController from '@/actions/App/Http/Controllers/RowController';
import DeviceDetailModal from '@/components/elevation/DeviceDetailModal.vue';
import RackConnectionOverlay from '@/components/elevation/RackConnectionOverlay.vue';
import RackElevationView from '@/components/elevation/RackElevationView.vue';
import UnplacedDevicesSidebar from '@/components/elevation/UnplacedDevicesSidebar.vue';
import UtilizationCard from '@/components/elevation/UtilizationCard.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { FeatureTour } from '@/components/help';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useRackElevation } from '@/composables/useRackElevation';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type {
    DatacenterReference,
    DeviceWidth,
    PlaceholderDevice,
    RackData,
    RackFace,
    RoomReference,
    RowReference,
} from '@/types/rooms';
import { Head, Link, router } from '@inertiajs/vue3';
import { Cable, PanelLeft, PanelLeftClose, Server } from 'lucide-vue-next';
import { ref } from 'vue';

/**
 * Device data structure returned from the backend.
 * Matches the PlaceholderDevice interface used by elevation components.
 */
interface DevicesData {
    placed: PlaceholderDevice[];
    unplaced: PlaceholderDevice[];
}

interface Props {
    datacenter: DatacenterReference;
    room: RoomReference;
    row: RowReference;
    rack: RackData;
    devices: DevicesData;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Datacenters',
        href: DatacenterController.index.url(),
    },
    {
        title: props.datacenter.name,
        href: DatacenterController.show.url(props.datacenter.id),
    },
    {
        title: 'Rooms',
        href: RoomController.index.url(props.datacenter.id),
    },
    {
        title: props.room.name,
        href: RoomController.show.url({
            datacenter: props.datacenter.id,
            room: props.room.id,
        }),
    },
    {
        title: 'Rows',
        href: RowController.index.url({
            datacenter: props.datacenter.id,
            room: props.room.id,
        }),
    },
    {
        title: props.row.name,
        href: RowController.show.url({
            datacenter: props.datacenter.id,
            room: props.room.id,
            row: props.row.id,
        }),
    },
    {
        title: 'Racks',
        href: RackController.index.url({
            datacenter: props.datacenter.id,
            room: props.room.id,
            row: props.row.id,
        }),
    },
    {
        title: props.rack.name,
        href: RackController.show.url({
            datacenter: props.datacenter.id,
            room: props.room.id,
            row: props.row.id,
            rack: props.rack.id,
        }),
    },
    {
        title: 'Elevation',
        href: RackController.elevation.url({
            datacenter: props.datacenter.id,
            room: props.room.id,
            row: props.row.id,
            rack: props.rack.id,
        }),
    },
];

// Get status badge variant
const getStatusVariant = (
    status: string | null,
): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (status) {
        case 'active':
            return 'default';
        case 'inactive':
            return 'secondary';
        case 'maintenance':
            return 'destructive';
        default:
            return 'outline';
    }
};

// Initialize the rack elevation composable with real device data
const rackHeight = props.rack.u_height ?? 42;
const rackId = props.rack.id;

const {
    placedDevices,
    unplacedDevices,
    draggedDevice,
    utilizationStats,
    canPlaceAt,
    placeDevice,
    moveDevice,
    removeDevice,
    setDraggedDevice,
} = useRackElevation(
    props.devices.placed,
    props.devices.unplaced,
    rackHeight,
    rackId,
);

// Sidebar collapsed state for responsive design
const isSidebarOpen = ref(true);

// Connection visualization toggle
const showConnections = ref(false);

// Modal state for device details
const isDeviceModalOpen = ref(false);
const selectedDevice = ref<PlaceholderDevice | null>(null);

// Handle device click to show modal
function handleDeviceClick(device: PlaceholderDevice) {
    selectedDevice.value = device;
    isDeviceModalOpen.value = true;
}

// Navigate to device details page
function navigateToDeviceDetails(device: PlaceholderDevice) {
    // Device ID is a string, convert to number for the route
    const deviceId = parseInt(device.id, 10);
    router.visit(DeviceController.show.url({ device: deviceId }));
}

// Handle removing device from rack
async function handleRemoveDevice(device: PlaceholderDevice) {
    await removeDevice(device);
    isDeviceModalOpen.value = false;
}

// Handle device drop onto rack (from sidebar or repositioning)
async function handleDeviceDrop(
    device: PlaceholderDevice,
    startU: number,
    face: RackFace,
    width: DeviceWidth,
) {
    // Check if device is already placed (has start_u defined)
    const isAlreadyPlaced =
        device.start_u !== undefined && device.face !== undefined;

    if (isAlreadyPlaced) {
        // Move existing device to new position
        await moveDevice(device, { start_u: startU, face, width });
    } else {
        // Place new device from sidebar
        await placeDevice(device, { start_u: startU, face, width });
    }
    setDraggedDevice(null);
}

// Handle drag start (from sidebar or placed device)
function handleDragStart(device: PlaceholderDevice) {
    setDraggedDevice(device);
}

// Handle drag end
function handleDragEnd() {
    setDraggedDevice(null);
}

// Connection diagram URL for this rack
const connectionDiagramUrl = diagramPage.url({
    query: { rack_id: props.rack.id },
});
</script>

<template>
    <Head :title="`${rack.name} - Elevation`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <HeadingSmall
                    :title="`${rack.name} - Elevation`"
                    :description="`Rack elevation diagram showing ${rack.u_height_label || 'U'} positions`"
                />
                <div class="flex items-center gap-3">
                    <Badge :variant="getStatusVariant(rack.status)">
                        {{ rack.status_label || 'Unknown' }}
                    </Badge>
                    <span class="text-sm text-muted-foreground">{{
                        rack.u_height_label
                    }}</span>
                </div>
            </div>

            <!-- Utilization Stats (always visible at top) -->
            <div id="utilization-card" data-tour="capacity-panel">
                <UtilizationCard :stats="utilizationStats" class="w-full" />
            </div>

            <!-- View Options -->
            <div
                id="view-options"
                data-tour="action-bar"
                class="flex items-center justify-between gap-4 rounded-lg border bg-card p-3"
            >
                <div class="flex items-center gap-6">
                    <!-- Connection Toggle -->
                    <div class="flex items-center gap-2">
                        <Switch
                            id="show-connections"
                            v-model:checked="showConnections"
                        />
                        <Label
                            for="show-connections"
                            class="flex cursor-pointer items-center gap-1.5 text-sm"
                        >
                            <Cable class="size-4" />
                            Show Connections
                        </Label>
                    </div>
                </div>

                <!-- View Full Diagram Link -->
                <Link :href="connectionDiagramUrl">
                    <Button variant="outline" size="sm" class="gap-1.5">
                        <Cable class="size-4" />
                        Full Connection Diagram
                    </Button>
                </Link>
            </div>

            <!-- Main Content: Sidebar + Elevation Views -->
            <div class="flex flex-1 flex-col gap-4 lg:flex-row">
                <!-- Collapsible Sidebar for Unplaced Devices - Mobile/Tablet -->
                <Collapsible v-model:open="isSidebarOpen" class="lg:hidden">
                    <div class="mb-2 flex items-center gap-2">
                        <CollapsibleTrigger as-child>
                            <Button variant="ghost" size="sm" class="gap-2">
                                <PanelLeft
                                    v-if="!isSidebarOpen"
                                    class="size-4"
                                />
                                <PanelLeftClose v-else class="size-4" />
                                {{ isSidebarOpen ? 'Hide' : 'Show' }} Unplaced
                                Devices
                            </Button>
                        </CollapsibleTrigger>
                        <Badge variant="secondary">
                            {{ unplacedDevices.length }}
                        </Badge>
                    </div>
                    <CollapsibleContent>
                        <UnplacedDevicesSidebar
                            :devices="unplacedDevices"
                            class="mb-4 max-h-64"
                            @drag-start="handleDragStart"
                            @drag-end="handleDragEnd"
                        />
                    </CollapsibleContent>
                </Collapsible>

                <!-- Desktop Sidebar (always visible on lg+) -->
                <div id="unplaced-sidebar" data-tour-target="unplaced">
                    <UnplacedDevicesSidebar
                        :devices="unplacedDevices"
                        class="hidden lg:flex lg:w-72 lg:shrink-0"
                        @drag-start="handleDragStart"
                        @drag-end="handleDragEnd"
                    />
                </div>

                <!-- Elevation Views Container
                     Responsive layout: stacks vertically on mobile/tablet (default + md),
                     side-by-side on desktop (lg+) -->
                <div
                    id="elevation-views"
                    data-tour="elevation-diagram"
                    class="flex flex-1 flex-col gap-4 lg:flex-row"
                >
                    <!-- Front View -->
                    <Card data-tour="view-toggle" class="flex-1">
                        <CardHeader class="pb-2">
                            <CardTitle
                                class="flex items-center gap-2 text-base"
                            >
                                <Server class="size-4" />
                                Front View
                            </CardTitle>
                        </CardHeader>
                        <!-- Responsive max-height: larger on tablet (vertical stack), smaller on desktop (side-by-side) -->
                        <CardContent
                            class="max-h-[calc(100vh-20rem)] overflow-y-auto lg:max-h-[calc(100vh-24rem)]"
                        >
                            <div class="flex gap-2">
                                <!-- Connection overlay for front view -->
                                <RackConnectionOverlay
                                    v-if="showConnections"
                                    :rack-id="rackId"
                                    :u-height="rackHeight"
                                    :devices="placedDevices"
                                    face="front"
                                />

                                <!-- Elevation View -->
                                <RackElevationView
                                    face="front"
                                    :u-height="rackHeight"
                                    :devices="placedDevices"
                                    :dragged-device="draggedDevice"
                                    :can-place-at="canPlaceAt"
                                    class="flex-1"
                                    @device-click="handleDeviceClick"
                                    @device-drop="handleDeviceDrop"
                                    @device-drag-start="handleDragStart"
                                    @device-drag-end="handleDragEnd"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Rear View -->
                    <Card class="flex-1">
                        <CardHeader class="pb-2">
                            <CardTitle
                                class="flex items-center gap-2 text-base"
                            >
                                <Server class="size-4" />
                                Rear View
                            </CardTitle>
                        </CardHeader>
                        <!-- Responsive max-height: larger on tablet (vertical stack), smaller on desktop (side-by-side) -->
                        <CardContent
                            class="max-h-[calc(100vh-20rem)] overflow-y-auto lg:max-h-[calc(100vh-24rem)]"
                        >
                            <div class="flex gap-2">
                                <!-- Elevation View -->
                                <RackElevationView
                                    face="rear"
                                    :u-height="rackHeight"
                                    :devices="placedDevices"
                                    :dragged-device="draggedDevice"
                                    :can-place-at="canPlaceAt"
                                    class="flex-1"
                                    @device-click="handleDeviceClick"
                                    @device-drop="handleDeviceDrop"
                                    @device-drag-start="handleDragStart"
                                    @device-drag-end="handleDragEnd"
                                />

                                <!-- Connection overlay for rear view -->
                                <RackConnectionOverlay
                                    v-if="showConnections"
                                    :rack-id="rackId"
                                    :u-height="rackHeight"
                                    :devices="placedDevices"
                                    face="rear"
                                />
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Back to Rack -->
            <div class="shrink-0">
                <Link
                    :href="
                        RackController.show.url({
                            datacenter: datacenter.id,
                            room: room.id,
                            row: row.id,
                            rack: rack.id,
                        })
                    "
                >
                    <Button variant="outline">Back to Rack</Button>
                </Link>
            </div>
        </div>

        <!-- Device Detail Modal -->
        <DeviceDetailModal
            v-model:open="isDeviceModalOpen"
            :device="selectedDevice"
            @remove="handleRemoveDevice"
            @view-details="navigateToDeviceDetails"
        />

        <!-- Feature Tour for Rack Elevation View -->
        <FeatureTour context-key="racks.elevation" :auto-start="true" />
    </AppLayout>
</template>
