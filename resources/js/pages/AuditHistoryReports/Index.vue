<script setup lang="ts">
/**
 * Audit History Reports Index Page
 *
 * Displays historical audit trends, finding counts by severity,
 * and resolution time metrics across datacenters.
 */
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { index as auditHistoryIndex, exportPdf, exportCsv } from '@/actions/App/Http/Controllers/AuditHistoryReportController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { ClipboardList } from 'lucide-vue-next';
import { ExportButtons } from '@/components/CapacityReports';
import {
    AuditHistoryFilters,
    AuditHistoryMetricCard,
    FindingTrendChart,
    ResolutionTimeTrendChart,
    AuditHistoryTable,
} from '@/components/AuditHistoryReports';

/**
 * Type definitions for Audit History Reports props
 */
interface SeverityCounts {
    critical: number;
    high: number;
    medium: number;
    low: number;
}

interface AuditSparkline {
    value: number;
    sparkline: number[];
}

interface FindingsMetric {
    value: number;
    bySeverity: SeverityCounts;
}

interface TimeMetric {
    value: number | null;
    formatted: string;
}

interface Metrics {
    totalAuditsCompleted: AuditSparkline;
    totalFindings: FindingsMetric;
    avgResolutionTime: TimeMetric;
    avgTimeToFirstResponse: TimeMetric;
}

interface FilterOption {
    id: number;
    name: string;
}

interface AuditTypeOption {
    value: string;
    label: string;
}

interface Filters {
    time_range_preset: string | null;
    start_date: string | null;
    end_date: string | null;
    datacenter_id: number | null;
    audit_type: string | null;
    sort_by: string;
    sort_direction: string;
}

interface FindingTrendItem {
    period: string;
    critical: number;
    high: number;
    medium: number;
    low: number;
}

interface ResolutionTimeTrendItem {
    period: string;
    avg_resolution_time: number | null;
    avg_first_response: number | null;
}

interface AuditHistoryItem {
    id: number;
    name: string;
    type: string;
    type_label: string;
    datacenter_id: number;
    datacenter_name: string;
    completion_date: string;
    completion_date_formatted: string;
    total_findings: number;
    severity_counts: SeverityCounts;
    avg_resolution_time: number | null;
    avg_resolution_time_formatted: string;
}

interface PaginatedAudits {
    data: AuditHistoryItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    metrics: Metrics;
    datacenterOptions: FilterOption[];
    auditTypeOptions: AuditTypeOption[];
    filters: Filters;
    findingTrendData: FindingTrendItem[];
    resolutionTimeTrendData: ResolutionTimeTrendItem[];
    audits: PaginatedAudits;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reports', href: '/reports' },
    { title: 'Audit History', href: auditHistoryIndex.url() },
];

// Track loading state for filter changes
const isFiltering = ref(false);

// Handle filtering state from child component
const handleFiltering = (value: boolean) => {
    isFiltering.value = value;
};

// Build export URL with current filters
const buildExportUrl = (format: 'pdf' | 'csv'): string => {
    const params: Record<string, string> = {};

    if (props.filters.time_range_preset) {
        params.time_range_preset = props.filters.time_range_preset;
    }
    if (props.filters.start_date) {
        params.start_date = props.filters.start_date;
    }
    if (props.filters.end_date) {
        params.end_date = props.filters.end_date;
    }
    if (props.filters.datacenter_id) {
        params.datacenter_id = String(props.filters.datacenter_id);
    }
    if (props.filters.audit_type) {
        params.audit_type = props.filters.audit_type;
    }

    if (format === 'pdf') {
        return exportPdf.url({ query: params });
    }
    return exportCsv.url({ query: params });
};

// Check if there is any audit data
const hasAuditData = computed(() => {
    return props.metrics.totalAuditsCompleted.value > 0;
});

// Check if there is trend data
const hasFindingTrendData = computed(() => {
    return props.findingTrendData.length > 0 && props.findingTrendData.some(
        item => item.critical > 0 || item.high > 0 || item.medium > 0 || item.low > 0
    );
});

const hasResolutionTimeTrendData = computed(() => {
    return props.resolutionTimeTrendData.length > 0 && props.resolutionTimeTrendData.some(
        item => item.avg_resolution_time !== null || item.avg_first_response !== null
    );
});

// Extract sparkline data for metrics
const auditSparklineData = computed(() => {
    return props.metrics.totalAuditsCompleted.sparkline;
});
</script>

