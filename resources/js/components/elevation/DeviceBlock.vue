<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { PlaceholderDevice } from '@/types/rooms';
import { Server, HardDrive, Network, Monitor, Zap, Cable, Box } from 'lucide-vue-next';

interface Props {
    /** The device to display */
    device: PlaceholderDevice;
    /** Whether this device is currently placed in the rack */
    isPlaced?: boolean;
    /** Height of a single U slot in pixels */
    slotHeight?: number;
    /** Additional CSS classes */
    class?: HTMLAttributes['class'];
}

const props = withDefaults(defineProps<Props>(), {
    isPlaced: false,
    slotHeight: 28, // Default matches USlot h-7 (28px)
});

const emit = defineEmits<{
    (e: 'deviceClick', device: PlaceholderDevice): void;
}>();

/**
 * Calculate the height based on device U-size
 */
const blockHeight = computed(() => {
    // Each U is slotHeight px, plus account for gap between slots (4px)
    const gapSize = 4;
    return props.device.u_size * props.slotHeight + (props.device.u_size - 1) * gapSize;
});

/**
 * Get width class based on device width property
 */
const widthClass = computed(() => {
    switch (props.device.width) {
        case 'half-left':
            return 'w-1/2';
        case 'half-right':
            return 'ml-auto w-1/2';
        case 'full':
        default:
            return 'w-full';
    }
});

/**
 * Get badge variant based on device type
 */
const badgeVariant = computed((): 'default' | 'secondary' | 'outline' | 'success' | 'warning' => {
    switch (props.device.type) {
        case 'server':
            return 'default';
        case 'storage':
            return 'secondary';
        case 'switch':
            return 'success';
        case 'ups':
        case 'pdu':
            return 'warning';
        default:
            return 'outline';
    }
});

/**
 * Get icon component based on device type
 */
const deviceIcon = computed(() => {
    switch (props.device.type) {
        case 'server':
            return Server;
        case 'storage':
            return HardDrive;
        case 'switch':
            return Network;
        case 'kvm':
        case 'console':
            return Monitor;
        case 'ups':
        case 'pdu':
            return Zap;
        case 'patch-panel':
            return Cable;
        default:
            return Box;
    }
});

/**
 * Handle device click - emit event to parent
 */
function handleClick() {
    emit('deviceClick', props.device);
}
</script>

<template>
    <div
        :class="cn(
            'flex cursor-pointer rounded border bg-card px-2 shadow-sm transition-all hover:shadow-md',
            device.u_size === 1 ? 'flex-row items-center gap-2' : 'flex-col justify-center py-1',
            widthClass,
            isPlaced && 'hover:ring-2 hover:ring-primary/50',
            props.class
        )"
        :style="{ height: `${blockHeight}px` }"
        @click="handleClick"
    >
        <!-- 1U compact single-row layout -->
        <template v-if="device.u_size === 1">
            <component :is="deviceIcon" class="size-3.5 shrink-0 text-muted-foreground" />
            <span class="min-w-0 flex-1 truncate text-xs font-medium">{{ device.name }}</span>
        </template>

        <!-- Multi-U layout with stacked content -->
        <template v-else>
            <!-- Device header with icon and name -->
            <div class="flex items-center gap-1.5 overflow-hidden">
                <component :is="deviceIcon" class="size-3.5 shrink-0 text-muted-foreground" />
                <span class="truncate text-xs font-medium">{{ device.name }}</span>
            </div>

            <!-- Device info: type badge and U-size -->
            <div class="mt-1 flex items-center gap-1.5">
                <Badge :variant="badgeVariant" class="text-[10px] px-1.5 py-0">
                    {{ device.type }}
                </Badge>
                <span class="text-[10px] text-muted-foreground">{{ device.u_size }}U</span>
            </div>
        </template>
    </div>
</template>
