<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import DashboardController from '@/actions/App/Http/Controllers/DashboardController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import {
    ActivityByEntityChart,
    ActivityFeed,
    ActivityFeedSkeleton,
    CapacityTrendChart,
    ChartCardSkeleton,
    DashboardCompletionChart,
    DashboardSeverityChart,
    DeviceCountTrendChart,
    MetricCard,
    MetricCardSkeleton,
    OpenFindingsCard,
    OpenFindingsCardSkeleton,
    TimePeriodFilter,
} from '@/components/dashboard';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { SeverityMetrics } from '@/types/dashboard';
import { debounce } from '@/lib/utils';

/**
 * Type definitions for Dashboard props
 */
interface TrendData {
    percentage: string;
    change: string;
}

interface BaseMetric {
    value: number;
    trend: TrendData;
    sparkline: number[];
}

interface RackUtilizationMetric extends BaseMetric {
    value: number; // percentage
}

interface DeviceCountMetric extends BaseMetric {
    value: number;
}

interface PendingAuditsMetric extends BaseMetric {
    value: number;
    pastDue: number;
}

interface OpenFindingsMetric extends BaseMetric {
    value: number;
    bySeverity: {
        critical: number;
        high: number;
        medium: number;
        low: number;
    };
}

interface DashboardMetrics {
    rackUtilization: RackUtilizationMetric;
    deviceCount: DeviceCountMetric;
    pendingAudits: PendingAuditsMetric;
    openFindings: OpenFindingsMetric;
}

interface DatacenterOption {
    id: number;
    name: string;
}

interface DashboardFilters {
    datacenter_id: number | null;
}

interface ActivityLogEntry {
    id: number;
    timestamp: string;
    timestamp_relative: string;
    user_name: string;
    action: 'created' | 'updated' | 'deleted' | 'restored';
    entity_type: string;
    summary: string;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
}

/**
 * Chart data interfaces
 */
interface ChartTrendData {
    labels: string[];
    data: number[];
}

interface AuditCompletionTrendData extends ChartTrendData {
    total: number;
}

interface ChartData {
    capacityTrend: ChartTrendData;
    deviceCountTrend: ChartTrendData;
    severityDistribution: SeverityMetrics;
    auditCompletionTrend: AuditCompletionTrendData;
    activityByEntity: ChartTrendData;
}

interface DashboardProps {
    metrics: DashboardMetrics;
    datacenterOptions: DatacenterOption[];
    filters: DashboardFilters;
    recentActivity: ActivityLogEntry[];
}

const props = defineProps<DashboardProps>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: DashboardController.index.url(),
    },
];

// Local filter state
const datacenterFilter = ref<string>(props.filters.datacenter_id?.toString() || '');
const timePeriodFilter = ref<string>('7_days');

// Track loading state for filter changes
const isFiltering = ref(false);

// Chart data state (loaded asynchronously)
const chartData = ref<ChartData | null>(null);
const isLoadingCharts = ref(true);

// Debounced filter application
const debouncedApplyFilters = debounce(() => {
    applyFilters();
}, 300);

// Apply filters to the page
const applyFilters = () => {
    isFiltering.value = true;
    router.get(
        DashboardController.index.url(),
        {
            datacenter_id: datacenterFilter.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                isFiltering.value = false;
                // Reload chart data with new filters
                loadChartData();
            },
        }
    );
};

// Load chart data from API
const loadChartData = async () => {
    isLoadingCharts.value = true;
    try {
        const params: Record<string, string> = {
            time_period: timePeriodFilter.value,
        };
        if (datacenterFilter.value) {
            params.datacenter_id = datacenterFilter.value;
        }

        const response = await fetch(DashboardController.chartData.url({ query: params }));
        if (response.ok) {
            chartData.value = await response.json();
        }
    } catch (error) {
        console.error('Failed to load chart data:', error);
    } finally {
        isLoadingCharts.value = false;
    }
};

// Watch for filter changes
watch(datacenterFilter, () => {
    debouncedApplyFilters();
});

// Watch for time period changes (only reload charts, not entire page)
watch(timePeriodFilter, () => {
    loadChartData();
});

// Load chart data on mount
onMounted(() => {
    loadChartData();
});

