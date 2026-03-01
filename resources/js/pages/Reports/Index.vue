<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AuditController from '@/actions/App/Http/Controllers/AuditController';
import AuditReportController from '@/actions/App/Http/Controllers/AuditReportController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Download, FileBarChart, ExternalLink, Search, ChevronDown, ChevronUp, X } from 'lucide-vue-next';
import { ref, watch, computed } from 'vue';
import debounce from 'lodash/debounce';

interface ReportData {
    id: number;
    audit_id: number;
    audit_name: string | null;
    datacenter_name: string | null;
    generator_name: string | null;
    generated_at: string | null;
    generated_at_formatted: string | null;
    file_size_bytes: number;
    file_size_formatted: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedReports {
    data: ReportData[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface DatacenterOption {
    id: number;
    name: string;
}

interface Filters {
    datacenter_id: string;
    search: string;
    date_from: string;
    date_to: string;
    sort: string;
    direction: string;
}

interface Props {
    reports: PaginatedReports;
    filters: Filters;
    datacenterOptions: DatacenterOption[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Reports',
        href: AuditReportController.index.url(),
    },
];

// Local filter state
const localFilters = ref({
    datacenter_id: props.filters.datacenter_id || '',
    search: props.filters.search || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
    sort: props.filters.sort || 'generated_at',
    direction: props.filters.direction || 'desc',
});

// Determine if filters are active
const hasActiveFilters = computed(() => {
    return localFilters.value.datacenter_id !== '' ||
        localFilters.value.search !== '' ||
        localFilters.value.date_from !== '' ||
        localFilters.value.date_to !== '';
});

// Common select styling
const selectClass = 'flex h-10 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50';

/**
 * Apply filters and navigate to the filtered URL
 */
function applyFilters(): void {
    const params: Record<string, string> = {};

    if (localFilters.value.datacenter_id) {
        params.datacenter_id = localFilters.value.datacenter_id;
    }
    if (localFilters.value.search) {
        params.search = localFilters.value.search;
    }
    if (localFilters.value.date_from) {
        params.date_from = localFilters.value.date_from;
    }
    if (localFilters.value.date_to) {
        params.date_to = localFilters.value.date_to;
    }
    if (localFilters.value.sort) {
        params.sort = localFilters.value.sort;
    }
    if (localFilters.value.direction) {
        params.direction = localFilters.value.direction;
    }

    router.get(AuditReportController.index.url(), params, {
        preserveState: true,
        preserveScroll: true,
    });
}

/**
 * Clear all filters
 */
function clearFilters(): void {
    localFilters.value = {
        datacenter_id: '',
        search: '',
        date_from: '',
        date_to: '',
        sort: 'generated_at',
        direction: 'desc',
    };
    applyFilters();
}

/**
 * Handle sort column click
 */
function sortBy(column: string): void {
    if (localFilters.value.sort === column) {
        // Toggle direction
        localFilters.value.direction = localFilters.value.direction === 'asc' ? 'desc' : 'asc';
    } else {
        localFilters.value.sort = column;
        localFilters.value.direction = 'desc';
    }
    applyFilters();
}

/**
 * Get sort icon for a column
 */
function getSortIcon(column: string): 'asc' | 'desc' | null {
    if (localFilters.value.sort !== column) return null;
    return localFilters.value.direction as 'asc' | 'desc';
}

// Debounced search
const debouncedSearch = debounce(() => {
    applyFilters();
}, 300);

// Watch search input
watch(() => localFilters.value.search, () => {
    debouncedSearch();
});

// Handle datacenter select change
function handleDatacenterChange(event: Event): void {
    localFilters.value.datacenter_id = (event.target as HTMLSelectElement).value;
    applyFilters();
}

// Handle date filter changes
function handleDateFromChange(event: Event): void {
    localFilters.value.date_from = (event.target as HTMLInputElement).value;
    applyFilters();
}

function handleDateToChange(event: Event): void {
    localFilters.value.date_to = (event.target as HTMLInputElement).value;
    applyFilters();
}
</script>

<template>
    <Head title="Audit Reports" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <HeadingSmall
                    title="Audit Reports"
                    description="View and download generated audit reports."
                />
            </div>

            <!-- Filters -->
            <Card>
                <CardContent class="pt-4">
                    <div class="flex flex-col gap-4">
                        <!-- Mobile stacked filters -->
                        <div class="flex flex-col gap-3 lg:hidden">
                            <div class="relative">
                                <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    v-model="localFilters.search"
                                    type="text"
                                    placeholder="Search by audit name..."
                                    class="pl-9"
                                />
                            </div>

                            <select
                                :class="selectClass"
                                :value="localFilters.datacenter_id"
                                @change="handleDatacenterChange"
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

                            <div class="flex gap-2">
                                <Input
                                    :value="localFilters.date_from"
                                    type="date"
                                    placeholder="From date"
                                    class="flex-1"
                                    @change="handleDateFromChange"
                                />
                                <Input
                                    :value="localFilters.date_to"
                                    type="date"
                                    placeholder="To date"
                                    class="flex-1"
                                    @change="handleDateToChange"
                                />
                            </div>
                        </div>

                        <!-- Desktop row filters -->
                        <div class="hidden gap-4 lg:flex">
                            <div class="relative flex-1">
                                <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    v-model="localFilters.search"
                                    type="text"
                                    placeholder="Search by audit name..."
                                    class="pl-9"
                                />
                            </div>

                            <select
                                :class="[selectClass, 'w-[200px]']"
                                :value="localFilters.datacenter_id"
                                @change="handleDatacenterChange"
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

                            <Input
                                :value="localFilters.date_from"
                                type="date"
                                placeholder="From"
                                class="w-[150px]"
                                @change="handleDateFromChange"
                            />
                            <Input
                                :value="localFilters.date_to"
                                type="date"
                                placeholder="To"
                                class="w-[150px]"
                                @change="handleDateToChange"
                            />

                            <Button
                                v-if="hasActiveFilters"
                                variant="ghost"
                                size="sm"
                                @click="clearFilters"
                            >
                                <X class="mr-1 size-4" />
                                Clear
                            </Button>
                        </div>

                        <!-- Mobile clear filters button -->
                        <Button
                            v-if="hasActiveFilters"
                            variant="ghost"
                            size="sm"
                            class="self-start lg:hidden"
                            @click="clearFilters"
                        >
                            <X class="mr-1 size-4" />
                            Clear Filters
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Reports Table (Desktop) -->
            <div class="hidden overflow-hidden rounded-lg border bg-card md:block">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th
                                class="h-11 cursor-pointer px-4 text-left font-medium text-muted-foreground hover:text-foreground"
                                @click="sortBy('audit_name')"
                            >
                                <span class="flex items-center gap-1">
                                    Audit Name
                                    <ChevronUp v-if="getSortIcon('audit_name') === 'asc'" class="size-4" />
                                    <ChevronDown v-else-if="getSortIcon('audit_name') === 'desc'" class="size-4" />
                                </span>
                            </th>
                            <th class="h-11 px-4 text-left font-medium text-muted-foreground">
                                Datacenter
                            </th>
                            <th
                                class="h-11 cursor-pointer px-4 text-left font-medium text-muted-foreground hover:text-foreground"
                                @click="sortBy('generated_at')"
                            >
                                <span class="flex items-center gap-1">
                                    Generated Date
                                    <ChevronUp v-if="getSortIcon('generated_at') === 'asc'" class="size-4" />
                                    <ChevronDown v-else-if="getSortIcon('generated_at') === 'desc'" class="size-4" />
                                </span>
                            </th>
                            <th class="h-11 px-4 text-left font-medium text-muted-foreground">
                                Generated By
                            </th>
                            <th class="h-11 px-4 text-left font-medium text-muted-foreground">
                                File Size
                            </th>
                            <th class="h-11 w-[150px] px-4 text-left font-medium text-muted-foreground">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="report in reports.data"
                            :key="report.id"
                            class="border-b transition-colors hover:bg-muted/50"
                        >
                            <td class="p-4 font-medium">
                                {{ report.audit_name || 'Unknown Audit' }}
                            </td>
                            <td class="p-4 text-muted-foreground">
                                {{ report.datacenter_name || '-' }}
                            </td>
                            <td class="p-4">
                                {{ report.generated_at_formatted || report.generated_at || '-' }}
                            </td>
                            <td class="p-4 text-muted-foreground">
                                {{ report.generator_name || 'Unknown' }}
                            </td>
                            <td class="p-4 text-muted-foreground">
                                {{ report.file_size_formatted }}
                            </td>
                            <td class="p-4">
                                <div class="flex gap-2">
                                    <a :href="AuditReportController.download.url(report.id)">
                                        <Button variant="outline" size="sm">
                                            <Download class="mr-1 size-3" />
                                            Download
                                        </Button>
                                    </a>
                                    <Link :href="AuditController.show.url(report.audit_id)">
                                        <Button variant="ghost" size="sm" title="View Audit">
                                            <ExternalLink class="size-4" />
                                        </Button>
                                    </Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="reports.data.length === 0">
                            <td colspan="6" class="p-8 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <FileBarChart class="size-8 text-muted-foreground/50" />
                                    <p class="text-sm text-muted-foreground">
                                        No reports found.
                                    </p>
                                    <p v-if="hasActiveFilters" class="text-xs text-muted-foreground">
                                        Try adjusting your filters.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Reports Cards (Mobile) -->
            <div class="space-y-3 md:hidden">
                <Card
                    v-for="report in reports.data"
                    :key="report.id"
                    class="overflow-hidden"
                >
                    <CardContent class="p-4">
                        <div class="mb-3 flex items-start justify-between gap-2">
                            <div class="flex-1">
                                <p class="font-medium">{{ report.audit_name || 'Unknown Audit' }}</p>
                                <p class="text-sm text-muted-foreground">{{ report.datacenter_name || '-' }}</p>
                            </div>
                            <span class="shrink-0 text-xs text-muted-foreground">{{ report.file_size_formatted }}</span>
                        </div>
                        <div class="mb-3 text-sm">
                            <p class="text-muted-foreground">
                                {{ report.generated_at_formatted || report.generated_at || '-' }} by {{ report.generator_name || 'Unknown' }}
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <a :href="AuditReportController.download.url(report.id)" class="flex-1">
                                <Button variant="outline" size="sm" class="w-full">
                                    <Download class="mr-1 size-3" />
                                    Download
                                </Button>
                            </a>
                            <Link :href="AuditController.show.url(report.audit_id)">
                                <Button variant="ghost" size="sm" title="View Audit">
                                    <ExternalLink class="size-4" />
                                </Button>
                            </Link>
                        </div>
                    </CardContent>
                </Card>

                <div v-if="reports.data.length === 0" class="py-8 text-center">
                    <FileBarChart class="mx-auto mb-2 size-8 text-muted-foreground/50" />
                    <p class="text-sm text-muted-foreground">No reports found.</p>
                    <p v-if="hasActiveFilters" class="mt-1 text-xs text-muted-foreground">
                        Try adjusting your filters.
                    </p>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="reports.last_page > 1" class="flex items-center justify-between border-t pt-4">
                <p class="text-sm text-muted-foreground">
                    Showing {{ (reports.current_page - 1) * reports.per_page + 1 }} to
                    {{ Math.min(reports.current_page * reports.per_page, reports.total) }} of
                    {{ reports.total }} reports
                </p>
                <div class="flex gap-1">
                    <template v-for="link in reports.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="inline-flex h-8 items-center justify-center rounded-md border px-3 text-sm transition-colors hover:bg-accent"
                            :class="{
                                'bg-primary text-primary-foreground hover:bg-primary/90': link.active,
                                'border-transparent': !link.active,
                            }"
                            preserve-scroll
                        >
                            <span v-html="link.label" />
                        </Link>
                        <span
                            v-else
                            class="inline-flex h-8 items-center justify-center px-3 text-sm text-muted-foreground"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
