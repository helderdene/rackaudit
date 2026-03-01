<script setup lang="ts">
/**
 * ConnectionsInventoryTable Component
 *
 * Displays a sortable, filterable, paginated table of connection inventory data
 * using PrimeVue DataTable with client-side processing.
 */

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { FilterMatchMode } from '@primevue/core/api';
import { Cable } from 'lucide-vue-next';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import { ref } from 'vue';

interface Connection {
    id: number;
    source_device_name: string | null;
    source_port_label: string | null;
    destination_device_name: string | null;
    destination_port_label: string | null;
    cable_type: string | null;
    cable_type_label: string;
    cable_length: number | string | null;
    cable_color: string | null;
}

interface Props {
    connections: Connection[];
    loading?: boolean;
}

withDefaults(defineProps<Props>(), {
    loading: false,
});

// Global filter for search
const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
});

// Format cable length with unit
const formatCableLength = (length: number | string | null): string => {
    if (length === null || length === undefined || length === '') {
        return '-';
    }
    const numLength = typeof length === 'string' ? parseFloat(length) : length;
    if (isNaN(numLength)) {
        return '-';
    }
    return `${numLength}m`;
};

// Get color swatch style for cable color
const getColorSwatchStyle = (
    color: string | null,
): Record<string, string> | null => {
    if (!color) {
        return null;
    }

    // Map common cable colors to CSS colors
    const colorMap: Record<string, string> = {
        black: '#000000',
        blue: '#2563eb',
        red: '#dc2626',
        green: '#16a34a',
        yellow: '#eab308',
        orange: '#ea580c',
        white: '#ffffff',
        gray: '#6b7280',
        grey: '#6b7280',
        purple: '#9333ea',
        pink: '#ec4899',
        brown: '#92400e',
        aqua: '#06b6d4',
        cyan: '#06b6d4',
    };

    const cssColor = colorMap[color.toLowerCase()] || color;

    return {
        backgroundColor: cssColor,
        border:
            cssColor.toLowerCase() === '#ffffff' ? '1px solid #d1d5db' : 'none',
    };
};
</script>

