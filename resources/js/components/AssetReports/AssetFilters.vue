<script setup lang="ts">
import { index as assetReportsIndex } from '@/actions/App/Http/Controllers/AssetReportController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Label } from '@/components/ui/label';
import { debounce } from '@/lib/utils';
import { router } from '@inertiajs/vue3';
import { Calendar, ChevronDown, Filter, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface FilterOption {
    id: number;
    name: string;
}

interface LifecycleOption {
    value: string;
    label: string;
}

interface Filters {
    datacenter_id: number | null;
    room_id: number | null;
    device_type_id: number | null;
    lifecycle_status: string | null;
    manufacturer: string | null;
    warranty_start: string | null;
    warranty_end: string | null;
}

interface Props {
    filters: Filters;
    datacenters: FilterOption[];
    rooms: FilterOption[];
    deviceTypes: FilterOption[];
    lifecycleStatuses: LifecycleOption[];
    manufacturers: string[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'filtering', value: boolean): void;
}>();

// Local filter state
const datacenterId = ref(
    props.filters.datacenter_id ? String(props.filters.datacenter_id) : '',
);
const roomId = ref(props.filters.room_id ? String(props.filters.room_id) : '');
const deviceTypeId = ref(
    props.filters.device_type_id ? String(props.filters.device_type_id) : '',
);
const lifecycleStatus = ref(props.filters.lifecycle_status ?? '');
const manufacturer = ref(props.filters.manufacturer ?? '');
const warrantyStart = ref(props.filters.warranty_start ?? '');
const warrantyEnd = ref(props.filters.warranty_end ?? '');

// Mobile collapsible state
const isOpen = ref(false);

// Common select styling
const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring dark:border-input dark:bg-transparent dark:text-foreground';
const inputClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring dark:border-input dark:bg-transparent dark:text-foreground';

// Check if any filters are active
const hasActiveFilters = computed(() => {
    return !!(
        datacenterId.value ||
        roomId.value ||
        deviceTypeId.value ||
        lifecycleStatus.value ||
        manufacturer.value ||
        warrantyStart.value ||
        warrantyEnd.value
    );
});

// Apply filters with Inertia
const applyFilters = () => {
    emit('filtering', true);

    const params: Record<string, string | undefined> = {
        datacenter_id: datacenterId.value || undefined,
        room_id: roomId.value || undefined,
        device_type_id: deviceTypeId.value || undefined,
        lifecycle_status: lifecycleStatus.value || undefined,
        manufacturer: manufacturer.value || undefined,
        warranty_start: warrantyStart.value || undefined,
        warranty_end: warrantyEnd.value || undefined,
    };

    // Remove undefined values
    Object.keys(params).forEach((key) => {
        if (params[key] === undefined) {
            delete params[key];
        }
    });

    router.get(assetReportsIndex.url(), params, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            emit('filtering', false);
        },
    });
};

// Debounced filter application
const debouncedApplyFilters = debounce(applyFilters, 300);

// Clear all filters
const clearFilters = () => {
    datacenterId.value = '';
    roomId.value = '';
    deviceTypeId.value = '';
    lifecycleStatus.value = '';
    manufacturer.value = '';
    warrantyStart.value = '';
    warrantyEnd.value = '';

    emit('filtering', true);
    router.get(
        assetReportsIndex.url(),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                emit('filtering', false);
            },
        },
    );
};

// Watch for datacenter changes to reset room filter
watch(datacenterId, () => {
    roomId.value = '';
    debouncedApplyFilters();
});

// Watch for room changes
watch(roomId, () => {
    debouncedApplyFilters();
});

// Watch for device type changes
watch(deviceTypeId, () => {
    debouncedApplyFilters();
});

// Watch for lifecycle status changes
watch(lifecycleStatus, () => {
    debouncedApplyFilters();
});

// Watch for manufacturer changes
watch(manufacturer, () => {
    debouncedApplyFilters();
});

// Watch for warranty date changes
watch(warrantyStart, () => {
    debouncedApplyFilters();
});

watch(warrantyEnd, () => {
    debouncedApplyFilters();
});
</script>

