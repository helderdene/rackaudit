<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { debounce } from '@/lib/utils';
import { computed, ref, watch } from 'vue';

interface DeviceOption {
    id: number;
    name: string;
    asset_tag: string | null;
    start_u: number | null;
    rack_id: number;
    rack_name: string | null;
}

interface Props {
    /** Selected device IDs */
    modelValue: number[];
    /** Selected rack IDs to fetch devices from */
    rackIds: number[];
    /** Error message for device selection */
    error?: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:modelValue': [value: number[]];
}>();

// Internal state
const devices = ref<DeviceOption[]>([]);
const isLoading = ref(false);
const searchQuery = ref('');

// Computed for v-model binding
const selectedDeviceIds = computed({
    get: () => props.modelValue,
    set: (value: number[]) => emit('update:modelValue', value),
});

// Filtered devices based on search
const filteredDevices = computed(() => {
    if (!searchQuery.value) {
        return devices.value;
    }
    const query = searchQuery.value.toLowerCase();
    return devices.value.filter(
        (device) =>
            device.name.toLowerCase().includes(query) ||
            device.asset_tag?.toLowerCase().includes(query) ||
            device.rack_name?.toLowerCase().includes(query),
    );
});

// Group devices by rack for better organization
const devicesByRack = computed(() => {
    const grouped: Record<string, DeviceOption[]> = {};
    for (const device of filteredDevices.value) {
        const rackName = device.rack_name || `Rack ${device.rack_id}`;
        if (!grouped[rackName]) {
            grouped[rackName] = [];
        }
        grouped[rackName].push(device);
    }
    return grouped;
});

// Check if a device is selected
const isDeviceSelected = (deviceId: number): boolean => {
    return selectedDeviceIds.value.includes(deviceId);
};

// Toggle device selection
const toggleDevice = (deviceId: number): void => {
    const currentIds = [...selectedDeviceIds.value];
    const index = currentIds.indexOf(deviceId);

    if (index > -1) {
        currentIds.splice(index, 1);
    } else {
        currentIds.push(deviceId);
    }

    selectedDeviceIds.value = currentIds;
};

// Select all visible devices
const selectAllVisible = (): void => {
    const visibleIds = filteredDevices.value.map((device) => device.id);
    const newIds = [...new Set([...selectedDeviceIds.value, ...visibleIds])];
    selectedDeviceIds.value = newIds;
};

// Clear all selections
const clearSelection = (): void => {
    selectedDeviceIds.value = [];
};

// Get selected device names for display
const selectedDeviceNames = computed(() => {
    return devices.value
        .filter((device) => selectedDeviceIds.value.includes(device.id))
        .map((device) => device.name);
});

// Watch for rack changes and fetch devices
watch(
    () => props.rackIds,
    async (newRackIds) => {
        if (newRackIds.length > 0) {
            await fetchDevicesForRacks(newRackIds);
        } else {
            devices.value = [];
            emit('update:modelValue', []);
        }
    },
    { deep: true },
);

/**
 * Fetch devices for the selected racks
 */
