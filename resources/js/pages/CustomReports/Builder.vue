<script setup lang="ts">
import {
    configure,
    index as customReportsIndex,
    preview,
} from '@/actions/App/Http/Controllers/CustomReportBuilderController';
import ColumnSelector from '@/components/CustomReports/ColumnSelector.vue';
import CustomReportFilters from '@/components/CustomReports/CustomReportFilters.vue';
import ExportButtons from '@/components/CustomReports/ExportButtons.vue';
import GroupBySelector from '@/components/CustomReports/GroupBySelector.vue';
import PreviewTable from '@/components/CustomReports/PreviewTable.vue';
import ReportTypeSelector from '@/components/CustomReports/ReportTypeSelector.vue';
import SortConfiguration from '@/components/CustomReports/SortConfiguration.vue';
import TypeSpecificFilters from '@/components/CustomReports/TypeSpecificFilters.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    Eye,
    FileBarChart,
    Settings,
} from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

/**
 * TypeScript interfaces for Custom Report Builder props
 */
interface ReportTypeOption {
    value: string;
    label: string;
    description: string;
}

interface DatacenterOption {
    id: number;
    name: string;
}

interface RoomOption {
    id: number;
    name: string;
}

interface RowOption {
    id: number;
    name: string;
}

interface ReportField {
    key: string;
    display_name: string;
    category: string;
    is_calculated?: boolean;
    data_type?: string;
}

interface FilterConfig {
    type: string;
    label: string;
    options?: Array<{ value: string; label: string }>;
}

interface ColumnHeader {
    key: string;
    label: string;
}

interface Pagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface PreviewData {
    columns: ColumnHeader[];
    data: Array<Record<string, unknown>>;
    pagination: Pagination;
}

interface SortConfig {
    column: string;
    direction: 'asc' | 'desc';
}

interface Props {
    reportTypes: ReportTypeOption[];
    datacenterOptions: DatacenterOption[];
    roomOptions?: RoomOption[];
    rowOptions?: RowOption[];
    selectedReportType?: string | null;
    selectedColumns?: string[];
    selectedFilters?: Record<string, unknown>;
    selectedSort?: SortConfig[];
    selectedGroupBy?: string | null;
    previewData?: PreviewData | null;
}

const props = withDefaults(defineProps<Props>(), {
    roomOptions: () => [],
    rowOptions: () => [],
    selectedReportType: null,
    selectedColumns: () => [],
    selectedFilters: () => ({}),
    selectedSort: () => [],
    selectedGroupBy: null,
    previewData: null,
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Custom Reports',
        href: customReportsIndex.url(),
    },
];

// UI flow steps
type BuilderStep = 'select-type' | 'configure' | 'preview';

// Local state
const currentStep = ref<BuilderStep>(
    props.previewData
        ? 'preview'
        : props.selectedReportType
          ? 'configure'
          : 'select-type',
);
const selectedReportType = ref<string | null>(props.selectedReportType);
const selectedColumns = ref<string[]>(props.selectedColumns);
const selectedFilters = ref<Record<string, unknown>>(props.selectedFilters);
const selectedSort = ref<SortConfig[]>(props.selectedSort);
const selectedGroupBy = ref<string | null>(props.selectedGroupBy);

// Location options for cascading filters
const roomOptions = ref<RoomOption[]>(props.roomOptions);
const rowOptions = ref<RowOption[]>(props.rowOptions);

// Configuration loaded from API
const availableFields = ref<ReportField[]>([]);
const availableFilters = ref<Record<string, FilterConfig>>({});
const calculatedFields = ref<ReportField[]>([]);

// Loading states
const isLoadingConfig = ref(false);
const isGeneratingPreview = ref(false);

// Error state
const configError = ref<string | null>(null);

// Refs for focus management
const stepContentRef = ref<HTMLElement | null>(null);
const announcementRef = ref<HTMLElement | null>(null);

/**
 * Step labels for the breadcrumb navigation
 */
const steps = [
    { key: 'select-type' as const, label: 'Select Type', number: 1 },
    { key: 'configure' as const, label: 'Configure', number: 2 },
    { key: 'preview' as const, label: 'Preview', number: 3 },
];

const currentStepIndex = computed(() => {
    return steps.findIndex((s) => s.key === currentStep.value);
});

/**
 * Get the current step label for screen reader announcement
 */
const currentStepLabel = computed(() => {
    return steps[currentStepIndex.value]?.label || '';
});

/**
 * Get the selected report type label
 */
