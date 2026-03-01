<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DatacenterController from '@/actions/App/Http/Controllers/DatacenterController';
import { create as createExport } from '@/actions/App/Http/Controllers/BulkExportController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import DeleteDatacenterDialog from '@/components/datacenters/DeleteDatacenterDialog.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { debounce } from '@/lib/utils';
import { usePermissions } from '@/composables/usePermissions';
import { Download } from 'lucide-vue-next';

interface DatacenterData {
    id: number;
    name: string;
    city: string;
    country: string;
    formatted_location: string;
    primary_contact_name: string;
    primary_contact_email: string;
    primary_contact_phone: string;
    floor_plan_path: string | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedDatacenters {
    data: DatacenterData[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Filters {
    search: string;
}

interface Props {
    datacenters: PaginatedDatacenters;
    filters: Filters;
    canCreate: boolean;
}

const props = defineProps<Props>();

const { hasAnyRole } = usePermissions();
const canExport = hasAnyRole(['Administrator', 'IT Manager']);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Datacenters',
        href: DatacenterController.index.url(),
    },
];

// Local filter state
const searchQuery = ref(props.filters.search);

// Apply filters with debounced search
const applyFilters = debounce(() => {
    router.get(
        DatacenterController.index.url(),
        {
            search: searchQuery.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        }
    );
}, 300);

watch([searchQuery], () => {
    applyFilters();
});

// Navigate to page
const goToPage = (url: string | null) => {
    if (url) {
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }
};

// Navigate to export create page with datacenter entity type pre-selected
const exportUrl = createExport.url({ query: { entity_type: 'datacenter' } });
</script>

<template>
    <Head title="Datacenters" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <HeadingSmall
                    title="Datacenter Management"
                    description="Manage datacenter locations, contacts, and floor plans."
                />
                <div class="flex items-center gap-2">
                    <Link v-if="canExport" :href="exportUrl">
                        <Button variant="outline">
                            <Download class="mr-2 h-4 w-4" />
                            Export
                        </Button>
                    </Link>
                    <Link v-if="canCreate" :href="DatacenterController.create.url()">
                        <Button>Create Datacenter</Button>
                    </Link>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                <div class="flex-1">
                    <Input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Search by name, city, or contact..."
                        class="max-w-sm"
                    />
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
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Primary Contact</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Rooms</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Racks</th>
                                <th class="h-12 w-[180px] px-4 text-left font-medium text-muted-foreground">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="datacenter in datacenters.data"
                                :key="datacenter.id"
                                class="border-b transition-colors hover:bg-muted/50"
                            >
                                <td class="p-4 font-medium">
                                    {{ datacenter.name }}
                                </td>
                                <td class="p-4">
                                    <div class="flex flex-col">
                                        <span>{{ datacenter.city }}</span>
                                        <span class="text-xs text-muted-foreground">{{ datacenter.country }}</span>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <div class="flex flex-col">
                                        <span>{{ datacenter.primary_contact_name }}</span>
                                        <span class="text-xs text-muted-foreground">{{ datacenter.primary_contact_email }}</span>
                                    </div>
                                </td>
                                <td class="p-4 text-muted-foreground">-</td>
                                <td class="p-4 text-muted-foreground">-</td>
                                <td class="p-4">
                                    <div class="flex items-center gap-2">
                                        <Link :href="DatacenterController.show.url(datacenter.id)">
                                            <Button variant="outline" size="sm">View</Button>
                                        </Link>
                                        <Link :href="DatacenterController.edit.url(datacenter.id)">
                                            <Button variant="outline" size="sm">Edit</Button>
                                        </Link>
                                        <DeleteDatacenterDialog
                                            :datacenter-id="datacenter.id"
                                            :datacenter-name="datacenter.name"
                                        />
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="datacenters.data.length === 0">
                                <td colspan="6" class="p-8 text-center text-muted-foreground">
                                    No datacenters found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="datacenters.last_page > 1" class="flex flex-col items-center justify-between gap-4 sm:flex-row">
                <div class="text-sm text-muted-foreground">
                    Showing {{ (datacenters.current_page - 1) * datacenters.per_page + 1 }} to
                    {{ Math.min(datacenters.current_page * datacenters.per_page, datacenters.total) }} of
                    {{ datacenters.total }} datacenters
                </div>
                <div class="flex flex-wrap gap-1">
                    <Button
                        v-for="link in datacenters.links"
                        :key="link.label"
                        variant="outline"
                        size="sm"
                        :disabled="!link.url || link.active"
                        @click="goToPage(link.url)"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
