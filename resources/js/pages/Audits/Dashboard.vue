<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuditController from '@/actions/App/Http/Controllers/AuditController';
import FindingController from '@/actions/App/Http/Controllers/FindingController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { debounce } from '@/lib/utils';
import SeverityDistributionChart from '@/components/audits/SeverityDistributionChart.vue';
import AuditCompletionTrendChart from '@/components/audits/AuditCompletionTrendChart.vue';
import AuditBreakdownTable from '@/components/audits/AuditBreakdownTable.vue';
import ActiveAuditProgress from '@/components/audits/ActiveAuditProgress.vue';
import type {
    DashboardProps,
    FindingSeverityValue,
} from '@/types/dashboard';

const props = defineProps<DashboardProps>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Audits',
        href: AuditController.index.url(),
    },
    {
        title: 'Dashboard',
        href: AuditController.dashboard.url(),
    },
];

// Local filter state
const datacenterFilter = ref(props.filters.datacenter_id || '');
const timePeriodFilter = ref(props.filters.time_period || '30_days');

// Debounced filter application
const debouncedApplyFilters = debounce(() => {
    applyFilters();
}, 300);

// Apply filters to the page
const applyFilters = () => {
    router.get(
        AuditController.dashboard.url(),
        {
            datacenter_id: datacenterFilter.value || undefined,
            time_period: timePeriodFilter.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        }
    );
};

// Watch for filter changes
watch([datacenterFilter, timePeriodFilter], () => {
    debouncedApplyFilters();
});

// Clear all filters
const clearFilters = () => {
    datacenterFilter.value = '';
    timePeriodFilter.value = '30_days';
    router.get(
        AuditController.dashboard.url(),
        {},
        {
            preserveState: true,
            preserveScroll: true,
        }
    );
};

// Check if any filters are active (beyond default)
const hasActiveFilters = (): boolean => {
    return !!(datacenterFilter.value || timePeriodFilter.value !== '30_days');
};

// Common select styling for filters (matching Index.vue pattern)
const selectClass =
    'flex h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';

// Format average resolution time as hours or days
const formatResolutionTime = (minutes: number | null): string => {
    if (minutes === null || minutes === 0) return 'N/A';
    const hours = Math.round(minutes / 60);
    if (hours < 24) {
        return `${hours} hour${hours !== 1 ? 's' : ''}`;
    }
    const days = Math.round(hours / 24);
    return `${days} day${days !== 1 ? 's' : ''}`;
};