<template>
    <Head title="Audit History Reports" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 md:p-6">
            <!-- Header with title and export buttons -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <HeadingSmall
                    title="Audit History Reports"
                    description="View historical audit trends, finding counts by severity, and resolution time metrics."
                />

                <!-- Export Buttons -->
                <ExportButtons
                    :pdf-url="buildExportUrl('pdf')"
                    :csv-url="buildExportUrl('csv')"
                    :loading="isFiltering"
                />
            </div>

            <!-- Filters -->
            <AuditHistoryFilters
                :filters="filters"
                :datacenters="datacenterOptions"
                :audit-types="auditTypeOptions"
                @filtering="handleFiltering"
            />

            <!-- Skeleton loading state -->
            <template v-if="isFiltering">
                <!-- Metrics Grid Skeleton -->
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card v-for="i in 4" :key="i" class="relative">
                        <CardHeader class="pb-2">
                            <Skeleton class="h-4 w-24" />
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-baseline gap-2">
                                        <Skeleton class="h-9 w-16" />
                                        <Skeleton class="h-4 w-10" />
                                    </div>
                                    <Skeleton class="mt-2 h-3 w-32" />
                                </div>
                                <Skeleton class="h-[30px] w-[80px]" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Trend Charts Skeleton -->
                <div class="grid gap-4 md:grid-cols-2">
                    <Card v-for="i in 2" :key="`trend-${i}`">
                        <CardHeader class="pb-2">
                            <Skeleton class="h-4 w-32" />
                        </CardHeader>
                        <CardContent>
                            <Skeleton class="h-64 w-full" />
                        </CardContent>
                    </Card>
                </div>

                <!-- Table Skeleton -->
                <Card>
                    <CardHeader>
                        <Skeleton class="h-5 w-40" />
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <Skeleton v-for="i in 5" :key="i" class="h-12 w-full" />
                        </div>
                    </CardContent>
                </Card>
            </template>

            <!-- Actual content -->
            <template v-else>
                <!-- Empty State -->
                <div v-if="!hasAuditData" class="py-12 text-center">
                    <ClipboardList class="mx-auto mb-4 size-12 text-muted-foreground/50" />
                    <h3 class="text-lg font-medium">No audit history available</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Complete audits to see historical trends and metrics.
                    </p>
                </div>

                <!-- Metrics Grid -->
                <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <!-- Total Audits Completed -->
                    <AuditHistoryMetricCard
                        title="Total Audits Completed"
                        :value="metrics.totalAuditsCompleted.value"
                        :sparkline-data="auditSparklineData"
                        description="Completed audits in selected period"
                        color="rgb(59, 130, 246)"
                    />

                    <!-- Total Findings -->
                    <AuditHistoryMetricCard
                        title="Total Findings"
                        :value="metrics.totalFindings.value"
                        :severity-breakdown="metrics.totalFindings.bySeverity"
                        description="Findings from completed audits"
                    />

                    <!-- Avg Resolution Time -->
                    <AuditHistoryMetricCard
                        title="Avg Resolution Time"
                        :value="metrics.avgResolutionTime.formatted"
                        description="Average time to resolve findings"
                        color="rgb(59, 130, 246)"
                    />

                    <!-- Avg Time to First Response -->
                    <AuditHistoryMetricCard
                        title="Avg First Response"
                        :value="metrics.avgTimeToFirstResponse.formatted"
                        description="Average time to initial response"
                        color="rgb(34, 197, 94)"
                    />
                </div>

                <!-- Trend Charts Section -->
                <div v-if="hasAuditData" class="space-y-4">
                    <h3 class="text-lg font-semibold">Trends Over Time</h3>

                    <div class="grid gap-4 md:grid-cols-2">
                        <!-- Finding Trend Chart (Stacked Area) -->
                        <FindingTrendChart
                            :data="findingTrendData"
                            title="Finding Trends by Severity"
                        />

                        <!-- Resolution Time Trend Chart -->
                        <ResolutionTimeTrendChart
                            :data="resolutionTimeTrendData"
                            title="Resolution Time Trends"
                        />
                    </div>

                    <!-- No Trend Data Message -->
                    <div
                        v-if="!hasFindingTrendData && !hasResolutionTimeTrendData"
                        class="rounded-lg border border-dashed border-muted-foreground/30 p-6 text-center"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="mx-auto mb-2 size-10 text-muted-foreground/50"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="1"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                        <p class="text-sm text-muted-foreground">No trend data available yet</p>
                        <p class="mt-1 text-xs text-muted-foreground/70">
                            Trend data will appear once audits have findings over multiple time periods.
                        </p>
                    </div>
                </div>

                <!-- Audit History Table -->
                <AuditHistoryTable
                    v-if="hasAuditData"
                    :audits="audits"
                    :filters="filters"
                />
            </template>
        </div>
    </AppLayout>
</template>
