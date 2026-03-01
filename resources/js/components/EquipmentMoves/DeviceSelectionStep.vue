<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import axios from 'axios';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Server, MapPin, Layers, AlertTriangle, Search, CheckCircle } from 'lucide-vue-next';
import { debounce } from '@/lib/utils';
import type { DeviceData } from '@/types/rooms';

interface DeviceWithConnections extends DeviceData {
    connections?: ConnectionData[];
    has_pending_move?: boolean;
    location_path?: string;
}

interface ConnectionData {
    id: number;
    source_port_label: string;
    destination_port_label: string;
    destination_device_name: string;
    cable_type: string | null;
    cable_length: string | null;
    cable_color: string | null;
}

interface Props {
    selectedDevice?: DeviceWithConnections | null;
}

const props = withDefaults(defineProps<Props>(), {
    selectedDevice: null,
});

const emit = defineEmits<{
    deviceSelected: [device: DeviceWithConnections];
}>();

// Search state
const searchQuery = ref('');
const searchResults = ref<DeviceWithConnections[]>([]);
const isSearching = ref(false);
const hasSearched = ref(false);
const searchError = ref<string | null>(null);

// Currently selected device
const currentDevice = ref<DeviceWithConnections | null>(props.selectedDevice || null);

// Watch for prop changes
watch(
    () => props.selectedDevice,
    (newDevice) => {
        if (newDevice) {
            currentDevice.value = newDevice;
        }
    },
    { immediate: true },
);

/**
 * Search devices by name, asset tag, or serial number
 */
async function searchDevices(query: string): Promise<void> {
    if (!query.trim()) {
        searchResults.value = [];
        hasSearched.value = false;
        return;
    }

    isSearching.value = true;
    searchError.value = null;
    hasSearched.value = true;

    try {
        const response = await axios.get('/api/devices/search', {
            params: {
                q: query,
                with_connections: true,
                with_pending_move: true,
                limit: 10,
            },
        });

        searchResults.value = response.data.data || [];
    } catch (err: unknown) {
        const axiosError = err as { response?: { data?: { message?: string } } };
        searchError.value = axiosError.response?.data?.message || 'Failed to search devices';
        searchResults.value = [];
    } finally {
        isSearching.value = false;
    }
}

// Debounced search
const debouncedSearch = debounce((query: string) => {
    searchDevices(query);
}, 300);

// Watch search query changes
watch(searchQuery, (query) => {
    debouncedSearch(query);
});

/**
 * Select a device from search results
 */
function selectDevice(device: DeviceWithConnections): void {
    currentDevice.value = device;
    emit('deviceSelected', device);
    searchResults.value = [];
    searchQuery.value = '';
    hasSearched.value = false;
}

/**
 * Clear selected device
 */
function clearSelection(): void {
    currentDevice.value = null;
    searchQuery.value = '';
}

/**
 * Format location path for display
 */
function formatLocationPath(device: DeviceWithConnections): string {
    if (device.location_path) {
        return device.location_path;
    }
    if (device.rack) {
        return device.rack.name + (device.start_u ? ` (U${device.start_u})` : '');
    }
    return 'Not placed';
}

/**
 * Get status badge variant
 */
function getStatusVariant(status: string | null): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'deployed':
            return 'default';
        case 'in_stock':
        case 'received':
            return 'secondary';
        case 'maintenance':
        case 'decommissioned':
            return 'destructive';
        default:
            return 'outline';
    }
}
</script>

