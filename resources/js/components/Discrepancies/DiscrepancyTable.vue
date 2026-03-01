<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { index as discrepanciesIndex } from '@/actions/App/Http/Controllers/DiscrepancyController';
import { Button } from '@/components/ui/button';
import { Badge, type BadgeVariants } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { ArrowUpDown, ArrowUp, ArrowDown, ChevronRight } from 'lucide-vue-next';

interface PortData {
    id: number;
    label: string;
    type: string | null;
    type_label: string | null;
    device_id: number | null;
    device: {
        id: number;
        name: string;
        asset_tag: string | null;
        rack: {
            id: number;
            name: string;
        } | null;
    } | null;
}

interface DiscrepancyData {
    id: number;
    discrepancy_type: string;
    discrepancy_type_label: string;
    status: string;
    status_label: string;
    title: string | null;
    description: string | null;
    detected_at: string | null;
    datacenter: {
        id: number;
        name: string;
    } | null;
    room: {
        id: number;
        name: string;
    } | null;
    source_port: PortData | null;
    dest_port: PortData | null;
    expected_config: Record<string, unknown> | null;
    actual_config: Record<string, unknown> | null;
    mismatch_details: Record<string, unknown> | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedDiscrepancies {
    data: DiscrepancyData[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
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
    discrepancies: PaginatedDiscrepancies;
    filters: Filters;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    select: [discrepancy: DiscrepancyData];
}>();

// Get type badge variant
const getTypeBadgeVariant = (type: string): BadgeVariants['variant'] => {
    switch (type) {
        case 'missing':
            return 'destructive';
        case 'unexpected':
            return 'warning';
        case 'mismatched':
            return 'info';
        case 'conflicting':
            return 'destructive';
        case 'configuration_mismatch':
            return 'warning';
        default:
            return 'secondary';
    }
};

// Get status badge variant
const getStatusBadgeVariant = (status: string): BadgeVariants['variant'] => {
    switch (status) {
        case 'open':
            return 'destructive';
        case 'acknowledged':
            return 'warning';
        case 'resolved':
            return 'success';
        case 'in_audit':
            return 'info';
        default:
            return 'secondary';
    }
};

// Format device/port info
const formatPortInfo = (port: PortData | null): string => {
    if (!port) return '-';
    const device = port.device?.name || 'Unknown Device';
    const rack = port.device?.rack?.name ? ` (${port.device.rack.name})` : '';
    return `${device}${rack} / ${port.label}`;
};

// Handle sorting
const handleSort = (column: string) => {
    const newOrder = props.filters.sort_by === column && props.filters.sort_order === 'asc' ? 'desc' : 'asc';

    router.get(discrepanciesIndex.url(), {
        ...getCurrentParams(),
        sort_by: column,
        sort_order: newOrder,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Get current params
const getCurrentParams = () => {
    const params: Record<string, string | undefined> = {};
    if (props.filters.discrepancy_type) params.discrepancy_type = props.filters.discrepancy_type;
    if (props.filters.datacenter_id) params.datacenter_id = props.filters.datacenter_id;
    if (props.filters.room_id) params.room_id = props.filters.room_id;
    if (props.filters.status) params.status = props.filters.status;
    if (props.filters.date_from) params.date_from = props.filters.date_from;
    if (props.filters.date_to) params.date_to = props.filters.date_to;
    return params;
};

// Get sort icon
const getSortIcon = (column: string) => {
    if (props.filters.sort_by !== column) return ArrowUpDown;
    return props.filters.sort_order === 'asc' ? ArrowUp : ArrowDown;
};

// Navigate to page
const goToPage = (url: string | null) => {
    if (url) {
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }
};

// Handle row click
const handleRowClick = (discrepancy: DiscrepancyData) => {
    emit('select', discrepancy);
};

// Format date for display
const formatDate = (dateString: string | null): string => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <!-- Mobile card view (visible on small screens) -->
    <div class="space-y-3 md:hidden">
        <div
            v-for="discrepancy in discrepancies.data"
            :key="discrepancy.id"
            class="cursor-pointer rounded-lg border bg-card p-4 shadow-sm transition-colors hover:bg-muted/50"
            @click="handleRowClick(discrepancy)"
        >
            <div class="mb-3 flex items-start justify-between gap-2">
                <div class="flex-1">
                    <Badge :variant="getTypeBadgeVariant(discrepancy.discrepancy_type)">
                        {{ discrepancy.discrepancy_type_label }}
                    </Badge>
                </div>
                <Badge :variant="getStatusBadgeVariant(discrepancy.status)">
                    {{ discrepancy.status_label }}
                </Badge>
            </div>
            <div class="mb-2 space-y-1 text-sm">
                <div class="flex items-center gap-2">
                    <span class="text-muted-foreground">Source:</span>
                    <span class="truncate">{{ formatPortInfo(discrepancy.source_port) }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-muted-foreground">Dest:</span>
                    <span class="truncate">{{ formatPortInfo(discrepancy.dest_port) }}</span>
                </div>
            </div>
            <div class="flex items-center justify-between text-sm text-muted-foreground">
                <span>{{ discrepancy.datacenter?.name || '-' }}</span>
                <span>{{ formatDate(discrepancy.detected_at) }}</span>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="discrepancies.data.length === 0" class="rounded-lg border border-dashed py-12 text-center text-muted-foreground">
            No discrepancies found.
        </div>
    </div>

    <!-- Desktop table view (hidden on small screens) -->
    <Card class="hidden md:block">
        <CardContent class="p-0">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="h-12 px-4 text-left font-medium text-muted-foreground">
                                <button
                                    class="flex items-center gap-1 hover:text-foreground"
                                    @click="handleSort('discrepancy_type')"
                                >
                                    Type
                                    <component :is="getSortIcon('discrepancy_type')" class="size-4" />
                                </button>
                            </th>
                            <th class="h-12 px-4 text-left font-medium text-muted-foreground">Source</th>
                            <th class="h-12 px-4 text-left font-medium text-muted-foreground">Destination</th>
                            <th class="h-12 px-4 text-left font-medium text-muted-foreground">
                                <button
                                    class="flex items-center gap-1 hover:text-foreground"
                                    @click="handleSort('datacenter_id')"
                                >
                                    Datacenter
                                    <component :is="getSortIcon('datacenter_id')" class="size-4" />
                                </button>
                            </th>
                            <th class="h-12 px-4 text-left font-medium text-muted-foreground">
                                <button
                                    class="flex items-center gap-1 hover:text-foreground"
                                    @click="handleSort('detected_at')"
                                >
                                    Detected
                                    <component :is="getSortIcon('detected_at')" class="size-4" />
                                </button>
                            </th>
                            <th class="h-12 px-4 text-left font-medium text-muted-foreground">
                                <button
                                    class="flex items-center gap-1 hover:text-foreground"
                                    @click="handleSort('status')"
                                >
                                    Status
                                    <component :is="getSortIcon('status')" class="size-4" />
                                </button>
                            </th>
                            <th class="h-12 w-[60px] px-4 text-left font-medium text-muted-foreground"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="discrepancy in discrepancies.data"
                            :key="discrepancy.id"
                            class="cursor-pointer border-b transition-colors hover:bg-muted/50"
                            @click="handleRowClick(discrepancy)"
                        >
                            <td class="p-4">
                                <Badge :variant="getTypeBadgeVariant(discrepancy.discrepancy_type)">
                                    {{ discrepancy.discrepancy_type_label }}
                                </Badge>
                            </td>
                            <td class="max-w-[200px] truncate p-4">
                                {{ formatPortInfo(discrepancy.source_port) }}
                            </td>
                            <td class="max-w-[200px] truncate p-4">
                                {{ formatPortInfo(discrepancy.dest_port) }}
                            </td>
                            <td class="p-4">
                                {{ discrepancy.datacenter?.name || '-' }}
                            </td>
                            <td class="p-4 text-muted-foreground">
                                {{ formatDate(discrepancy.detected_at) }}
                            </td>
                            <td class="p-4">
                                <Badge :variant="getStatusBadgeVariant(discrepancy.status)">
                                    {{ discrepancy.status_label }}
                                </Badge>
                            </td>
                            <td class="p-4">
                                <ChevronRight class="size-4 text-muted-foreground" />
                            </td>
                        </tr>
                        <tr v-if="discrepancies.data.length === 0">
                            <td colspan="7" class="p-8 text-center text-muted-foreground">
                                No discrepancies found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </CardContent>
    </Card>

    <!-- Pagination - responsive layout -->
    <div v-if="discrepancies.last_page > 1" class="mt-4 flex flex-col items-center justify-between gap-4 sm:flex-row">
        <div class="text-sm text-muted-foreground">
            Showing {{ (discrepancies.current_page - 1) * discrepancies.per_page + 1 }} to
            {{ Math.min(discrepancies.current_page * discrepancies.per_page, discrepancies.total) }} of
            {{ discrepancies.total }} discrepancies
        </div>
        <div class="flex flex-wrap justify-center gap-1">
            <Button
                v-for="link in discrepancies.links"
                :key="link.label"
                variant="outline"
                size="sm"
                :disabled="!link.url || link.active"
                @click="goToPage(link.url)"
                v-html="link.label"
            />
        </div>
    </div>
</template>