// Common select styling for filters with enhanced dark mode support and touch-friendly sizing
// Added min-h-10 for touch target (40px minimum) and md:min-w-40 for tablet landscape readability
const selectClass =
    'flex h-9 min-h-10 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring dark:border-input dark:bg-transparent dark:text-foreground';
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 md:p-6">
            <!-- Header - responsive layout: stacks on mobile, inline on larger screens -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <HeadingSmall
                    title="Dashboard"
                    description="Overview of key infrastructure metrics"
                />
            </div>

            <!-- Filters - stacks on mobile, inline on larger screens with adequate tablet spacing -->
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:gap-4">
                <div class="flex w-full flex-wrap items-center gap-3 sm:w-auto sm:gap-4">
                    <select
                        v-model="datacenterFilter"
                        :class="selectClass"
                        class="w-full sm:w-auto md:min-w-44"
                        aria-label="Filter by datacenter"
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
                    <TimePeriodFilter v-model="timePeriodFilter" />
                </div>
            </div>

            <!-- Metric Cards Grid - Responsive: 1 col mobile, 2 col tablet, 4 col desktop -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Show skeletons when filtering -->
                <template v-if="isFiltering">
                    <MetricCardSkeleton />
                    <MetricCardSkeleton />
                    <MetricCardSkeleton />
                    <OpenFindingsCardSkeleton />
                </template>
                <template v-else>
                    <!-- Rack Utilization Card -->
                    <MetricCard
                        title="Rack Utilization"
                        :value="metrics.rackUtilization.value"
                        unit="%"
                        :trend="metrics.rackUtilization.trend"
                        :sparkline-data="metrics.rackUtilization.sparkline"
                    />

                    <!-- Device Count Card -->
                    <MetricCard
                        title="Device Count"
                        :value="metrics.deviceCount.value"
                        :trend="metrics.deviceCount.trend"
                        :sparkline-data="metrics.deviceCount.sparkline"
                    />

                    <!-- Pending Audits Card -->
                    <MetricCard
                        title="Pending Audits"
                        :value="metrics.pendingAudits.value"
                        :trend="metrics.pendingAudits.trend"
                        :sparkline-data="metrics.pendingAudits.sparkline"
                    >
                        <div
                            v-if="metrics.pendingAudits.pastDue > 0"
                            class="mt-2 text-xs text-red-600 md:text-sm dark:text-red-400"
                        >
                            {{ metrics.pendingAudits.pastDue }} past due
                        </div>
                    </MetricCard>

                    <!-- Open Findings Card with Severity Breakdown -->
                    <OpenFindingsCard
                        title="Open Findings"
                        :value="metrics.openFindings.value"
                        :trend="metrics.openFindings.trend"
                        :sparkline-data="metrics.openFindings.sparkline"
                        :by-severity="metrics.openFindings.bySeverity"
                    />
                </template>
            </div>

            <!-- Charts Section - 2 column grid on desktop, stack on mobile/tablet -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <!-- Show skeletons when loading charts -->
                <template v-if="isLoadingCharts || !chartData">
                    <ChartCardSkeleton />
                    <ChartCardSkeleton />
                    <ChartCardSkeleton />
                    <ChartCardSkeleton />
                    <ChartCardSkeleton />
                </template>
                <template v-else>
                    <!-- Capacity Trend Chart -->
                    <CapacityTrendChart
                        :data="chartData.capacityTrend.data"
                        :labels="chartData.capacityTrend.labels"
                    />

                    <!-- Device Count Trend Chart -->
                    <DeviceCountTrendChart
                        :data="chartData.deviceCountTrend.data"
                        :labels="chartData.deviceCountTrend.labels"
                    />

                    <!-- Severity Distribution Chart -->
                    <DashboardSeverityChart :severity-metrics="chartData.severityDistribution" />

                    <!-- Audit Completion Trend Chart -->
                    <DashboardCompletionChart
                        :data="chartData.auditCompletionTrend.data"
                        :labels="chartData.auditCompletionTrend.labels"
                        :total="chartData.auditCompletionTrend.total"
                    />

                    <!-- Activity by Entity Chart -->
                    <ActivityByEntityChart
                        :data="chartData.activityByEntity.data"
                        :labels="chartData.activityByEntity.labels"
                    />
                </template>
            </div>

            <!-- Recent Activity Feed - full width on all sizes -->
            <div class="flex-1">
                <ActivityFeedSkeleton v-if="isFiltering" />
                <ActivityFeed v-else :activities="recentActivity" />
            </div>
        </div>
    </AppLayout>
</template>
