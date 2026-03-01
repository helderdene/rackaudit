<script setup lang="ts">
/**
 * DeviceInventoryTable Component
 *
 * Displays a sortable, paginated table of device inventory data.
 * Shows key device information including asset tag, name, serial number,
 * manufacturer, model, device type, lifecycle status, location, and warranty.
 */

import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Button } from '@/components/ui/button';
import {
    ChevronUp,
    ChevronDown,
    ChevronsUpDown,
    ExternalLink,
    Package,
    ChevronLeft,
    ChevronRight,
} from 'lucide-vue-next';

interface Device {
    id: number;
    asset_tag: string;
    name: string;
    serial_number: string | null;
    manufacturer: string | null;
    model: string | null;
    device_type: {
        id: number;
        name: string;
    } | null;
    lifecycle_status: string;
    lifecycle_status_label: string;
    datacenter_name: string | null;
    room_name: string | null;
    rack_name: string | null;
    start_u: number | null;
    warranty_end_date: string | null;
}

interface Pagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    devices: Device[];
    pagination: Pagination;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
});

const emit = defineEmits<{
    (e: 'page-change', page: number): void;
    (e: 'sort', column: string, direction: 'asc' | 'desc'): void;
}>();

type SortKey = 'asset_tag' | 'name' | 'manufacturer' | 'device_type' | 'lifecycle_status' | 'location' | 'warranty_end_date';
type SortDirection = 'asc' | 'desc';

// Sort state
const sortKey = ref<SortKey>('asset_tag');
const sortDirection = ref<SortDirection>('asc');

// Sort devices locally
const sortedDevices = computed(() => {
    const sorted = [...props.devices];

    sorted.sort((a, b) => {
        let comparison = 0;

        switch (sortKey.value) {
            case 'asset_tag':
                comparison = (a.asset_tag || '').localeCompare(b.asset_tag || '');
                break;
            case 'name':
                comparison = (a.name || '').localeCompare(b.name || '');
                break;
            case 'manufacturer':
                comparison = (a.manufacturer || '').localeCompare(b.manufacturer || '');
                break;
            case 'device_type':
                comparison = (a.device_type?.name || '').localeCompare(b.device_type?.name || '');
                break;
            case 'lifecycle_status':
                comparison = (a.lifecycle_status_label || '').localeCompare(b.lifecycle_status_label || '');
                break;
            case 'location':
                const locA = getLocationString(a);
                const locB = getLocationString(b);
                comparison = locA.localeCompare(locB);
                break;
            case 'warranty_end_date':
                const dateA = a.warranty_end_date || '';
                const dateB = b.warranty_end_date || '';
                comparison = dateA.localeCompare(dateB);
                break;
        }

        return sortDirection.value === 'asc' ? comparison : -comparison;
    });

    return sorted;
});

// Toggle sort for a column
const toggleSort = (key: SortKey) => {
    if (sortKey.value === key) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDirection.value = 'asc';
    }
    emit('sort', key, sortDirection.value);
};

// Get sort icon for a column
const getSortIcon = (key: SortKey) => {
    if (sortKey.value !== key) return ChevronsUpDown;
    return sortDirection.value === 'asc' ? ChevronUp : ChevronDown;
};

// Format location string
const getLocationString = (device: Device): string => {
    if (!device.datacenter_name) return 'Not Racked';

    const parts = [device.datacenter_name];
    if (device.room_name) parts.push(device.room_name);
    if (device.rack_name) parts.push(device.rack_name);
    if (device.start_u) parts.push(`U${device.start_u}`);

    return parts.join(' > ');
};

// Format warranty date
const formatWarrantyDate = (dateStr: string | null): string => {
    if (!dateStr) return 'Not tracked';

    const date = new Date(dateStr);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const formatted = date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });

    return formatted;
};

// Get warranty status badge variant
const getWarrantyBadgeVariant = (dateStr: string | null): 'success' | 'warning' | 'destructive' | 'secondary' => {
    if (!dateStr) return 'secondary';

    const date = new Date(dateStr);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const daysUntilExpiry = Math.ceil((date.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));

    if (daysUntilExpiry < 0) return 'destructive';
    if (daysUntilExpiry <= 30) return 'warning';
    return 'success';
};

