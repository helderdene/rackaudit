<script setup lang="ts">
/**
 * Connection Reports Page
 *
 * Provides comprehensive reporting on connection inventory, cable types,
 * and port utilization across datacenters.
 */

import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    index as connectionReportsIndex,
    exportPdf,
    exportCsv,
} from '@/actions/App/Http/Controllers/ConnectionReportController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Cable } from 'lucide-vue-next';
import { ExportButtons } from '@/components/CapacityReports';
import {
    ConnectionFilters,
    ConnectionMetricsCards,
    CableTypeDistributionChart,
    PortUtilizationChart,
    PortStatusBreakdown,
    ConnectionsInventoryTable,
} from '@/components/ConnectionReports';

/**
 * Type definitions for Connection Reports props
 */
interface FilterOption {
    id: number;
    name: string;
}

interface Filters {
    datacenter_id: number | null;
    room_id: number | null;
}

interface CableTypeDistributionItem {
    type: string;
    label: string;
    count: number;
    percentage: number;
}

interface PortTypeDistributionItem {
    type: string;
    label: string;
    count: number;
    percentage: number;
}

interface CableLengthStats {
    mean: number | null;
    min: number | null;
    max: number | null;
    count: number;
}

interface PortUtilizationByType {
    type: string;
    label: string;
    total: number;
    connected: number;
    percentage: number;
}

interface PortUtilizationByStatus {
    status: string;
    label: string;
    count: number;
    percentage: number;
}

interface PortUtilization {
    byType: PortUtilizationByType[];
    byStatus: PortUtilizationByStatus[];
    overall: {
        total: number;
        connected: number;
        percentage: number;
    };
}

interface Connection {
    id: number;
    source_device_name: string | null;
    source_port_label: string | null;
    destination_device_name: string | null;
    destination_port_label: string | null;
    cable_type: string | null;
    cable_type_label: string;
    cable_length: number | null;
    cable_color: string | null;
}

interface Metrics {
    totalConnections: number;
    cableTypeDistribution: CableTypeDistributionItem[];
    portTypeDistribution: PortTypeDistributionItem[];
    cableLengthStats: CableLengthStats;
    portUtilization: PortUtilization;
    connections: Connection[];
}

interface Props {
    metrics: Metrics;
    datacenterOptions: FilterOption[];
    roomOptions: FilterOption[];
    filters: Filters;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Connection Reports',
        href: connectionReportsIndex.url(),
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

    if (format === 'pdf') {
        return exportPdf.url({ query: params });
    }
    return exportCsv.url({ query: params });
};

// Check if there is any connection data
const hasConnectionData = computed(() => {
    return props.metrics.connections.length > 0;
});
</script>

<template>
    <Head title="Connection Reports" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 md:p-6">
            <!-- Header with title and export buttons -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <HeadingSmall
                    title="Connection Reports"
                    description="View connection inventory, cable types, and port utilization across your datacenters."
                />

                <!-- Export Buttons -->
                <ExportButtons
                    :pdf-url="buildExportUrl('pdf')"
                    :csv-url="buildExportUrl('csv')"
                    :loading="isFiltering"
                />
            </div>

            <!-- Filters -->
            <ConnectionFilters
                :filters="filters"
                :datacenters="datacenterOptions"
                :rooms="roomOptions"
                @filtering="handleFiltering"
            />

            <!-- Skeleton loading state -->
            <template v-if="isFiltering">
                <!-- Metrics Cards Skeleton -->
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card v-for="i in 4" :key="i">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <Skeleton class="h-4 w-20" />
                            <Skeleton class="size-8 rounded-full" />
                        </CardHeader>
                        <CardContent>
                            <Skeleton class="mb-2 h-8 w-16" />
                            <Skeleton class="h-3 w-24" />
                        </CardContent>
                    </Card>
                </div>

                <!-- Charts Skeleton -->
                <div class="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader class="pb-2">
                            <Skeleton class="h-5 w-40" />
                        </CardHeader>
                        <CardContent>
                            <Skeleton class="h-64 w-full" />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <Skeleton class="h-5 w-40" />
                        </CardHeader>
                        <CardContent>
                            <Skeleton class="h-48 w-full" />
                        </CardContent>
                    </Card>
                </div>

                <!-- Port Status Skeleton -->
                <Card>
                    <CardHeader class="pb-2">
                        <Skeleton class="h-5 w-40" />
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <Skeleton v-for="i in 4" :key="i" class="h-8 w-full" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Connections Inventory Skeleton -->
                <Card>
                    <CardHeader>
                        <Skeleton class="h-5 w-32" />
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <Skeleton class="h-10 w-full" />
                            <Skeleton class="h-12 w-full" />
                            <Skeleton class="h-12 w-full" />
                            <Skeleton class="h-12 w-full" />
                        </div>
                    </CardContent>
                </Card>
            </template>

            <!-- Actual content -->
            <template v-else>
                <!-- Empty State -->
                <div v-if="!hasConnectionData" class="py-12 text-center">
                    <Cable class="mx-auto mb-4 size-12 text-muted-foreground/50" />
                    <h3 class="text-lg font-medium">No connection data available</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Add connections to see connection reports.
                    </p>
                </div>

                <template v-else>
                    <!-- Metrics Cards -->
                    <ConnectionMetricsCards
                        :total-connections="metrics.totalConnections"
                        :port-type-distribution="metrics.portTypeDistribution"
                        :cable-length-stats="metrics.cableLengthStats"
                    />

                    <!-- Charts Row -->
                    <div class="grid gap-4 lg:grid-cols-2">
                        <!-- Cable Type Distribution Chart -->
                        <CableTypeDistributionChart
                            :distribution="metrics.cableTypeDistribution"
                        />

                        <!-- Port Utilization Chart -->
                        <PortUtilizationChart
                            :by-type="metrics.portUtilization.byType"
                            :overall="metrics.portUtilization.overall"
                        />
                    </div>

                    <!-- Port Status Breakdown -->
                    <PortStatusBreakdown
                        :by-status="metrics.portUtilization.byStatus"
                        :total-ports="metrics.portUtilization.overall.total"
                    />

                    <!-- Connections Inventory Table (client-side filtering/sorting/pagination) -->
                    <ConnectionsInventoryTable
                        :connections="metrics.connections"
                        :loading="isFiltering"
                    />
                </template>
            </template>
        </div>
    </AppLayout>
</template>
