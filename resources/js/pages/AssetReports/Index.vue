<script setup lang="ts">
/**
 * Asset Reports Page
 *
 * Provides comprehensive reporting on device inventory, warranty status,
 * lifecycle distribution, and asset counts.
 */

import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import {
    index as assetReportsIndex,
    exportPdf,
    exportCsv,
} from '@/actions/App/Http/Controllers/AssetReportController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Package } from 'lucide-vue-next';
import { ExportButtons } from '@/components/CapacityReports';
import {
    AssetFilters,
    WarrantyStatusCards,
    LifecycleDistributionChart,
    DeviceInventoryTable,
    AssetCountTables,
} from '@/components/AssetReports';

/**
 * Type definitions for Asset Reports props
 */
interface FilterOption {
    id: number;
    name: string;
}

interface LifecycleOption {
    value: string;
    label: string;
}

interface Filters {
    datacenter_id: number | null;
    room_id: number | null;
    device_type_id: number | null;
    lifecycle_status: string | null;
    manufacturer: string | null;
    warranty_start: string | null;
    warranty_end: string | null;
}

interface WarrantyStatus {
    active: number;
    expiring_soon: number;
    expired: number;
    unknown: number;
}

interface LifecycleDistributionItem {
    status: string;
    label: string;
    count: number;
    percentage: number;
}

interface CountItem {
    name: string;
    count: number;
}

interface Device {
    id: number;
    asset_tag: string;
    name: string;
    serial_number: string | null;
    manufacturer: string | null;
    model: string | null;
    device_type: {
        id: number;
        name: string;
    } | null;
    lifecycle_status: string;
    lifecycle_status_label: string;
    datacenter_name: string | null;
    room_name: string | null;
    rack_name: string | null;
    start_u: number | null;
    warranty_end_date: string | null;
}

interface Pagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Metrics {
    warrantyStatus: WarrantyStatus;
    lifecycleDistribution: LifecycleDistributionItem[];
    countsByType: CountItem[];
    countsByManufacturer: CountItem[];
    devices: Device[];
    pagination: Pagination;
}

interface Props {
    metrics: Metrics;
    datacenterOptions: FilterOption[];
    roomOptions: FilterOption[];
    deviceTypeOptions: FilterOption[];
    lifecycleStatusOptions: LifecycleOption[];
    manufacturerOptions: string[];
    filters: Filters;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Asset Reports',
        href: assetReportsIndex.url(),
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
    if (props.filters.device_type_id) {
        params.device_type_id = String(props.filters.device_type_id);
    }
    if (props.filters.lifecycle_status) {
        params.lifecycle_status = props.filters.lifecycle_status;
    }
    if (props.filters.manufacturer) {
        params.manufacturer = props.filters.manufacturer;
    }
    if (props.filters.warranty_start) {
        params.warranty_start = props.filters.warranty_start;
    }
    if (props.filters.warranty_end) {
        params.warranty_end = props.filters.warranty_end;
    }

    if (format === 'pdf') {
        return exportPdf.url({ query: params });
    }
    return exportCsv.url({ query: params });
};

// Check if there is any device data
const hasDeviceData = computed(() => {
    return props.metrics.pagination.total > 0;
});

// Handle page change for device inventory table
const handlePageChange = (page: number) => {
    isFiltering.value = true;

    const params: Record<string, string | number> = {
        page,
    };

    if (props.filters.datacenter_id) {
        params.datacenter_id = props.filters.datacenter_id;
    }
    if (props.filters.room_id) {
        params.room_id = props.filters.room_id;
    }
    if (props.filters.device_type_id) {
        params.device_type_id = props.filters.device_type_id;
    }
    if (props.filters.lifecycle_status) {
        params.lifecycle_status = props.filters.lifecycle_status;
    }
    if (props.filters.manufacturer) {
        params.manufacturer = props.filters.manufacturer;
    }
    if (props.filters.warranty_start) {
        params.warranty_start = props.filters.warranty_start;
    }
    if (props.filters.warranty_end) {
        params.warranty_end = props.filters.warranty_end;
    }

    router.get(assetReportsIndex.url(), params, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            isFiltering.value = false;
        },
    });
};

// Handle sort change (currently just client-side)
const handleSort = (column: string, direction: 'asc' | 'desc') => {
    // Sorting is handled client-side in the DeviceInventoryTable component
    // Future enhancement: server-side sorting
};
</script>

<template>
    <Head title="Asset Reports" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 md:p-6">
            <!-- Header with title and export buttons -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <HeadingSmall
                    title="Asset Reports"
                    description="View device inventory, warranty status, lifecycle distribution, and asset counts across your datacenters."
                />

                <!-- Export Buttons -->
                <ExportButtons
                    :pdf-url="buildExportUrl('pdf')"
                    :csv-url="buildExportUrl('csv')"
                    :loading="isFiltering"
                />
            </div>

            <!-- Filters -->
            <AssetFilters
                :filters="filters"
                :datacenters="datacenterOptions"
                :rooms="roomOptions"
                :device-types="deviceTypeOptions"
                :lifecycle-statuses="lifecycleStatusOptions"
                :manufacturers="manufacturerOptions"
                @filtering="handleFiltering"
            />

            <!-- Skeleton loading state -->
            <template v-if="isFiltering">
                <!-- Warranty Status Cards Skeleton -->
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

                <!-- Chart and Tables Skeleton -->
                <div class="grid gap-4 md:grid-cols-2">
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
                            <Skeleton class="h-64 w-full" />
                        </CardContent>
                    </Card>
                </div>

                <!-- Device Inventory Skeleton -->
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
                <div v-if="!hasDeviceData" class="py-12 text-center">
                    <Package class="mx-auto mb-4 size-12 text-muted-foreground/50" />
                    <h3 class="text-lg font-medium">No device data available</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Add devices to see asset reports.
                    </p>
                </div>

                <template v-else>
                    <!-- Warranty Status Cards -->
                    <WarrantyStatusCards :warranty-status="metrics.warrantyStatus" />

                    <!-- Lifecycle Distribution and Asset Counts Row -->
                    <div class="grid gap-4 lg:grid-cols-2">
                        <!-- Lifecycle Distribution Chart -->
                        <LifecycleDistributionChart :distribution="metrics.lifecycleDistribution" />

                        <!-- Asset Count Tables -->
                        <div class="flex flex-col gap-4">
                            <AssetCountTables
                                :counts-by-type="metrics.countsByType"
                                :counts-by-manufacturer="metrics.countsByManufacturer"
                            />
                        </div>
                    </div>

                    <!-- Device Inventory Table -->
                    <DeviceInventoryTable
                        :devices="metrics.devices"
                        :pagination="metrics.pagination"
                        :loading="isFiltering"
                        @page-change="handlePageChange"
                        @sort="handleSort"
                    />
                </template>
            </template>
        </div>
    </AppLayout>
</template>