const selectedReportTypeLabel = computed(() => {
    if (!selectedReportType.value) return '';
    const type = props.reportTypes.find(
        (t) => t.value === selectedReportType.value,
    );
    return type?.label || selectedReportType.value;
});

/**
 * Check if we can proceed to the next step
 */
const canProceedToPreview = computed(() => {
    return selectedReportType.value && selectedColumns.value.length > 0;
});

/**
 * Group fields by category for the column selector
 */
const fieldsByCategory = computed(() => {
    const grouped: Record<string, ReportField[]> = {};

    for (const field of availableFields.value) {
        const category = field.category || 'Other';
        if (!grouped[category]) {
            grouped[category] = [];
        }
        grouped[category].push(field);
    }

    return grouped;
});

/**
 * Get selected columns as ReportField objects for sort/group configuration
 */
const selectedColumnsAsFields = computed(() => {
    return selectedColumns.value
        .map((key) => availableFields.value.find((f) => f.key === key))
        .filter((f): f is ReportField => f !== undefined);
});

/**
 * Build report configuration for export
 */
const reportConfig = computed(() => ({
    report_type: selectedReportType.value || '',
    columns: selectedColumns.value,
    filters: selectedFilters.value,
    sort: selectedSort.value,
    group_by: selectedGroupBy.value,
}));

/**
 * Announce step change to screen readers
 */
function announceStepChange(stepLabel: string) {
    if (announcementRef.value) {
        announcementRef.value.textContent = `Step ${currentStepIndex.value + 1} of ${steps.length}: ${stepLabel}`;
    }
}

/**
 * Focus the step content area after navigation
 */
function focusStepContent() {
    nextTick(() => {
        if (stepContentRef.value) {
            // Find the first focusable element in the step content
            const focusable = stepContentRef.value.querySelector<HTMLElement>(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
            );
            if (focusable) {
                focusable.focus();
            } else {
                // Fallback: focus the container itself
                stepContentRef.value.focus();
            }
        }
    });
}

/**
 * Handle report type selection
 */
async function handleReportTypeSelect(reportType: string) {
    selectedReportType.value = reportType;
    selectedColumns.value = [];
    selectedFilters.value = {};
    selectedSort.value = [];
    selectedGroupBy.value = null;
    configError.value = null;

    // Fetch configuration for the selected report type
    isLoadingConfig.value = true;

    try {
        const response = await fetch(
            configure.url({ query: { report_type: reportType } }),
        );

        if (!response.ok) {
            throw new Error('Failed to load report configuration');
        }

        const data = await response.json();

        availableFields.value = data.fields || [];
        availableFilters.value = data.filters || {};
        calculatedFields.value = data.calculatedFields || [];

        // Move to configure step
        currentStep.value = 'configure';
        announceStepChange('Configure');
        focusStepContent();
    } catch (error) {
        configError.value =
            'Failed to load report configuration. Please try again.';
        console.error('Error loading configuration:', error);
    } finally {
        isLoadingConfig.value = false;
    }
}

/**
 * Handle column selection update
 */
function handleColumnsUpdate(columns: string[]) {
    selectedColumns.value = columns;
}

/**
 * Handle filter update
 */
function handleFiltersUpdate(filters: Record<string, unknown>) {
    selectedFilters.value = { ...selectedFilters.value, ...filters };
}

/**
 * Handle location filter change for cascading behavior
 */
async function handleLocationChange(location: {
    type: 'datacenter' | 'room' | 'row';
    id: number | null;
}) {
    // Reset child options when parent changes
    if (location.type === 'datacenter') {
        roomOptions.value = [];
        rowOptions.value = [];

        if (location.id) {
            // Fetch rooms for this datacenter
            try {
                const response = await fetch(
                    `/api/datacenters/${location.id}/rooms`,
                );
                if (response.ok) {
                    roomOptions.value = await response.json();
                }
            } catch (error) {
                console.error('Failed to fetch rooms:', error);
            }
        }
    } else if (location.type === 'room') {
        rowOptions.value = [];

        if (location.id) {
            // Fetch rows for this room
            try {
                const response = await fetch(`/api/rooms/${location.id}/rows`);
                if (response.ok) {
                    rowOptions.value = await response.json();
                }
            } catch (error) {
                console.error('Failed to fetch rows:', error);
            }
        }
    }
}

/**
 * Handle sort configuration update
 */
function handleSortUpdate(sort: SortConfig[]) {
    selectedSort.value = sort;
}

/**
 * Handle group by update
 */
