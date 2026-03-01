<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import FindingController from '@/actions/App/Http/Controllers/FindingController';
import AuditController from '@/actions/App/Http/Controllers/AuditController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import BulkActionToolbar from '@/components/BulkActionToolbar.vue';
import DueDateIndicator from '@/components/DueDateIndicator.vue';
import RealtimeToastContainer from '@/components/notifications/RealtimeToastContainer.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { debounce } from '@/lib/utils';
import { useRealtimeUpdates } from '@/composables/useRealtimeUpdates';
import type {
    FindingsIndexProps,
    FindingListData,
    FindingSeverityValue,
    FindingStatusValue,
    BulkOperationResponse,
} from '@/types/finding';
import axios from 'axios';

const props = defineProps<FindingsIndexProps>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Findings',
        href: FindingController.index.url(),
    },
];

// Real-time updates integration (null datacenter ID to listen to all updates)
const {
    pendingUpdates,
    dismissUpdate,
    clearUpdates,
    onDataChange,
} = useRealtimeUpdates(null);

// Register handler for finding changes
onDataChange('finding', (data) => {
    // Toast will be automatically shown via pendingUpdates
    console.log('Finding changed:', data);
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
const statusFilter = ref(props.filters.status);
const severityFilter = ref(props.filters.severity);
const categoryFilter = ref(props.filters.category);
const auditFilter = ref(props.filters.audit_id);
const assigneeFilter = ref(props.filters.assigned_to);
const dueDateFilter = ref(props.filters.due_date_status);

// Bulk selection state
const selectedFindingIds = ref<number[]>([]);
const processingBulkAssign = ref(false);
const processingBulkStatus = ref(false);

// Check if all visible findings are selected
const allSelected = computed(() => {
    if (props.findings.data.length === 0) return false;
    return props.findings.data.every((finding) =>
        selectedFindingIds.value.includes(finding.id)
    );
});

// Check if some but not all are selected
const someSelected = computed(() => {
    return selectedFindingIds.value.length > 0 && !allSelected.value;
});

// Toggle select all
const toggleSelectAll = () => {
    if (allSelected.value) {
        // Deselect all
        selectedFindingIds.value = [];
    } else {
        // Select all visible
        selectedFindingIds.value = props.findings.data.map((f) => f.id);
    }
};

// Toggle individual selection
const toggleSelection = (findingId: number) => {
    const index = selectedFindingIds.value.indexOf(findingId);
    if (index === -1) {
        selectedFindingIds.value.push(findingId);
    } else {
        selectedFindingIds.value.splice(index, 1);
    }
};

// Check if a finding is selected
const isSelected = (findingId: number): boolean => {
    return selectedFindingIds.value.includes(findingId);
};

// Handle bulk assign
const handleBulkAssign = async (userId: number) => {
    processingBulkAssign.value = true;
    try {
        const response = await axios.post<BulkOperationResponse>('/findings/bulk-assign', {
            finding_ids: selectedFindingIds.value,
            assigned_to: userId,
        });

        if (response.data.success) {
            selectedFindingIds.value = [];
            router.reload({ only: ['findings'] });
        }
    } catch (error) {
        console.error('Bulk assign failed:', error);
    } finally {
        processingBulkAssign.value = false;
    }
};

// Handle bulk status change
const handleBulkStatus = async (status: FindingStatusValue) => {
    processingBulkStatus.value = true;
    try {
        const response = await axios.post<BulkOperationResponse>('/findings/bulk-status', {
            finding_ids: selectedFindingIds.value,
            status: status,
        });

        if (response.data.success) {
            selectedFindingIds.value = [];
            router.reload({ only: ['findings'] });
        }
    } catch (error) {
        console.error('Bulk status change failed:', error);
    } finally {
        processingBulkStatus.value = false;
    }
};

// Handle bulk defer
const handleBulkDefer = async () => {
    await handleBulkStatus('deferred');
};

// Clear bulk selection
const handleClearSelection = () => {
    selectedFindingIds.value = [];
};

// Debounced search function
const debouncedSearch = debounce(() => {
    applyFilters();
}, 300);

// Apply all filters
const applyFilters = () => {
    router.get(
        FindingController.index.url(),
        {
            search: searchQuery.value || undefined,
            status: statusFilter.value || undefined,
            severity: severityFilter.value || undefined,
            category: categoryFilter.value || undefined,
            audit_id: auditFilter.value || undefined,
            assigned_to: assigneeFilter.value || undefined,
            due_date_status: dueDateFilter.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        }
    );
};

watch(searchQuery, () => {
    debouncedSearch();
});

watch([statusFilter, severityFilter, categoryFilter, auditFilter, assigneeFilter, dueDateFilter], () => {
    applyFilters();
});

// Clear all filters
const clearFilters = () => {
    searchQuery.value = '';
    statusFilter.value = '';
    severityFilter.value = '';
    categoryFilter.value = '';
    auditFilter.value = '';
    assigneeFilter.value = '';
    dueDateFilter.value = '';
    router.get(FindingController.index.url(), {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Check if any filters are active
const hasActiveFilters = (): boolean => {
    return !!(
        searchQuery.value ||
        statusFilter.value ||
        severityFilter.value ||
        categoryFilter.value ||
        auditFilter.value ||
        assigneeFilter.value ||
        dueDateFilter.value
    );
};

// Navigate to page
const goToPage = (url: string | null) => {
    if (url) {
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }
};

// Get severity badge classes based on enum color() method output
const getSeverityBadgeClass = (severity: FindingSeverityValue): string => {
    const baseClasses = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium';
    switch (severity) {
        case 'critical':
            return `${baseClasses} bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400`;
        case 'high':
            return `${baseClasses} bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400`;
        case 'medium':
            return `${baseClasses} bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400`;
        case 'low':
            return `${baseClasses} bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400`;
        default:
            return `${baseClasses} bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300`;
    }
};

// Get status badge classes based on enum color() method output
const getStatusBadgeClass = (status: FindingStatusValue): string => {
    const baseClasses = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium';
    switch (status) {
        case 'open':
            return `${baseClasses} bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400`;
        case 'in_progress':
            return `${baseClasses} bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400`;
        case 'pending_review':
            return `${baseClasses} bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400`;
        case 'deferred':
            return `${baseClasses} bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400`;
        case 'resolved':
            return `${baseClasses} bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400`;
        default:
            return `${baseClasses} bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300`;
    }
};

// Common select styling for filters
const selectClass = 'flex h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';
</script>

<template>
    <Head title="Findings" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 md:p-6">
            <!-- Header - responsive layout -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <HeadingSmall
                    title="Finding Management"
                    description="Track, categorize, and resolve discrepancies discovered during datacenter audits."
                />
            </div>

            <!-- Filters - responsive stacking -->
            <div class="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-center lg:gap-4">
                <div class="flex-1">
                    <Input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Search by title or description..."
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
                        v-model="severityFilter"
                        :class="selectClass"
                        class="flex-1 sm:flex-none"
                    >
                        <option value="">All Severities</option>
                        <option
                            v-for="option in severityOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <select
                        v-model="categoryFilter"
                        :class="selectClass"
                        class="flex-1 sm:flex-none"
                    >
                        <option value="">All Categories</option>
                        <option
                            v-for="option in categoryOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <select
                        v-model="assigneeFilter"
                        :class="selectClass"
                        class="flex-1 sm:flex-none"
                    >
                        <option value="">All Assignees</option>
                        <option
                            v-for="option in assigneeOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <select
                        v-model="dueDateFilter"
                        :class="selectClass"
                        class="flex-1 sm:flex-none"
                    >
                        <option value="">All Due Dates</option>
                        <option
                            v-for="option in dueDateOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <Button
                        v-if="hasActiveFilters()"
                        variant="ghost"
                        size="sm"
                        @click="clearFilters"
                    >
                        Clear
                    </Button>
                </div>
            </div>

            <!-- Bulk Action Toolbar -->
            <BulkActionToolbar
                :selected-count="selectedFindingIds.length"
                :assignee-options="assigneeOptions"
                :status-options="statusOptions"
                :processing-assign="processingBulkAssign"
                :processing-status="processingBulkStatus"
                @assign="handleBulkAssign"
                @change-status="handleBulkStatus"
                @defer="handleBulkDefer"
                @clear="handleClearSelection"
            />

            <!-- Mobile card view (visible on small screens) -->
            <div class="space-y-3 md:hidden">
                <div
                    v-for="finding in findings.data"
                    :key="finding.id"
                    class="rounded-lg border bg-card p-4 shadow-sm"
                >
                    <div class="mb-3 flex items-start gap-3">
                        <!-- Checkbox -->
                        <Checkbox
                            :checked="isSelected(finding.id)"
                            @update:checked="toggleSelection(finding.id)"
                        />
                        <div class="flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex-1">
                                    <h3 class="font-medium text-foreground">{{ finding.title }}</h3>
                                    <p v-if="finding.audit" class="text-sm text-muted-foreground">
                                        <Link
                                            :href="AuditController.show.url(finding.audit.id)"
                                            class="hover:underline"
                                        >
                                            {{ finding.audit.name }}
                                        </Link>
                                    </p>
                                </div>
                                <span :class="getSeverityBadgeClass(finding.severity)">
                                    {{ finding.severity_label }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3 flex flex-wrap gap-2">
                        <span :class="getStatusBadgeClass(finding.status)">
                            {{ finding.status_label }}
                        </span>
                        <span v-if="finding.category" class="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground">
                            {{ finding.category.name }}
                        </span>
                        <DueDateIndicator
                            v-if="finding.due_date"
                            :due-date="finding.due_date"
                            :is-overdue="finding.is_overdue"
                            :is-due-soon="finding.is_due_soon"
                        />
                    </div>
                    <div class="flex items-center justify-between text-sm text-muted-foreground">
                        <div class="flex items-center gap-4">
                            <span v-if="finding.assignee">{{ finding.assignee.name }}</span>
                            <span v-else class="italic">Unassigned</span>
                        </div>
                        <Link :href="FindingController.show.url(finding.id)">
                            <Button variant="outline" size="sm">View</Button>
                        </Link>
                    </div>
                </div>
                <div v-if="findings.data.length === 0" class="rounded-lg border border-dashed py-12 text-center text-muted-foreground">
                    No findings found.
                </div>
            </div>

            <!-- Desktop table view (hidden on small screens) -->
            <div class="hidden overflow-hidden rounded-md border md:block">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/50">
                            <tr>
                                <th class="h-12 w-12 px-4">
                                    <Checkbox
                                        :checked="allSelected"
                                        :indeterminate="someSelected"
                                        @update:checked="toggleSelectAll"
                                    />
                                </th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Title</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Severity</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Status</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Due Date</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Category</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground lg:table-cell">Audit</th>
                                <th class="hidden h-12 px-4 text-left font-medium text-muted-foreground xl:table-cell">Assignee</th>
                                <th class="h-12 w-[100px] px-4 text-left font-medium text-muted-foreground">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="finding in findings.data"
                                :key="finding.id"
                                :class="[
                                    'border-b transition-colors hover:bg-muted/50',
                                    isSelected(finding.id) ? 'bg-muted/30' : '',
                                ]"
                            >
                                <td class="p-4">
                                    <Checkbox
                                        :checked="isSelected(finding.id)"
                                        @update:checked="toggleSelection(finding.id)"
                                    />
                                </td>
                                <td class="p-4 font-medium">
                                    <div class="max-w-xs truncate">{{ finding.title }}</div>
                                </td>
                                <td class="p-4">
                                    <span :class="getSeverityBadgeClass(finding.severity)">
                                        {{ finding.severity_label }}
                                    </span>
                                </td>
                                <td class="p-4">
                                    <span :class="getStatusBadgeClass(finding.status)">
                                        {{ finding.status_label }}
                                    </span>
                                </td>
                                <td class="p-4">
                                    <DueDateIndicator
                                        :due-date="finding.due_date"
                                        :is-overdue="finding.is_overdue"
                                        :is-due-soon="finding.is_due_soon"
                                    />
                                </td>
                                <td class="p-4">
                                    {{ finding.category?.name || '-' }}
                                </td>
                                <td class="p-4 lg:table-cell">
                                    <Link
                                        v-if="finding.audit"
                                        :href="AuditController.show.url(finding.audit.id)"
                                        class="text-primary hover:underline"
                                    >
                                        {{ finding.audit.name }}
                                    </Link>
                                    <span v-else>-</span>
                                </td>
                                <td class="hidden p-4 xl:table-cell">
                                    {{ finding.assignee?.name || '-' }}
                                </td>
                                <td class="p-4">
                                    <Link :href="FindingController.show.url(finding.id)">
                                        <Button variant="outline" size="sm">View</Button>
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="findings.data.length === 0">
                                <td colspan="9" class="p-8 text-center text-muted-foreground">
                                    No findings found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination - responsive layout -->
            <div v-if="findings.last_page > 1" class="flex flex-col items-center justify-between gap-4 sm:flex-row">
                <div class="text-sm text-muted-foreground">
                    Showing {{ (findings.current_page - 1) * findings.per_page + 1 }} to
                    {{ Math.min(findings.current_page * findings.per_page, findings.total) }} of
                    {{ findings.total }} findings
                </div>
                <div class="flex flex-wrap justify-center gap-1">
                    <Button
                        v-for="link in findings.links"
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

        <!-- Real-time Toast Container -->
        <RealtimeToastContainer
            :updates="pendingUpdates"
            @dismiss="handleDismissUpdate"
            @refresh="handleRefresh"
            @clear-all="handleClearAll"
        />
    </AppLayout>
</template>