// Get severity badge styling based on severity value
const getSeverityBadgeClass = (severity: FindingSeverityValue): string => {
    const baseClasses = 'inline-flex items-center justify-center rounded-full px-3 py-1 text-sm font-medium transition-colors cursor-pointer hover:opacity-80';
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

// Navigate to findings list with severity filter
const navigateToFindingsBySeverity = (severity: FindingSeverityValue) => {
    router.get(FindingController.index.url({ query: { severity } }));
};
</script>

<template>
    <Head title="Audit Status Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 md:p-6">
            <!-- Header - responsive layout -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <HeadingSmall
                    title="Audit Status Dashboard"
                    description="Overview of audit progress, finding severity, and resolution status"
                />
            </div>

            <!-- Filters - responsive stacking -->
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:gap-4">
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Datacenter filter -->
                    <select
                        v-model="datacenterFilter"
                        :class="selectClass"
                        class="flex-1 sm:flex-none"
                    >
                        <option value="">All Datacenters</option>
                        <option
                            v-for="option in datacenterOptions"
                            :key="option.id"
                            :value="option.id"
                        >
                            {{ option.name }}
                        </option>
                    </select>

                    <!-- Time period filter -->
                    <select
                        v-model="timePeriodFilter"
                        :class="selectClass"
                        class="flex-1 sm:flex-none"
                    >
                        <option
                            v-for="option in timePeriodOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>

                    <!-- Clear filters button -->
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

            <!-- Dashboard Content -->
            <div class="mt-4 grid gap-6">
                <!-- Audit Progress Metrics Section (Task 5.2) -->
                <Card>
                    <CardHeader>
                        <CardTitle>Audit Progress</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <!-- Status counts grid - responsive: 2 cols mobile, 5 cols desktop -->
                        <div class="grid gap-4 grid-cols-2 sm:grid-cols-3 lg:grid-cols-5">
                            <!-- Total Audits -->
                            <div class="rounded-lg border bg-muted/50 p-4">
                                <div class="text-3xl font-bold">{{ auditMetrics.total }}</div>
                                <div class="text-sm text-muted-foreground">Total Audits</div>
                            </div>
                            <!-- Pending -->
                            <div class="rounded-lg border bg-yellow-50 p-4 dark:bg-yellow-900/20">
                                <div class="text-3xl font-bold text-yellow-700 dark:text-yellow-400">
                                    {{ auditMetrics.byStatus.pending }}
                                </div>
                                <div class="text-sm text-muted-foreground">Pending</div>
                            </div>
                            <!-- In Progress -->
                            <div class="rounded-lg border bg-blue-50 p-4 dark:bg-blue-900/20">
                                <div class="text-3xl font-bold text-blue-700 dark:text-blue-400">
                                    {{ auditMetrics.byStatus.in_progress }}
                                </div>
                                <div class="text-sm text-muted-foreground">In Progress</div>
                            </div>
                            <!-- Completed -->
                            <div class="rounded-lg border bg-green-50 p-4 dark:bg-green-900/20">
                                <div class="text-3xl font-bold text-green-700 dark:text-green-400">
                                    {{ auditMetrics.byStatus.completed }}
                                </div>
                                <div class="text-sm text-muted-foreground">Completed</div>
                            </div>
                            <!-- Cancelled -->
                            <div class="rounded-lg border bg-gray-50 p-4 dark:bg-gray-800/50">
                                <div class="text-3xl font-bold text-gray-700 dark:text-gray-400">
                                    {{ auditMetrics.byStatus.cancelled }}
                                </div>
                                <div class="text-sm text-muted-foreground">Cancelled</div>
                            </div>
                        </div>

                        <!-- Completion percentage and warnings row -->
                        <div class="mt-6 grid gap-4 grid-cols-1 sm:grid-cols-3">
                            <!-- Completion Rate with visual indicator -->
                            <div class="rounded-lg border p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm text-muted-foreground">Completion Rate</span>
                                    <span class="text-2xl font-bold text-green-600 dark:text-green-400">
                                        {{ auditMetrics.completionPercentage }}%
                                    </span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                    <div
                                        class="h-2 rounded-full bg-green-500 transition-all dark:bg-green-400"
                                        :style="{ width: `${auditMetrics.completionPercentage}%` }"
                                    ></div>
                                </div>
                            </div>
                            <!-- Past Due with warning styling -->
                            <div class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                                <div class="text-3xl font-bold text-red-600 dark:text-red-400">
                                    {{ auditMetrics.pastDue }}
                                </div>
                                <div class="text-sm text-red-600/80 dark:text-red-400/80">Past Due</div>
                            </div>
                            <!-- Due Soon with warning styling -->
                            <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-900/20">
                                <div class="text-3xl font-bold text-yellow-600 dark:text-yellow-400">
                                    {{ auditMetrics.dueSoon }}
                                </div>
                                <div class="text-sm text-yellow-600/80 dark:text-yellow-400/80">Due Soon (7 days)</div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Charts Section (Task Group 6) -->
                <div class="grid gap-6 lg:grid-cols-2">
                    <!-- Severity Distribution Donut Chart (Task 6.3) -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Finding Severity Distribution</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <SeverityDistributionChart :severity-metrics="severityMetrics" />
                        </CardContent>
                    </Card>

                    <!-- Audit Completion Trend Line Chart (Task 6.4) -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Audit Completion Trend</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <AuditCompletionTrendChart
                                :trend-data="trendData"
                                :time-period="filters.time_period"
                            />
                        </CardContent>
                    </Card>
                </div>

                <!-- Finding Severity and Resolution Metrics Grid -->
                <div class="grid gap-6 lg:grid-cols-2">
                    <!-- Finding Severity Summary Section (Task 5.3) -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Finding Severity</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <!-- Severity badges grid - 2 cols on mobile, 2 cols on desktop -->
                            <div class="grid gap-4 grid-cols-2">
                                <!-- Critical - clickable badge -->
                                <div
                                    class="flex items-center justify-between rounded-lg border bg-red-50 p-4 cursor-pointer transition-colors hover:bg-red-100 dark:bg-red-900/20 dark:hover:bg-red-900/30"
                                    @click="navigateToFindingsBySeverity('critical')"
                                    role="button"
                                    tabindex="0"
                                    @keydown.enter="navigateToFindingsBySeverity('critical')"
                                >
                                    <span class="font-medium text-red-700 dark:text-red-400">Critical</span>
                                    <span :class="getSeverityBadgeClass('critical')">
                                        {{ severityMetrics.critical.count }}
                                    </span>
                                </div>
                                <!-- High - clickable badge -->
                                <div
                                    class="flex items-center justify-between rounded-lg border bg-orange-50 p-4 cursor-pointer transition-colors hover:bg-orange-100 dark:bg-orange-900/20 dark:hover:bg-orange-900/30"
                                    @click="navigateToFindingsBySeverity('high')"
                                    role="button"
                                    tabindex="0"
                                    @keydown.enter="navigateToFindingsBySeverity('high')"
                                >
                                    <span class="font-medium text-orange-700 dark:text-orange-400">High</span>
                                    <span :class="getSeverityBadgeClass('high')">
                                        {{ severityMetrics.high.count }}
                                    </span>
                                </div>
                                <!-- Medium - clickable badge -->
                                <div
                                    class="flex items-center justify-between rounded-lg border bg-yellow-50 p-4 cursor-pointer transition-colors hover:bg-yellow-100 dark:bg-yellow-900/20 dark:hover:bg-yellow-900/30"
                                    @click="navigateToFindingsBySeverity('medium')"
                                    role="button"
                                    tabindex="0"
                                    @keydown.enter="navigateToFindingsBySeverity('medium')"
                                >
                                    <span class="font-medium text-yellow-700 dark:text-yellow-400">Medium</span>
                                    <span :class="getSeverityBadgeClass('medium')">
                                        {{ severityMetrics.medium.count }}
                                    </span>
                                </div>
                                <!-- Low - clickable badge -->
                                <div
                                    class="flex items-center justify-between rounded-lg border bg-blue-50 p-4 cursor-pointer transition-colors hover:bg-blue-100 dark:bg-blue-900/20 dark:hover:bg-blue-900/30"
                                    @click="navigateToFindingsBySeverity('low')"
                                    role="button"
                                    tabindex="0"
                                    @keydown.enter="navigateToFindingsBySeverity('low')"
                                >
                                    <span class="font-medium text-blue-700 dark:text-blue-400">Low</span>
                                    <span :class="getSeverityBadgeClass('low')">
                                        {{ severityMetrics.low.count }}
                                    </span>
                                </div>
                            </div>
                            <!-- Total findings count -->
                            <div class="mt-4 text-center text-sm text-muted-foreground">
                                Total: {{ severityMetrics.total }} finding{{ severityMetrics.total !== 1 ? 's' : '' }}
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Resolution Status Metrics Section (Task 5.4) -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Resolution Status</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <!-- Resolution metrics grid -->
                            <div class="grid gap-4 grid-cols-2">
                                <!-- Open Findings -->
                                <div class="rounded-lg border p-4">
                                    <div class="text-3xl font-bold">{{ resolutionMetrics.openCount }}</div>
                                    <div class="text-sm text-muted-foreground">Open Findings</div>
                                </div>
                                <!-- Resolved Findings -->
                                <div class="rounded-lg border bg-green-50 p-4 dark:bg-green-900/20">
                                    <div class="text-3xl font-bold text-green-600 dark:text-green-400">
                                        {{ resolutionMetrics.resolvedCount }}
                                    </div>
                                    <div class="text-sm text-muted-foreground">Resolved</div>
                                </div>
                                <!-- Resolution Rate -->
                                <div class="rounded-lg border p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-sm text-muted-foreground">Resolution Rate</span>
                                        <span class="text-2xl font-bold text-green-600 dark:text-green-400">
                                            {{ resolutionMetrics.resolutionRate }}%
                                        </span>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                        <div
                                            class="h-2 rounded-full bg-green-500 transition-all dark:bg-green-400"
                                            :style="{ width: `${resolutionMetrics.resolutionRate}%` }"
                                        ></div>
                                    </div>
                                </div>
                                <!-- Overdue Findings with warning styling -->
                                <div class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                                    <div class="text-3xl font-bold text-red-600 dark:text-red-400">
                                        {{ resolutionMetrics.overdueCount }}
                                    </div>
                                    <div class="text-sm text-red-600/80 dark:text-red-400/80">Overdue</div>
                                </div>
                            </div>
                            <!-- Average resolution time -->
                            <div class="mt-4 text-center text-sm text-muted-foreground">
                                Avg. Resolution Time: {{ formatResolutionTime(resolutionMetrics.averageResolutionTime) }}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Active Audits Progress Section (Task Group 8) -->
                <ActiveAuditProgress :audits="activeAuditProgress" />

                <!-- Per-Audit Breakdown Table (Task Group 7) -->
                <AuditBreakdownTable :audit-breakdown="auditBreakdown" />
            </div>
        </div>
    </AppLayout>
</template>
