<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { index as auditHistoryIndex } from '@/actions/App/Http/Controllers/AuditHistoryReportController';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { ChevronDown, Filter, X, Calendar } from 'lucide-vue-next';
import { debounce } from '@/lib/utils';

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

interface Props {
    filters: Filters;
    datacenters: FilterOption[];
    auditTypes: AuditTypeOption[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'filtering', value: boolean): void;
}>();

// Local filter state
const timeRangePreset = ref(props.filters.time_range_preset ?? '');
const startDate = ref(props.filters.start_date ?? '');
const endDate = ref(props.filters.end_date ?? '');
const datacenterId = ref(props.filters.datacenter_id ? String(props.filters.datacenter_id) : '');
const auditType = ref(props.filters.audit_type ?? '');

// Mobile collapsible state
const isOpen = ref(false);

// Show custom date range inputs
const showCustomDateRange = ref(timeRangePreset.value === 'custom' || (!!startDate.value && !!endDate.value));

// Common select styling
const selectClass = 'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring dark:border-input dark:bg-transparent dark:text-foreground';
const inputClass = 'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring dark:border-input dark:bg-transparent dark:text-foreground';

// Time range preset options
const timeRangeOptions = [
    { value: '', label: 'Last 12 months (default)' },
    { value: '30_days', label: 'Last 30 days' },
    { value: '6_months', label: 'Last 6 months' },
    { value: '12_months', label: 'Last 12 months' },
    { value: 'custom', label: 'Custom date range' },
];

// Check if any filters are active
const hasActiveFilters = computed(() => {
    return !!(
        timeRangePreset.value ||
        datacenterId.value ||
        auditType.value ||
        (startDate.value && endDate.value)
    );
});

// Apply filters with Inertia
const applyFilters = () => {
    emit('filtering', true);

    const params: Record<string, string | undefined> = {
        time_range_preset: timeRangePreset.value === 'custom' ? undefined : timeRangePreset.value || undefined,
        start_date: timeRangePreset.value === 'custom' ? startDate.value || undefined : undefined,
        end_date: timeRangePreset.value === 'custom' ? endDate.value || undefined : undefined,
        datacenter_id: datacenterId.value || undefined,
        audit_type: auditType.value || undefined,
        sort_by: props.filters.sort_by !== 'completion_date' ? props.filters.sort_by : undefined,
        sort_direction: props.filters.sort_direction !== 'desc' ? props.filters.sort_direction : undefined,
    };

    // Remove undefined values
    Object.keys(params).forEach(key => {
        if (params[key] === undefined) {
            delete params[key];
        }
    });

    router.get(auditHistoryIndex.url(), params, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            emit('filtering', false);
        },
    });
};

// Debounced filter application
const debouncedApplyFilters = debounce(applyFilters, 300);

// Clear all filters
const clearFilters = () => {
    timeRangePreset.value = '';
    startDate.value = '';
    endDate.value = '';
    datacenterId.value = '';
    auditType.value = '';
    showCustomDateRange.value = false;

    emit('filtering', true);
    router.get(auditHistoryIndex.url(), {}, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            emit('filtering', false);
        },
    });
};

// Watch for time range preset changes
watch(timeRangePreset, (newValue) => {
    if (newValue === 'custom') {
        showCustomDateRange.value = true;
        // Don't apply filters until both dates are set
    } else {
        showCustomDateRange.value = false;
        startDate.value = '';
        endDate.value = '';
        debouncedApplyFilters();
    }
});

// Watch for custom date changes
watch([startDate, endDate], () => {
    if (timeRangePreset.value === 'custom' && startDate.value && endDate.value) {
        debouncedApplyFilters();
    }
});

// Watch for datacenter changes
watch(datacenterId, () => {
    debouncedApplyFilters();
});

// Watch for audit type changes
watch(auditType, () => {
    debouncedApplyFilters();
});
</script>

