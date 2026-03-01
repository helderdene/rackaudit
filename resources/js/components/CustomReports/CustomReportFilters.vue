<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Label } from '@/components/ui/label';
import { ChevronDown, Filter, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/**
 * TypeScript interfaces
 */
interface LocationOption {
    id: number;
    name: string;
}

interface Props {
    reportType: string;
    datacenterOptions: LocationOption[];
    roomOptions: LocationOption[];
    rowOptions: LocationOption[];
    filters: Record<string, unknown>;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:filters', value: Record<string, unknown>): void;
    (
        e: 'locationChange',
        location: { type: 'datacenter' | 'room' | 'row'; id: number | null },
    ): void;
}>();

// Local filter state for cascading location filters
const datacenterId = ref<string>(
    props.filters.datacenter_id ? String(props.filters.datacenter_id) : '',
);
const roomId = ref<string>(
    props.filters.room_id ? String(props.filters.room_id) : '',
);
const rowId = ref<string>(
    props.filters.row_id ? String(props.filters.row_id) : '',
);

// Mobile collapsible state
const isOpen = ref(false);

// Common select styling with ARIA-friendly focus states
const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 dark:border-input dark:bg-transparent dark:text-foreground';

/**
 * Check if row filter should be shown (only for Capacity reports)
 */
const showRowFilter = computed(() => {
    return props.reportType === 'capacity';
});

/**
 * Check if any filters are active
 */
const hasActiveFilters = computed(() => {
    return !!(datacenterId.value || roomId.value || rowId.value);
});

/**
 * Count of active filters for badge display
 */
const activeFilterCount = computed(() => {
    let count = 0;
    if (datacenterId.value) count++;
    if (roomId.value) count++;
    if (rowId.value) count++;
    return count;
});

/**
 * Emit filter update
 */
function emitFilters() {
    const locationFilters: Record<string, unknown> = {
        ...props.filters,
        datacenter_id: datacenterId.value ? parseInt(datacenterId.value) : null,
        room_id: roomId.value ? parseInt(roomId.value) : null,
    };

    // Only include row_id for Capacity reports
    if (showRowFilter.value) {
        locationFilters.row_id = rowId.value ? parseInt(rowId.value) : null;
    }

    emit('update:filters', locationFilters);
}

/**
 * Clear all location filters
 */
function clearFilters() {
    datacenterId.value = '';
    roomId.value = '';
    rowId.value = '';
    emitFilters();
}

// Watch for datacenter changes to reset child filters
watch(datacenterId, (newValue) => {
    // Reset room and row when datacenter changes
    roomId.value = '';
    rowId.value = '';

    emit('locationChange', {
        type: 'datacenter',
        id: newValue ? parseInt(newValue) : null,
    });

    emitFilters();
});

// Watch for room changes to reset row filter
watch(roomId, (newValue) => {
    // Reset row when room changes
    rowId.value = '';

    emit('locationChange', {
        type: 'room',
        id: newValue ? parseInt(newValue) : null,
    });

    emitFilters();
});

// Watch for row changes
watch(rowId, () => {
    emitFilters();
});

// Sync with external filter changes
watch(
    () => props.filters,
    (newFilters) => {
        if (newFilters.datacenter_id !== undefined) {
            const newDatacenterId = newFilters.datacenter_id
                ? String(newFilters.datacenter_id)
                : '';
            if (datacenterId.value !== newDatacenterId) {
                datacenterId.value = newDatacenterId;
            }
        }
        if (newFilters.room_id !== undefined) {
            const newRoomId = newFilters.room_id
                ? String(newFilters.room_id)
                : '';
            if (roomId.value !== newRoomId) {
                roomId.value = newRoomId;
            }
        }
        if (newFilters.row_id !== undefined) {
            const newRowId = newFilters.row_id ? String(newFilters.row_id) : '';
            if (rowId.value !== newRowId) {
                rowId.value = newRowId;
            }
        }
    },
    { deep: true },
);
</script>

<template>
    <!-- Mobile: Collapsible -->
    <div class="lg:hidden">
        <Collapsible v-model:open="isOpen">
            <Card>
                <CardHeader class="p-3">
                    <CollapsibleTrigger
                        class="flex w-full items-center justify-between"
                        :aria-expanded="isOpen"
                        aria-controls="location-filters-mobile-content"
                    >
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Filter class="size-4" aria-hidden="true" />
                            <span>Location Filters</span>
                            <Badge
                                v-if="hasActiveFilters"
                                variant="default"
                                class="ml-1"
                                :aria-label="`${activeFilterCount} active filter${activeFilterCount !== 1 ? 's' : ''}`"
                            >
                                {{ activeFilterCount }}
                            </Badge>
                        </CardTitle>
                        <ChevronDown
                            class="size-4 shrink-0 transition-transform duration-200"
                            :class="{ 'rotate-180': isOpen }"
                            aria-hidden="true"
                        />
                    </CollapsibleTrigger>
                </CardHeader>
                <CollapsibleContent id="location-filters-mobile-content">
                    <CardContent class="space-y-4 pt-0">
                        <!-- Datacenter Filter -->
                        <div class="space-y-2">
                            <Label for="datacenter-mobile">Datacenter</Label>
                            <select
                                id="datacenter-mobile"
                                v-model="datacenterId"
                                :class="selectClass"
                                aria-describedby="datacenter-mobile-description"
                            >
                                <option value="">All Datacenters</option>
                                <option
                                    v-for="dc in datacenterOptions"
                                    :key="dc.id"
                                    :value="String(dc.id)"
                                >
                                    {{ dc.name }}
                                </option>
                            </select>
                            <span
                                id="datacenter-mobile-description"
                                class="sr-only"
                            >
                                Select a datacenter to filter results
                            </span>
                        </div>

                        <!-- Room Filter (visible when datacenter selected) -->
                        <div
                            v-if="datacenterId && roomOptions.length > 0"
                            class="space-y-2"
                        >
                            <Label for="room-mobile">Room</Label>
                            <select
                                id="room-mobile"
                                v-model="roomId"
                                :class="selectClass"
                                aria-describedby="room-mobile-description"
                            >
                                <option value="">All Rooms</option>
                                <option
                                    v-for="room in roomOptions"
                                    :key="room.id"
                                    :value="String(room.id)"
                                >
                                    {{ room.name }}
                                </option>
                            </select>
                            <span id="room-mobile-description" class="sr-only">
                                Select a room within the chosen datacenter
                            </span>
                        </div>

                        <!-- Row Filter (visible when room selected, only for Capacity) -->
                        <div
                            v-if="
                                showRowFilter && roomId && rowOptions.length > 0
                            "
                            class="space-y-2"
                        >
                            <Label for="row-mobile">Row</Label>
                            <select
                                id="row-mobile"
                                v-model="rowId"
                                :class="selectClass"
                                aria-describedby="row-mobile-description"
                            >
                                <option value="">All Rows</option>
                                <option
                                    v-for="row in rowOptions"
                                    :key="row.id"
                                    :value="String(row.id)"
                                >
                                    {{ row.name }}
                                </option>
                            </select>
                            <span id="row-mobile-description" class="sr-only">
                                Select a row within the chosen room
                            </span>
                        </div>

                        <!-- Clear Filters -->
                        <Button
                            v-if="hasActiveFilters"
                            variant="ghost"
                            size="sm"
                            class="w-full"
                            @click="clearFilters"
                            aria-label="Clear all location filters"
                        >
                            <X class="mr-2 size-4" aria-hidden="true" />
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
            <CardHeader class="pb-2">
                <CardTitle
                    class="flex items-center justify-between text-sm font-medium"
                >
                    <span class="flex items-center gap-2">
                        <Filter class="size-4" aria-hidden="true" />
                        <span>Location Filters</span>
                        <Badge
                            v-if="hasActiveFilters"
                            variant="secondary"
                            class="ml-1"
                            :aria-label="`${activeFilterCount} active filter${activeFilterCount !== 1 ? 's' : ''}`"
                        >
                            {{ activeFilterCount }}
                        </Badge>
                    </span>
                    <Button
                        v-if="hasActiveFilters"
                        variant="ghost"
                        size="sm"
                        class="h-7 px-2 text-xs"
                        @click="clearFilters"
                        aria-label="Clear all location filters"
                    >
                        <X class="mr-1 size-3" aria-hidden="true" />
                        Clear
                    </Button>
                </CardTitle>
            </CardHeader>
            <CardContent class="pt-0">
                <div
                    class="flex flex-row items-end gap-4"
                    role="group"
                    aria-label="Location filters"
                >
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
                                v-for="dc in datacenterOptions"
                                :key="dc.id"
                                :value="String(dc.id)"
                            >
                                {{ dc.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Room Filter (visible when datacenter selected) -->
                    <div
                        v-if="datacenterId && roomOptions.length > 0"
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
                                v-for="room in roomOptions"
                                :key="room.id"
                                :value="String(room.id)"
                            >
                                {{ room.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Row Filter (visible when room selected, only for Capacity) -->
                    <div
                        v-if="showRowFilter && roomId && rowOptions.length > 0"
                        class="flex-1"
                    >
                        <Label
                            for="row-desktop"
                            class="mb-1 block text-sm font-medium text-muted-foreground"
                        >
                            Row
                        </Label>
                        <select
                            id="row-desktop"
                            v-model="rowId"
                            :class="selectClass"
                        >
                            <option value="">All Rows</option>
                            <option
                                v-for="row in rowOptions"
                                :key="row.id"
                                :value="String(row.id)"
                            >
                                {{ row.name }}
                            </option>
                        </select>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
