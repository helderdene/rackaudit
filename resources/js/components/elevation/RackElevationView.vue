<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { DeviceWidth, PlaceholderDevice, RackFace } from '@/types/rooms';
import type { HTMLAttributes } from 'vue';
import { computed, ref } from 'vue';
import DeviceBlock from './DeviceBlock.vue';
import USlot from './USlot.vue';

interface Props {
    /** Which face of the rack to display */
    face: RackFace;
    /** Total rack height in U */
    uHeight: number;
    /** Devices placed on this face of the rack */
    devices: PlaceholderDevice[];
    /** Height of a single U slot in pixels */
    slotHeight?: number;
    /** The device currently being dragged (from sidebar or elsewhere) */
    draggedDevice?: PlaceholderDevice | null;
    /** Function to check if a device can be placed at a specific position */
    canPlaceAt?: (
        device: PlaceholderDevice,
        startU: number,
        face: RackFace,
        width?: DeviceWidth,
    ) => boolean;
    /** Additional CSS classes */
    class?: HTMLAttributes['class'];
}

const props = withDefaults(defineProps<Props>(), {
    slotHeight: 28, // Default h-7
    draggedDevice: null,
    canPlaceAt: () => true,
});

const emit = defineEmits<{
    (e: 'slotClick', uNumber: number, face: RackFace): void;
    (e: 'deviceClick', device: PlaceholderDevice): void;
    (
        e: 'deviceDrop',
        device: PlaceholderDevice,
        startU: number,
        face: RackFace,
        width: DeviceWidth,
    ): void;
    (
        e: 'deviceMove',
        device: PlaceholderDevice,
        startU: number,
        face: RackFace,
    ): void;
    (e: 'dragEnter', uNumber: number, face: RackFace): void;
    (e: 'dragLeave', uNumber: number, face: RackFace): void;
    (e: 'deviceDragStart', device: PlaceholderDevice): void;
    (e: 'deviceDragEnd'): void;
}>();

// Track which U slot is currently being hovered during drag
const hoveredUSlot = ref<number | null>(null);

/**
 * Generate U-slots from uHeight (highest at top, U1 at bottom)
 */
const uSlots = computed(() => {
    const slots: number[] = [];
    for (let i = props.uHeight; i >= 1; i--) {
        slots.push(i);
    }
    return slots;
});

/**
 * Get devices filtered by this rack face
 */
const faceDevices = computed(() => {
    return props.devices.filter((d) => d.face === props.face);
});

/**
 * Create a map of U positions to devices for quick lookup
 * Key is the U number, value is the device occupying that U
 * For multi-U devices, each occupied U maps to the same device
 */
const deviceMap = computed(() => {
    const map = new Map<number, PlaceholderDevice>();
    for (const device of faceDevices.value) {
        if (device.start_u !== undefined) {
            // Mark all U positions this device occupies
            for (
                let u = device.start_u;
                u < device.start_u + device.u_size;
                u++
            ) {
                map.set(u, device);
            }
        }
    }
    return map;
});

/**
 * Get devices grouped by position for a U slot
 * Returns separate arrays for full-width, half-left, and half-right devices
 */
function getDevicesAtU(uNumber: number): {
    full: PlaceholderDevice | undefined;
    halfLeft: PlaceholderDevice | undefined;
    halfRight: PlaceholderDevice | undefined;
} {
    const devicesStartingHere = faceDevices.value.filter(
        (d) => d.start_u === uNumber,
    );

    return {
        full: devicesStartingHere.find((d) => d.width === 'full'),
        halfLeft: devicesStartingHere.find((d) => d.width === 'half-left'),
        halfRight: devicesStartingHere.find((d) => d.width === 'half-right'),
    };
}

/**
 * Check if this U slot is the start of a multi-U device
 * (Used to render the device block only once at its starting position)
 */
function isDeviceStart(uNumber: number): boolean {
    return faceDevices.value.some((d) => d.start_u === uNumber);
}

/**
 * Check if this U slot is part of a multi-U device but not the start
 * (Used to hide slots that are covered by a spanning device)
 */
function isPartOfMultiUDevice(uNumber: number): boolean {
    const device = deviceMap.value.get(uNumber);
    if (!device) {
        return false;
    }
    return device.start_u !== uNumber;
}

/**
 * Check if a specific U slot is a valid drop target for the current dragged device
 */
function isValidDropTarget(uNumber: number, width?: DeviceWidth): boolean {
    if (!props.draggedDevice) {
        return false;
    }
    return props.canPlaceAt(
        props.draggedDevice,
        uNumber,
        props.face,
        width || props.draggedDevice.width,
    );
}

/**
 * Check if the U slot is currently being targeted during drag
 */
