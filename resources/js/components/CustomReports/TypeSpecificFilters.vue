<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ChevronDown, SlidersHorizontal, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/**
 * TypeScript interfaces
 */
interface FilterOption {
    value: string;
    label: string;
}

interface FilterConfig {
    type: string;
    label: string;
    options?: FilterOption[];
}

interface Props {
    reportType: string;
    filterOptions: Record<string, FilterConfig>;
    filters: Record<string, unknown>;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:filters', value: Record<string, unknown>): void;
}>();

// Mobile collapsible state
const isOpen = ref(false);

// Common select styling with accessible focus states
const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 dark:border-input dark:bg-transparent dark:text-foreground';

// Local filter state based on report type
// Assets filters
const deviceTypeId = ref<string>(
    props.filters.device_type_id ? String(props.filters.device_type_id) : '',
);
const lifecycleStatus = ref<string>(
    props.filters.lifecycle_status
        ? String(props.filters.lifecycle_status)
        : '',
);
const manufacturer = ref<string>(
    props.filters.manufacturer ? String(props.filters.manufacturer) : '',
);
const warrantyStart = ref<string>(
    props.filters.warranty_start ? String(props.filters.warranty_start) : '',
);
const warrantyEnd = ref<string>(
    props.filters.warranty_end ? String(props.filters.warranty_end) : '',
);

// Capacity filters
const utilizationThreshold = ref<string>(
    props.filters.utilization_threshold
        ? String(props.filters.utilization_threshold)
        : '',
);

// Connections filters
const cableType = ref<string>(
    props.filters.cable_type ? String(props.filters.cable_type) : '',
);
const connectionStatus = ref<string>(
    props.filters.connection_status
        ? String(props.filters.connection_status)
        : '',
);

// AuditHistory filters
const startDate = ref<string>(
    props.filters.start_date ? String(props.filters.start_date) : '',
);
const endDate = ref<string>(
    props.filters.end_date ? String(props.filters.end_date) : '',
);
const auditType = ref<string>(
    props.filters.audit_type ? String(props.filters.audit_type) : '',
);
const findingSeverity = ref<string>(
    props.filters.finding_severity
        ? String(props.filters.finding_severity)
        : '',
);

/**
 * Get filter options for a specific filter key
 */
function getFilterOptions(filterKey: string): FilterOption[] {
    return props.filterOptions[filterKey]?.options || [];
}

/**
 * Check if we should show filters for this report type
 */
const hasTypeSpecificFilters = computed(() => {
    // Location filters are handled by CustomReportFilters
    const locationFilters = ['datacenter_id', 'room_id', 'row_id'];
    const typeSpecificFilters = Object.keys(props.filterOptions).filter(
        (key) => !locationFilters.includes(key),
    );
    return typeSpecificFilters.length > 0;
});

/**
 * Check if any type-specific filters are active
 */
const hasActiveFilters = computed(() => {
    switch (props.reportType) {
        case 'assets':
            return !!(
                deviceTypeId.value ||
                lifecycleStatus.value ||
                manufacturer.value ||
                warrantyStart.value ||
                warrantyEnd.value
            );
        case 'capacity':
            return !!utilizationThreshold.value;
        case 'connections':
            return !!(cableType.value || connectionStatus.value);
        case 'audit_history':
            return !!(
                startDate.value ||
                endDate.value ||
                auditType.value ||
                findingSeverity.value
            );
        default:
            return false;
    }
});

/**
 * Count of active type-specific filters for badge display
 */
const activeFilterCount = computed(() => {
    let count = 0;
    switch (props.reportType) {
        case 'assets':
            if (deviceTypeId.value) count++;
            if (lifecycleStatus.value) count++;
            if (manufacturer.value) count++;
            if (warrantyStart.value || warrantyEnd.value) count++;
            break;
        case 'capacity':
            if (utilizationThreshold.value) count++;
            break;
        case 'connections':
            if (cableType.value) count++;
            if (connectionStatus.value) count++;
            break;
        case 'audit_history':
            if (startDate.value || endDate.value) count++;
            if (auditType.value) count++;
            if (findingSeverity.value) count++;
            break;
    }
    return count;
});

