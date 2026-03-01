<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import {
    allDiscrepancyTypes,
    useComparisonFilters,
    type ComparisonFiltersState,
} from '@/composables/useComparisonFilters';
import type { DiscrepancyTypeValue } from '@/types/comparison';
import {
    ChevronDown,
    Download,
    Filter,
    RefreshCw,
    Search,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface DeviceOption {
    id: number;
    name: string;
}

interface RackOption {
    id: number;
    name: string;
}

interface Props {
    /** Available devices for the device filter dropdown */
    devices: DeviceOption[];
    /** Available racks for the rack filter dropdown */
    racks: RackOption[];
    /** Whether filters are currently being applied (loading state) */
    isLoading?: boolean;
    /** Export URL for download button */
    exportUrl?: string;
}

const props = withDefaults(defineProps<Props>(), {
    isLoading: false,
    exportUrl: '',
});

const emit = defineEmits<{
    (e: 'filter-change', filters: ComparisonFiltersState): void;
    (e: 'export'): void;
    (e: 'refresh'): void;
}>();

// Use the composable for filter management
const {
    filters,
    isApplyingFilters,
    hasActiveFilters,
    activeFilterCount,
    setDiscrepancyTypes,
    toggleDiscrepancyType,
    setDeviceId,
    setRackId,
    setShowAcknowledged,
    resetFilters,
} = useComparisonFilters({
    debounceMs: 300,
    onFiltersChange: (newFilters) => {
        emit('filter-change', newFilters);
    },
});

// Device search state
const deviceSearchQuery = ref('');
const deviceDropdownOpen = ref(false);

// Rack search state
const rackDropdownOpen = ref(false);

// Computed: filtered devices based on search
const filteredDevices = computed(() => {
    if (!deviceSearchQuery.value) {
        return props.devices;
    }
    const query = deviceSearchQuery.value.toLowerCase();
    return props.devices.filter((device) =>
        device.name.toLowerCase().includes(query),
    );
});

// Computed: selected device name
const selectedDeviceName = computed(() => {
    if (filters.value.deviceId === null) {
        return null;
    }
    const device = props.devices.find((d) => d.id === filters.value.deviceId);
    return device?.name ?? null;
});

// Computed: selected rack name
const selectedRackName = computed(() => {
    if (filters.value.rackId === null) {
        return null;
    }
    const rack = props.racks.find((r) => r.id === filters.value.rackId);
    return rack?.name ?? null;
});

// Computed: discrepancy type selection label
const discrepancyTypeLabel = computed(() => {
    if (filters.value.discrepancyTypes.length === 0) {
        return 'All Types';
    }
    if (filters.value.discrepancyTypes.length === 1) {
        const selected = allDiscrepancyTypes.find(
            (t) => t.value === filters.value.discrepancyTypes[0],
        );
        return selected?.label ?? 'Selected';
    }
    return `${filters.value.discrepancyTypes.length} Types`;
});

// Combined loading state
const isFilterLoading = computed(
    () => props.isLoading || isApplyingFilters.value,
);

/**
 * Handle device selection
 */
function handleDeviceSelect(deviceId: number | null): void {
    setDeviceId(deviceId);
    deviceDropdownOpen.value = false;
    deviceSearchQuery.value = '';
}

/**
 * Handle rack selection
 */
function handleRackSelect(rackId: number | null): void {
    setRackId(rackId);
    rackDropdownOpen.value = false;
}

/**
 * Handle show acknowledged toggle
 */
function handleAcknowledgedToggle(checked: boolean): void {
    setShowAcknowledged(checked);
}

/**
 * Handle export button click
 */
function handleExport(): void {
    emit('export');
}

/**
 * Handle refresh button click
 */
function handleRefresh(): void {
    emit('refresh');
}

/**
 * Clear all filters
 */
function handleClearFilters(): void {
    resetFilters();
}

/**
 * Get color class for discrepancy type badge
 */
function getDiscrepancyTypeColorClass(type: DiscrepancyTypeValue): string {
    switch (type) {
        case 'matched':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
        case 'missing':
            return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
        case 'unexpected':
            return 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400';
        case 'mismatched':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400';
        case 'conflicting':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400';
    }
}
</script>

<template>
    <div
        class="flex flex-wrap items-center gap-3 rounded-lg border bg-background p-3"
    >
        <!-- Filter icon with active indicator -->
        <div class="flex items-center gap-2">
            <div class="relative">
                <Filter class="size-4 text-muted-foreground" />
                <span
                    v-if="hasActiveFilters"
                    class="absolute -top-1 -right-1 flex size-3 items-center justify-center rounded-full bg-primary text-[8px] font-bold text-primary-foreground"
                >
                    {{ activeFilterCount }}
                </span>
            </div>
            <span class="text-sm font-medium text-muted-foreground"
                >Filters:</span
            >
        </div>

        <!-- Discrepancy Type Multi-Select -->
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button
                    variant="outline"
                    size="sm"
                    class="h-8 gap-1.5"
                    :class="{
                        'border-primary': filters.discrepancyTypes.length > 0,
                    }"
                >
                    <span class="text-xs">{{ discrepancyTypeLabel }}</span>
                    <ChevronDown class="size-3.5" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" class="w-48">
                <DropdownMenuLabel>Discrepancy Types</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuCheckboxItem
                    v-for="type in allDiscrepancyTypes"
                    :key="type.value"
                    :checked="filters.discrepancyTypes.includes(type.value)"
                    @update:checked="toggleDiscrepancyType(type.value)"
                >
                    <Badge
                        variant="outline"
                        class="mr-2 px-1.5 py-0 text-[10px]"
                        :class="getDiscrepancyTypeColorClass(type.value)"
                    >
                        {{ type.label }}
                    </Badge>
                </DropdownMenuCheckboxItem>
                <DropdownMenuSeparator />
                <div class="p-1">
                    <Button
                        v-if="filters.discrepancyTypes.length > 0"
                        variant="ghost"
                        size="sm"
                        class="h-7 w-full justify-start text-xs"
                        @click="setDiscrepancyTypes([])"
                    >
                        <X class="mr-1.5 size-3" />
                        Clear Selection
                    </Button>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>

        <!-- Device Filter Dropdown (Searchable) -->
        <DropdownMenu v-model:open="deviceDropdownOpen">
            <DropdownMenuTrigger as-child>
                <Button
                    variant="outline"
                    size="sm"
                    class="h-8 min-w-[120px] justify-between gap-1.5"
                    :class="{
                        'border-primary': filters.deviceId !== null,
                    }"
                >
                    <span class="truncate text-xs">
                        {{ selectedDeviceName ?? 'All Devices' }}
                    </span>
                    <ChevronDown class="size-3.5 shrink-0" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" class="w-56">
                <DropdownMenuLabel>Filter by Device</DropdownMenuLabel>
                <div class="p-2">
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-2 size-3.5 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="deviceSearchQuery"
                            placeholder="Search devices..."
                            class="h-8 pl-7 text-xs"
                            @keydown.stop
                        />
                    </div>
                </div>
                <DropdownMenuSeparator />
                <div class="max-h-48 overflow-y-auto">
                    <!-- All Devices option -->
                    <button
                        type="button"
                        class="relative flex w-full cursor-pointer items-center rounded-sm px-2 py-1.5 text-xs outline-hidden select-none hover:bg-accent focus:bg-accent"
                        :class="{ 'bg-accent': filters.deviceId === null }"
                        @click="handleDeviceSelect(null)"
                    >
                        All Devices
                    </button>
                    <!-- Device options -->
                    <button
                        v-for="device in filteredDevices"
                        :key="device.id"
                        type="button"
                        class="relative flex w-full cursor-pointer items-center rounded-sm px-2 py-1.5 text-xs outline-hidden select-none hover:bg-accent focus:bg-accent"
                        :class="{ 'bg-accent': filters.deviceId === device.id }"
                        @click="handleDeviceSelect(device.id)"
                    >
                        {{ device.name }}
                    </button>
                    <!-- No results -->
                    <div
                        v-if="filteredDevices.length === 0 && deviceSearchQuery"
                        class="px-2 py-4 text-center text-xs text-muted-foreground"
                    >
                        No devices found.
                    </div>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>

        <!-- Rack Filter Dropdown -->
        <DropdownMenu v-model:open="rackDropdownOpen">
            <DropdownMenuTrigger as-child>
                <Button
                    variant="outline"
                    size="sm"
                    class="h-8 min-w-[100px] justify-between gap-1.5"
                    :class="{
                        'border-primary': filters.rackId !== null,
                    }"
                >
                    <span class="truncate text-xs">
                        {{ selectedRackName ?? 'All Racks' }}
                    </span>
                    <ChevronDown class="size-3.5 shrink-0" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" class="w-48">
                <DropdownMenuLabel>Filter by Rack</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <div class="max-h-48 overflow-y-auto">
                    <!-- All Racks option -->
                    <button
                        type="button"
                        class="relative flex w-full cursor-pointer items-center rounded-sm px-2 py-1.5 text-xs outline-hidden select-none hover:bg-accent focus:bg-accent"
                        :class="{ 'bg-accent': filters.rackId === null }"
                        @click="handleRackSelect(null)"
                    >
                        All Racks
                    </button>
                    <!-- Rack options -->
                    <button
                        v-for="rack in racks"
                        :key="rack.id"
                        type="button"
                        class="relative flex w-full cursor-pointer items-center rounded-sm px-2 py-1.5 text-xs outline-hidden select-none hover:bg-accent focus:bg-accent"
                        :class="{ 'bg-accent': filters.rackId === rack.id }"
                        @click="handleRackSelect(rack.id)"
                    >
                        {{ rack.name }}
                    </button>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>

        <!-- Show Acknowledged Checkbox -->
        <label class="flex cursor-pointer items-center gap-2">
            <Checkbox
                :checked="filters.showAcknowledged"
                @update:checked="handleAcknowledgedToggle"
            />
            <span class="text-xs text-muted-foreground">Show Acknowledged</span>
        </label>

        <!-- Spacer -->
        <div class="flex-1" />

        <!-- Loading indicator -->
        <Spinner v-if="isFilterLoading" class="size-4" />

        <!-- Clear Filters -->
        <Button
            v-if="hasActiveFilters"
            variant="ghost"
            size="sm"
            class="h-8 gap-1.5 text-xs"
            @click="handleClearFilters"
        >
            <X class="size-3.5" />
            Clear
        </Button>

        <!-- Refresh Button -->
        <Button
            variant="ghost"
            size="sm"
            class="h-8 gap-1.5 text-xs"
            :disabled="isFilterLoading"
            @click="handleRefresh"
        >
            <RefreshCw
                class="size-3.5"
                :class="{ 'animate-spin': isFilterLoading }"
            />
            Refresh
        </Button>

        <!-- Export Button -->
        <Button
            v-if="exportUrl"
            variant="outline"
            size="sm"
            class="h-8 gap-1.5 text-xs"
            :disabled="isFilterLoading"
            @click="handleExport"
        >
            <Download class="size-3.5" />
            Export
        </Button>
    </div>
</template>
