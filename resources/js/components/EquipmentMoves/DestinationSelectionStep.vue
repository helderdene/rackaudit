<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Skeleton } from '@/components/ui/skeleton';
import {
    MapPin,
    Building2,
    Layers,
    Server,
    AlertTriangle,
    Check,
    X,
} from 'lucide-vue-next';
import {
    useDestinationPicker,
    type LocationHierarchy,
    type PlacedDevice,
} from '@/composables/useDestinationPicker';
import type { DeviceData, DeviceWidth, RackFace } from '@/types/rooms';
import { cn } from '@/lib/utils';

interface Props {
    device: DeviceData | null;
    locationHierarchy?: LocationHierarchy;
    initialDestination?: {
        destination_rack_id: number | null;
        destination_start_u: number | null;
        destination_rack_face: string;
        destination_width_type: string;
    };
}

const props = defineProps<Props>();

const emit = defineEmits<{
    destinationChanged: [
        data: {
            destination_rack_id: number | null;
            destination_start_u: number | null;
            destination_rack_face: string;
            destination_width_type: string;
        },
    ];
}>();

// Device reference for destination picker
const deviceRef = ref(
    props.device
        ? { id: props.device.id, u_height: props.device.u_height }
        : null,
);

// Use destination picker composable
const {
    selection,
    hierarchy,
    selectedRack,
    isLoading,
    error,
    filteredRooms,
    filteredRows,
    filteredRacks,
    utilizationStats,
    setDatacenter,
    setRoom,
    setRow,
    setRack,
    setPosition,
    setRackFace,
    setWidthType,
    canPlaceAt,
    getValidDropPositions,
} = useDestinationPicker(props.locationHierarchy, deviceRef);

// Face and width options
const faceOptions: { value: RackFace; label: string }[] = [
    { value: 'front', label: 'Front' },
    { value: 'rear', label: 'Rear' },
];

const widthOptions: { value: DeviceWidth; label: string }[] = [
    { value: 'full', label: 'Full Width' },
    { value: 'half-left', label: 'Half Left' },
    { value: 'half-right', label: 'Half Right' },
];

// Watch device prop changes
watch(
    () => props.device,
    (newDevice) => {
        if (newDevice) {
            deviceRef.value = { id: newDevice.id, u_height: newDevice.u_height };
        }
    },
    { immediate: true },
);

// Emit destination changes
watch(
    selection,
    (newSelection) => {
        emit('destinationChanged', {
            destination_rack_id: newSelection.rackId,
            destination_start_u: newSelection.startU,
            destination_rack_face: newSelection.rackFace,
            destination_width_type: mapWidthToBackend(newSelection.widthType),
        });
    },
    { deep: true },
);

// Initialize from props
onMounted(async () => {
    if (props.initialDestination?.destination_rack_id && props.locationHierarchy) {
        // Find the rack in hierarchy and set selections
        const rack = props.locationHierarchy.racks.find(
            (r) => r.id === props.initialDestination!.destination_rack_id,
        );
        if (rack) {
            const row = props.locationHierarchy.rows.find((r) => r.id === rack.row_id);
            const room = row
                ? props.locationHierarchy.rooms.find((r) => r.id === row.room_id)
                : null;
            const datacenter = room
                ? props.locationHierarchy.datacenters.find((d) => d.id === room.datacenter_id)
                : null;

            if (datacenter) {
                setDatacenter(datacenter.id);
            }
            if (room) {
                setRoom(room.id);
            }
            if (row) {
                setRow(row.id);
            }
            await setRack(rack.id);

            if (props.initialDestination.destination_start_u) {
                setPosition(props.initialDestination.destination_start_u);
            }
            if (props.initialDestination.destination_rack_face) {
                setRackFace(props.initialDestination.destination_rack_face as RackFace);
            }
            if (props.initialDestination.destination_width_type) {
                setWidthType(mapWidthFromBackend(props.initialDestination.destination_width_type));
            }
        }
    }
});

/**
 * Map frontend DeviceWidth to backend format
 */
function mapWidthToBackend(width: DeviceWidth): string {
    switch (width) {
        case 'half-left':
            return 'half_left';
        case 'half-right':
            return 'half_right';
        default:
            return 'full';
    }
}

/**
 * Map backend width_type to frontend DeviceWidth
 */
function mapWidthFromBackend(widthType: string): DeviceWidth {
    switch (widthType) {
        case 'half_left':
            return 'half-left';
        case 'half_right':
            return 'half-right';
        default:
            return 'full';
    }
}

/**
 * Get valid positions for U position picker
 */
const validPositions = computed(() => {
    if (!props.device) return [];
    return getValidDropPositions(props.device.u_height);
});

/**
 * Generate U slots for visual picker (high to low)
 */
