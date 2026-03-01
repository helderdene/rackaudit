<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuditController from '@/actions/App/Http/Controllers/AuditController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { debounce } from '@/lib/utils';

interface AuditData {
    id: number;
    name: string;
    description: string | null;
    due_date: string | null;
    type: string;
    type_label: string;
    scope_type: string;
    scope_type_label: string;
    status: string;
    status_label: string;
    datacenter: {
        id: number;
        name: string;
    };
    room: {
        id: number;
        name: string;
    } | null;
    assignees_count: number;
    created_at: string | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedAudits {
    data: AuditData[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface StatusOption {
    value: string;
    label: string;
}

interface TypeOption {
    value: string;
    label: string;
}

interface Filters {
    search: string;
    status: string;
    type: string;
}

interface Props {
    audits: PaginatedAudits;
    filters: Filters;
    statusOptions: StatusOption[];
    typeOptions: TypeOption[];
    canCreate: boolean;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Audits',
        href: AuditController.index.url(),
    },
];

// Local filter state
const searchQuery = ref(props.filters.search);
const statusFilter = ref(props.filters.status);
const typeFilter = ref(props.filters.type);

// Debounced search function
const debouncedSearch = debounce(() => {
    router.get(
        AuditController.index.url(),
        {
            search: searchQuery.value || undefined,
            status: statusFilter.value || undefined,
            type: typeFilter.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        }
    );
}, 300);

watch(searchQuery, () => {
    debouncedSearch();
});

watch([statusFilter, typeFilter], () => {
    router.get(
        AuditController.index.url(),
        {
            search: searchQuery.value || undefined,
            status: statusFilter.value || undefined,
            type: typeFilter.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        }
    );
});

// Clear all filters
const clearFilters = () => {
    searchQuery.value = '';
    statusFilter.value = '';
    typeFilter.value = '';
    router.get(AuditController.index.url(), {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Navigate to page
const goToPage = (url: string | null) => {
    if (url) {
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }
};

// Get status badge classes
const getStatusBadgeClass = (status: string): string => {
    const baseClasses = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium';
    switch (status) {
        case 'pending':
            return `${baseClasses} bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200`;
        case 'in_progress':
            return `${baseClasses} bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200`;
        case 'completed':
            return `${baseClasses} bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200`;
        case 'cancelled':
            return `${baseClasses} bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300`;
        default:
            return `${baseClasses} bg-gray-100 text-gray-800`;
    }
};

// Get type badge classes
const getTypeBadgeClass = (type: string): string => {
    const baseClasses = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium';
    switch (type) {
        case 'connection':
            return `${baseClasses} bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200`;
        case 'inventory':
            return `${baseClasses} bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200`;
        default:
            return `${baseClasses} bg-gray-100 text-gray-800`;
    }
};

// Common select styling for filters
const selectClass = 'flex h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';
</script>

<template>
    <Head title="Audits" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 md:p-6">
            <!-- Header - responsive layout -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <HeadingSmall
                    title="Audit Management"
                    description="Create and manage datacenter audits for connection verification and inventory checks."
                />
                <div class="flex items-center gap-2">
                    <Link v-if="canCreate" :href="AuditController.create.url()">
                        <Button class="w-full sm:w-auto">Create Audit</Button>
                    </Link>
                </div>
            </div>

            <!-- Filters - responsive stacking -->
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:gap-4">
                <div class="flex-1">
                    <Input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Search by audit name..."
                        class="w-full lg:max-w-sm"
                    />
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <select
                        v-model="statusFilter"
                        :class="selectClass"
                        class="flex-1 sm:flex-none"
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
                    <select
                        v-model="typeFilter"
                        :class="selectClass"
                        class="flex-1 sm:flex-none"
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
                    <Button
                        v-if="searchQuery || statusFilter || typeFilter"
                        variant="ghost"
                        size="sm"
                        @click="clearFilters"
                    >
                        Clear
                    </Button>
                </div>
            </div>

            <!-- Mobile card view (visible on small screens) -->
            <div class="space-y-3 md:hidden">
                <div
                    v-for="audit in audits.data"
                    :key="audit.id"
                    class="rounded-lg border bg-card p-4 shadow-sm"
                >
                    <div class="mb-3 flex items-start justify-between gap-2">
                        <div class="flex-1">
                            <h3 class="font-medium text-foreground">{{ audit.name }}</h3>
                            <p class="text-sm text-muted-foreground">{{ audit.datacenter.name }}</p>
                        </div>
                        <span :class="getStatusBadgeClass(audit.status)">
                            {{ audit.status_label }}
                        </span>
                    </div>
                    <div class="mb-3 flex flex-wrap gap-2">
                        <span :class="getTypeBadgeClass(audit.type)">
                            {{ audit.type_label }}
                        </span>
                        <span class="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground">
                            {{ audit.scope_type_label }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between text-sm text-muted-foreground">
                        <div class="flex items-center gap-4">
                            <span v-if="audit.due_date">Due: {{ audit.due_date }}</span>
                            <span>{{ audit.assignees_count }} assignee{{ audit.assignees_count !== 1 ? 's' : '' }}</span>
                        </div>
                        <Link :href="AuditController.show.url(audit.id)">
                            <Button variant="outline" size="sm">View</Button>
                        </Link>
                    </div>
                </div>
                <div v-if="audits.data.length === 0" class="rounded-lg border border-dashed py-12 text-center text-muted-foreground">
                    No audits found.
                </div>
            </div>

            <!-- Desktop table view (hidden on small screens) -->
            <div class="hidden overflow-hidden rounded-md border md:block">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/50">
                            <tr>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Name</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Type</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Scope</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground lg:table-cell">Datacenter</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Status</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Due Date</th>
                                <th class="hidden h-12 px-4 text-left font-medium text-muted-foreground xl:table-cell">Assignees</th>
                                <th class="h-12 w-[100px] px-4 text-left font-medium text-muted-foreground">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="audit in audits.data"
                                :key="audit.id"
                                class="border-b transition-colors hover:bg-muted/50"
                            >
                                <td class="p-4 font-medium">
                                    {{ audit.name }}
                                </td>
                                <td class="p-4">
                                    <span :class="getTypeBadgeClass(audit.type)">
                                        {{ audit.type_label }}
                                    </span>
                                </td>
                                <td class="p-4">
                                    {{ audit.scope_type_label }}
                                </td>
                                <td class="p-4 lg:table-cell">
                                    {{ audit.datacenter.name }}
                                </td>
                                <td class="p-4">
                                    <span :class="getStatusBadgeClass(audit.status)">
                                        {{ audit.status_label }}
                                    </span>
                                </td>
                                <td class="p-4">
                                    {{ audit.due_date || '-' }}
                                </td>
                                <td class="hidden p-4 xl:table-cell">
                                    {{ audit.assignees_count }}
                                </td>
                                <td class="p-4">
                                    <Link :href="AuditController.show.url(audit.id)">
                                        <Button variant="outline" size="sm">View</Button>
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="audits.data.length === 0">
                                <td colspan="8" class="p-8 text-center text-muted-foreground">
                                    No audits found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination - responsive layout -->
            <div v-if="audits.last_page > 1" class="flex flex-col items-center justify-between gap-4 sm:flex-row">
                <div class="text-sm text-muted-foreground">
                    Showing {{ (audits.current_page - 1) * audits.per_page + 1 }} to
                    {{ Math.min(audits.current_page * audits.per_page, audits.total) }} of
                    {{ audits.total }} audits
                </div>
                <div class="flex flex-wrap justify-center gap-1">
                    <Button
                        v-for="link in audits.links"
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
