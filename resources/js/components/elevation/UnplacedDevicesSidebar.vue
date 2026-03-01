<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { PlaceholderDevice } from '@/types/rooms';
import {
    Box,
    HardDrive,
    Monitor,
    Network,
    Package,
    Search,
    Server,
    Zap,
} from 'lucide-vue-next';
import type { HTMLAttributes } from 'vue';
import { computed, ref } from 'vue';

interface Props {
    /** List of unplaced devices available for placement */
    devices: PlaceholderDevice[];
    /** Additional CSS classes */
    class?: HTMLAttributes['class'];
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'dragStart', device: PlaceholderDevice): void;
    (e: 'dragEnd'): void;
}>();

// Search filter
const searchQuery = ref('');

/**
 * Filter devices based on search query
 */
const filteredDevices = computed(() => {
    if (!searchQuery.value.trim()) {
        return props.devices;
    }

    const query = searchQuery.value.toLowerCase();
    return props.devices.filter(
        (device) =>
            device.name.toLowerCase().includes(query) ||
            device.type.toLowerCase().includes(query),
    );
});

/**
 * Get icon component based on device type
 */
function getDeviceIcon(type: string) {
    switch (type) {
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
        default:
            return Box;
    }
}

/**
 * Get badge variant based on device type
 */
function getBadgeVariant(
    type: string,
): 'default' | 'secondary' | 'outline' | 'success' | 'warning' {
    switch (type) {
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
}

/**
 * Handle drag start event - native HTML5 drag
 */
function handleDragStart(event: DragEvent, device: PlaceholderDevice) {
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'all';
        event.dataTransfer.setData('text/plain', device.id);
    }
    emit('dragStart', device);
}

/**
 * Handle drag end event
 */
function handleDragEnd() {
    emit('dragEnd');
}
</script>

<template>
    <Card :class="cn('flex h-full flex-col', props.class)">
        <CardHeader class="shrink-0 pb-3">
            <CardTitle class="flex items-center gap-2 text-base">
                <Package class="size-4" />
                Unplaced Devices
                <Badge variant="secondary" class="ml-auto">
                    {{ devices.length }}
                </Badge>
            </CardTitle>
        </CardHeader>
        <CardContent class="flex flex-1 flex-col gap-3 overflow-hidden">
            <!-- Search input -->
            <div class="relative shrink-0">
                <Search
                    class="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="searchQuery"
                    type="search"
                    placeholder="Search devices..."
                    class="pl-8"
                />
            </div>

            <!-- Device list -->
            <div class="flex-1 overflow-y-auto">
                <div class="flex flex-col gap-2">
                    <div
                        v-for="device in filteredDevices"
                        :key="device.id"
                        :data-device-id="device.id"
                        draggable="true"
                        class="flex cursor-grab items-center gap-3 rounded-lg border bg-card p-3 shadow-sm transition-all hover:shadow-md active:cursor-grabbing"
                        @dragstart="handleDragStart($event, device)"
                        @dragend="handleDragEnd"
                    >
                        <!-- Device icon -->
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-md bg-muted"
                        >
                            <component
                                :is="getDeviceIcon(device.type)"
                                class="size-4 text-muted-foreground"
                            />
                        </div>

                        <!-- Device info -->
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium">
                                {{ device.name }}
                            </div>
                            <div class="mt-0.5 flex items-center gap-1.5">
                                <Badge
                                    :variant="getBadgeVariant(device.type)"
                                    class="px-1.5 py-0 text-[10px]"
                                >
                                    {{ device.type }}
                                </Badge>
                                <span class="text-xs text-muted-foreground">
                                    {{ device.u_size }}U
                                </span>
                                <span
                                    v-if="device.width !== 'full'"
                                    class="text-xs text-muted-foreground"
                                >
                                    ({{
                                        device.width === 'half-left'
                                            ? 'Left'
                                            : 'Right'
                                    }})
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty state -->
                <div
                    v-if="filteredDevices.length === 0"
                    class="flex flex-col items-center justify-center py-8 text-center"
                >
                    <Package class="size-10 text-muted-foreground/50" />
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{
                            searchQuery
                                ? 'No devices match your search'
                                : 'All devices have been placed'
                        }}
                    </p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