const uSlots = computed(() => {
    if (!selectedRack.value) return [];
    const slots: number[] = [];
    for (let i = selectedRack.value.u_height; i >= 1; i--) {
        slots.push(i);
    }
    return slots;
});

/**
 * Check if a U position is valid for placement
 */
function isPositionValid(startU: number): boolean {
    if (!props.device) return false;
    return canPlaceAt(startU, selection.value.rackFace, selection.value.widthType, props.device.u_height);
}

/**
 * Check if a U position is occupied
 */
function isPositionOccupied(uNumber: number): PlacedDevice | null {
    if (!selectedRack.value) return null;

    return selectedRack.value.devices.find((d) => {
        if (d.rack_face !== selection.value.rackFace) return false;
        return uNumber >= d.start_u && uNumber < d.start_u + d.u_height;
    }) || null;
}

/**
 * Check if the device being moved is the current source rack
 */
const isSourceRack = computed(() => {
    return props.device?.rack?.id === selection.value.rackId;
});

/**
 * Handle U position click
 */
function handlePositionClick(startU: number): void {
    if (isPositionValid(startU)) {
        setPosition(startU);
    }
}

/**
 * Handle datacenter change
 */
function handleDatacenterChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    setDatacenter(value ? parseInt(value, 10) : null);
}

/**
 * Handle room change
 */
function handleRoomChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    setRoom(value ? parseInt(value, 10) : null);
}

/**
 * Handle row change
 */
function handleRowChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    setRow(value ? parseInt(value, 10) : null);
}

/**
 * Handle rack change
 */
async function handleRackChange(event: Event): Promise<void> {
    const value = (event.target as HTMLSelectElement).value;
    await setRack(value ? parseInt(value, 10) : null);
}

/**
 * Handle face change
 */
function handleFaceChange(face: RackFace): void {
    setRackFace(face);
}

/**
 * Handle width change
 */
function handleWidthChange(width: DeviceWidth): void {
    setWidthType(width);
}
</script>

