<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { index as discrepanciesIndex } from '@/actions/App/Http/Controllers/DiscrepancyController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import DiscrepancyFilters from '@/components/Discrepancies/DiscrepancyFilters.vue';
import DiscrepancyTable from '@/components/Discrepancies/DiscrepancyTable.vue';
import DiscrepancySummaryStats from '@/components/Discrepancies/DiscrepancySummaryStats.vue';
import DiscrepancyDetailModal from '@/components/Discrepancies/DiscrepancyDetailModal.vue';
import RunDetectionButton from '@/components/Discrepancies/RunDetectionButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { ref } from 'vue';

interface PortData {
    id: number;
    label: string;
    type: string | null;
    type_label: string | null;
    device_id: number | null;
    device: {
        id: number;
        name: string;
        asset_tag: string | null;
        rack: {
            id: number;
            name: string;
        } | null;
    } | null;
}

interface DiscrepancyData {
    id: number;
    discrepancy_type: string;
    discrepancy_type_label: string;
    status: string;
    status_label: string;
    title: string | null;
    description: string | null;
    detected_at: string | null;
    datacenter: {
        id: number;
        name: string;
    } | null;
    room: {
        id: number;
        name: string;
    } | null;
    source_port: PortData | null;
    dest_port: PortData | null;
    expected_config: Record<string, unknown> | null;
    actual_config: Record<string, unknown> | null;
    mismatch_details: Record<string, unknown> | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedDiscrepancies {
    data: DiscrepancyData[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface SummaryData {
    total: number;
    by_type: Record<string, number>;
    by_status: Record<string, number>;
    by_datacenter: Array<{
        id: number;
        name: string;
        count: number;
    }>;
}

interface FilterOption {
    value: string;
    label: string;
}

interface DatacenterOption {
    id: number;
    name: string;
}

interface RoomOption {
    id: number;
    name: string;
}

interface Filters {
    discrepancy_type: string;
    datacenter_id: string;
    room_id: string;
    status: string;
    date_from: string;
    date_to: string;
    sort_by: string;
    sort_order: string;
}

interface Props {
    discrepancies: PaginatedDiscrepancies;
    summary: SummaryData;
    filters: Filters;
    datacenters: DatacenterOption[];
    rooms: RoomOption[];
    typeOptions: FilterOption[];
    statusOptions: FilterOption[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Discrepancies',
        href: discrepanciesIndex.url(),
    },
];

// Selected discrepancy for modal
const selectedDiscrepancy = ref<DiscrepancyData | null>(null);
const showDetailModal = ref(false);

// Open detail modal
const openDetail = (discrepancy: DiscrepancyData) => {
    selectedDiscrepancy.value = discrepancy;
    showDetailModal.value = true;
};

// Close detail modal
const closeDetail = () => {
    showDetailModal.value = false;
    selectedDiscrepancy.value = null;
};
</script>

<template>
    <Head title="Discrepancies" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 md:p-6">
            <!-- Header - responsive layout -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <HeadingSmall
                    title="Discrepancy Dashboard"
                    description="Monitor and manage detected discrepancies between expected and actual connections."
                />
                <div class="flex items-center gap-2">
                    <RunDetectionButton :datacenters="datacenters" />
                </div>
            </div>

            <!-- Summary Stats -->
            <DiscrepancySummaryStats
                :summary="summary"
                :filters="filters"
            />

            <!-- Filters and Table in responsive layout -->
            <div class="flex flex-col gap-4 lg:flex-row">
                <!-- Filters sidebar (mobile: collapsible, desktop: fixed sidebar) -->
                <div class="w-full lg:w-64 lg:shrink-0">
                    <DiscrepancyFilters
                        :filters="filters"
                        :datacenters="datacenters"
                        :rooms="rooms"
                        :type-options="typeOptions"
                        :status-options="statusOptions"
                    />
                </div>

                <!-- Main content area -->
                <div class="flex-1 min-w-0">
                    <DiscrepancyTable
                        :discrepancies="discrepancies"
                        :filters="filters"
                        @select="openDetail"
                    />
                </div>
            </div>

            <!-- Detail Modal -->
            <DiscrepancyDetailModal
                v-if="selectedDiscrepancy"
                :discrepancy="selectedDiscrepancy"
                :open="showDetailModal"
                @close="closeDetail"
            />
        </div>
    </AppLayout>
</template>