<template>
    <!-- Mobile: Collapsible -->
    <div class="lg:hidden">
        <Collapsible v-model:open="isOpen">
            <Card>
                <CardHeader class="p-3">
                    <CollapsibleTrigger class="flex w-full items-center justify-between">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Filter class="size-4" />
                            Filters
                            <span
                                v-if="hasActiveFilters"
                                class="rounded-full bg-primary px-2 py-0.5 text-xs text-primary-foreground"
                            >
                                Active
                            </span>
                        </CardTitle>
                        <ChevronDown class="size-4 transition-transform" :class="{ 'rotate-180': isOpen }" />
                    </CollapsibleTrigger>
                </CardHeader>
                <CollapsibleContent>
                    <CardContent class="space-y-4 pt-0">
                        <!-- Time Range Filter -->
                        <div class="space-y-2">
                            <Label for="time-range-mobile">Time Range</Label>
                            <select
                                id="time-range-mobile"
                                v-model="timeRangePreset"
                                :class="selectClass"
                            >
                                <option
                                    v-for="option in timeRangeOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>

                        <!-- Custom Date Range (visible when custom is selected) -->
                        <div v-if="showCustomDateRange" class="space-y-3">
                            <div class="space-y-2">
                                <Label for="start-date-mobile">Start Date</Label>
                                <input
                                    id="start-date-mobile"
                                    v-model="startDate"
                                    type="date"
                                    :class="inputClass"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="end-date-mobile">End Date</Label>
                                <input
                                    id="end-date-mobile"
                                    v-model="endDate"
                                    type="date"
                                    :class="inputClass"
                                />
                            </div>
                        </div>

                        <!-- Datacenter Filter -->
                        <div class="space-y-2">
                            <Label for="datacenter-mobile">Datacenter</Label>
                            <select
                                id="datacenter-mobile"
                                v-model="datacenterId"
                                :class="selectClass"
                            >
                                <option value="">All Datacenters</option>
                                <option
                                    v-for="dc in datacenters"
                                    :key="dc.id"
                                    :value="String(dc.id)"
                                >
                                    {{ dc.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Audit Type Filter -->
                        <div class="space-y-2">
                            <Label for="audit-type-mobile">Audit Type</Label>
                            <select
                                id="audit-type-mobile"
                                v-model="auditType"
                                :class="selectClass"
                            >
                                <option value="">All Types</option>
                                <option
                                    v-for="type in auditTypes"
                                    :key="type.value"
                                    :value="type.value"
                                >
                                    {{ type.label }}
                                </option>
                            </select>
                        </div>

                        <!-- Clear Filters -->
                        <Button
                            v-if="hasActiveFilters"
                            variant="ghost"
                            size="sm"
                            class="w-full"
                            @click="clearFilters"
                        >
                            <X class="mr-2 size-4" />
                            Clear Filters
                        </Button>
                    </CardContent>
                </CollapsibleContent>
            </Card>
        </Collapsible>
    </div>

    <!-- Desktop: Inline filter row -->
    <div class="hidden lg:block">
        <Card>
            <CardContent class="pt-4">
                <div class="flex flex-row flex-wrap items-end gap-4">
                    <!-- Time Range Filter -->
                    <div class="min-w-[180px] flex-1">
                        <Label for="time-range-desktop" class="mb-1 block text-sm font-medium text-muted-foreground">
                            Time Range
                        </Label>
                        <select
                            id="time-range-desktop"
                            v-model="timeRangePreset"
                            :class="selectClass"
                        >
                            <option
                                v-for="option in timeRangeOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </div>

                    <!-- Custom Date Range (visible when custom is selected) -->
                    <template v-if="showCustomDateRange">
                        <div class="min-w-[150px]">
                            <Label for="start-date-desktop" class="mb-1 block text-sm font-medium text-muted-foreground">
                                Start Date
                            </Label>
                            <input
                                id="start-date-desktop"
                                v-model="startDate"
                                type="date"
                                :class="inputClass"
                            />
                        </div>
                        <div class="min-w-[150px]">
                            <Label for="end-date-desktop" class="mb-1 block text-sm font-medium text-muted-foreground">
                                End Date
                            </Label>
                            <input
                                id="end-date-desktop"
                                v-model="endDate"
                                type="date"
                                :class="inputClass"
                            />
                        </div>
                    </template>

                    <!-- Datacenter Filter -->
                    <div class="min-w-[180px] flex-1">
                        <Label for="datacenter-desktop" class="mb-1 block text-sm font-medium text-muted-foreground">
                            Datacenter
                        </Label>
                        <select
                            id="datacenter-desktop"
                            v-model="datacenterId"
                            :class="selectClass"
                        >
                            <option value="">All Datacenters</option>
                            <option
                                v-for="dc in datacenters"
                                :key="dc.id"
                                :value="String(dc.id)"
                            >
                                {{ dc.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Audit Type Filter -->
                    <div class="min-w-[160px] flex-1">
                        <Label for="audit-type-desktop" class="mb-1 block text-sm font-medium text-muted-foreground">
                            Audit Type
                        </Label>
                        <select
                            id="audit-type-desktop"
                            v-model="auditType"
                            :class="selectClass"
                        >
                            <option value="">All Types</option>
                            <option
                                v-for="type in auditTypes"
                                :key="type.value"
                                :value="type.value"
                            >
                                {{ type.label }}
                            </option>
                        </select>
                    </div>

                    <!-- Clear Filters -->
                    <div v-if="hasActiveFilters" class="shrink-0">
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="clearFilters"
                        >
                            <X class="mr-1 size-4" />
                            Clear
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
