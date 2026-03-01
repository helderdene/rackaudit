<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { index as discrepanciesIndex } from '@/actions/App/Http/Controllers/DiscrepancyController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { ChevronDown, Filter, X } from 'lucide-vue-next';

interface FilterOption {
    value: string;
    label: string;
}

interface DatacenterOption {
    id: number;
    name: string;
}

interface RoomOption {
    id: number;
    name: string;
}

interface Filters {
    discrepancy_type: string;
    datacenter_id: string;
    room_id: string;
    status: string;
    date_from: string;
    date_to: string;
    sort_by: string;
    sort_order: string;
}

interface Props {
    filters: Filters;
    datacenters: DatacenterOption[];
    rooms: RoomOption[];
    typeOptions: FilterOption[];
    statusOptions: FilterOption[];
}

const props = defineProps<Props>();

// Local filter state
const discrepancyType = ref(props.filters.discrepancy_type);
const datacenterId = ref(props.filters.datacenter_id);
const roomId = ref(props.filters.room_id);
const status = ref(props.filters.status);
const dateFrom = ref(props.filters.date_from);
const dateTo = ref(props.filters.date_to);

// Mobile collapsible state
const isOpen = ref(false);

// Common select styling
const selectClass = 'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';

// Check if any filters are active
const hasActiveFilters = computed(() => {
    return !!(
        discrepancyType.value ||
        datacenterId.value ||
        roomId.value ||
        status.value ||
        dateFrom.value ||
        dateTo.value
    );
});

// Apply filters
const applyFilters = () => {
    const params: Record<string, string | undefined> = {
        discrepancy_type: discrepancyType.value || undefined,
        datacenter_id: datacenterId.value || undefined,
        room_id: roomId.value || undefined,
        status: status.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
    };

    // Remove undefined values
    Object.keys(params).forEach(key => {
        if (params[key] === undefined) {
            delete params[key];
        }
    });

    router.get(discrepanciesIndex.url(), params, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Clear all filters
const clearFilters = () => {
    discrepancyType.value = '';
    datacenterId.value = '';
    roomId.value = '';
    status.value = '';
    dateFrom.value = '';
    dateTo.value = '';

    router.get(discrepanciesIndex.url(), {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Watch for datacenter changes to reset room filter
watch(datacenterId, () => {
    roomId.value = '';
    applyFilters();
});

// Apply filters on change (except datacenter which has its own watcher)
watch([discrepancyType, roomId, status, dateFrom, dateTo], () => {
    applyFilters();
});
</script>

<template>
    <!-- Mobile: Collapsible -->
    <div class="lg:hidden">
        <Collapsible v-model:open="isOpen">
            <Card>
                <CardHeader class="p-3">
                    <CollapsibleTrigger class="flex w-full items-center justify-between">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Filter class="size-4" />
                            Filters
                            <span v-if="hasActiveFilters" class="rounded-full bg-primary px-2 py-0.5 text-xs text-primary-foreground">
                                Active
                            </span>
                        </CardTitle>
                        <ChevronDown class="size-4 transition-transform" :class="{ 'rotate-180': isOpen }" />
                    </CollapsibleTrigger>
                </CardHeader>
                <CollapsibleContent>
                    <CardContent class="space-y-4 pt-0">
                        <!-- Type Filter -->
                        <div class="space-y-2">
                            <Label for="type-mobile">Discrepancy Type</Label>
                            <select
                                id="type-mobile"
                                v-model="discrepancyType"
                                :class="selectClass"
                            >
                                <option value="">All Types</option>
                                <option
                                    v-for="option in typeOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>

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

                        <!-- Room Filter -->
                        <div v-if="datacenterId && rooms.length > 0" class="space-y-2">
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

                        <!-- Status Filter -->
                        <div class="space-y-2">
                            <Label for="status-mobile">Status</Label>
                            <select
                                id="status-mobile"
                                v-model="status"
                                :class="selectClass"
                            >
                                <option value="">All Statuses</option>
                                <option
                                    v-for="option in statusOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>

                        <!-- Date Range -->
                        <div class="space-y-2">
                            <Label>Date Range</Label>
                            <div class="grid grid-cols-2 gap-2">
                                <Input
                                    v-model="dateFrom"
                                    type="date"
                                    placeholder="From"
                                />
                                <Input
                                    v-model="dateTo"
                                    type="date"
                                    placeholder="To"
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

    <!-- Desktop: Fixed sidebar -->
    <div class="hidden lg:block">
        <Card>
            <CardHeader class="pb-3">
                <CardTitle class="flex items-center justify-between text-base">
                    <span class="flex items-center gap-2">
                        <Filter class="size-4" />
                        Filters
                    </span>
                    <Button
                        v-if="hasActiveFilters"
                        variant="ghost"
                        size="sm"
                        @click="clearFilters"
                    >
                        <X class="size-4" />
                    </Button>
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <!-- Type Filter -->
                <div class="space-y-2">
                    <Label for="type-desktop">Discrepancy Type</Label>
                    <select
                        id="type-desktop"
                        v-model="discrepancyType"
                        :class="selectClass"
                    >
                        <option value="">All Types</option>
                        <option
                            v-for="option in typeOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <!-- Datacenter Filter -->
                <div class="space-y-2">
                    <Label for="datacenter-desktop">Datacenter</Label>
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

                <!-- Room Filter -->
                <div v-if="datacenterId && rooms.length > 0" class="space-y-2">
                    <Label for="room-desktop">Room</Label>
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

                <!-- Status Filter -->
                <div class="space-y-2">
                    <Label for="status-desktop">Status</Label>
                    <select
                        id="status-desktop"
                        v-model="status"
                        :class="selectClass"
                    >
                        <option value="">All Statuses</option>
                        <option
                            v-for="option in statusOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <!-- Date Range -->
                <div class="space-y-2">
                    <Label>Date Range</Label>
                    <div class="space-y-2">
                        <Input
                            v-model="dateFrom"
                            type="date"
                            placeholder="From"
                        />
                        <Input
                            v-model="dateTo"
                            type="date"
                            placeholder="To"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
