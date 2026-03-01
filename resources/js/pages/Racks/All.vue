<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { show } from '@/actions/App/Http/Controllers/RackController';
import { show as showDatacenter } from '@/actions/App/Http/Controllers/DatacenterController';
import { create as createExport } from '@/actions/App/Http/Controllers/BulkExportController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { SelectOption } from '@/types/rooms';
import { debounce } from '@/lib/utils';
import { usePermissions } from '@/composables/usePermissions';
import { Download } from 'lucide-vue-next';

interface RackLocation {
    datacenter_id: number;
    datacenter_name: string;
    room_id: number;
    room_name: string;
    row_id: number;
    row_name: string;
}

interface RackListItem {
    id: number;
    name: string;
    position: number;
    u_height: number | null;
    u_height_label: string | null;
    serial_number: string | null;
    status: string | null;
    status_label: string | null;
    pdu_count: number;
    device_count: number;
    location: RackLocation | null;
}

interface PaginatedRacks {
    data: RackListItem[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    racks: PaginatedRacks;
    statusOptions: SelectOption[];
    filters: {
        search: string;
        status: string;
    };
    canCreate: boolean;
}

const props = defineProps<Props>();

const { hasAnyRole } = usePermissions();
const canExport = hasAnyRole(['Administrator', 'IT Manager']);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Racks',
        href: '/racks',
    },
];

// Local filter state
const searchQuery = ref(props.filters.search);
const statusFilter = ref(props.filters.status);

// Debounced search function
const debouncedSearch = debounce(() => {
    router.get('/racks', {
        search: searchQuery.value || undefined,
        status: statusFilter.value || undefined,
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
    router.get('/racks', {
        search: searchQuery.value || undefined,
        status: statusFilter.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
});

// Clear all filters
const clearFilters = () => {
    searchQuery.value = '';
    statusFilter.value = '';
    router.get('/racks', {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Get status badge variant
const getStatusVariant = (status: string | null): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (status) {
        case 'active':
            return 'default';
        case 'inactive':
            return 'secondary';
        case 'maintenance':
            return 'destructive';
        default:
            return 'outline';
    }
};

// Navigate to export create page with rack entity type pre-selected
const exportUrl = createExport.url({ query: { entity_type: 'rack' } });
</script>

<template>
    <Head title="Racks" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <HeadingSmall
                    title="Rack Management"
                    description="View and manage all racks across datacenters."
                />
                <div class="flex items-center gap-2">
                    <Link v-if="canExport" :href="exportUrl">
                        <Button variant="outline">
                            <Download class="mr-2 h-4 w-4" />
                            Export
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                <div class="flex-1">
                    <Input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Search by name or serial number..."
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
                            v-for="option in statusOptions"
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
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Location</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">U-Height</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Devices</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">PDUs</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Status</th>
                                <th class="h-12 w-[100px] px-4 text-left font-medium text-muted-foreground">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="rack in racks.data"
                                :key="rack.id"
                                class="border-b transition-colors hover:bg-muted/50"
                            >
                                <td class="p-4 font-medium">
                                    <Link
                                        v-if="rack.location"
                                        :href="show.url({
                                            datacenter: rack.location.datacenter_id,
                                            room: rack.location.room_id,
                                            row: rack.location.row_id,
                                            rack: rack.id
                                        })"
                                        class="hover:underline"
                                    >
                                        {{ rack.name }}
                                    </Link>
                                    <span v-else>{{ rack.name }}</span>
                                </td>
                                <td class="p-4 text-muted-foreground">
                                    <template v-if="rack.location">
                                        <Link
                                            :href="showDatacenter.url(rack.location.datacenter_id)"
                                            class="hover:underline"
                                        >
                                            {{ rack.location.datacenter_name }}
                                        </Link>
                                        <span class="mx-1">/</span>
                                        <span>{{ rack.location.room_name }}</span>
                                        <span class="mx-1">/</span>
                                        <span>{{ rack.location.row_name }}</span>
                                    </template>
                                    <span v-else class="italic">Unknown</span>
                                </td>
                                <td class="p-4">{{ rack.u_height_label || '-' }}</td>
                                <td class="p-4 text-muted-foreground">{{ rack.device_count }}</td>
                                <td class="p-4 text-muted-foreground">{{ rack.pdu_count }}</td>
                                <td class="p-4">
                                    <Badge :variant="getStatusVariant(rack.status)">
                                        {{ rack.status_label || 'Unknown' }}
                                    </Badge>
                                </td>
                                <td class="p-4">
                                    <div class="flex items-center gap-2">
                                        <Link
                                            v-if="rack.location"
                                            :href="show.url({
                                                datacenter: rack.location.datacenter_id,
                                                room: rack.location.room_id,
                                                row: rack.location.row_id,
                                                rack: rack.id
                                            })"
                                        >
                                            <Button variant="outline" size="sm">View</Button>
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="racks.data.length === 0">
                                <td colspan="7" class="p-8 text-center text-muted-foreground">
                                    No racks found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="racks.last_page > 1" class="flex items-center justify-between">
                <p class="text-sm text-muted-foreground">
                    Showing {{ (racks.current_page - 1) * racks.per_page + 1 }} to
                    {{ Math.min(racks.current_page * racks.per_page, racks.total) }} of
                    {{ racks.total }} racks
                </p>
                <div class="flex gap-1">
                    <template v-for="link in racks.links" :key="link.label">
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
    </AppLayout>
</template>
