<script setup lang="ts">
import RackController from '@/actions/App/Http/Controllers/RackController';
import { cn } from '@/lib/utils';
import type { PlaceholderDevice, RackData } from '@/types/rooms';
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    /** The rack data containing u_height */
    rack: RackData;
    /** Devices placed in the rack */
    devices: PlaceholderDevice[];
    /** Datacenter ID for navigation */
    datacenterId: number;
    /** Room ID for navigation */
    roomId: number;
    /** Row ID for navigation */
    rowId: number;
}

const props = defineProps<Props>();

/**
 * Calculate the height for the preview based on U-height
 * Target ~400-500px max height, scaling down for smaller racks
 */
const previewHeight = computed(() => {
    const uHeight = props.rack.u_height ?? 42;
    // Scale: min 200px for small racks, max 480px for 48U
    const minHeight = 200;
    const maxHeight = 480;
    const scaledHeight = Math.min(maxHeight, Math.max(minHeight, uHeight * 10));
    return scaledHeight;
});

/**
 * Calculate slot height based on preview height and U-count
 */
const slotHeight = computed(() => {
    const uHeight = props.rack.u_height ?? 42;
    // Account for gaps between slots (1px gap per slot)
    const totalGaps = uHeight - 1;
    return (previewHeight.value - totalGaps) / uHeight;
});

/**
 * Generate U-slots from highest to lowest (visual top to bottom)
 */
const uSlots = computed(() => {
    const uHeight = props.rack.u_height ?? 42;
    const slots: number[] = [];
    for (let i = uHeight; i >= 1; i--) {
        slots.push(i);
    }
    return slots;
});

/**
 * Create a map of U positions to devices for quick lookup
 * Only stores the device at its starting position
 */
const deviceStartMap = computed(() => {
    const map = new Map<number, PlaceholderDevice>();
    for (const device of props.devices) {
        if (device.start_u !== undefined) {
            map.set(device.start_u, device);
        }
    }
    return map;
});

/**
 * Create a map of all occupied U positions
 */
const occupiedSlots = computed(() => {
    const occupied = new Set<number>();
    for (const device of props.devices) {
        if (device.start_u !== undefined) {
            for (
                let u = device.start_u;
                u < device.start_u + device.u_size;
                u++
            ) {
                occupied.add(u);
            }
        }
    }
    return occupied;
});

/**
 * Check if a U position is the start of a device
 */
function isDeviceStart(uNumber: number): boolean {
    return deviceStartMap.value.has(uNumber);
}

/**
 * Check if a U position is occupied but not a device start (middle of multi-U device)
 */
function isPartOfDevice(uNumber: number): boolean {
    return (
        occupiedSlots.value.has(uNumber) && !deviceStartMap.value.has(uNumber)
    );
}

/**
 * Get the device at a starting U position
 */
function getDeviceAt(uNumber: number): PlaceholderDevice | undefined {
    return deviceStartMap.value.get(uNumber);
}

/**
 * Calculate device block height based on U-size
 */
function getDeviceHeight(device: PlaceholderDevice): number {
    // Height = slots + gaps between them
    return device.u_size * slotHeight.value + (device.u_size - 1);
}

/**
 * Get background color class based on device type
 * Matches DeviceBlock.vue badge variants
 */
function getDeviceColorClass(device: PlaceholderDevice): string {
    switch (device.type.toLowerCase()) {
        case 'server':
            return 'bg-primary text-primary-foreground';
        case 'storage':
            return 'bg-secondary text-secondary-foreground';
        case 'switch':
            return 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300';
        case 'ups':
        case 'pdu':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300';
        default:
            return 'bg-muted text-muted-foreground border border-border';
    }
}

/**
 * Navigate to the full elevation view when clicked
 */
function navigateToElevation(): void {
    const url = RackController.elevation.url({
        datacenter: props.datacenterId,
        room: props.roomId,
        row: props.rowId,
        rack: props.rack.id,
    });
    router.visit(url);
}

/**
 * Show U label only for certain U positions to avoid clutter
 * Show U1, U10, U20, etc. and the max U
 */
function shouldShowULabel(uNumber: number): boolean {
    const uHeight = props.rack.u_height ?? 42;
    if (uNumber === 1 || uNumber === uHeight) {
        return true;
    }
    // Show every 10th U for medium/large racks
    if (uHeight >= 20 && uNumber % 10 === 0) {
        return true;
    }
    // Show every 5th U for smaller racks
    if (uHeight < 20 && uNumber % 5 === 0) {
        return true;
    }
    return false;
}
</script>

<template>
    <div
        data-testid="mini-elevation-preview"
        :class="
            cn(
                'relative w-[300px] cursor-pointer rounded-lg border bg-card p-2 shadow-sm',
                'transition-all duration-200 hover:shadow-md hover:ring-2 hover:ring-primary/30',
            )
        "
        role="button"
        tabindex="0"
        aria-label="Click to view full elevation diagram"
        @click="navigateToElevation"
        @keydown.enter="navigateToElevation"
        @keydown.space.prevent="navigateToElevation"
    >
        <!-- Rack frame -->
        <div
            class="relative flex overflow-hidden rounded border border-muted-foreground/20 bg-muted/30"
            :style="{ height: `${previewHeight}px` }"
        >
            <!-- U labels column -->
            <div
                class="flex w-8 flex-col justify-between py-1 text-[9px] text-muted-foreground"
            >
                <template v-for="uNumber in uSlots" :key="`label-${uNumber}`">
                    <div
                        v-if="shouldShowULabel(uNumber)"
                        class="flex items-center justify-end pr-1"
                        :style="{ height: `${slotHeight}px` }"
                    >
                        U{{ uNumber }}
                    </div>
                    <div v-else :style="{ height: `${slotHeight}px` }" />
                </template>
            </div>

            <!-- Rack slots container -->
            <div class="flex flex-1 flex-col gap-px py-1 pr-1">
                <template v-for="uNumber in uSlots" :key="`slot-${uNumber}`">
                    <!-- Skip slots that are part of a multi-U device -->
                    <template v-if="!isPartOfDevice(uNumber)">
                        <!-- Device block -->
                        <div
                            v-if="isDeviceStart(uNumber)"
                            :class="
                                cn(
                                    'flex items-center justify-center rounded-sm px-1 text-[9px] font-medium',
                                    getDeviceColorClass(getDeviceAt(uNumber)!),
                                )
                            "
                            :style="{
                                height: `${getDeviceHeight(getDeviceAt(uNumber)!)}px`,
                            }"
                            :title="`${getDeviceAt(uNumber)!.name} (${getDeviceAt(uNumber)!.u_size}U at U${uNumber})`"
                        >
                            <span class="truncate">{{
                                getDeviceAt(uNumber)!.name
                            }}</span>
                        </div>

                        <!-- Empty slot -->
                        <div
                            v-else
                            class="rounded-sm bg-muted/50"
                            :style="{ height: `${slotHeight}px` }"
                        />
                    </template>
                </template>
            </div>
        </div>

        <!-- Click hint -->
        <p class="mt-2 text-center text-xs text-muted-foreground">
            Click to view full elevation
        </p>
    </div>
</template>