// Get lifecycle status badge variant
const getLifecycleBadgeVariant = (status: string): 'default' | 'secondary' | 'outline' | 'destructive' | 'success' | 'warning' | 'info' => {
    switch (status) {
        case 'deployed':
            return 'success';
        case 'maintenance':
            return 'warning';
        case 'decommissioned':
        case 'disposed':
            return 'destructive';
        case 'in_stock':
            return 'info';
        default:
            return 'secondary';
    }
};

// Pagination helpers
const goToPage = (page: number) => {
    if (page >= 1 && page <= props.pagination.last_page) {
        emit('page-change', page);
    }
};

const paginationRange = computed(() => {
    const current = props.pagination.current_page;
    const last = props.pagination.last_page;
    const delta = 2;

    const range: (number | string)[] = [];
    const rangeWithDots: (number | string)[] = [];

    for (let i = Math.max(2, current - delta); i <= Math.min(last - 1, current + delta); i++) {
        range.push(i);
    }

    if (current - delta > 2) {
        rangeWithDots.push(1, '...');
    } else {
        rangeWithDots.push(1);
    }

    rangeWithDots.push(...range);

    if (current + delta < last - 1) {
        rangeWithDots.push('...', last);
    } else if (last > 1) {
        if (!rangeWithDots.includes(last)) {
            rangeWithDots.push(last);
        }
    }

    return rangeWithDots;
});