function handleGroupByUpdate(groupBy: string | null) {
    selectedGroupBy.value = groupBy;
}

/**
 * Handle page change in preview table
 */
function handlePageChange(page: number) {
    isGeneratingPreview.value = true;

    // Ensure sort has default if empty
    const sortConfig =
        selectedSort.value.length > 0
            ? selectedSort.value
            : selectedColumns.value.length > 0
              ? [
                    {
                        column: selectedColumns.value[0],
                        direction: 'desc' as const,
                    },
                ]
              : [];

    router.post(
        preview.url(),
        {
            report_type: selectedReportType.value,
            columns: selectedColumns.value,
            filters: selectedFilters.value,
            sort: sortConfig,
            group_by: selectedGroupBy.value,
            page,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                isGeneratingPreview.value = false;
            },
        },
    );
}

/**
 * Generate preview with current configuration
 */
function generatePreview() {
    if (!canProceedToPreview.value) return;

    isGeneratingPreview.value = true;

    // Ensure sort has default if empty
    const sortConfig =
        selectedSort.value.length > 0
            ? selectedSort.value
            : selectedColumns.value.length > 0
              ? [
                    {
                        column: selectedColumns.value[0],
                        direction: 'desc' as const,
                    },
                ]
              : [];

    router.post(
        preview.url(),
        {
            report_type: selectedReportType.value,
            columns: selectedColumns.value,
            filters: selectedFilters.value,
            sort: sortConfig,
            group_by: selectedGroupBy.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                isGeneratingPreview.value = false;
                currentStep.value = 'preview';
                announceStepChange('Preview');
                focusStepContent();
            },
        },
    );
}

/**
 * Go back to the previous step
 */
function goBack() {
    if (currentStep.value === 'preview') {
        currentStep.value = 'configure';
        announceStepChange('Configure');
        focusStepContent();
    } else if (currentStep.value === 'configure') {
        currentStep.value = 'select-type';
        selectedReportType.value = null;
        availableFields.value = [];
        availableFilters.value = {};
        calculatedFields.value = [];
        announceStepChange('Select Type');
        focusStepContent();
    }
}

/**
 * Start a new report configuration
 */
function startNewReport() {
    currentStep.value = 'select-type';
    selectedReportType.value = null;
    selectedColumns.value = [];
    selectedFilters.value = {};
    selectedSort.value = [];
    selectedGroupBy.value = null;
    availableFields.value = [];
    availableFilters.value = {};
    calculatedFields.value = [];
    roomOptions.value = [];
    rowOptions.value = [];

    announceStepChange('Select Type');

    // Navigate to clean state
    router.get(
        customReportsIndex.url(),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                focusStepContent();
            },
        },
    );
}

// Initialize configuration if we have a selected report type from props
watch(
    () => props.selectedReportType,
    async (newType) => {
        if (newType && !availableFields.value.length) {
            await handleReportTypeSelect(newType);
            // Restore selections from props
            selectedColumns.value = props.selectedColumns;
            selectedFilters.value = props.selectedFilters;
            selectedSort.value = props.selectedSort;
            selectedGroupBy.value = props.selectedGroupBy;
        }
    },
    { immediate: true },
);

// Sync room and row options from props
watch(
    () => props.roomOptions,
    (newRooms) => {
        roomOptions.value = newRooms;
    },
    { immediate: true },
);

watch(
    () => props.rowOptions,
    (newRows) => {
        rowOptions.value = newRows;
    },
    { immediate: true },
);
</script>

