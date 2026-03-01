<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { PlaceholderDevice } from '@/types/rooms';
import {
    Box,
    Cable,
    ExternalLink,
    HardDrive,
    Monitor,
    Network,
    Server,
    Zap,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    /** The device to display details for */
    device: PlaceholderDevice | null;
    /** Whether the modal is open */
    open: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'remove', device: PlaceholderDevice): void;
    (e: 'viewDetails', device: PlaceholderDevice): void;
}>();

/**
 * Get badge variant based on device type
 */
const badgeVariant = computed(
    (): 'default' | 'secondary' | 'outline' | 'success' | 'warning' => {
        if (!props.device) return 'outline';
        switch (props.device.type.toLowerCase()) {
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
    },
);

/**
 * Get icon component based on device type
 */
const deviceIcon = computed(() => {
    if (!props.device) return Box;
    switch (props.device.type.toLowerCase()) {
        case 'server':
            return Server;
        case 'storage':
            return HardDrive;
        case 'switch':
            return Network;
        case 'kvm':
        case 'console server':
            return Monitor;
        case 'ups':
        case 'pdu':
            return Zap;
        case 'patch panel':
            return Cable;
        default:
            return Box;
    }
});

/**
 * Get human-readable face label
 */
const faceLabel = computed(() => {
    if (!props.device?.face) return 'Not placed';
    return props.device.face === 'front' ? 'Front' : 'Rear';
});

/**
 * Get human-readable width label
 */
const widthLabel = computed(() => {
    if (!props.device) return '';
    switch (props.device.width) {
        case 'half-left':
            return 'Half-width (Left)';
        case 'half-right':
            return 'Half-width (Right)';
        case 'full':
        default:
            return 'Full-width';
    }
});

function handleClose() {
    emit('update:open', false);
}

function handleRemove() {
    if (props.device) {
        emit('remove', props.device);
        emit('update:open', false);
    }
}

function handleViewDetails() {
    if (props.device) {
        emit('viewDetails', props.device);
        emit('update:open', false);
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <component :is="deviceIcon" class="size-5" />
                    {{ device?.name || 'Device Details' }}
                </DialogTitle>
                <DialogDescription>
                    Device information and placement details
                </DialogDescription>
            </DialogHeader>

            <div v-if="device" class="space-y-4 py-4">
                <!-- Device Type -->
                <div class="flex items-center justify-between">
                    <span class="text-sm text-muted-foreground">Type</span>
                    <Badge :variant="badgeVariant">{{ device.type }}</Badge>
                </div>

                <!-- U-Size -->
                <div class="flex items-center justify-between">
                    <span class="text-sm text-muted-foreground">Size</span>
                    <span class="text-sm font-medium"
                        >{{ device.u_size }}U</span
                    >
                </div>

                <!-- Width -->
                <div class="flex items-center justify-between">
                    <span class="text-sm text-muted-foreground">Width</span>
                    <span class="text-sm font-medium">{{ widthLabel }}</span>
                </div>

                <!-- Position (if placed) -->
                <template v-if="device.start_u !== undefined && device.face">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-muted-foreground"
                            >Position</span
                        >
                        <span class="text-sm font-medium">
                            U{{ device.start_u }}
                            <template v-if="device.u_size > 1">
                                - U{{ device.start_u + device.u_size - 1 }}
                            </template>
                        </span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm text-muted-foreground">Face</span>
                        <span class="text-sm font-medium">{{ faceLabel }}</span>
                    </div>
                </template>

                <!-- Not placed indicator -->
                <div
                    v-else
                    class="rounded-md bg-muted p-3 text-center text-sm text-muted-foreground"
                >
                    This device has not been placed in the rack yet.
                </div>

                <!-- Device ID (for reference) -->
                <div class="flex items-center justify-between border-t pt-4">
                    <span class="text-sm text-muted-foreground">ID</span>
                    <code class="text-xs text-muted-foreground">{{
                        device.id
                    }}</code>
                </div>
            </div>

            <DialogFooter class="flex-col gap-2 sm:flex-row">
                <Button
                    variant="default"
                    size="sm"
                    class="gap-2"
                    @click="handleViewDetails"
                >
                    <ExternalLink class="size-4" />
                    View Full Details
                </Button>
                <Button
                    v-if="device?.start_u !== undefined"
                    variant="destructive"
                    size="sm"
                    @click="handleRemove"
                >
                    Remove from Rack
                </Button>
                <Button variant="outline" size="sm" @click="handleClose">
                    Close
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