/**
 * Emit filter update
 */
function emitFilters() {
    const typeFilters: Record<string, unknown> = { ...props.filters };

    switch (props.reportType) {
        case 'assets':
            typeFilters.device_type_id = deviceTypeId.value
                ? parseInt(deviceTypeId.value)
                : null;
            typeFilters.lifecycle_status = lifecycleStatus.value || null;
            typeFilters.manufacturer = manufacturer.value || null;
            typeFilters.warranty_start = warrantyStart.value || null;
            typeFilters.warranty_end = warrantyEnd.value || null;
            break;
        case 'capacity':
            typeFilters.utilization_threshold = utilizationThreshold.value
                ? parseFloat(utilizationThreshold.value)
                : null;
            break;
        case 'connections':
            typeFilters.cable_type = cableType.value || null;
            typeFilters.connection_status = connectionStatus.value || null;
            break;
        case 'audit_history':
            typeFilters.start_date = startDate.value || null;
            typeFilters.end_date = endDate.value || null;
            typeFilters.audit_type = auditType.value || null;
            typeFilters.finding_severity = findingSeverity.value || null;
            break;
    }

    emit('update:filters', typeFilters);
}

/**
 * Clear all type-specific filters
 */
function clearFilters() {
    switch (props.reportType) {
        case 'assets':
            deviceTypeId.value = '';
            lifecycleStatus.value = '';
            manufacturer.value = '';
            warrantyStart.value = '';
            warrantyEnd.value = '';
            break;
        case 'capacity':
            utilizationThreshold.value = '';
            break;
        case 'connections':
            cableType.value = '';
            connectionStatus.value = '';
            break;
        case 'audit_history':
            startDate.value = '';
            endDate.value = '';
            auditType.value = '';
            findingSeverity.value = '';
            break;
    }
    emitFilters();
}

// Watch all filter values and emit changes
watch(
    [
        deviceTypeId,
        lifecycleStatus,
        manufacturer,
        warrantyStart,
        warrantyEnd,
        utilizationThreshold,
        cableType,
        connectionStatus,
        startDate,
        endDate,
        auditType,
        findingSeverity,
    ],
    () => {
        emitFilters();
    },
);

// Sync with external filter changes
watch(
    () => props.filters,
    (newFilters) => {
        // Assets
        if (newFilters.device_type_id !== undefined) {
            deviceTypeId.value = newFilters.device_type_id
                ? String(newFilters.device_type_id)
                : '';
        }
        if (newFilters.lifecycle_status !== undefined) {
            lifecycleStatus.value = newFilters.lifecycle_status
                ? String(newFilters.lifecycle_status)
                : '';
        }
        if (newFilters.manufacturer !== undefined) {
            manufacturer.value = newFilters.manufacturer
                ? String(newFilters.manufacturer)
                : '';
        }
        if (newFilters.warranty_start !== undefined) {
            warrantyStart.value = newFilters.warranty_start
                ? String(newFilters.warranty_start)
                : '';
        }
        if (newFilters.warranty_end !== undefined) {
            warrantyEnd.value = newFilters.warranty_end
                ? String(newFilters.warranty_end)
                : '';
        }
        // Capacity
        if (newFilters.utilization_threshold !== undefined) {
            utilizationThreshold.value = newFilters.utilization_threshold
                ? String(newFilters.utilization_threshold)
                : '';
        }
        // Connections
        if (newFilters.cable_type !== undefined) {
            cableType.value = newFilters.cable_type
                ? String(newFilters.cable_type)
                : '';
        }
        if (newFilters.connection_status !== undefined) {
            connectionStatus.value = newFilters.connection_status
                ? String(newFilters.connection_status)
                : '';
        }
        // AuditHistory
        if (newFilters.start_date !== undefined) {
            startDate.value = newFilters.start_date
                ? String(newFilters.start_date)
                : '';
        }
        if (newFilters.end_date !== undefined) {
            endDate.value = newFilters.end_date
                ? String(newFilters.end_date)
                : '';
        }
        if (newFilters.audit_type !== undefined) {
            auditType.value = newFilters.audit_type
                ? String(newFilters.audit_type)
                : '';
        }
        if (newFilters.finding_severity !== undefined) {
            findingSeverity.value = newFilters.finding_severity
                ? String(newFilters.finding_severity)
                : '';
        }
    },
    { deep: true },
);