<template>
    <Head title="Custom Report Builder" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 md:p-6">
            <!-- Screen reader live region for step announcements -->
            <div
                ref="announcementRef"
                class="sr-only"
                aria-live="polite"
                aria-atomic="true"
            ></div>

            <!-- Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <HeadingSmall
                    title="Custom Report Builder"
                    description="Create customized reports by selecting data fields, filters, and output format."
                />

                <div v-if="currentStep !== 'select-type'" class="flex gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        @click="startNewReport"
                        aria-label="Start a new report"
                    >
                        <FileBarChart class="mr-1 size-4" aria-hidden="true" />
                        New Report
                    </Button>
                </div>
            </div>

            <!-- Step Indicator -->
            <nav
                class="flex items-center justify-center gap-2 py-2"
                aria-label="Report builder steps"
            >
                <ol class="flex items-center gap-2">
                    <template v-for="(step, index) in steps" :key="step.key">
                        <li class="flex items-center gap-2">
                            <div
                                class="flex items-center gap-2"
                                :class="{
                                    'text-primary': currentStepIndex >= index,
                                    'text-muted-foreground':
                                        currentStepIndex < index,
                                }"
                            >
                                <div
                                    class="flex size-8 items-center justify-center rounded-full border-2 text-sm font-medium transition-colors"
                                    :class="{
                                        'border-primary bg-primary text-primary-foreground':
                                            currentStepIndex === index,
                                        'border-primary bg-primary/10 text-primary':
                                            currentStepIndex > index,
                                        'border-muted-foreground/30':
                                            currentStepIndex < index,
                                    }"
                                    :aria-current="
                                        currentStepIndex === index
                                            ? 'step'
                                            : undefined
                                    "
                                >
                                    {{ step.number }}
                                </div>
                                <span
                                    class="hidden text-sm font-medium sm:inline"
                                >
                                    {{ step.label }}
                                    <span class="sr-only">
                                        {{
                                            currentStepIndex === index
                                                ? '(current step)'
                                                : currentStepIndex > index
                                                  ? '(completed)'
                                                  : ''
                                        }}
                                    </span>
                                </span>
                            </div>
                        </li>
                        <li v-if="index < steps.length - 1" aria-hidden="true">
                            <ChevronRight
                                class="size-4 text-muted-foreground/50"
                            />
                        </li>
                    </template>
                </ol>
            </nav>

            <!-- Step Content Container -->
            <div
                ref="stepContentRef"
                tabindex="-1"
                class="focus:outline-none"
                :aria-label="`Step ${currentStepIndex + 1}: ${currentStepLabel}`"
            >
                <!-- Step 1: Select Report Type -->
                <template v-if="currentStep === 'select-type'">
                    <ReportTypeSelector
                        :report-types="reportTypes"
                        :selected-type="selectedReportType"
                        :loading="isLoadingConfig"
                        @select="handleReportTypeSelect"
                    />

                    <div v-if="configError" class="text-center" role="alert">
                        <p class="text-sm text-destructive">
                            {{ configError }}
                        </p>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="mt-2"
                            @click="configError = null"
                        >
                            Dismiss
                        </Button>
                    </div>
                </template>

                <!-- Step 2: Configure Columns and Filters -->
                <template v-else-if="currentStep === 'configure'">
                    <!-- Loading Skeleton -->
                    <template v-if="isLoadingConfig">
                        <Card>
                            <CardHeader>
                                <Skeleton class="h-5 w-40" />
                            </CardHeader>
                            <CardContent>
                                <div
                                    class="space-y-4"
                                    role="status"
                                    aria-label="Loading configuration"
                                >
                                    <span class="sr-only"
                                        >Loading report configuration, please
                                        wait...</span
                                    >
                                    <div
                                        v-for="i in 3"
                                        :key="i"
                                        class="space-y-2"
                                    >
                                        <Skeleton class="h-4 w-24" />
                                        <div
                                            class="grid grid-cols-2 gap-2 sm:grid-cols-3"
                                        >
                                            <Skeleton
                                                v-for="j in 4"
                                                :key="j"
                                                class="h-6 w-full"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </template>

                    <!-- Configuration Form -->
                    <template v-else>
                        <div class="space-y-4">
                            <!-- Report Type Badge -->
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-muted-foreground"
                                    >Report Type:</span
                                >
                                <span
                                    class="rounded-md bg-primary/10 px-2 py-1 text-sm font-medium text-primary"
                                >
                                    {{ selectedReportTypeLabel }}
                                </span>
                            </div>

                            <!-- Column Selector -->
                            <ColumnSelector
                                :available-columns="fieldsByCategory"
                                :selected-columns="selectedColumns"
                                :calculated-fields="calculatedFields"
                                @update:selected-columns="handleColumnsUpdate"
                            />

                            <!-- Location Filters -->
                            <CustomReportFilters
                                v-if="selectedReportType"
                                :report-type="selectedReportType"
                                :datacenter-options="datacenterOptions"
                                :room-options="roomOptions"
                                :row-options="rowOptions"
                                :filters="selectedFilters"
                                @update:filters="handleFiltersUpdate"
                                @location-change="handleLocationChange"
                            />

                            <!-- Type-Specific Filters -->
                            <TypeSpecificFilters
                                v-if="
                                    selectedReportType &&
                                    Object.keys(availableFilters).length > 0
                                "
                                :report-type="selectedReportType"
                                :filter-options="availableFilters"
                                :filters="selectedFilters"
                                @update:filters="handleFiltersUpdate"
                            />

                            <!-- Sort Configuration -->
                            <SortConfiguration
                                v-if="selectedColumns.length > 0"
                                :available-columns="selectedColumnsAsFields"
                                :sort-config="selectedSort"
                                @update:sort-config="handleSortUpdate"
                            />

                            <!-- Group By Selector -->
                            <GroupBySelector
                                v-if="selectedColumns.length > 0"
                                :available-columns="selectedColumnsAsFields"
                                :group-by="selectedGroupBy"
                                @update:group-by="handleGroupByUpdate"
                            />

                            <!-- Action Buttons -->
                            <div class="flex justify-between pt-4">
                                <Button
                                    variant="outline"
                                    @click="goBack"
                                    aria-label="Go back to select report type"
                                >
                                    <ChevronLeft
                                        class="mr-1 size-4"
                                        aria-hidden="true"
                                    />
                                    Back
                                </Button>

                                <Button
                                    :disabled="
                                        !canProceedToPreview ||
                                        isGeneratingPreview
                                    "
                                    @click="generatePreview"
                                    :aria-busy="isGeneratingPreview"
                                    aria-label="Generate preview of your report"
                                >
                                    <Eye
                                        class="mr-1 size-4"
                                        aria-hidden="true"
                                    />
                                    {{
                                        isGeneratingPreview
                                            ? 'Generating...'
                                            : 'Generate Preview'
                                    }}
                                </Button>
                            </div>

                            <p
                                v-if="selectedColumns.length === 0"
                                class="text-center text-sm text-muted-foreground"
                                role="status"
                            >
                                Select at least one column to generate a
                                preview.
                            </p>
                        </div>
                    </template>
                </template>

                <!-- Step 3: Preview -->
                <template v-else-if="currentStep === 'preview'">
                    <div class="space-y-4">
                        <!-- Report Info and Export Buttons -->
                        <div
                            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm text-muted-foreground"
                                    >Report Type:</span
                                >
                                <span
                                    class="rounded-md bg-primary/10 px-2 py-1 text-sm font-medium text-primary"
                                >
                                    {{ selectedReportTypeLabel }}
                                </span>
                                <span
                                    class="text-sm text-muted-foreground"
                                    aria-hidden="true"
                                    >|</span
                                >
                                <span class="text-sm text-muted-foreground">
                                    {{ selectedColumns.length }} column{{
                                        selectedColumns.length !== 1 ? 's' : ''
                                    }}
                                    selected
                                </span>
                                <span
                                    v-if="selectedGroupBy"
                                    class="text-sm text-muted-foreground"
                                >
                                    <span aria-hidden="true">|</span> Grouped by
                                    {{ selectedGroupBy }}
                                </span>
                            </div>

                            <!-- Export Buttons -->
                            <ExportButtons
                                v-if="
                                    previewData && previewData.data.length > 0
                                "
                                :report-config="reportConfig"
                                :loading="isGeneratingPreview"
                            />
                        </div>

                        <!-- Preview Data Table -->
                        <PreviewTable
                            v-if="previewData"
                            :columns="previewData.columns"
                            :data="previewData.data"
                            :pagination="previewData.pagination"
                            :loading="isGeneratingPreview"
                            :group-by="selectedGroupBy"
                            @page-change="handlePageChange"
                        />

                        <!-- Empty State if no preview data -->
                        <Card v-else>
                            <CardContent class="py-12 text-center">
                                <FileBarChart
                                    class="mx-auto mb-4 size-12 text-muted-foreground/50"
                                    aria-hidden="true"
                                />
                                <h3 class="text-lg font-medium">
                                    No data available
                                </h3>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    No records match your current configuration.
                                </p>
                            </CardContent>
                        </Card>

                        <!-- Action Buttons -->
                        <div class="flex justify-between">
                            <Button
                                variant="outline"
                                @click="goBack"
                                aria-label="Go back to edit configuration"
                            >
                                <ChevronLeft
                                    class="mr-1 size-4"
                                    aria-hidden="true"
                                />
                                Edit Configuration
                            </Button>

                            <Button
                                variant="outline"
                                @click="generatePreview"
                                :disabled="isGeneratingPreview"
                                :aria-busy="isGeneratingPreview"
                                aria-label="Refresh the preview"
                            >
                                <Settings
                                    class="mr-1 size-4"
                                    aria-hidden="true"
                                />
                                {{
                                    isGeneratingPreview
                                        ? 'Refreshing...'
                                        : 'Refresh Preview'
                                }}
                            </Button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
