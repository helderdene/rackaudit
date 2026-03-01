<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { create, show, edit, destroy } from '@/actions/App/Http/Controllers/DeviceController';
import { create as createExport } from '@/actions/App/Http/Controllers/BulkExportController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import DeleteDeviceDialog from '@/components/devices/DeleteDeviceDialog.vue';
import RealtimeToastContainer from '@/components/notifications/RealtimeToastContainer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { DeviceData, PaginatedDevices, SelectOption } from '@/types/rooms';
import { debounce } from '@/lib/utils';
import { usePermissions } from '@/composables/usePermissions';
import { useRealtimeUpdates } from '@/composables/useRealtimeUpdates';
import { Download } from 'lucide-vue-next';

interface Props {
    devices: PaginatedDevices;
    lifecycleStatusOptions: SelectOption[];
    filters: {
        search: string;
        lifecycle_status: string;
    };
    canCreate: boolean;
}

const props = defineProps<Props>();

const { hasAnyRole } = usePermissions();
const canExport = hasAnyRole(['Administrator', 'IT Manager']);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Devices',
        href: '/devices',
    },
];

// Real-time updates integration (null datacenter ID to listen to all updates)
const {
    pendingUpdates,
    dismissUpdate,
    clearUpdates,
    onDataChange,
} = useRealtimeUpdates(null);

// Register handler for device changes
onDataChange('device', (data) => {
    // Toast will be automatically shown via pendingUpdates
    console.log('Device changed:', data);
});

// Handle toast dismissal
function handleDismissUpdate(id: string): void {
    dismissUpdate(id);
}

// Handle toast refresh
function handleRefresh(): void {
    clearUpdates();
    router.reload();
}

// Handle clear all updates
function handleClearAll(): void {
    clearUpdates();
}

// Local filter state
const searchQuery = ref(props.filters.search);
const statusFilter = ref(props.filters.lifecycle_status);

// Debounced search function
const debouncedSearch = debounce(() => {
    router.get('/devices', {
        search: searchQuery.value || undefined,
        lifecycle_status: statusFilter.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
}, 300);

// Watch for filter changes
watch(searchQuery, () => {
    debouncedSearch();
});

watch(statusFilter, () => {
    router.get('/devices', {
        search: searchQuery.value || undefined,
        lifecycle_status: statusFilter.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
});

// Clear all filters
const clearFilters = () => {
    searchQuery.value = '';
    statusFilter.value = '';
    router.get('/devices', {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Get lifecycle status badge variant
const getLifecycleStatusVariant = (status: string | null): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (status) {
        case 'deployed':
            return 'default';
        case 'in_stock':
        case 'received':
            return 'secondary';
        case 'maintenance':
        case 'decommissioned':
            return 'destructive';
        case 'ordered':
        case 'disposed':
            return 'outline';
        default:
            return 'outline';
    }
};

// Get warranty status badge variant
const getWarrantyStatusVariant = (status: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (status) {
        case 'active':
            return 'default';
        case 'expired':
            return 'destructive';
        case 'none':
        default:
            return 'outline';
    }
};

// Format warranty status label
const getWarrantyStatusLabel = (status: string): string => {
    switch (status) {
        case 'active':
            return 'Active';
        case 'expired':
            return 'Expired';
        case 'none':
        default:
            return 'None';
    }
};

// Navigate to export create page with device entity type pre-selected
const exportUrl = createExport.url({ query: { entity_type: 'device' } });
</script>

<template>
    <Head title="Devices" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <HeadingSmall
                    title="Device Management"
                    description="Manage datacenter devices and assets."
                />
                <div class="flex items-center gap-2">
                    <Link v-if="canExport" :href="exportUrl">
                        <Button variant="outline">
                            <Download class="mr-2 h-4 w-4" />
                            Export
                        </Button>
                    </Link>
                    <Link v-if="canCreate" :href="create.url()">
                        <Button>Add Device</Button>
                    </Link>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                <div class="flex-1">
                    <Input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Search by name, asset tag, or serial number..."
                        class="max-w-sm"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <select
                        v-model="statusFilter"
                        class="flex h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    >
                        <option value="">All Statuses</option>
                        <option
                            v-for="option in lifecycleStatusOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <Button
                        v-if="searchQuery || statusFilter"
                        variant="ghost"
                        size="sm"
                        @click="clearFilters"
                    >
                        Clear
                    </Button>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-md border">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/50">
                            <tr>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Name</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Type</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Asset Tag</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Status</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Rack</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Warranty</th>
                                <th class="h-12 w-[140px] px-4 text-left font-medium text-muted-foreground">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="device in (devices.data as DeviceData[])"
                                :key="device.id"
                                class="border-b transition-colors hover:bg-muted/50"
                            >
                                <td class="p-4 font-medium">
                                    <Link
                                        :href="show.url(device.id)"
                                        class="hover:underline"
                                    >
                                        {{ device.name }}
                                    </Link>
                                </td>
                                <td class="p-4 text-muted-foreground">
                                    {{ device.device_type?.name || '-' }}
                                </td>
                                <td class="p-4 font-mono text-xs">
                                    {{ device.asset_tag }}
                                </td>
                                <td class="p-4">
                                    <Badge :variant="getLifecycleStatusVariant(device.lifecycle_status)">
                                        {{ device.lifecycle_status_label || 'Unknown' }}
                                    </Badge>
                                </td>
                                <td class="p-4 text-muted-foreground">
                                    <template v-if="device.rack">
                                        {{ device.rack.name }}
                                        <span v-if="device.start_u" class="text-xs">
                                            (U{{ device.start_u }})
                                        </span>
                                    </template>
                                    <span v-else class="italic">Unplaced</span>
                                </td>
                                <td class="p-4">
                                    <Badge :variant="getWarrantyStatusVariant(device.warranty_status)">
                                        {{ getWarrantyStatusLabel(device.warranty_status) }}
                                    </Badge>
                                </td>
                                <td class="p-4">
                                    <div class="flex items-center gap-2">
                                        <Link :href="show.url(device.id)">
                                            <Button variant="outline" size="sm">View</Button>
                                        </Link>
                                        <Link :href="edit.url(device.id)">
                                            <Button variant="outline" size="sm">Edit</Button>
                                        </Link>
                                        <DeleteDeviceDialog
                                            :device-id="device.id"
                                            :device-name="device.name"
                                        />
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="(devices.data as DeviceData[]).length === 0">
                                <td colspan="7" class="p-8 text-center text-muted-foreground">
                                    No devices found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="devices.last_page > 1" class="flex items-center justify-between">
                <p class="text-sm text-muted-foreground">
                    Showing {{ (devices.current_page - 1) * devices.per_page + 1 }} to
                    {{ Math.min(devices.current_page * devices.per_page, devices.total) }} of
                    {{ devices.total }} devices
                </p>
                <div class="flex gap-1">
                    <template v-for="link in devices.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-state
                            preserve-scroll
                        >
                            <Button
                                variant="outline"
                                size="sm"
                                :class="{ 'bg-muted': link.active }"
                                v-html="link.label"
                            />
                        </Link>
                        <Button
                            v-else
                            variant="outline"
                            size="sm"
                            disabled
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>

        <!-- Real-time Toast Container -->
        <RealtimeToastContainer
            :updates="pendingUpdates"
            @dismiss="handleDismissUpdate"
            @refresh="handleRefresh"
            @clear-all="handleClearAll"
        />
    </AppLayout>
</template>
