<script setup lang="ts">
import { computed, ref, watch, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import ConnectionHistoryController from '@/actions/App/Http/Controllers/ConnectionHistoryController';
import ConnectionHistoryRow from '@/components/connections/ConnectionHistoryRow.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { debounce } from '@/lib/utils';
import { Download, FileSpreadsheet, FileText, Loader2, CheckCircle, XCircle, X } from 'lucide-vue-next';

interface ActivityLogData {
    id: number;
    subject_type: string;
    subject_id: number;
    causer_id: number | null;
    causer_name: string;
    causer_role: string | null;
    action: 'created' | 'updated' | 'deleted' | 'restored';
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
    search: string;
}

interface ExportData {
    id: number;
    format: string;
    status: string;
    status_label: string;
    is_completed: boolean;
    download_url: string | null;
    progress_percentage: number;
    total_rows: number;
    processed_rows: number;
}

interface Props {
    activityLogs: PaginatedActivityLogs;
    availableActions: string[];
    users: User[];
    filters: Filters;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Connection History',
        href: ConnectionHistoryController.index.url(),
    },
];

// Local filter state
const searchQuery = ref(props.filters.search);
const startDate = ref(props.filters.start_date);
const endDate = ref(props.filters.end_date);
const actionFilter = ref(props.filters.action);
const userFilter = ref(props.filters.user_id);

// Expanded row IDs for detail panel
const expandedRowIds = ref<Set<number>>(new Set());

