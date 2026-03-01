<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { index as capacityReportsIndex, exportPdf, exportCsv } from '@/actions/App/Http/Controllers/CapacityReportController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { BarChart3 } from 'lucide-vue-next';
import {
    CapacityFilters,
    CapacityMetricCard,
    ExportButtons,
    HistoricalTrendChart,
    PortCapacityGrid,
    RackCapacityTable,
} from '@/components/CapacityReports';

/**
 * Type definitions for Capacity Reports props
 */
interface USpaceMetrics {
    total_u_space: number;
    used_u_space: number;
    available_u_space: number;
    utilization_percent: number;
}

interface PowerMetrics {
    total_capacity: number;
    total_consumption: number;
    power_headroom: number;
    utilization_percent: number | null;
}

interface PortCapacityItem {
    total_ports: number;
    connected_ports: number;
    available_ports: number;
    label: string;
}

interface RackApproachingCapacity {
    id: number;
    name: string;
    datacenter_id: number;
    datacenter_name: string;
    room_id: number;
    room_name: string;
    row_id: number;
    row_name: string;
    u_height: number;
    used_u_space: number;
    available_u_space: number;
    utilization_percent: number;
    power_capacity_watts: number | null;
    power_used_watts: number | null;
    power_available_watts: number | null;
    power_utilization_percent: number | null;
    status: 'warning' | 'critical' | 'normal';
}

interface Metrics {
    u_space: USpaceMetrics;
    power: PowerMetrics;
    port_capacity: Record<string, PortCapacityItem>;
    racks_approaching_capacity: RackApproachingCapacity[];
}

interface FilterOption {
    id: number;
    name: string;
}

interface Filters {
    datacenter_id: number | null;
    room_id: number | null;
    row_id: number | null;
}

interface HistoricalSnapshot {
    date: string;
    rack_utilization: number;
    power_utilization: number | null;
}

interface TrendItem {
    percentage: string;
    change: string;
}

interface TrendData {
    rack_utilization_trend: TrendItem | null;
    power_utilization_trend: TrendItem | null;
    has_trend_data: boolean;
}

interface Props {
    metrics: Metrics;
    datacenterOptions: FilterOption[];
    roomOptions: FilterOption[];
    rowOptions: FilterOption[];
    filters: Filters;
    historicalSnapshots: HistoricalSnapshot[];
    trendData: TrendData;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Capacity Reports',
        href: capacityReportsIndex.url(),
    },
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

    if (props.filters.datacenter_id) {
        params.datacenter_id = String(props.filters.datacenter_id);
    }
    if (props.filters.room_id) {
        params.room_id = String(props.filters.room_id);
    }
    if (props.filters.row_id) {
        params.row_id = String(props.filters.row_id);
    }

    if (format === 'pdf') {
        return exportPdf.url({ query: params });
    }
    return exportCsv.url({ query: params });
};

// Extract sparkline data from historical snapshots
const uSpaceSparklineData = computed(() => {
    return props.historicalSnapshots.map(s => s.rack_utilization);
});

const powerSparklineData = computed(() => {
    return props.historicalSnapshots
        .filter(s => s.power_utilization !== null)
        .map(s => s.power_utilization as number);
});

// Extract date labels from historical snapshots
const historicalDateLabels = computed(() => {
    return props.historicalSnapshots.map(s => s.date);
});

// Check if there is any capacity data
const hasCapacityData = computed(() => {
    return props.metrics.u_space.total_u_space > 0;
});

// Check if there is historical data for trend charts
const hasHistoricalData = computed(() => {
    return props.historicalSnapshots.length > 0;
});

// Check if there is power historical data
const hasPowerHistoricalData = computed(() => {
    return props.historicalSnapshots.some(s => s.power_utilization !== null);
});

// Get racks approaching capacity count by status
const racksApproachingCapacity = computed(() => {
    return props.metrics.racks_approaching_capacity;
});

const criticalRackCount = computed(() => {
    return racksApproachingCapacity.value.filter(r => r.status === 'critical').length;
});

const warningRackCount = computed(() => {
    return racksApproachingCapacity.value.filter(r => r.status === 'warning').length;
});
</script>