function isDropTarget(uNumber: number): boolean {
    return hoveredUSlot.value === uNumber && props.draggedDevice !== null;
}

/**
 * Handle drag enter on a slot
 */
function handleDragEnter(uNumber: number) {
    hoveredUSlot.value = uNumber;
    emit('dragEnter', uNumber, props.face);
}

/**
 * Handle drag leave on a slot - with check for child elements
 */
function handleDragLeave(uNumber: number, event?: DragEvent) {
    // If event is provided, check if we're actually leaving the element
    if (event) {
        const target = event.currentTarget as HTMLElement;
        const relatedTarget = event.relatedTarget as Node | null;
        // Don't clear if moving to a child element
        if (relatedTarget && target.contains(relatedTarget)) {
            return;
        }
    }

    if (hoveredUSlot.value === uNumber) {
        hoveredUSlot.value = null;
    }
    emit('dragLeave', uNumber, props.face);
}

/**
 * Handle drop on a slot
 */
function handleDrop(uNumber: number, _width: DeviceWidth) {
    if (!props.draggedDevice) {
        hoveredUSlot.value = null;
        return;
    }

    // Always use the device's own width to preserve half-width devices
    const effectiveWidth = props.draggedDevice.width;
    const isValid = isValidDropTarget(uNumber, effectiveWidth);

    if (isValid) {
        emit(
            'deviceDrop',
            props.draggedDevice,
            uNumber,
            props.face,
            effectiveWidth,
        );
    }
    hoveredUSlot.value = null;
}

/**
 * Handle dragover event to enable drop
 */
function handleDragOver(event: DragEvent) {
    // Set drop effect based on whether we have a dragged device
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = props.draggedDevice ? 'copy' : 'none';
    }
}

/**
 * Handle drag start on a placed device
 */
function handleDeviceDragStart(event: DragEvent, device: PlaceholderDevice) {
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'all';
        event.dataTransfer.setData('text/plain', device.id);
    }
    emit('deviceDragStart', device);
}

/**
 * Handle drag end on a placed device
 */
function handleDeviceDragEnd() {
    hoveredUSlot.value = null;
    emit('deviceDragEnd');
}

/**
 * Handle drop on the container
 */
function handleContainerDrop(event: DragEvent) {
    event.preventDefault();
    // Get the target U number from the drop location
    const target = event.target as HTMLElement;
    const uSlotElement = target.closest('[data-u-number]');
    if (uSlotElement) {
        const uNumber = parseInt(
            uSlotElement.getAttribute('data-u-number') || '0',
            10,
        );
        if (uNumber > 0 && props.draggedDevice) {
            handleDrop(uNumber, props.draggedDevice.width);
        }
    }
}
</script>