// Reset filters when report type changes
watch(
    () => props.reportType,
    () => {
        deviceTypeId.value = '';
        lifecycleStatus.value = '';
        manufacturer.value = '';
        warrantyStart.value = '';
        warrantyEnd.value = '';
        utilizationThreshold.value = '';
        cableType.value = '';
        connectionStatus.value = '';
        startDate.value = '';
        endDate.value = '';
        auditType.value = '';
        findingSeverity.value = '';
    },
);
</script>

<template>
    <div v-if="hasTypeSpecificFilters">
        <!-- Mobile: Collapsible -->
        <div class="lg:hidden">
            <Collapsible v-model:open="isOpen">
                <Card>
                    <CardHeader class="p-3">
                        <CollapsibleTrigger
                            class="flex w-full items-center justify-between"
                            :aria-expanded="isOpen"
                            aria-controls="type-filters-mobile-content"
                        >
                            <CardTitle
                                class="flex items-center gap-2 text-base"
                            >
                                <SlidersHorizontal
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                <span>Report Filters</span>
                                <Badge
                                    v-if="hasActiveFilters"
                                    variant="default"
                                    class="ml-1"
                                    :aria-label="`${activeFilterCount} active filter${activeFilterCount !== 1 ? 's' : ''}`"
                                >
                                    {{ activeFilterCount }}
                                </Badge>
                            </CardTitle>
                            <ChevronDown
                                class="size-4 shrink-0 transition-transform duration-200"
                                :class="{ 'rotate-180': isOpen }"
                                aria-hidden="true"
                            />
                        </CollapsibleTrigger>
                    </CardHeader>
                    <CollapsibleContent id="type-filters-mobile-content">
                        <CardContent class="space-y-4 pt-0">
                            <!-- Assets Filters -->
                            <template v-if="reportType === 'assets'">
                                <!-- Device Type -->
                                <div
                                    v-if="filterOptions.device_type_id"
                                    class="space-y-2"
                                >
                                    <Label for="device-type-mobile"
                                        >Device Type</Label
                                    >
                                    <select
                                        id="device-type-mobile"
                                        v-model="deviceTypeId"
                                        :class="selectClass"
                                    >
                                        <option value="">All Types</option>
                                        <option
                                            v-for="option in getFilterOptions(
                                                'device_type_id',
                                            )"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Lifecycle Status -->
                                <div
                                    v-if="filterOptions.lifecycle_status"
                                    class="space-y-2"
                                >
                                    <Label for="lifecycle-status-mobile"
                                        >Lifecycle Status</Label
                                    >
                                    <select
                                        id="lifecycle-status-mobile"
                                        v-model="lifecycleStatus"
                                        :class="selectClass"
                                    >
                                        <option value="">All Statuses</option>
                                        <option
                                            v-for="option in getFilterOptions(
                                                'lifecycle_status',
                                            )"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Manufacturer -->
                                <div
                                    v-if="filterOptions.manufacturer"
                                    class="space-y-2"
                                >
                                    <Label for="manufacturer-mobile"
                                        >Manufacturer</Label
                                    >
                                    <select
                                        id="manufacturer-mobile"
                                        v-model="manufacturer"
                                        :class="selectClass"
                                    >
                                        <option value="">
                                            All Manufacturers
                                        </option>
                                        <option
                                            v-for="option in getFilterOptions(
                                                'manufacturer',
                                            )"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Warranty Date Range -->
                                <fieldset class="space-y-2">
                                    <legend class="text-sm font-medium">
                                        Warranty Date Range
                                    </legend>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <Label
                                                for="warranty-start-mobile"
                                                class="sr-only"
                                                >From date</Label
                                            >
                                            <Input
                                                id="warranty-start-mobile"
                                                v-model="warrantyStart"
                                                type="date"
                                                aria-label="Warranty start date"
                                            />
                                        </div>
                                        <div>
                                            <Label
                                                for="warranty-end-mobile"
                                                class="sr-only"
                                                >To date</Label
                                            >
                                            <Input
                                                id="warranty-end-mobile"
                                                v-model="warrantyEnd"
                                                type="date"
                                                aria-label="Warranty end date"
                                            />
                                        </div>
                                    </div>
                                </fieldset>
                            </template>

                            <!-- Capacity Filters -->
                            <template v-if="reportType === 'capacity'">
                                <div
                                    v-if="filterOptions.utilization_threshold"
                                    class="space-y-2"
                                >
                                    <Label for="utilization-threshold-mobile"
                                        >Utilization Threshold (%)</Label
                                    >
                                    <Input
                                        id="utilization-threshold-mobile"
                                        v-model="utilizationThreshold"
                                        type="number"
                                        min="0"
                                        max="100"
                                        placeholder="e.g., 80"
                                        aria-describedby="utilization-threshold-mobile-description"
                                    />
                                    <p
                                        id="utilization-threshold-mobile-description"
                                        class="text-xs text-muted-foreground"
                                    >
                                        Show racks with utilization above this
                                        threshold
                                    </p>
                                </div>
                            </template>

                            <!-- Connections Filters -->
                            <template v-if="reportType === 'connections'">
                                <!-- Cable Type -->
                                <div
                                    v-if="filterOptions.cable_type"
                                    class="space-y-2"
                                >
                                    <Label for="cable-type-mobile"
                                        >Cable Type</Label
                                    >
                                    <select
                                        id="cable-type-mobile"
                                        v-model="cableType"
                                        :class="selectClass"
                                    >
                                        <option value="">
                                            All Cable Types
                                        </option>
                                        <option
                                            v-for="option in getFilterOptions(
                                                'cable_type',
                                            )"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Connection Status -->
                                <div
                                    v-if="filterOptions.connection_status"
                                    class="space-y-2"
                                >
                                    <Label for="connection-status-mobile"
                                        >Connection Status</Label
                                    >
                                    <select
                                        id="connection-status-mobile"
                                        v-model="connectionStatus"
                                        :class="selectClass"
                                    >
                                        <option value="">All Statuses</option>
                                        <option
                                            v-for="option in getFilterOptions(
                                                'connection_status',
                                            )"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </div>
                            </template>

                            <!-- Audit History Filters -->
                            <template v-if="reportType === 'audit_history'">
                                <!-- Date Range -->
                                <fieldset class="space-y-2">
                                    <legend class="text-sm font-medium">
                                        Date Range
                                    </legend>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <Label
                                                for="start-date-mobile"
                                                class="sr-only"
                                                >From date</Label
                                            >
                                            <Input
                                                id="start-date-mobile"
                                                v-model="startDate"
                                                type="date"
                                                aria-label="Start date"
                                            />
                                        </div>
                                        <div>
                                            <Label
                                                for="end-date-mobile"
                                                class="sr-only"
                                                >To date</Label
                                            >
                                            <Input
                                                id="end-date-mobile"
                                                v-model="endDate"
                                                type="date"
                                                aria-label="End date"
                                            />
                                        </div>
                                    </div>
                                </fieldset>

                                <!-- Audit Type -->
                                <div
                                    v-if="filterOptions.audit_type"
                                    class="space-y-2"
                                >
                                    <Label for="audit-type-mobile"
                                        >Audit Type</Label
                                    >
                                    <select
                                        id="audit-type-mobile"
                                        v-model="auditType"
                                        :class="selectClass"
                                    >
                                        <option value="">All Types</option>
                                        <option
                                            v-for="option in getFilterOptions(
                                                'audit_type',
                                            )"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Finding Severity -->
                                <div
                                    v-if="filterOptions.finding_severity"
                                    class="space-y-2"
                                >
                                    <Label for="finding-severity-mobile"
                                        >Finding Severity</Label
                                    >
                                    <select
                                        id="finding-severity-mobile"
                                        v-model="findingSeverity"
                                        :class="selectClass"
                                    >
                                        <option value="">All Severities</option>
                                        <option
                                            v-for="option in getFilterOptions(
                                                'finding_severity',
                                            )"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </div>
                            </template>

                            <!-- Clear Filters -->
                            <Button
                                v-if="hasActiveFilters"
                                variant="ghost"
                                size="sm"
                                class="w-full"
                                @click="clearFilters"
                                aria-label="Clear all report filters"
                            >
                                <X class="mr-2 size-4" aria-hidden="true" />
                                Clear Filters
                            </Button>
                        </CardContent>
                    </CollapsibleContent>
                </Card>
            </Collapsible>
        </div>

        <!-- Desktop: Inline layout -->
        <div class="hidden lg:block">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle
                        class="flex items-center justify-between text-sm font-medium"
                    >
                        <span class="flex items-center gap-2">
                            <SlidersHorizontal
                                class="size-4"
                                aria-hidden="true"
                            />
                            <span>Report Filters</span>
                            <Badge
                                v-if="hasActiveFilters"
                                variant="secondary"
                                class="ml-1"
                                :aria-label="`${activeFilterCount} active filter${activeFilterCount !== 1 ? 's' : ''}`"
                            >
                                {{ activeFilterCount }}
                            </Badge>
                        </span>
                        <Button
                            v-if="hasActiveFilters"
                            variant="ghost"
                            size="sm"
                            class="h-7 px-2 text-xs"
                            @click="clearFilters"
                            aria-label="Clear all report filters"
                        >
                            <X class="mr-1 size-3" aria-hidden="true" />
                            Clear
                        </Button>
                    </CardTitle>
                </CardHeader>
                <CardContent class="pt-0">
                    <!-- Assets Filters -->
                    <template v-if="reportType === 'assets'">
                        <div
                            class="grid grid-cols-2 gap-4 lg:grid-cols-4"
                            role="group"
                            aria-label="Asset filters"
                        >
                            <!-- Device Type -->
                            <div
                                v-if="filterOptions.device_type_id"
                                class="space-y-1"
                            >
                                <Label
                                    for="device-type-desktop"
                                    class="text-xs text-muted-foreground"
                                >
                                    Device Type
                                </Label>
                                <select
                                    id="device-type-desktop"
                                    v-model="deviceTypeId"
                                    :class="selectClass"
                                >
                                    <option value="">All Types</option>
                                    <option
                                        v-for="option in getFilterOptions(
                                            'device_type_id',
                                        )"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                            </div>

                            <!-- Lifecycle Status -->
                            <div
                                v-if="filterOptions.lifecycle_status"
                                class="space-y-1"
                            >
                                <Label
                                    for="lifecycle-status-desktop"
                                    class="text-xs text-muted-foreground"
                                >
                                    Lifecycle Status
                                </Label>
                                <select
                                    id="lifecycle-status-desktop"
                                    v-model="lifecycleStatus"
                                    :class="selectClass"
                                >
                                    <option value="">All Statuses</option>
                                    <option
                                        v-for="option in getFilterOptions(
                                            'lifecycle_status',
                                        )"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                            </div>

                            <!-- Manufacturer -->
                            <div
                                v-if="filterOptions.manufacturer"
                                class="space-y-1"
                            >
                                <Label
                                    for="manufacturer-desktop"
                                    class="text-xs text-muted-foreground"
                                >
                                    Manufacturer
                                </Label>
                                <select
                                    id="manufacturer-desktop"
                                    v-model="manufacturer"
                                    :class="selectClass"
                                >
                                    <option value="">All Manufacturers</option>
                                    <option
                                        v-for="option in getFilterOptions(
                                            'manufacturer',
                                        )"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                            </div>

                            <!-- Warranty Date Range -->
                            <div class="space-y-1">
                                <Label class="text-xs text-muted-foreground"
                                    >Warranty Date Range</Label
                                >
                                <div class="flex gap-2">
                                    <Input
                                        v-model="warrantyStart"
                                        type="date"
                                        class="h-9"
                                        aria-label="Warranty start date"
                                    />
                                    <Input
                                        v-model="warrantyEnd"
                                        type="date"
                                        class="h-9"
                                        aria-label="Warranty end date"
                                    />
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Capacity Filters -->
                    <template v-if="reportType === 'capacity'">
                        <div
                            class="flex items-end gap-4"
                            role="group"
                            aria-label="Capacity filters"
                        >
                            <div
                                v-if="filterOptions.utilization_threshold"
                                class="w-48 space-y-1"
                            >
                                <Label
                                    for="utilization-threshold-desktop"
                                    class="text-xs text-muted-foreground"
                                >
                                    Utilization Threshold (%)
                                </Label>
                                <Input
                                    id="utilization-threshold-desktop"
                                    v-model="utilizationThreshold"
                                    type="number"
                                    min="0"
                                    max="100"
                                    placeholder="e.g., 80"
                                    aria-describedby="utilization-threshold-desktop-description"
                                />
                            </div>
                            <p
                                id="utilization-threshold-desktop-description"
                                class="pb-2 text-xs text-muted-foreground"
                            >
                                Show racks with utilization above this threshold
                            </p>
                        </div>
                    </template>

                    <!-- Connections Filters -->
                    <template v-if="reportType === 'connections'">
                        <div
                            class="grid grid-cols-2 gap-4 lg:grid-cols-4"
                            role="group"
                            aria-label="Connection filters"
                        >
                            <!-- Cable Type -->
                            <div
                                v-if="filterOptions.cable_type"
                                class="space-y-1"
                            >
                                <Label
                                    for="cable-type-desktop"
                                    class="text-xs text-muted-foreground"
                                >
                                    Cable Type
                                </Label>
                                <select
                                    id="cable-type-desktop"
                                    v-model="cableType"
                                    :class="selectClass"
                                >
                                    <option value="">All Cable Types</option>
                                    <option
                                        v-for="option in getFilterOptions(
                                            'cable_type',
                                        )"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                            </div>

                            <!-- Connection Status -->
                            <div
                                v-if="filterOptions.connection_status"
                                class="space-y-1"
                            >
                                <Label
                                    for="connection-status-desktop"
                                    class="text-xs text-muted-foreground"
                                >
                                    Connection Status
                                </Label>
                                <select
                                    id="connection-status-desktop"
                                    v-model="connectionStatus"
                                    :class="selectClass"
                                >
                                    <option value="">All Statuses</option>
                                    <option
                                        v-for="option in getFilterOptions(
                                            'connection_status',
                                        )"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </template>

                    <!-- Audit History Filters -->
                    <template v-if="reportType === 'audit_history'">
                        <div
                            class="grid grid-cols-2 gap-4 lg:grid-cols-4"
                            role="group"
                            aria-label="Audit history filters"
                        >
                            <!-- Date Range -->
                            <div class="space-y-1">
                                <Label
                                    for="start-date-desktop"
                                    class="text-xs text-muted-foreground"
                                    >Start Date</Label
                                >
                                <Input
                                    id="start-date-desktop"
                                    v-model="startDate"
                                    type="date"
                                    class="h-9"
                                />
                            </div>
                            <div class="space-y-1">
                                <Label
                                    for="end-date-desktop"
                                    class="text-xs text-muted-foreground"
                                    >End Date</Label
                                >
                                <Input
                                    id="end-date-desktop"
                                    v-model="endDate"
                                    type="date"
                                    class="h-9"
                                />
                            </div>

                            <!-- Audit Type -->
                            <div
                                v-if="filterOptions.audit_type"
                                class="space-y-1"
                            >
                                <Label
                                    for="audit-type-desktop"
                                    class="text-xs text-muted-foreground"
                                >
                                    Audit Type
                                </Label>
                                <select
                                    id="audit-type-desktop"
                                    v-model="auditType"
                                    :class="selectClass"
                                >
                                    <option value="">All Types</option>
                                    <option
                                        v-for="option in getFilterOptions(
                                            'audit_type',
                                        )"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                            </div>

                            <!-- Finding Severity -->
                            <div
                                v-if="filterOptions.finding_severity"
                                class="space-y-1"
                            >
                                <Label
                                    for="finding-severity-desktop"
                                    class="text-xs text-muted-foreground"
                                >
                                    Finding Severity
                                </Label>
                                <select
                                    id="finding-severity-desktop"
                                    v-model="findingSeverity"
                                    :class="selectClass"
                                >
                                    <option value="">All Severities</option>
                                    <option
                                        v-for="option in getFilterOptions(
                                            'finding_severity',
                                        )"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </template>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