<template>
    <!-- Mobile: Collapsible -->
    <div class="lg:hidden">
        <Collapsible v-model:open="isOpen">
            <Card>
                <CardHeader class="p-3">
                    <CollapsibleTrigger
                        class="flex w-full items-center justify-between"
                    >
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Filter class="size-4" />
                            Filters
                            <span
                                v-if="hasActiveFilters"
                                class="rounded-full bg-primary px-2 py-0.5 text-xs text-primary-foreground"
                            >
                                Active
                            </span>
                        </CardTitle>
                        <ChevronDown
                            class="size-4 transition-transform"
                            :class="{ 'rotate-180': isOpen }"
                        />
                    </CollapsibleTrigger>
                </CardHeader>
                <CollapsibleContent>
                    <CardContent class="space-y-4 pt-0">
                        <!-- Datacenter Filter -->
                        <div class="space-y-2">
                            <Label for="datacenter-mobile">Datacenter</Label>
                            <select
                                id="datacenter-mobile"
                                v-model="datacenterId"
                                :class="selectClass"
                            >
                                <option value="">All Datacenters</option>
                                <option
                                    v-for="dc in datacenters"
                                    :key="dc.id"
                                    :value="String(dc.id)"
                                >
                                    {{ dc.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Room Filter (visible when datacenter selected) -->
                        <div
                            v-if="datacenterId && rooms.length > 0"
                            class="space-y-2"
                        >
                            <Label for="room-mobile">Room</Label>
                            <select
                                id="room-mobile"
                                v-model="roomId"
                                :class="selectClass"
                            >
                                <option value="">All Rooms</option>
                                <option
                                    v-for="room in rooms"
                                    :key="room.id"
                                    :value="String(room.id)"
                                >
                                    {{ room.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Device Type Filter -->
                        <div class="space-y-2">
                            <Label for="device-type-mobile">Device Type</Label>
                            <select
                                id="device-type-mobile"
                                v-model="deviceTypeId"
                                :class="selectClass"
                            >
                                <option value="">All Device Types</option>
                                <option
                                    v-for="dt in deviceTypes"
                                    :key="dt.id"
                                    :value="String(dt.id)"
                                >
                                    {{ dt.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Lifecycle Status Filter -->
                        <div class="space-y-2">
                            <Label for="lifecycle-mobile"
                                >Lifecycle Status</Label
                            >
                            <select
                                id="lifecycle-mobile"
                                v-model="lifecycleStatus"
                                :class="selectClass"
                            >
                                <option value="">All Statuses</option>
                                <option
                                    v-for="status in lifecycleStatuses"
                                    :key="status.value"
                                    :value="status.value"
                                >
                                    {{ status.label }}
                                </option>
                            </select>
                        </div>

                        <!-- Manufacturer Filter -->
                        <div class="space-y-2">
                            <Label for="manufacturer-mobile"
                                >Manufacturer</Label
                            >
                            <select
                                id="manufacturer-mobile"
                                v-model="manufacturer"
                                :class="selectClass"
                            >
                                <option value="">All Manufacturers</option>
                                <option
                                    v-for="mfr in manufacturers"
                                    :key="mfr"
                                    :value="mfr"
                                >
                                    {{ mfr }}
                                </option>
                            </select>
                        </div>

                        <!-- Warranty Date Range -->
                        <div class="grid grid-cols-2 gap-2">
                            <div class="space-y-2">
                                <Label for="warranty-start-mobile">
                                    <Calendar class="mr-1 inline size-3" />
                                    Warranty Start
                                </Label>
                                <input
                                    id="warranty-start-mobile"
                                    v-model="warrantyStart"
                                    type="date"
                                    :class="inputClass"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="warranty-end-mobile">
                                    <Calendar class="mr-1 inline size-3" />
                                    Warranty End
                                </Label>
                                <input
                                    id="warranty-end-mobile"
                                    v-model="warrantyEnd"
                                    type="date"
                                    :class="inputClass"
                                />
                            </div>
                        </div>

                        <!-- Clear Filters -->
                        <Button
                            v-if="hasActiveFilters"
                            variant="ghost"
                            size="sm"
                            class="w-full"
                            @click="clearFilters"
                        >
                            <X class="mr-2 size-4" />
                            Clear Filters
                        </Button>
                    </CardContent>
                </CollapsibleContent>
            </Card>
        </Collapsible>
    </div>

    <!-- Desktop: Inline filter row -->
    <div class="hidden lg:block">
        <Card>
            <CardContent class="pt-4">
                <div class="flex flex-col gap-4">
                    <!-- First row: Location and Type filters -->
                    <div class="flex flex-row items-end gap-4">
                        <!-- Datacenter Filter -->
                        <div class="flex-1">
                            <Label
                                for="datacenter-desktop"
                                class="mb-1 block text-sm font-medium text-muted-foreground"
                            >
                                Datacenter
                            </Label>
                            <select
                                id="datacenter-desktop"
                                v-model="datacenterId"
                                :class="selectClass"
                            >
                                <option value="">All Datacenters</option>
                                <option
                                    v-for="dc in datacenters"
                                    :key="dc.id"
                                    :value="String(dc.id)"
                                >
                                    {{ dc.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Room Filter (visible when datacenter selected) -->
                        <div
                            v-if="datacenterId && rooms.length > 0"
                            class="flex-1"
                        >
                            <Label
                                for="room-desktop"
                                class="mb-1 block text-sm font-medium text-muted-foreground"
                            >
                                Room
                            </Label>
                            <select
                                id="room-desktop"
                                v-model="roomId"
                                :class="selectClass"
                            >
                                <option value="">All Rooms</option>
                                <option
                                    v-for="room in rooms"
                                    :key="room.id"
                                    :value="String(room.id)"
                                >
                                    {{ room.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Device Type Filter -->
                        <div class="flex-1">
                            <Label
                                for="device-type-desktop"
                                class="mb-1 block text-sm font-medium text-muted-foreground"
                            >
                                Device Type
                            </Label>
                            <select
                                id="device-type-desktop"
                                v-model="deviceTypeId"
                                :class="selectClass"
                            >
                                <option value="">All Device Types</option>
                                <option
                                    v-for="dt in deviceTypes"
                                    :key="dt.id"
                                    :value="String(dt.id)"
                                >
                                    {{ dt.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Lifecycle Status Filter -->
                        <div class="flex-1">
                            <Label
                                for="lifecycle-desktop"
                                class="mb-1 block text-sm font-medium text-muted-foreground"
                            >
                                Lifecycle Status
                            </Label>
                            <select
                                id="lifecycle-desktop"
                                v-model="lifecycleStatus"
                                :class="selectClass"
                            >
                                <option value="">All Statuses</option>
                                <option
                                    v-for="status in lifecycleStatuses"
                                    :key="status.value"
                                    :value="status.value"
                                >
                                    {{ status.label }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Second row: Manufacturer and Warranty filters -->
                    <div class="flex flex-row items-end gap-4">
                        <!-- Manufacturer Filter -->
                        <div class="flex-1">
                            <Label
                                for="manufacturer-desktop"
                                class="mb-1 block text-sm font-medium text-muted-foreground"
                            >
                                Manufacturer
                            </Label>
                            <select
                                id="manufacturer-desktop"
                                v-model="manufacturer"
                                :class="selectClass"
                            >
                                <option value="">All Manufacturers</option>
                                <option
                                    v-for="mfr in manufacturers"
                                    :key="mfr"
                                    :value="mfr"
                                >
                                    {{ mfr }}
                                </option>
                            </select>
                        </div>

                        <!-- Warranty Start Date -->
                        <div class="flex-1">
                            <Label
                                for="warranty-start-desktop"
                                class="mb-1 block text-sm font-medium text-muted-foreground"
                            >
                                Warranty Start
                            </Label>
                            <input
                                id="warranty-start-desktop"
                                v-model="warrantyStart"
                                type="date"
                                :class="inputClass"
                            />
                        </div>

                        <!-- Warranty End Date -->
                        <div class="flex-1">
                            <Label
                                for="warranty-end-desktop"
                                class="mb-1 block text-sm font-medium text-muted-foreground"
                            >
                                Warranty End
                            </Label>
                            <input
                                id="warranty-end-desktop"
                                v-model="warrantyEnd"
                                type="date"
                                :class="inputClass"
                            />
                        </div>

                        <!-- Clear Filters -->
                        <div v-if="hasActiveFilters" class="shrink-0">
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="clearFilters"
                            >
                                <X class="mr-1 size-4" />
                                Clear
                            </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