// Calculate displayed range
const displayedRange = computed(() => {
    const start = (props.pagination.current_page - 1) * props.pagination.per_page + 1;
    const end = Math.min(start + props.pagination.per_page - 1, props.pagination.total);
    return { start, end };
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2 text-base">
                <Package class="size-5" />
                Device Inventory
                <span class="text-sm font-normal text-muted-foreground">
                    ({{ pagination.total }} total)
                </span>
            </CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Loading skeleton -->
            <div v-if="loading" class="space-y-3">
                <Skeleton class="h-10 w-full" />
                <Skeleton class="h-12 w-full" />
                <Skeleton class="h-12 w-full" />
                <Skeleton class="h-12 w-full" />
                <Skeleton class="h-12 w-full" />
                <Skeleton class="h-12 w-full" />
            </div>

            <!-- Table content -->
            <template v-else>
                <!-- Empty state -->
                <div v-if="devices.length === 0" class="py-12 text-center">
                    <Package class="mx-auto mb-4 size-12 text-muted-foreground/50" />
                    <h3 class="text-lg font-medium">No devices found</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        No devices match the current filter criteria.
                    </p>
                </div>

                <!-- Data table -->
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/50">
                            <tr>
                                <th
                                    class="h-10 cursor-pointer px-3 text-left font-medium text-muted-foreground hover:text-foreground"
                                    @click="toggleSort('asset_tag')"
                                >
                                    <div class="flex items-center gap-1">
                                        Asset Tag
                                        <component :is="getSortIcon('asset_tag')" class="size-4" />
                                    </div>
                                </th>
                                <th
                                    class="h-10 cursor-pointer px-3 text-left font-medium text-muted-foreground hover:text-foreground"
                                    @click="toggleSort('name')"
                                >
                                    <div class="flex items-center gap-1">
                                        Name
                                        <component :is="getSortIcon('name')" class="size-4" />
                                    </div>
                                </th>
                                <th class="h-10 px-3 text-left font-medium text-muted-foreground">
                                    Serial Number
                                </th>
                                <th
                                    class="h-10 cursor-pointer px-3 text-left font-medium text-muted-foreground hover:text-foreground"
                                    @click="toggleSort('manufacturer')"
                                >
                                    <div class="flex items-center gap-1">
                                        Manufacturer
                                        <component :is="getSortIcon('manufacturer')" class="size-4" />
                                    </div>
                                </th>
                                <th class="h-10 px-3 text-left font-medium text-muted-foreground">
                                    Model
                                </th>
                                <th
                                    class="h-10 cursor-pointer px-3 text-left font-medium text-muted-foreground hover:text-foreground"
                                    @click="toggleSort('device_type')"
                                >
                                    <div class="flex items-center gap-1">
                                        Device Type
                                        <component :is="getSortIcon('device_type')" class="size-4" />
                                    </div>
                                </th>
                                <th
                                    class="h-10 cursor-pointer px-3 text-left font-medium text-muted-foreground hover:text-foreground"
                                    @click="toggleSort('lifecycle_status')"
                                >
                                    <div class="flex items-center gap-1">
                                        Status
                                        <component :is="getSortIcon('lifecycle_status')" class="size-4" />
                                    </div>
                                </th>
                                <th
                                    class="h-10 cursor-pointer px-3 text-left font-medium text-muted-foreground hover:text-foreground"
                                    @click="toggleSort('location')"
                                >
                                    <div class="flex items-center gap-1">
                                        Location
                                        <component :is="getSortIcon('location')" class="size-4" />
                                    </div>
                                </th>
                                <th
                                    class="h-10 cursor-pointer px-3 text-left font-medium text-muted-foreground hover:text-foreground"
                                    @click="toggleSort('warranty_end_date')"
                                >
                                    <div class="flex items-center gap-1">
                                        Warranty
                                        <component :is="getSortIcon('warranty_end_date')" class="size-4" />
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="device in sortedDevices"
                                :key="device.id"
                                class="border-b transition-colors hover:bg-muted/50"
                            >
                                <td class="p-3">
                                    <span class="font-mono text-xs font-medium">
                                        {{ device.asset_tag }}
                                    </span>
                                </td>
                                <td class="p-3 font-medium">
                                    {{ device.name }}
                                </td>
                                <td class="p-3 font-mono text-xs text-muted-foreground">
                                    {{ device.serial_number || '-' }}
                                </td>
                                <td class="p-3">
                                    {{ device.manufacturer || '-' }}
                                </td>
                                <td class="p-3 text-muted-foreground">
                                    {{ device.model || '-' }}
                                </td>
                                <td class="p-3">
                                    <span v-if="device.device_type">
                                        {{ device.device_type.name }}
                                    </span>
                                    <span v-else class="text-muted-foreground">-</span>
                                </td>
                                <td class="p-3">
                                    <Badge :variant="getLifecycleBadgeVariant(device.lifecycle_status)">
                                        {{ device.lifecycle_status_label }}
                                    </Badge>
                                </td>
                                <td class="max-w-[200px] truncate p-3 text-muted-foreground">
                                    {{ getLocationString(device) }}
                                </td>
                                <td class="p-3">
                                    <Badge :variant="getWarrantyBadgeVariant(device.warranty_end_date)">
                                        {{ formatWarrantyDate(device.warranty_end_date) }}
                                    </Badge>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="devices.length > 0 && pagination.last_page > 1"
                    class="mt-4 flex items-center justify-between border-t pt-4"
                >
                    <div class="text-sm text-muted-foreground">
                        Showing {{ displayedRange.start }} to {{ displayedRange.end }} of {{ pagination.total }} results
                    </div>

                    <div class="flex items-center gap-1">
                        <Button
                            variant="outline"
                            size="icon"
                            class="size-8"
                            :disabled="pagination.current_page === 1"
                            @click="goToPage(pagination.current_page - 1)"
                        >
                            <ChevronLeft class="size-4" />
                        </Button>

                        <template v-for="(page, index) in paginationRange" :key="index">
                            <span
                                v-if="page === '...'"
                                class="px-2 text-muted-foreground"
                            >
                                ...
                            </span>
                            <Button
                                v-else
                                variant="outline"
                                size="sm"
                                class="size-8"
                                :class="{ 'bg-primary text-primary-foreground': page === pagination.current_page }"
                                @click="goToPage(page as number)"
                            >
                                {{ page }}
                            </Button>
                        </template>

                        <Button
                            variant="outline"
                            size="icon"
                            class="size-8"
                            :disabled="pagination.current_page === pagination.last_page"
                            @click="goToPage(pagination.current_page + 1)"
                        >
                            <ChevronRight class="size-4" />
                        </Button>
                    </div>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
