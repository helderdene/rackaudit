<script setup lang="ts">
import { index as searchIndex } from '@/actions/App/Http/Controllers/SearchController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Label } from '@/components/ui/label';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    Building2,
    Cable,
    ChevronDown,
    ChevronRight,
    Filter,
    HardDrive,
    Plug,
    Search,
    SearchX,
    Server,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

defineOptions({
    layout: AppLayout,
});

interface SearchResultItem {
    id: number;
    name: string;
    entity_type: string;
    breadcrumb: string;
    matched_fields: string[];
    datacenter_id?: number;
    device_id?: number;
    lifecycle_status?: string;
    lifecycle_status_label?: string;
    status?: string;
    status_label?: string;
    type?: string;
    type_label?: string;
    cable_color?: string;
    source_port_label?: string;
    dest_port_label?: string;
    [key: string]: unknown;
}

interface SearchResult {
    items: SearchResultItem[];
    total: number;
}

interface FilterOption {
    id: number;
    name: string;
}

interface EnumOption {
    value: string;
    label: string;
}

interface Props {
    results: {
        datacenters: SearchResult;
        racks: SearchResult;
        devices: SearchResult;
        ports: SearchResult;
        connections: SearchResult;
    };
    query: string;
    filters: {
        type: string | null;
        datacenter_id: number | null;
        room_id: number | null;
        row_id: number | null;
        rack_id: number | null;
        lifecycle_status: string | null;
        port_type: string | null;
        port_status: string | null;
        rack_status: string | null;
    };
    filterOptions: {
        datacenters: FilterOption[];
        rooms: FilterOption[];
        rows: FilterOption[];
        racks: FilterOption[];
        lifecycleStatuses: EnumOption[];
        portTypes: EnumOption[];
        portStatuses: EnumOption[];
        rackStatuses: EnumOption[];
        entityTypes: EnumOption[];
    };
}

const props = defineProps<Props>();

// Entity type configuration
const entityTypes = [
    { key: 'datacenters' as const, label: 'Datacenters', icon: Building2 },
    { key: 'racks' as const, label: 'Racks', icon: Server },
    { key: 'devices' as const, label: 'Devices', icon: HardDrive },
    { key: 'ports' as const, label: 'Ports', icon: Plug },
    { key: 'connections' as const, label: 'Connections', icon: Cable },
];

// Local filter state
const searchQuery = ref(props.query || '');
const activeEntityType = ref<string | null>(props.filters.type);
const datacenterId = ref(
    props.filters.datacenter_id ? String(props.filters.datacenter_id) : '',
);
const roomId = ref(props.filters.room_id ? String(props.filters.room_id) : '');
const rowId = ref(props.filters.row_id ? String(props.filters.row_id) : '');
const rackId = ref(props.filters.rack_id ? String(props.filters.rack_id) : '');
const lifecycleStatus = ref(props.filters.lifecycle_status || '');
const portType = ref(props.filters.port_type || '');
const portStatus = ref(props.filters.port_status || '');
const rackStatus = ref(props.filters.rack_status || '');

// Expanded sections state
const expandedSections = ref<Record<string, boolean>>({
    datacenters: true,
    racks: true,
    devices: true,
    ports: true,
    connections: true,
});

// Mobile filter collapsible state
const isFilterOpen = ref(false);

// Common select styling
const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring dark:border-input dark:bg-transparent dark:text-foreground';

// Check if any filters are active
const hasActiveFilters = computed(() => {
    return !!(
        datacenterId.value ||
        roomId.value ||
        rowId.value ||
        rackId.value ||
        lifecycleStatus.value ||
        portType.value ||
        portStatus.value ||
        rackStatus.value
    );
});

// Check if there are any results
const hasResults = computed(() => {
    return entityTypes.some((et) => props.results[et.key]?.total > 0);
});

// Total results count
const totalResults = computed(() => {
    return entityTypes.reduce(
        (sum, et) => sum + (props.results[et.key]?.total ?? 0),
        0,
    );
});

// Filter visible entity types based on active filter
const visibleEntityTypes = computed(() => {
    if (activeEntityType.value) {
        return entityTypes.filter((et) => et.key === activeEntityType.value);
    }
    return entityTypes;
});