async function fetchDevicesForRacks(rackIds: number[]): Promise<void> {
    if (rackIds.length === 0) {
        devices.value = [];
        return;
    }

    isLoading.value = true;
    try {
        const queryString = rackIds.map((id) => `rack_ids[]=${id}`).join('&');
        const response = await fetch(
            `/api/audits/racks/devices?${queryString}`,
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        if (!response.ok) {
            throw new Error('Failed to fetch devices');
        }

        const data = await response.json();
        devices.value = data.data || [];

        // Clear any previously selected devices that are not in the new racks
        const validIds = devices.value.map((d) => d.id);
        const newSelection = selectedDeviceIds.value.filter((id) =>
            validIds.includes(id),
        );
        if (newSelection.length !== selectedDeviceIds.value.length) {
            emit('update:modelValue', newSelection);
        }
    } catch (error) {
        console.error('Error fetching devices:', error);
        devices.value = [];
    } finally {
        isLoading.value = false;
    }
}

// Debounced search handler
const handleSearchInput = debounce((value: string) => {
    searchQuery.value = value;
}, 200);

// Format U position for display
const formatUPosition = (startU: number | null): string => {
    if (startU === null) return '';
    return `U${startU}`;
};
</script>

<template>
    <div class="space-y-3">
        <div
            class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
        >
            <Label>
                Devices
                <span class="text-xs text-muted-foreground">(optional)</span>
            </Label>
            <div class="flex items-center gap-2">
                <button
                    v-if="filteredDevices.length > 0 && !isLoading"
                    type="button"
                    class="text-xs text-primary hover:underline"
                    @click="selectAllVisible"
                >
                    Select all
                </button>
                <span
                    v-if="selectedDeviceIds.length > 0 && !isLoading"
                    class="text-xs text-muted-foreground"
                    >|</span
                >
                <button
                    v-if="selectedDeviceIds.length > 0 && !isLoading"
                    type="button"
                    class="text-xs text-muted-foreground hover:text-foreground hover:underline"
                    @click="clearSelection"
                >
                    Clear selection
                </button>
            </div>
        </div>

        <p class="text-xs text-muted-foreground">
            Leave empty to include all devices in the selected racks.
        </p>

        <!-- Search input -->
        <div class="relative">
            <Input
                type="text"
                placeholder="Search devices..."
                :model-value="searchQuery"
                :disabled="isLoading"
                @update:model-value="handleSearchInput"
                class="pr-8"
            />
            <svg
                class="absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                />
            </svg>
        </div>

        <!-- Loading state with skeleton -->
        <div
            v-if="isLoading"
            class="space-y-2 rounded-md border border-input p-3"
        >
            <div class="flex items-center gap-2">
                <Spinner class="h-4 w-4" />
                <span class="text-sm text-muted-foreground"
                    >Loading devices...</span
                >
            </div>
            <div class="space-y-3">
                <div class="space-y-2">
                    <Skeleton class="h-4 w-24" />
                    <Skeleton class="h-10 w-full" />
                    <Skeleton class="h-10 w-full" />
                </div>
                <div class="space-y-2">
                    <Skeleton class="h-4 w-20" />
                    <Skeleton class="h-10 w-3/4" />
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div
            v-else-if="props.rackIds.length === 0"
            class="rounded-md border border-dashed border-input py-8 text-center text-sm text-muted-foreground"
        >
            Select racks to view available devices
        </div>

        <div
            v-else-if="devices.length === 0"
            class="rounded-md border border-dashed border-input py-8 text-center text-sm text-muted-foreground"
        >
            No devices found in the selected racks
        </div>

        <div
            v-else-if="filteredDevices.length === 0"
            class="rounded-md border border-dashed border-input py-8 text-center text-sm text-muted-foreground"
        >
            No devices match your search
        </div>

        <!-- Device list grouped by rack - responsive design -->
        <div
            v-else
            class="max-h-64 space-y-3 overflow-y-auto rounded-md border border-input p-2 sm:max-h-72"
        >
            <div
                v-for="(rackDevices, rackName) in devicesByRack"
                :key="rackName"
            >
                <div
                    class="mb-1.5 px-2 text-xs font-medium text-muted-foreground"
                >
                    {{ rackName }}
                </div>
                <div class="space-y-0.5">
                    <label
                        v-for="device in rackDevices"
                        :key="device.id"
                        :for="`device-${device.id}`"
                        class="flex cursor-pointer touch-manipulation items-center gap-3 rounded-md px-2 py-2 hover:bg-muted/50 active:bg-muted/70 sm:py-1.5"
                    >
                        <Checkbox
                            :id="`device-${device.id}`"
                            :checked="isDeviceSelected(device.id)"
                            class="h-5 w-5 sm:h-4 sm:w-4"
                            @update:checked="toggleDevice(device.id)"
                        />
                        <div
                            class="flex flex-1 flex-col gap-1 sm:flex-row sm:items-center sm:justify-between sm:gap-2"
                        >
                            <div class="flex flex-col gap-0.5">
                                <span class="text-sm font-medium">{{
                                    device.name
                                }}</span>
                                <span
                                    v-if="device.asset_tag"
                                    class="text-xs text-muted-foreground"
                                >
                                    Asset: {{ device.asset_tag }}
                                </span>
                            </div>
                            <Badge
                                v-if="device.start_u !== null"
                                variant="outline"
                                class="w-fit shrink-0 text-xs font-normal"
                            >
                                {{ formatUPosition(device.start_u) }}
                            </Badge>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        <!-- Selection summary - responsive wrapping -->
        <div
            v-if="selectedDeviceIds.length > 0"
            class="flex flex-wrap items-center gap-2"
        >
            <Badge variant="secondary" class="font-normal">
                {{ selectedDeviceIds.length }}
                {{ selectedDeviceIds.length === 1 ? 'device' : 'devices' }}
                selected
            </Badge>
            <span
                v-if="selectedDeviceNames.length <= 3"
                class="hidden text-xs text-muted-foreground sm:inline"
            >
                ({{ selectedDeviceNames.join(', ') }})
            </span>
        </div>
        <div
            v-else-if="devices.length > 0"
            class="text-xs text-muted-foreground"
        >
            All {{ devices.length }}
            {{ devices.length === 1 ? 'device' : 'devices' }} will be included
        </div>

        <!-- Error message -->
        <p v-if="error" class="text-sm text-destructive">
            {{ error }}
        </p>
    </div>
</template>