<template>
    <Head title="Capacity Planning Reports" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 md:p-6">
            <!-- Header with title and export buttons -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <HeadingSmall
                    title="Capacity Planning Reports"
                    description="View rack utilization, power consumption, and available capacity metrics across your datacenters."
                />

                <!-- Export Buttons -->
                <ExportButtons
                    :pdf-url="buildExportUrl('pdf')"
                    :csv-url="buildExportUrl('csv')"
                    :loading="isFiltering"
                />
            </div>

            <!-- Filters -->
            <CapacityFilters
                :filters="filters"
                :datacenters="datacenterOptions"
                :rooms="roomOptions"
                :rows="rowOptions"
                @filtering="handleFiltering"
            />

            <!-- Skeleton loading state -->
            <template v-if="isFiltering">
                <!-- Metrics Grid Skeleton -->
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <Card v-for="i in 3" :key="i" class="relative">
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
                                    <Skeleton class="mt-2 h-2 w-full" />
                                    <Skeleton class="mt-2 h-3 w-32" />
                                </div>
                                <Skeleton class="h-[30px] w-[80px]" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Historical Trends Skeleton -->
                <div class="grid gap-4 md:grid-cols-2">
                    <Card v-for="i in 2" :key="`trend-${i}`">
                        <CardHeader class="pb-2">
                            <Skeleton class="h-4 w-32" />
                        </CardHeader>
                        <CardContent>
                            <Skeleton class="h-48 w-full" />
                        </CardContent>
                    </Card>
                </div>

                <!-- Port Capacity Skeleton -->
                <Card>
                    <CardHeader>
                        <Skeleton class="h-5 w-40" />
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div v-for="i in 3" :key="i" class="rounded-lg border p-4">
                                <Skeleton class="mb-3 h-5 w-24" />
                                <Skeleton class="mb-2 h-2 w-full" />
                                <div class="space-y-2">
                                    <Skeleton class="h-4 w-full" />
                                    <Skeleton class="h-4 w-full" />
                                    <Skeleton class="h-4 w-full" />
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </template>

            <!-- Actual content -->
            <template v-else>
                <!-- Empty State -->
                <div v-if="!hasCapacityData" class="py-12 text-center">
                    <BarChart3 class="mx-auto mb-4 size-12 text-muted-foreground/50" />
                    <h3 class="text-lg font-medium">No capacity data available</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Add racks and devices to see capacity metrics.
                    </p>
                </div>

                <!-- Metrics Grid -->
                <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <!-- U-Space Utilization Card -->
                    <CapacityMetricCard
                        title="U-Space Utilization"
                        :value="metrics.u_space.utilization_percent"
                        unit="%"
                        :total="metrics.u_space.total_u_space"
                        :available="metrics.u_space.available_u_space"
                        :sparkline-data="uSpaceSparklineData"
                        :trend="trendData.rack_utilization_trend ?? undefined"
                    >
                        <div class="mt-2 text-xs text-muted-foreground">
                            {{ metrics.u_space.used_u_space }}U used of {{ metrics.u_space.total_u_space }}U total
                        </div>
                    </CapacityMetricCard>

                    <!-- Power Utilization Card -->
                    <CapacityMetricCard
                        v-if="metrics.power.utilization_percent !== null"
                        title="Power Utilization"
                        :value="metrics.power.utilization_percent"
                        unit="%"
                        :total="Math.round(metrics.power.total_capacity / 1000)"
                        :available="Math.round(metrics.power.power_headroom / 1000)"
                        :sparkline-data="powerSparklineData"
                        :trend="trendData.power_utilization_trend ?? undefined"
                    >
                        <div class="mt-2 text-xs text-muted-foreground">
                            {{ (metrics.power.total_consumption / 1000).toFixed(1) }}kW used of {{ (metrics.power.total_capacity / 1000).toFixed(1) }}kW total
                        </div>
                    </CapacityMetricCard>

                    <!-- Power Not Configured Card -->
                    <Card v-else>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium text-muted-foreground">
                                Power Utilization
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="flex h-20 items-center justify-center">
                                <span class="text-sm text-muted-foreground">Not configured</span>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Racks Approaching Capacity Card -->
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium text-muted-foreground">
                                Racks Approaching Capacity
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div v-if="racksApproachingCapacity.length > 0" class="space-y-2">
                                <!-- Summary badges -->
                                <div class="mb-3 flex gap-2">
                                    <span
                                        v-if="criticalRackCount > 0"
                                        class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400"
                                    >
                                        {{ criticalRackCount }} critical
                                    </span>
                                    <span
                                        v-if="warningRackCount > 0"
                                        class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-400"
                                    >
                                        {{ warningRackCount }} warning
                                    </span>
                                </div>

                                <!-- Rack list preview -->
                                <div
                                    v-for="rack in racksApproachingCapacity.slice(0, 5)"
                                    :key="rack.id"
                                    class="flex items-center justify-between text-sm"
                                >
                                    <span class="truncate font-medium">{{ rack.name }}</span>
                                    <span
                                        class="shrink-0 rounded px-2 py-0.5 text-xs font-medium"
                                        :class="{
                                            'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400': rack.status === 'critical',
                                            'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400': rack.status === 'warning',
                                        }"
                                    >
                                        {{ rack.utilization_percent }}%
                                    </span>
                                </div>

                                <!-- More indicator -->
                                <div v-if="racksApproachingCapacity.length > 5" class="pt-1 text-xs text-muted-foreground">
                                    +{{ racksApproachingCapacity.length - 5 }} more
                                </div>
                            </div>

                            <!-- All racks healthy -->
                            <div v-else class="flex h-20 items-center justify-center">
                                <span class="text-sm text-green-600 dark:text-green-400">All racks below 80%</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Historical Trends Section -->
                <div v-if="hasCapacityData" class="space-y-4">
                    <h3 class="text-lg font-semibold">Historical Trends</h3>

                    <div class="grid gap-4 md:grid-cols-2">
                        <!-- U-Space Historical Trend Chart -->
                        <HistoricalTrendChart
                            title="Rack Utilization Over Time"
                            :data="uSpaceSparklineData"
                            :labels="historicalDateLabels"
                            unit="%"
                            color="rgb(59, 130, 246)"
                            fill-color="rgba(59, 130, 246, 0.1)"
                        />

                        <!-- Power Historical Trend Chart -->
                        <HistoricalTrendChart
                            v-if="hasPowerHistoricalData"
                            title="Power Utilization Over Time"
                            :data="powerSparklineData"
                            :labels="historicalDateLabels.slice(0, powerSparklineData.length)"
                            unit="%"
                            color="rgb(245, 158, 11)"
                            fill-color="rgba(245, 158, 11, 0.1)"
                        />

                        <!-- Power Not Available Card -->
                        <Card v-else>
                            <CardHeader class="pb-2">
                                <CardTitle class="text-sm font-medium text-muted-foreground">
                                    Power Utilization Over Time
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div
                                    class="flex h-48 flex-col items-center justify-center rounded-lg border border-dashed border-muted-foreground/30"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="mb-2 size-12 text-muted-foreground/50"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="1"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M13 10V3L4 14h7v7l9-11h-7z"
                                        />
                                    </svg>
                                    <p class="text-sm text-muted-foreground">No power data available</p>
                                    <p class="mt-1 text-xs text-muted-foreground/70">Configure power capacity on racks to track usage</p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- No Historical Data Message -->
                    <div
                        v-if="!hasHistoricalData"
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
                        <p class="text-sm text-muted-foreground">No historical data available yet</p>
                        <p class="mt-1 text-xs text-muted-foreground/70">
                            Historical snapshots are captured weekly and will appear here once available.
                        </p>
                    </div>
                </div>

                <!-- Port Capacity Grid -->
                <PortCapacityGrid
                    v-if="hasCapacityData"
                    :port-stats="metrics.port_capacity"
                />

                <!-- Rack Capacity Table (for racks approaching capacity) -->
                <RackCapacityTable
                    v-if="hasCapacityData && racksApproachingCapacity.length > 0"
                    :racks="racksApproachingCapacity"
                />
            </template>
        </div>
    </AppLayout>
</template>