<template>
    <Card>
        <CardHeader
            class="flex flex-row items-center justify-between space-y-0 pb-4"
        >
            <CardTitle class="flex items-center gap-2 text-base">
                <Cable class="size-5" />
                Connections Inventory
                <span class="text-sm font-normal text-muted-foreground">
                    ({{ connections.length }} total)
                </span>
            </CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Loading skeleton -->
            <div v-if="loading" class="space-y-3">
                <Skeleton class="h-10 w-full" />
                <Skeleton class="h-12 w-full" />
                <Skeleton class="h-12 w-full" />
                <Skeleton class="h-12 w-full" />
                <Skeleton class="h-12 w-full" />
                <Skeleton class="h-12 w-full" />
            </div>

            <!-- DataTable content -->
            <template v-else>
                <!-- Empty state - no data at all -->
                <div v-if="connections.length === 0" class="py-12 text-center">
                    <Cable
                        class="mx-auto mb-4 size-12 text-muted-foreground/50"
                    />
                    <h3 class="text-lg font-medium">No connections found</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        No connections match the current filter criteria.
                    </p>
                </div>

                <!-- PrimeVue DataTable with client-side features -->
                <DataTable
                    v-else
                    v-model:filters="filters"
                    :value="connections"
                    :paginator="true"
                    :rows="25"
                    :rowsPerPageOptions="[10, 25, 50, 100]"
                    paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                    currentPageReportTemplate="Showing {first} to {last} of {totalRecords} connections"
                    :globalFilterFields="[
                        'source_device_name',
                        'source_port_label',
                        'destination_device_name',
                        'destination_port_label',
                        'cable_type_label',
                        'cable_color',
                    ]"
                    filterDisplay="row"
                    sortMode="single"
                    removableSort
                    stripedRows
                    size="small"
                    tableStyle="min-width: 50rem"
                >
                    <template #header>
                        <div class="flex justify-end">
                            <IconField>
                                <InputIcon class="pi pi-search" />
                                <InputText
                                    v-model="filters['global'].value"
                                    placeholder="Search connections..."
                                    class="w-64"
                                />
                            </IconField>
                        </div>
                    </template>
                    <template #empty>
                        <div class="py-8 text-center">
                            <div
                                class="pi pi-search mx-auto mb-4 text-4xl text-muted-foreground/50"
                            />
                            <p class="text-muted-foreground">
                                No connections match your search.
                            </p>
                        </div>
                    </template>
                    <Column
                        field="source_device_name"
                        header="Source Device"
                        sortable
                    >
                        <template #body="{ data }">
                            <span class="font-medium">{{
                                data.source_device_name || '-'
                            }}</span>
                        </template>
                    </Column>
                    <Column
                        field="source_port_label"
                        header="Source Port"
                        sortable
                    >
                        <template #body="{ data }">
                            <span class="font-mono text-xs">{{
                                data.source_port_label || '-'
                            }}</span>
                        </template>
                    </Column>
                    <Column
                        field="destination_device_name"
                        header="Dest Device"
                        sortable
                    >
                        <template #body="{ data }">
                            <span class="font-medium">{{
                                data.destination_device_name || '-'
                            }}</span>
                        </template>
                    </Column>
                    <Column
                        field="destination_port_label"
                        header="Dest Port"
                        sortable
                    >
                        <template #body="{ data }">
                            <span class="font-mono text-xs">{{
                                data.destination_port_label || '-'
                            }}</span>
                        </template>
                    </Column>
                    <Column
                        field="cable_type_label"
                        header="Cable Type"
                        sortable
                    >
                        <template #body="{ data }">
                            {{ data.cable_type_label || '-' }}
                        </template>
                    </Column>
                    <Column field="cable_length" header="Length" sortable>
                        <template #body="{ data }">
                            <span
                                class="font-mono text-xs text-muted-foreground"
                            >
                                {{ formatCableLength(data.cable_length) }}
                            </span>
                        </template>
                    </Column>
                    <Column field="cable_color" header="Color">
                        <template #body="{ data }">
                            <div
                                v-if="data.cable_color"
                                class="flex items-center gap-2"
                            >
                                <span
                                    v-if="getColorSwatchStyle(data.cable_color)"
                                    class="inline-block size-4 rounded-full"
                                    :style="
                                        getColorSwatchStyle(data.cable_color) ??
                                        {}
                                    "
                                ></span>
                                <span class="text-sm">{{
                                    data.cable_color
                                }}</span>
                            </div>
                            <span v-else class="text-muted-foreground">-</span>
                        </template>
                    </Column>
                </DataTable>
            </template>
        </CardContent>
    </Card>
</template>

<style scoped>
/* Ensure PrimeVue DataTable fits the design */
:deep(.p-datatable) {
    font-size: 0.875rem;
}

:deep(.p-datatable .p-datatable-header) {
    background: transparent;
    border: none;
    padding: 0 0 1rem 0;
}

:deep(.p-datatable .p-datatable-thead > tr > th) {
    background-color: hsl(var(--muted) / 0.5);
    color: hsl(var(--muted-foreground));
    font-weight: 500;
    padding: 0.75rem;
}

:deep(.p-datatable .p-datatable-tbody > tr) {
    transition: background-color 0.2s;
}

:deep(.p-datatable .p-datatable-tbody > tr:hover) {
    background-color: hsl(var(--muted) / 0.5);
}

:deep(.p-datatable .p-datatable-tbody > tr > td) {
    padding: 0.75rem;
    border-bottom: 1px solid hsl(var(--border));
}

:deep(.p-paginator) {
    padding: 1rem 0 0 0;
    border-top: 1px solid hsl(var(--border));
}

:deep(.p-inputtext) {
    padding: 0.5rem 0.75rem 0.5rem 2.5rem;
    border: 1px solid hsl(var(--border));
    border-radius: 0.375rem;
    background-color: transparent;
}

:deep(.p-inputtext:focus) {
    outline: none;
    box-shadow: 0 0 0 2px hsl(var(--ring));
}

:deep(.p-icon-field) {
    position: relative;
}

:deep(.p-icon-field .p-input-icon) {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: hsl(var(--muted-foreground));
}
</style>