<template>
    <div
        :class="cn('flex flex-col gap-1', props.class)"
        @dragover.prevent="handleDragOver"
        @dragenter.prevent
        @drop.prevent="handleContainerDrop"
    >
        <!-- U-slots rendered top to bottom, with highest U at top, U1 at bottom -->
        <template v-for="uNumber in uSlots" :key="uNumber">
            <!-- Skip slots that are covered by a multi-U device from below -->
            <template v-if="!isPartOfMultiUDevice(uNumber)">
                <!-- Slot with device(s) starting here -->
                <template v-if="isDeviceStart(uNumber)">
                    <div
                        class="relative"
                        :data-u-number="uNumber"
                        @dragover.prevent="handleDragEnter(uNumber)"
                        @dragenter.prevent="handleDragEnter(uNumber)"
                        @dragleave="handleDragLeave(uNumber, $event)"
                        @drop.prevent="
                            handleDrop(
                                uNumber,
                                props.draggedDevice?.width || 'full',
                            )
                        "
                    >
                        <!-- U label for reference -->
                        <span
                            class="absolute top-1/2 left-2 z-10 -translate-y-1/2 text-xs font-medium text-muted-foreground"
                        >
                            U{{ uNumber }}
                        </span>

                        <!-- Device container -->
                        <div data-tour="device-slot" class="ml-12 flex gap-1">
                            <!-- Full-width device -->
                            <template v-if="getDevicesAtU(uNumber).full">
                                <DeviceBlock
                                    :device="getDevicesAtU(uNumber).full!"
                                    :is-placed="true"
                                    :slot-height="slotHeight"
                                    class="cursor-grab active:cursor-grabbing"
                                    draggable="true"
                                    @device-click="$emit('deviceClick', $event)"
                                    @dragstart="
                                        handleDeviceDragStart(
                                            $event,
                                            getDevicesAtU(uNumber).full!,
                                        )
                                    "
                                    @dragend="handleDeviceDragEnd"
                                />
                            </template>

                            <!-- Half-width devices -->
                            <template v-else>
                                <div class="flex flex-1 gap-1">
                                    <div class="flex-1">
                                        <DeviceBlock
                                            v-if="
                                                getDevicesAtU(uNumber).halfLeft
                                            "
                                            :device="
                                                getDevicesAtU(uNumber).halfLeft!
                                            "
                                            :is-placed="true"
                                            :slot-height="slotHeight"
                                            class="w-full cursor-grab active:cursor-grabbing"
                                            draggable="true"
                                            @device-click="
                                                $emit('deviceClick', $event)
                                            "
                                            @dragstart="
                                                handleDeviceDragStart(
                                                    $event,
                                                    getDevicesAtU(uNumber)
                                                        .halfLeft!,
                                                )
                                            "
                                            @dragend="handleDeviceDragEnd"
                                        />
                                        <div
                                            v-else
                                            :class="
                                                cn(
                                                    'h-7 rounded border border-dashed border-muted-foreground/30 bg-muted/20',
                                                    isDropTarget(uNumber) &&
                                                        isValidDropTarget(
                                                            uNumber,
                                                            'half-left',
                                                        ) &&
                                                        'border-green-500 bg-green-50 dark:bg-green-950/30',
                                                    isDropTarget(uNumber) &&
                                                        !isValidDropTarget(
                                                            uNumber,
                                                            'half-left',
                                                        ) &&
                                                        'border-red-500 bg-red-50 dark:bg-red-950/30',
                                                )
                                            "
                                            @drop.stop="
                                                handleDrop(uNumber, 'half-left')
                                            "
                                            @dragover.prevent="
                                                handleDragEnter(uNumber)
                                            "
                                            @dragenter="
                                                handleDragEnter(uNumber)
                                            "
                                            @dragleave="
                                                handleDragLeave(uNumber, $event)
                                            "
                                        />
                                    </div>
                                    <div class="flex-1">
                                        <DeviceBlock
                                            v-if="
                                                getDevicesAtU(uNumber).halfRight
                                            "
                                            :device="
                                                getDevicesAtU(uNumber)
                                                    .halfRight!
                                            "
                                            :is-placed="true"
                                            :slot-height="slotHeight"
                                            class="w-full cursor-grab active:cursor-grabbing"
                                            draggable="true"
                                            @device-click="
                                                $emit('deviceClick', $event)
                                            "
                                            @dragstart="
                                                handleDeviceDragStart(
                                                    $event,
                                                    getDevicesAtU(uNumber)
                                                        .halfRight!,
                                                )
                                            "
                                            @dragend="handleDeviceDragEnd"
                                        />
                                        <div
                                            v-else
                                            :class="
                                                cn(
                                                    'h-7 rounded border border-dashed border-muted-foreground/30 bg-muted/20',
                                                    isDropTarget(uNumber) &&
                                                        isValidDropTarget(
                                                            uNumber,
                                                            'half-right',
                                                        ) &&
                                                        'border-green-500 bg-green-50 dark:bg-green-950/30',
                                                    isDropTarget(uNumber) &&
                                                        !isValidDropTarget(
                                                            uNumber,
                                                            'half-right',
                                                        ) &&
                                                        'border-red-500 bg-red-50 dark:bg-red-950/30',
                                                )
                                            "
                                            @drop.stop="
                                                handleDrop(
                                                    uNumber,
                                                    'half-right',
                                                )
                                            "
                                            @dragover.prevent="
                                                handleDragEnter(uNumber)
                                            "
                                            @dragenter="
                                                handleDragEnter(uNumber)
                                            "
                                            @dragleave="
                                                handleDragLeave(uNumber, $event)
                                            "
                                        />
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <!-- Empty slot -->
                <template v-else>
                    <USlot
                        :u-number="uNumber"
                        :is-occupied="false"
                        :is-drop-target="isDropTarget(uNumber)"
                        :is-valid-drop="isValidDropTarget(uNumber)"
                        :is-left-half-valid="
                            isValidDropTarget(uNumber, 'half-left')
                        "
                        :is-right-half-valid="
                            isValidDropTarget(uNumber, 'half-right')
                        "
                        :show-drag-preview="
                            isDropTarget(uNumber) && draggedDevice !== null
                        "
                        :drag-preview-height="draggedDevice?.u_size ?? 1"
                        @slot-click="$emit('slotClick', uNumber, face)"
                        @drop="handleDrop"
                        @drag-enter="handleDragEnter"
                        @drag-leave="handleDragLeave"
                    >
                        <template #default>
                            <span class="ml-12 text-xs text-muted-foreground/50"
                                >Empty</span
                            >
                        </template>
                    </USlot>
                </template>
            </template>
        </template>
    </div>
</template>