<template>
    <div class="space-y-6">
        <div>
            <h3 class="text-lg font-medium">Select Device to Move</h3>
            <p class="text-sm text-muted-foreground">
                Search for a device by name, asset tag, or serial number.
            </p>
        </div>

        <!-- Search Input -->
        <div class="space-y-2">
            <Label for="device-search">Search Device</Label>
            <div class="relative">
                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    id="device-search"
                    v-model="searchQuery"
                    type="search"
                    placeholder="Enter device name, asset tag, or serial number..."
                    class="pl-10"
                    :disabled="!!currentDevice"
                />
            </div>
        </div>

        <!-- Search Results -->
        <div v-if="isSearching" class="space-y-2">
            <Skeleton class="h-16 w-full" />
            <Skeleton class="h-16 w-full" />
            <Skeleton class="h-16 w-full" />
        </div>

        <div v-else-if="searchError" class="rounded-lg border border-destructive p-4">
            <p class="text-sm text-destructive">{{ searchError }}</p>
        </div>

        <div v-else-if="searchResults.length > 0" class="max-h-60 space-y-2 overflow-y-auto">
            <button
                v-for="device in searchResults"
                :key="device.id"
                type="button"
                class="w-full rounded-lg border p-3 text-left transition-colors hover:bg-muted/50 focus:outline-none focus:ring-2 focus:ring-ring"
                :class="{ 'opacity-50 cursor-not-allowed': device.has_pending_move }"
                :disabled="device.has_pending_move"
                @click="selectDevice(device)"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-medium">{{ device.name }}</span>
                            <Badge v-if="device.has_pending_move" variant="warning" class="shrink-0">
                                Pending Move
                            </Badge>
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                            <span class="font-mono">{{ device.asset_tag }}</span>
                            <span v-if="device.device_type">{{ device.device_type.name }}</span>
                            <span>{{ formatLocationPath(device) }}</span>
                        </div>
                    </div>
                    <Badge :variant="getStatusVariant(device.lifecycle_status)">
                        {{ device.lifecycle_status_label || 'Unknown' }}
                    </Badge>
                </div>
            </button>
        </div>

        <div v-else-if="hasSearched && searchQuery.trim()" class="rounded-lg border border-dashed p-8 text-center">
            <p class="text-sm text-muted-foreground">No devices found matching "{{ searchQuery }}"</p>
        </div>

        <!-- Selected Device Display -->
        <Card v-if="currentDevice" class="border-primary">
            <CardHeader class="pb-3">
                <div class="flex items-start justify-between">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <CheckCircle class="h-5 w-5 text-primary" />
                        Selected Device
                    </CardTitle>
                    <button
                        type="button"
                        class="text-sm text-muted-foreground hover:text-foreground"
                        @click="clearSelection"
                    >
                        Change
                    </button>
                </div>
            </CardHeader>
            <CardContent class="space-y-4">
                <!-- Device Info -->
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-muted">
                        <Server class="h-6 w-6 text-muted-foreground" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h4 class="font-medium">{{ currentDevice.name }}</h4>
                        <p class="text-sm text-muted-foreground">
                            {{ currentDevice.device_type?.name || 'Device' }}
                        </p>
                        <p class="font-mono text-xs text-muted-foreground">
                            {{ currentDevice.asset_tag }}
                        </p>
                    </div>
                    <Badge :variant="getStatusVariant(currentDevice.lifecycle_status)">
                        {{ currentDevice.lifecycle_status_label || 'Unknown' }}
                    </Badge>
                </div>

                <!-- Location Info -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="flex items-start gap-3">
                        <MapPin class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                        <div>
                            <p class="text-sm font-medium">Current Location</p>
                            <p class="text-sm text-muted-foreground">
                                {{ formatLocationPath(currentDevice) }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <Layers class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                        <div>
                            <p class="text-sm font-medium">Physical Attributes</p>
                            <p class="text-sm text-muted-foreground">
                                {{ currentDevice.u_height }}U,
                                {{ currentDevice.width_type_label || 'Full Width' }},
                                {{ currentDevice.rack_face_label || 'Front' }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Pending Move Warning -->
                <Alert v-if="currentDevice.has_pending_move" variant="destructive">
                    <AlertTriangle class="h-4 w-4" />
                    <AlertDescription>
                        This device already has a pending move request. You cannot create another move
                        request until the existing one is completed or cancelled.
                    </AlertDescription>
                </Alert>

                <!-- Connection Count Info -->
                <div v-if="currentDevice.connections?.length" class="rounded-lg bg-muted/50 p-3">
                    <p class="text-sm">
                        <span class="font-medium">{{ currentDevice.connections.length }}</span>
                        active connection{{ currentDevice.connections.length !== 1 ? 's' : '' }}
                        will need to be reviewed in the next step.
                    </p>
                </div>
            </CardContent>
        </Card>

        <!-- Empty State -->
        <div
            v-else-if="!searchQuery.trim() && !hasSearched"
            class="rounded-lg border border-dashed p-8 text-center"
        >
            <Server class="mx-auto h-12 w-12 text-muted-foreground/50" />
            <p class="mt-4 text-sm text-muted-foreground">
                Start typing to search for a device to move.
            </p>
        </div>
    </div>
</template>
