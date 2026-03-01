<script setup lang="ts">
import ActivityLogController from '@/actions/App/Http/Controllers/ActivityLogController';
import ActionBadge from '@/components/ActionBadge.vue';
import ActivityDetailPanel from '@/components/activity/ActivityDetailPanel.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { debounce } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface ActivityLogData {
    id: number;
    subject_type: string;
    subject_id: number;
    causer_id: number | null;
    causer_name: string;
    action: 'created' | 'updated' | 'deleted';
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip_address: string;
    user_agent: string | null;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedActivityLogs {
    data: ActivityLogData[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface User {
    id: number;
    name: string;
}

interface Filters {
    start_date: string;
    end_date: string;
    action: string;
    user_id: string;
    subject_type: string;
    search: string;
}

interface Props {
    activityLogs: PaginatedActivityLogs;
    availableActions: string[];
    availableSubjectTypes: string[];
    users: User[];
    filters: Filters;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Activity Logs',
        href: ActivityLogController.index.url(),
    },
];

// Local filter state
const searchQuery = ref(props.filters.search);
const startDate = ref(props.filters.start_date);
const endDate = ref(props.filters.end_date);
const actionFilter = ref(props.filters.action);
const userFilter = ref(props.filters.user_id);
const subjectTypeFilter = ref(props.filters.subject_type);

// Expanded row IDs for detail panel
const expandedRowIds = ref<Set<number>>(new Set());

// Toggle row expansion
const toggleRowExpansion = (id: number) => {
    const newExpanded = new Set(expandedRowIds.value);
    if (newExpanded.has(id)) {
        newExpanded.delete(id);
    } else {
        newExpanded.add(id);
    }
    expandedRowIds.value = newExpanded;
};

const isRowExpanded = (id: number): boolean => {
    return expandedRowIds.value.has(id);
};

// Apply filters with debounced search
const applyFilters = debounce(() => {
    router.get(
        ActivityLogController.index.url(),
        {
            search: searchQuery.value || undefined,
            start_date: startDate.value || undefined,
            end_date: endDate.value || undefined,
            action: actionFilter.value || undefined,
            user_id: userFilter.value || undefined,
            subject_type: subjectTypeFilter.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}, 300);

watch([searchQuery], () => {
    applyFilters();
});

const handleFilterChange = () => {
    applyFilters();
};

// Format timestamp for display
const formatTimestamp = (dateString: string): string => {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffSeconds = Math.floor(diffMs / 1000);
    const diffMinutes = Math.floor(diffSeconds / 60);
    const diffHours = Math.floor(diffMinutes / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffSeconds < 60) {
        return 'Just now';
    } else if (diffMinutes < 60) {
        return `${diffMinutes} minute${diffMinutes > 1 ? 's' : ''} ago`;
    } else if (diffHours < 24) {
        return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
    } else if (diffDays < 7) {
        return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
    } else {
        return date.toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }
};

// Format subject type for display (extract class name)
const formatSubjectType = (subjectType: string): string => {
    const parts = subjectType.split('\\');
    return parts[parts.length - 1];
};

// Format action label with proper capitalization
const formatActionLabel = (action: string): string => {
    return action.charAt(0).toUpperCase() + action.slice(1);
};

// Get summary text from changes
const getSummary = (log: ActivityLogData): string => {
    if (log.action === 'created' && log.new_values) {
        const keys = Object.keys(log.new_values).slice(0, 3);
        if (keys.length === 0) return 'New record created';
        return `Created with ${keys.join(', ')}${Object.keys(log.new_values).length > 3 ? '...' : ''}`;
    } else if (log.action === 'deleted' && log.old_values) {
        const keys = Object.keys(log.old_values).slice(0, 3);
        if (keys.length === 0) return 'Record deleted';
        return `Deleted: ${keys.join(', ')}${Object.keys(log.old_values).length > 3 ? '...' : ''}`;
    } else if (log.action === 'updated' && log.old_values && log.new_values) {
        const changedKeys = Object.keys(log.new_values).filter(
            (key) =>
                JSON.stringify(log.old_values?.[key]) !==
                JSON.stringify(log.new_values?.[key]),
        );
        if (changedKeys.length === 0) return 'No visible changes';
        return `Changed: ${changedKeys.slice(0, 3).join(', ')}${changedKeys.length > 3 ? '...' : ''}`;
    }
    return 'No details available';
};

// Navigate to page
const goToPage = (url: string | null) => {
    if (url) {
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }
};

// Pagination info
const paginationInfo = computed(() => {
    const { current_page, per_page, total } = props.activityLogs;
    const from = (current_page - 1) * per_page + 1;
    const to = Math.min(current_page * per_page, total);
    return { from, to, total };
});
</script>

<template>
    <Head title="Activity Logs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <HeadingSmall
                    title="Activity Logs"
                    description="View and filter activity logs across the system."
                />
            </div>

            <!-- Filters -->
            <div class="flex flex-col gap-4">
                <!-- First row: Search and Date filters -->
                <div
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5"
                >
                    <!-- Search Input -->
                    <div class="lg:col-span-2">
                        <Input
                            v-model="searchQuery"
                            type="search"
                            placeholder="Search in values..."
                            class="w-full"
                        />
                    </div>

                    <!-- Start Date -->
                    <div>
                        <Input
                            v-model="startDate"
                            type="date"
                            @change="handleFilterChange"
                            class="w-full"
                        />
                    </div>

                    <!-- End Date -->
                    <div>
                        <Input
                            v-model="endDate"
                            type="date"
                            @change="handleFilterChange"
                            class="w-full"
                        />
                    </div>
                </div>

                <!-- Second row: Dropdown filters -->
                <div
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <!-- Action Filter -->
                    <select
                        v-model="actionFilter"
                        @change="handleFilterChange"
                        class="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus:ring-2 focus:ring-ring focus:outline-none"
                    >
                        <option value="">All Actions</option>
                        <option
                            v-for="action in availableActions"
                            :key="action"
                            :value="action"
                        >
                            {{ formatActionLabel(action) }}
                        </option>
                    </select>

                    <!-- User Filter -->
                    <select
                        v-model="userFilter"
                        @change="handleFilterChange"
                        class="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus:ring-2 focus:ring-ring focus:outline-none"
                    >
                        <option value="">All Users</option>
                        <option
                            v-for="user in users"
                            :key="user.id"
                            :value="user.id"
                        >
                            {{ user.name }}
                        </option>
                    </select>

                    <!-- Subject Type Filter -->
                    <select
                        v-model="subjectTypeFilter"
                        @change="handleFilterChange"
                        class="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus:ring-2 focus:ring-ring focus:outline-none"
                    >
                        <option value="">All Entity Types</option>
                        <option
                            v-for="type in availableSubjectTypes"
                            :key="type"
                            :value="type"
                        >
                            {{ formatSubjectType(type) }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto rounded-md border">
                <table class="w-full min-w-[800px] text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th
                                class="h-12 px-4 text-left font-medium text-muted-foreground"
                            >
                                Timestamp
                            </th>
                            <th
                                class="h-12 px-4 text-left font-medium text-muted-foreground"
                            >
                                User
                            </th>
                            <th
                                class="h-12 px-4 text-left font-medium text-muted-foreground"
                            >
                                Action
                            </th>
                            <th
                                class="h-12 px-4 text-left font-medium text-muted-foreground"
                            >
                                Entity Type
                            </th>
                            <th
                                class="h-12 px-4 text-left font-medium text-muted-foreground"
                            >
                                Entity ID
                            </th>
                            <th
                                class="h-12 px-4 text-left font-medium text-muted-foreground"
                            >
                                Summary
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <template
                            v-for="log in activityLogs.data"
                            :key="log.id"
                        >
                            <tr
                                class="cursor-pointer border-b transition-colors hover:bg-muted/50"
                                :class="{
                                    'bg-muted/30': isRowExpanded(log.id),
                                }"
                                @click="toggleRowExpansion(log.id)"
                            >
                                <td class="p-4 text-muted-foreground">
                                    {{ formatTimestamp(log.created_at) }}
                                </td>
                                <td class="p-4 font-medium">
                                    {{ log.causer_name }}
                                </td>
                                <td class="p-4">
                                    <ActionBadge :action="log.action" />
                                </td>
                                <td class="p-4">
                                    {{ formatSubjectType(log.subject_type) }}
                                </td>
                                <td class="p-4 font-mono text-xs">
                                    {{ log.subject_id }}
                                </td>
                                <td
                                    class="max-w-[200px] truncate p-4 text-muted-foreground"
                                >
                                    {{ getSummary(log) }}
                                </td>
                            </tr>
                            <!-- Expanded detail panel row -->
                            <tr v-if="isRowExpanded(log.id)">
                                <td colspan="6" class="bg-muted/10 px-4 pb-4">
                                    <ActivityDetailPanel
                                        :old-values="log.old_values"
                                        :new-values="log.new_values"
                                    />
                                </td>
                            </tr>
                        </template>
                        <tr v-if="activityLogs.data.length === 0">
                            <td
                                colspan="6"
                                class="p-8 text-center text-muted-foreground"
                            >
                                No activity logs found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="activityLogs.last_page > 1"
                class="flex flex-col items-center justify-between gap-4 sm:flex-row"
            >
                <div class="text-sm text-muted-foreground">
                    Showing {{ paginationInfo.from }} to
                    {{ paginationInfo.to }} of
                    {{ paginationInfo.total }} results
                </div>
                <div class="flex flex-wrap gap-1">
                    <Button
                        v-for="link in activityLogs.links"
                        :key="link.label"
                        variant="outline"
                        size="sm"
                        :disabled="!link.url || link.active"
                        @click="goToPage(link.url)"
                        ><span v-html="link.label"
                    /></Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