<template>
    <div class="space-y-6">
        <div>
            <h3 class="text-lg font-medium">Select Destination</h3>
            <p class="text-sm text-muted-foreground">
                Choose the destination rack and U position for the device.
            </p>
        </div>

        <!-- Error Alert -->
        <Alert v-if="error" variant="destructive">
            <AlertTriangle class="h-4 w-4" />
            <AlertDescription>{{ error }}</AlertDescription>
        </Alert>

        <!-- Location Hierarchy Selection -->
        <div class="grid gap-4 sm:grid-cols-2">
            <!-- Datacenter -->
            <div class="space-y-2">
                <Label for="datacenter-select">
                    <Building2 class="mr-1 inline h-4 w-4" />
                    Datacenter
                </Label>
                <select
                    id="datacenter-select"
                    :value="selection.datacenterId ?? ''"
                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    @change="handleDatacenterChange"
                >
                    <option value="">Select datacenter...</option>
                    <option
                        v-for="dc in hierarchy.datacenters"
                        :key="dc.id"
                        :value="dc.id"
                    >
                        {{ dc.name }}
                    </option>
                </select>
            </div>

            <!-- Room -->
            <div class="space-y-2">
                <Label for="room-select">Room</Label>
                <select
                    id="room-select"
                    :value="selection.roomId ?? ''"
                    :disabled="!selection.datacenterId"
                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:opacity-50"
                    @change="handleRoomChange"
                >
                    <option value="">Select room...</option>
                    <option v-for="room in filteredRooms" :key="room.id" :value="room.id">
                        {{ room.name }}
                    </option>
                </select>
            </div>

            <!-- Row -->
            <div class="space-y-2">
                <Label for="row-select">Row</Label>
                <select
                    id="row-select"
                    :value="selection.rowId ?? ''"
                    :disabled="!selection.roomId"
                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:opacity-50"
                    @change="handleRowChange"
                >
                    <option value="">Select row...</option>
                    <option v-for="row in filteredRows" :key="row.id" :value="row.id">
                        {{ row.name }}
                    </option>
                </select>
            </div>

            <!-- Rack -->
            <div class="space-y-2">
                <Label for="rack-select">
                    <Server class="mr-1 inline h-4 w-4" />
                    Rack
                </Label>
                <select
                    id="rack-select"
                    :value="selection.rackId ?? ''"
                    :disabled="!selection.rowId"
                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:opacity-50"
                    @change="handleRackChange"
                >
                    <option value="">Select rack...</option>
                    <option v-for="rack in filteredRacks" :key="rack.id" :value="rack.id">
                        {{ rack.name }} ({{ rack.u_height }}U)
                    </option>
                </select>
            </div>
        </div>

        <!-- Rack Details and Position Picker -->
        <div v-if="selection.rackId" class="space-y-4">
            <!-- Same Rack Warning -->
            <Alert v-if="isSourceRack">
                <MapPin class="h-4 w-4" />
                <AlertDescription>
                    This is the device's current rack. You can move it to a different U position
                    within the same rack (intra-rack move).
                </AlertDescription>
            </Alert>

            <!-- Loading State -->
            <div v-if="isLoading" class="space-y-4">
                <Skeleton class="h-20 w-full" />
                <Skeleton class="h-48 w-full" />
            </div>

            <template v-else-if="selectedRack">
                <!-- Utilization Stats -->
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm">Rack Utilization</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-center gap-4">
                            <div class="flex-1">
                                <div class="mb-1 flex justify-between text-xs">
                                    <span>{{ utilizationStats?.usedU }}U used</span>
                                    <span>{{ utilizationStats?.availableU }}U available</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-muted">
                                    <div
                                        class="h-full bg-primary transition-all"
                                        :style="{ width: `${utilizationStats?.utilizationPercent ?? 0}%` }"
                                    />
                                </div>
                            </div>
                            <Badge variant="outline">
                                {{ Math.round(utilizationStats?.utilizationPercent ?? 0) }}%
                            </Badge>
                        </div>
                    </CardContent>
                </Card>

                <!-- Face and Width Selection -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label>Rack Face</Label>
                        <div class="flex gap-2">
                            <button
                                v-for="option in faceOptions"
                                :key="option.value"
                                type="button"
                                :class="cn(
                                    'flex-1 rounded-md border px-3 py-2 text-sm font-medium transition-colors',
                                    selection.rackFace === option.value
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-input hover:bg-muted'
                                )"
                                @click="handleFaceChange(option.value)"
                            >
                                {{ option.label }}
                            </button>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <Label>Width Type</Label>
                        <div class="flex gap-2">
                            <button
                                v-for="option in widthOptions"
                                :key="option.value"
                                type="button"
                                :class="cn(
                                    'flex-1 rounded-md border px-3 py-2 text-sm font-medium transition-colors',
                                    selection.widthType === option.value
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-input hover:bg-muted'
                                )"
                                @click="handleWidthChange(option.value)"
                            >
                                {{ option.label }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- U Position Picker -->
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center justify-between text-sm">
                            <span>Select U Position</span>
                            <span v-if="selection.startU" class="font-normal text-muted-foreground">
                                Selected: U{{ selection.startU }} - U{{ selection.startU + (device?.u_height ?? 1) - 1 }}
                            </span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="max-h-64 overflow-y-auto rounded border">
                            <div class="flex flex-col gap-0.5 p-1">
                                <button
                                    v-for="uNumber in uSlots"
                                    :key="uNumber"
                                    type="button"
                                    :class="cn(
                                        'flex h-7 items-center gap-2 rounded px-2 text-sm transition-colors',
                                        isPositionOccupied(uNumber)
                                            ? 'bg-muted/50 text-muted-foreground cursor-not-allowed'
                                            : isPositionValid(uNumber)
                                              ? selection.startU === uNumber
                                                ? 'bg-primary text-primary-foreground'
                                                : selection.startU && uNumber > selection.startU && uNumber < selection.startU + (device?.u_height ?? 1)
                                                  ? 'bg-primary/50 text-primary-foreground'
                                                  : 'hover:bg-muted cursor-pointer'
                                              : 'bg-red-50 dark:bg-red-950/20 text-muted-foreground cursor-not-allowed'
                                    )"
                                    :disabled="!isPositionValid(uNumber)"
                                    @click="handlePositionClick(uNumber)"
                                >
                                    <span class="w-8 font-mono text-xs">U{{ uNumber }}</span>
                                    <span v-if="isPositionOccupied(uNumber)" class="flex-1 truncate text-xs">
                                        {{ isPositionOccupied(uNumber)?.name }}
                                    </span>
                                    <Check
                                        v-if="selection.startU === uNumber"
                                        class="ml-auto h-4 w-4"
                                    />
                                    <X
                                        v-else-if="!isPositionValid(uNumber) && !isPositionOccupied(uNumber)"
                                        class="ml-auto h-4 w-4 text-red-500"
                                    />
                                </button>
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-muted-foreground">
                            Device requires {{ device?.u_height ?? 1 }}U.
                            Click on an available position to select.
                        </p>
                    </CardContent>
                </Card>
            </template>
        </div>

        <!-- No Rack Selected -->
        <div
            v-else
            class="rounded-lg border border-dashed p-8 text-center"
        >
            <Layers class="mx-auto h-12 w-12 text-muted-foreground/50" />
            <p class="mt-4 text-sm text-muted-foreground">
                Select a datacenter, room, row, and rack to view available positions.
            </p>
        </div>
    </div>
</template>