// Export state
const exportDropdownOpen = ref(false);
const isExporting = ref(false);
const exportModalOpen = ref(false);
const currentExport = ref<ExportData | null>(null);
const exportError = ref<string | null>(null);
let pollInterval: ReturnType<typeof setInterval> | null = null;

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
        ConnectionHistoryController.index.url(),
        {
            search: searchQuery.value || undefined,
            start_date: startDate.value || undefined,
            end_date: endDate.value || undefined,
            action: actionFilter.value || undefined,
            user_id: userFilter.value || undefined,
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

const handleFilterChange = () => {
    applyFilters();
};

// Format action label with proper capitalization
const formatActionLabel = (action: string): string => {
    return action.charAt(0).toUpperCase() + action.slice(1);
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

// Get current filters for export
const getCurrentFilters = () => {
    const filters: Record<string, string | number> = {};
    if (startDate.value) filters.start_date = startDate.value;
    if (endDate.value) filters.end_date = endDate.value;
    if (actionFilter.value) filters.action = actionFilter.value;
    if (userFilter.value) filters.user_id = parseInt(userFilter.value);
    if (searchQuery.value) filters.search = searchQuery.value;
    return filters;
};

// Poll for export status
const pollExportStatus = async (exportId: number) => {
    try {
        const response = await fetch(`/connections/history/export/${exportId}/status`, {
            headers: {
                'Accept': 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('Failed to fetch export status');
        }

        const data = await response.json();
        currentExport.value = data.data;

        // Stop polling if completed or failed
        if (data.data.status === 'completed' || data.data.status === 'failed') {
            if (pollInterval) {
                clearInterval(pollInterval);
                pollInterval = null;
            }
            isExporting.value = false;
        }
    } catch (error) {
        console.error('Error polling export status:', error);
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
        isExporting.value = false;
        exportError.value = 'Failed to check export status';
    }
};

// Initiate export
const initiateExport = async (format: 'csv' | 'pdf') => {
    exportDropdownOpen.value = false;
    isExporting.value = true;
    exportModalOpen.value = true;
    exportError.value = null;
    currentExport.value = null;

    try {
        const filters = getCurrentFilters();
        const response = await fetch('/connections/history/export', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({
                format,
                ...filters,
            }),
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.message || 'Failed to initiate export');
        }

        const data = await response.json();
        currentExport.value = data.data;

        // If not completed, start polling
        if (data.data.status !== 'completed') {
            pollInterval = setInterval(() => {
                pollExportStatus(data.data.id);
            }, 1000);
        } else {
            isExporting.value = false;
        }
    } catch (error) {
        console.error('Export error:', error);
        isExporting.value = false;
        exportError.value = error instanceof Error ? error.message : 'An error occurred';
    }
};

const handleExportCSV = () => {
    initiateExport('csv');
};

const handleExportPDF = () => {
    initiateExport('pdf');
};

// Close export modal
const closeExportModal = () => {
    exportModalOpen.value = false;
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
};

// Download export file
const downloadExport = () => {
    if (currentExport.value?.download_url) {
        window.open(currentExport.value.download_url, '_blank');
    }
};

// Clean up on unmount
onUnmounted(() => {
    if (pollInterval) {
        clearInterval(pollInterval);
    }
});

// Export status display helpers
const getExportStatusVariant = (status: string): 'default' | 'secondary' | 'destructive' | 'success' => {
    switch (status) {
        case 'completed':
            return 'success';
        case 'failed':
            return 'destructive';
        case 'processing':
            return 'default';
        default:
            return 'secondary';
    }
};
</script>

<template>
    <Head title="Connection History" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <HeadingSmall
                    title="Connection History"
                    description="View and filter all connection changes across the system."
                />

                <!-- Export buttons -->
                <div class="relative">
                    <Button
                        variant="outline"
                        size="sm"
                        @click="exportDropdownOpen = !exportDropdownOpen"
                        :disabled="isExporting"
                    >
                        <Loader2 v-if="isExporting" class="mr-2 h-4 w-4 animate-spin" />
                        <Download v-else class="mr-2 h-4 w-4" />
                        Export
                    </Button>

                    <div
                        v-if="exportDropdownOpen"
                        class="absolute right-0 z-10 mt-2 w-48 rounded-md border bg-popover p-1 shadow-lg"
                    >
                        <button
                            class="flex w-full items-center gap-2 rounded-sm px-3 py-2 text-sm hover:bg-accent"
                            @click="handleExportCSV"
                        >
                            <FileSpreadsheet class="h-4 w-4" />
                            Export as CSV
                        </button>
                        <button
                            class="flex w-full items-center gap-2 rounded-sm px-3 py-2 text-sm hover:bg-accent"
                            @click="handleExportPDF"
                        >
                            <FileText class="h-4 w-4" />
                            Export as PDF
                        </button>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-col gap-4">
                <!-- First row: Search and Date filters -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
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
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <!-- Action Filter -->
                    <select
                        v-model="actionFilter"
                        @change="handleFilterChange"
                        class="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-ring"
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
                        class="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-ring"
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
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto rounded-md border">
                <table class="w-full min-w-[800px] text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="h-12 px-4 text-left font-medium text-muted-foreground">Timestamp</th>
                            <th class="h-12 px-4 text-left font-medium text-muted-foreground">User</th>
                            <th class="h-12 px-4 text-left font-medium text-muted-foreground">Action</th>
                            <th class="h-12 px-4 text-left font-medium text-muted-foreground">Connection</th>
                            <th class="h-12 px-4 text-left font-medium text-muted-foreground">Summary</th>
                            <th class="hidden h-12 px-4 text-left font-medium text-muted-foreground lg:table-cell">IP Address</th>
                            <th class="h-12 w-12 px-4"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="log in activityLogs.data" :key="log.id">
                            <ConnectionHistoryRow
                                :id="log.id"
                                :subject-type="log.subject_type"
                                :subject-id="log.subject_id"
                                :action="log.action"
                                :causer-name="log.causer_name"
                                :causer-role="log.causer_role"
                                :ip-address="log.ip_address"
                                :old-values="log.old_values"
                                :new-values="log.new_values"
                                :created-at="log.created_at"
                                :is-expanded="isRowExpanded(log.id)"
                                @toggle="toggleRowExpansion(log.id)"
                            />
                        </template>
                        <tr v-if="activityLogs.data.length === 0">
                            <td colspan="7" class="p-8 text-center text-muted-foreground">
                                No connection history found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="activityLogs.last_page > 1" class="flex flex-col items-center justify-between gap-4 sm:flex-row">
                <div class="text-sm text-muted-foreground">
                    Showing {{ paginationInfo.from }} to {{ paginationInfo.to }} of {{ paginationInfo.total }} results
                </div>
                <div class="flex flex-wrap gap-1">
                    <Button
                        v-for="link in activityLogs.links"
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

        <!-- Export Status Modal -->
        <div
            v-if="exportModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            @click.self="closeExportModal"
        >
            <div class="w-full max-w-md rounded-lg bg-background p-6 shadow-xl">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold">Export Connection History</h3>
                    <Button variant="ghost" size="icon-sm" @click="closeExportModal">
                        <X class="h-4 w-4" />
                    </Button>
                </div>

                <!-- Error state -->
                <div v-if="exportError" class="flex flex-col items-center gap-4 py-4">
                    <XCircle class="h-12 w-12 text-destructive" />
                    <p class="text-center text-muted-foreground">{{ exportError }}</p>
                    <Button variant="outline" @click="closeExportModal">Close</Button>
                </div>

                <!-- Loading/Processing state -->
                <div v-else-if="isExporting || (currentExport && currentExport.status === 'processing')" class="flex flex-col items-center gap-4 py-4">
                    <Loader2 class="h-12 w-12 animate-spin text-primary" />
                    <p class="text-center text-muted-foreground">
                        {{ currentExport ? 'Processing export...' : 'Initiating export...' }}
                    </p>
                    <div v-if="currentExport" class="w-full">
                        <div class="flex justify-between text-sm text-muted-foreground mb-1">
                            <span>Progress</span>
                            <span>{{ currentExport.progress_percentage?.toFixed(0) || 0 }}%</span>
                        </div>
                        <div class="h-2 w-full rounded-full bg-muted">
                            <div
                                class="h-2 rounded-full bg-primary transition-all"
                                :style="{ width: `${currentExport.progress_percentage || 0}%` }"
                            />
                        </div>
                        <p class="mt-2 text-center text-xs text-muted-foreground">
                            {{ currentExport.processed_rows || 0 }} / {{ currentExport.total_rows || 0 }} rows
                        </p>
                    </div>
                </div>

                <!-- Completed state -->
                <div v-else-if="currentExport && currentExport.status === 'completed'" class="flex flex-col items-center gap-4 py-4">
                    <CheckCircle class="h-12 w-12 text-green-500" />
                    <p class="text-center font-medium">Export completed successfully!</p>
                    <p class="text-center text-sm text-muted-foreground">
                        {{ currentExport.total_rows }} records exported as {{ currentExport.format?.toUpperCase() }}
                    </p>
                    <div class="flex gap-2">
                        <Button variant="outline" @click="closeExportModal">Close</Button>
                        <Button @click="downloadExport">
                            <Download class="mr-2 h-4 w-4" />
                            Download
                        </Button>
                    </div>
                </div>

                <!-- Failed state -->
                <div v-else-if="currentExport && currentExport.status === 'failed'" class="flex flex-col items-center gap-4 py-4">
                    <XCircle class="h-12 w-12 text-destructive" />
                    <p class="text-center font-medium">Export failed</p>
                    <p class="text-center text-sm text-muted-foreground">
                        An error occurred while generating the export. Please try again.
                    </p>
                    <Button variant="outline" @click="closeExportModal">Close</Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