// Check if entity-specific filters should be shown
const showDeviceFilters = computed(() => {
    return !activeEntityType.value || activeEntityType.value === 'devices';
});

const showPortFilters = computed(() => {
    return !activeEntityType.value || activeEntityType.value === 'ports';
});

const showRackFilters = computed(() => {
    return !activeEntityType.value || activeEntityType.value === 'racks';
});

// Apply filters with Inertia
const applyFilters = () => {
    const params: Record<string, string | number | undefined> = {
        q: searchQuery.value || undefined,
        type: activeEntityType.value || undefined,
        datacenter_id: datacenterId.value
            ? Number(datacenterId.value)
            : undefined,
        room_id: roomId.value ? Number(roomId.value) : undefined,
        row_id: rowId.value ? Number(rowId.value) : undefined,
        rack_id: rackId.value ? Number(rackId.value) : undefined,
        lifecycle_status: lifecycleStatus.value || undefined,
        port_type: portType.value || undefined,
        port_status: portStatus.value || undefined,
        rack_status: rackStatus.value || undefined,
    };

    // Remove undefined values
    Object.keys(params).forEach((key) => {
        if (params[key] === undefined) {
            delete params[key];
        }
    });

    router.get(searchIndex.url(), params, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Clear all filters
const clearFilters = () => {
    datacenterId.value = '';
    roomId.value = '';
    rowId.value = '';
    rackId.value = '';
    lifecycleStatus.value = '';
    portType.value = '';
    portStatus.value = '';
    rackStatus.value = '';

    applyFilters();
};

// Watch for datacenter changes to reset child filters
watch(datacenterId, () => {
    roomId.value = '';
    rowId.value = '';
    rackId.value = '';
    applyFilters();
});

// Watch for room changes to reset row and rack filters
watch(roomId, () => {
    rowId.value = '';
    rackId.value = '';
    applyFilters();
});

// Watch for row changes to reset rack filter
watch(rowId, () => {
    rackId.value = '';
    applyFilters();
});

// Watch for rack changes
watch(rackId, () => {
    applyFilters();
});

// Watch for entity-specific filter changes
watch([lifecycleStatus, portType, portStatus, rackStatus], () => {
    applyFilters();
});

// Handle entity type tab change
const handleEntityTypeChange = (value: string | number) => {
    const strValue = String(value);
    activeEntityType.value = strValue === 'all' ? null : strValue;
    applyFilters();
};

// Toggle section expansion
const toggleSection = (key: string) => {
    expandedSections.value[key] = !expandedSections.value[key];
};

// Navigate to entity detail page
// Using simple URL strings because search results don't include full hierarchy
const navigateToEntity = (item: SearchResultItem, type: string) => {
    let url = '';
    switch (type) {
        case 'datacenters':
            url = `/datacenters/${item.id}`;
            break;
        case 'racks':
            url = `/racks/${item.id}`;
            break;
        case 'devices':
            url = `/devices/${item.id}`;
            break;
        case 'ports':
            // Ports navigate to their parent device
            if (item.device_id) {
                url = `/devices/${item.device_id}`;
            }
            break;
        case 'connections':
            url = `/connections/${item.id}`;
            break;
    }
    if (url) {
        router.visit(url);
    }
};

// Highlight matched text
const highlightMatch = (text: string): string => {
    if (!searchQuery.value.trim() || !text) return text;

    const query = searchQuery.value.trim();
    const regex = new RegExp(`(${escapeRegex(query)})`, 'gi');
    return text.replace(
        regex,
        '<mark class="bg-yellow-200 dark:bg-yellow-800/50 text-inherit px-0.5 rounded">$1</mark>',
    );
};

const escapeRegex = (str: string): string => {
    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
};

// Get status badge variant
const getStatusBadgeVariant = (
    status: string | undefined,
): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (!status) return 'outline';
    switch (status) {
        case 'deployed':
        case 'active':
        case 'connected':
            return 'default';
        case 'in_stock':
        case 'received':
        case 'available':
        case 'reserved':
            return 'secondary';
        case 'maintenance':
        case 'decommissioned':
        case 'disabled':
            return 'destructive';
        default:
            return 'outline';
    }
};
</script>

<template>
    <Head :title="query ? `Search: ${query}` : 'Search Results'" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 md:p-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <HeadingSmall
                title="Search Results"
                :description="
                    query
                        ? `Results for '${query}'`
                        : 'Enter a search term to find datacenters, racks, devices, ports, and connections.'
                "
            />
            <div v-if="totalResults > 0" class="text-sm text-muted-foreground">
                {{ totalResults }} result{{ totalResults !== 1 ? 's' : '' }}
                found
            </div>
        </div>

        <!-- Entity Type Filter Tabs -->
        <div class="flex items-center gap-4 overflow-x-auto pb-2">
            <Tabs
                :default-value="activeEntityType || 'all'"
                @update:model-value="handleEntityTypeChange"
            >
                <TabsList>
                    <TabsTrigger value="all" class="gap-2">
                        <Search class="size-4" />
                        <span class="hidden sm:inline">All</span>
                        <Badge
                            variant="secondary"
                            class="ml-1 h-5 px-1.5 text-xs"
                            >{{ totalResults }}</Badge
                        >
                    </TabsTrigger>
                    <TabsTrigger
                        v-for="entityType in entityTypes"
                        :key="entityType.key"
                        :value="entityType.key"
                        class="gap-2"
                    >
                        <component :is="entityType.icon" class="size-4" />
                        <span class="hidden sm:inline">{{
                            entityType.label
                        }}</span>
                        <Badge
                            v-if="results[entityType.key]?.total > 0"
                            variant="secondary"
                            class="ml-1 h-5 px-1.5 text-xs"
                        >
                            {{ results[entityType.key].total }}
                        </Badge>
                    </TabsTrigger>
                </TabsList>
            </Tabs>
        </div>

        <!-- Main Content Layout -->
        <div class="flex flex-col gap-4 lg:flex-row">
            <!-- Filters Sidebar -->
            <div class="w-full lg:w-64 lg:shrink-0">
                <!-- Mobile: Collapsible -->
                <div class="lg:hidden">
                    <Collapsible v-model:open="isFilterOpen">
                        <Card>
                            <CardHeader class="p-3">
                                <CollapsibleTrigger
                                    class="flex w-full items-center justify-between"
                                >
                                    <CardTitle
                                        class="flex items-center gap-2 text-base"
                                    >
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
                                        :class="{ 'rotate-180': isFilterOpen }"
                                    />
                                </CollapsibleTrigger>
                            </CardHeader>
                            <CollapsibleContent>
                                <CardContent class="space-y-4 pt-0">
                                    <!-- Hierarchical Location Filters -->
                                    <div class="space-y-2">
                                        <Label for="datacenter-mobile"
                                            >Datacenter</Label
                                        >
                                        <select
                                            id="datacenter-mobile"
                                            v-model="datacenterId"
                                            :class="selectClass"
                                        >
                                            <option value="">
                                                All Datacenters
                                            </option>
                                            <option
                                                v-for="dc in filterOptions.datacenters"
                                                :key="dc.id"
                                                :value="String(dc.id)"
                                            >
                                                {{ dc.name }}
                                            </option>
                                        </select>
                                    </div>

                                    <div
                                        v-if="
                                            datacenterId &&
                                            filterOptions.rooms.length > 0
                                        "
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
                                                v-for="room in filterOptions.rooms"
                                                :key="room.id"
                                                :value="String(room.id)"
                                            >
                                                {{ room.name }}
                                            </option>
                                        </select>
                                    </div>

                                    <div
                                        v-if="
                                            roomId &&
                                            filterOptions.rows.length > 0
                                        "
                                        class="space-y-2"
                                    >
                                        <Label for="row-mobile">Row</Label>
                                        <select
                                            id="row-mobile"
                                            v-model="rowId"
                                            :class="selectClass"
                                        >
                                            <option value="">All Rows</option>
                                            <option
                                                v-for="row in filterOptions.rows"
                                                :key="row.id"
                                                :value="String(row.id)"
                                            >
                                                {{ row.name }}
                                            </option>
                                        </select>
                                    </div>

                                    <div
                                        v-if="
                                            rowId &&
                                            filterOptions.racks.length > 0
                                        "
                                        class="space-y-2"
                                    >
                                        <Label for="rack-mobile">Rack</Label>
                                        <select
                                            id="rack-mobile"
                                            v-model="rackId"
                                            :class="selectClass"
                                        >
                                            <option value="">All Racks</option>
                                            <option
                                                v-for="rack in filterOptions.racks"
                                                :key="rack.id"
                                                :value="String(rack.id)"
                                            >
                                                {{ rack.name }}
                                            </option>
                                        </select>
                                    </div>

                                    <!-- Entity-Specific Filters -->
                                    <div
                                        v-if="showDeviceFilters"
                                        class="space-y-2"
                                    >
                                        <Label for="lifecycle-status-mobile"
                                            >Device Status</Label
                                        >
                                        <select
                                            id="lifecycle-status-mobile"
                                            v-model="lifecycleStatus"
                                            :class="selectClass"
                                        >
                                            <option value="">
                                                All Statuses
                                            </option>
                                            <option
                                                v-for="option in filterOptions.lifecycleStatuses"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <div
                                        v-if="showRackFilters"
                                        class="space-y-2"
                                    >
                                        <Label for="rack-status-mobile"
                                            >Rack Status</Label
                                        >
                                        <select
                                            id="rack-status-mobile"
                                            v-model="rackStatus"
                                            :class="selectClass"
                                        >
                                            <option value="">
                                                All Statuses
                                            </option>
                                            <option
                                                v-for="option in filterOptions.rackStatuses"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <div
                                        v-if="showPortFilters"
                                        class="space-y-2"
                                    >
                                        <Label for="port-type-mobile"
                                            >Port Type</Label
                                        >
                                        <select
                                            id="port-type-mobile"
                                            v-model="portType"
                                            :class="selectClass"
                                        >
                                            <option value="">All Types</option>
                                            <option
                                                v-for="option in filterOptions.portTypes"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <div
                                        v-if="showPortFilters"
                                        class="space-y-2"
                                    >
                                        <Label for="port-status-mobile"
                                            >Port Status</Label
                                        >
                                        <select
                                            id="port-status-mobile"
                                            v-model="portStatus"
                                            :class="selectClass"
                                        >
                                            <option value="">
                                                All Statuses
                                            </option>
                                            <option
                                                v-for="option in filterOptions.portStatuses"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
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
                            <CardTitle
                                class="flex items-center justify-between text-base"
                            >
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
                            <!-- Hierarchical Location Filters -->
                            <div class="space-y-2">
                                <Label for="datacenter-desktop"
                                    >Datacenter</Label
                                >
                                <select
                                    id="datacenter-desktop"
                                    v-model="datacenterId"
                                    :class="selectClass"
                                >
                                    <option value="">All Datacenters</option>
                                    <option
                                        v-for="dc in filterOptions.datacenters"
                                        :key="dc.id"
                                        :value="String(dc.id)"
                                    >
                                        {{ dc.name }}
                                    </option>
                                </select>
                            </div>

                            <div
                                v-if="
                                    datacenterId &&
                                    filterOptions.rooms.length > 0
                                "
                                class="space-y-2"
                            >
                                <Label for="room-desktop">Room</Label>
                                <select
                                    id="room-desktop"
                                    v-model="roomId"
                                    :class="selectClass"
                                >
                                    <option value="">All Rooms</option>
                                    <option
                                        v-for="room in filterOptions.rooms"
                                        :key="room.id"
                                        :value="String(room.id)"
                                    >
                                        {{ room.name }}
                                    </option>
                                </select>
                            </div>

                            <div
                                v-if="roomId && filterOptions.rows.length > 0"
                                class="space-y-2"
                            >
                                <Label for="row-desktop">Row</Label>
                                <select
                                    id="row-desktop"
                                    v-model="rowId"
                                    :class="selectClass"
                                >
                                    <option value="">All Rows</option>
                                    <option
                                        v-for="row in filterOptions.rows"
                                        :key="row.id"
                                        :value="String(row.id)"
                                    >
                                        {{ row.name }}
                                    </option>
                                </select>
                            </div>

                            <div
                                v-if="rowId && filterOptions.racks.length > 0"
                                class="space-y-2"
                            >
                                <Label for="rack-desktop">Rack</Label>
                                <select
                                    id="rack-desktop"
                                    v-model="rackId"
                                    :class="selectClass"
                                >
                                    <option value="">All Racks</option>
                                    <option
                                        v-for="rack in filterOptions.racks"
                                        :key="rack.id"
                                        :value="String(rack.id)"
                                    >
                                        {{ rack.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- Entity-Specific Filters -->
                            <div v-if="showDeviceFilters" class="space-y-2">
                                <Label for="lifecycle-status-desktop"
                                    >Device Status</Label
                                >
                                <select
                                    id="lifecycle-status-desktop"
                                    v-model="lifecycleStatus"
                                    :class="selectClass"
                                >
                                    <option value="">All Statuses</option>
                                    <option
                                        v-for="option in filterOptions.lifecycleStatuses"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                            </div>

                            <div v-if="showRackFilters" class="space-y-2">
                                <Label for="rack-status-desktop"
                                    >Rack Status</Label
                                >
                                <select
                                    id="rack-status-desktop"
                                    v-model="rackStatus"
                                    :class="selectClass"
                                >
                                    <option value="">All Statuses</option>
                                    <option
                                        v-for="option in filterOptions.rackStatuses"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                            </div>

                            <div v-if="showPortFilters" class="space-y-2">
                                <Label for="port-type-desktop">Port Type</Label>
                                <select
                                    id="port-type-desktop"
                                    v-model="portType"
                                    :class="selectClass"
                                >
                                    <option value="">All Types</option>
                                    <option
                                        v-for="option in filterOptions.portTypes"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                            </div>

                            <div v-if="showPortFilters" class="space-y-2">
                                <Label for="port-status-desktop"
                                    >Port Status</Label
                                >
                                <select
                                    id="port-status-desktop"
                                    v-model="portStatus"
                                    :class="selectClass"
                                >
                                    <option value="">All Statuses</option>
                                    <option
                                        v-for="option in filterOptions.portStatuses"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Search Results -->
            <div class="min-w-0 flex-1">
                <!-- Empty State -->
                <div
                    v-if="!hasResults"
                    class="flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-muted-foreground/25 p-12 text-center"
                >
                    <SearchX class="mb-4 size-12 text-muted-foreground/50" />
                    <h3 class="mb-2 text-lg font-semibold">No results found</h3>
                    <p class="mb-4 max-w-sm text-sm text-muted-foreground">
                        <template v-if="query">
                            No matches found for "{{ query }}". Try adjusting
                            your search or filters.
                        </template>
                        <template v-else>
                            Enter a search term to find datacenters, racks,
                            devices, ports, and connections.
                        </template>
                    </p>
                    <Button
                        v-if="hasActiveFilters"
                        variant="outline"
                        @click="clearFilters"
                    >
                        <X class="mr-2 size-4" />
                        Clear Filters
                    </Button>
                </div>

                <!-- Results by Entity Type -->
                <div v-else class="space-y-4">
                    <template
                        v-for="entityType in visibleEntityTypes"
                        :key="entityType.key"
                    >
                        <Card v-if="results[entityType.key]?.items.length > 0">
                            <!-- Section Header (Collapsible) -->
                            <CardHeader
                                class="cursor-pointer pb-3"
                                @click="toggleSection(entityType.key)"
                            >
                                <CardTitle
                                    class="flex items-center justify-between"
                                >
                                    <div class="flex items-center gap-2">
                                        <component
                                            :is="entityType.icon"
                                            class="size-5"
                                        />
                                        <span>{{ entityType.label }}</span>
                                        <Badge variant="secondary">
                                            {{ results[entityType.key].total }}
                                        </Badge>
                                    </div>
                                    <ChevronDown
                                        class="size-4 transition-transform"
                                        :class="{
                                            '-rotate-90':
                                                !expandedSections[
                                                    entityType.key
                                                ],
                                        }"
                                    />
                                </CardTitle>
                            </CardHeader>

                            <!-- Section Content -->
                            <CardContent
                                v-if="expandedSections[entityType.key]"
                                class="pt-0"
                            >
                                <div class="divide-y">
                                    <div
                                        v-for="item in results[entityType.key]
                                            .items"
                                        :key="item.id"
                                        class="group cursor-pointer py-3 transition-colors first:pt-0 last:pb-0 hover:bg-muted/50"
                                        @click="
                                            navigateToEntity(
                                                item,
                                                entityType.key,
                                            )
                                        "
                                    >
                                        <div
                                            class="flex items-start justify-between gap-4"
                                        >
                                            <!-- Left: Name and Breadcrumb -->
                                            <div class="min-w-0 flex-1">
                                                <!-- Entity Name with highlighted match -->
                                                <div
                                                    class="font-medium group-hover:text-primary"
                                                    v-html="
                                                        highlightMatch(
                                                            item.name,
                                                        )
                                                    "
                                                />

                                                <!-- Breadcrumb Location Context -->
                                                <div
                                                    class="mt-1 flex items-center gap-1 text-xs text-muted-foreground"
                                                >
                                                    <span
                                                        v-html="
                                                            highlightMatch(
                                                                item.breadcrumb,
                                                            )
                                                        "
                                                    />
                                                </div>

                                                <!-- Additional Metadata -->
                                                <div
                                                    v-if="
                                                        item.matched_fields &&
                                                        item.matched_fields
                                                            .length > 0
                                                    "
                                                    class="mt-1 text-xs text-muted-foreground"
                                                >
                                                    Matched:
                                                    {{
                                                        item.matched_fields.join(
                                                            ', ',
                                                        )
                                                    }}
                                                </div>
                                            </div>

                                            <!-- Right: Status/Type Badges -->
                                            <div
                                                class="flex shrink-0 items-center gap-2"
                                            >
                                                <!-- Device lifecycle status -->
                                                <Badge
                                                    v-if="
                                                        entityType.key ===
                                                            'devices' &&
                                                        item.lifecycle_status_label
                                                    "
                                                    :variant="
                                                        getStatusBadgeVariant(
                                                            item.lifecycle_status,
                                                        )
                                                    "
                                                >
                                                    {{
                                                        item.lifecycle_status_label
                                                    }}
                                                </Badge>

                                                <!-- Rack status -->
                                                <Badge
                                                    v-if="
                                                        entityType.key ===
                                                            'racks' &&
                                                        item.status_label
                                                    "
                                                    :variant="
                                                        getStatusBadgeVariant(
                                                            item.status,
                                                        )
                                                    "
                                                >
                                                    {{ item.status_label }}
                                                </Badge>

                                                <!-- Port type and status -->
                                                <template
                                                    v-if="
                                                        entityType.key ===
                                                        'ports'
                                                    "
                                                >
                                                    <Badge
                                                        v-if="item.type_label"
                                                        variant="outline"
                                                    >
                                                        {{ item.type_label }}
                                                    </Badge>
                                                    <Badge
                                                        v-if="item.status_label"
                                                        :variant="
                                                            getStatusBadgeVariant(
                                                                item.status,
                                                            )
                                                        "
                                                    >
                                                        {{ item.status_label }}
                                                    </Badge>
                                                </template>

                                                <!-- Connection cable color -->
                                                <Badge
                                                    v-if="
                                                        entityType.key ===
                                                            'connections' &&
                                                        item.cable_color
                                                    "
                                                    variant="outline"
                                                >
                                                    {{ item.cable_color }}
                                                </Badge>

                                                <!-- Arrow indicator -->
                                                <ChevronRight
                                                    class="size-4 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Pagination Note (if more results exist) -->
                                <div
                                    v-if="
                                        results[entityType.key].total >
                                        results[entityType.key].items.length
                                    "
                                    class="mt-4 border-t pt-4 text-center text-sm text-muted-foreground"
                                >
                                    Showing
                                    {{ results[entityType.key].items.length }}
                                    of {{ results[entityType.key].total }}
                                    {{ entityType.label.toLowerCase() }}.
                                    <button
                                        type="button"
                                        class="ml-1 text-primary hover:underline"
                                        @click.stop="
                                            handleEntityTypeChange(
                                                entityType.key,
                                            )
                                        "
                                    >
                                        View all
                                    </button>
                                </div>
                            </CardContent>
                        </Card>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
