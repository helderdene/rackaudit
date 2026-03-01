<script setup lang="ts">
import { configure } from '@/actions/App/Http/Controllers/CustomReportBuilderController';
import {
    index,
    store,
} from '@/actions/App/Http/Controllers/ReportScheduleController';
import ColumnSelector from '@/components/CustomReports/ColumnSelector.vue';
import CustomReportFilters from '@/components/CustomReports/CustomReportFilters.vue';
import GroupBySelector from '@/components/CustomReports/GroupBySelector.vue';
import ReportTypeSelector from '@/components/CustomReports/ReportTypeSelector.vue';
import SortConfiguration from '@/components/CustomReports/SortConfiguration.vue';
import TypeSpecificFilters from '@/components/CustomReports/TypeSpecificFilters.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import ScheduleFrequencySelector from '@/components/ReportSchedules/ScheduleFrequencySelector.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Calendar,
    Check,
    ChevronRight,
    FileBarChart,
    FileText,
    Mail,
    Send,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface DistributionListOption {
    id: number;
    name: string;
    members_count: number;
}

interface ReportTypeOption {
    value: string;
    label: string;
    description: string;
}

interface FrequencyOption {
    value: string;
    label: string;
    description: string;
}

interface FormatOption {
    value: string;
    label: string;
}

interface TimezoneOption {
    value: string;
    label: string;
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

interface SortConfig {
    column: string;
    direction: 'asc' | 'desc';
}

interface Props {
    distributionLists: DistributionListOption[];
    reportTypes: ReportTypeOption[];
    frequencies: FrequencyOption[];
    formats: FormatOption[];
    timezones: TimezoneOption[];
    defaultTimezone: string;
    datacenterOptions?: Array<{ id: number; name: string }>;
}

const props = withDefaults(defineProps<Props>(), {
    datacenterOptions: () => [],
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Scheduled Reports',
        href: '/report-schedules',
    },
    {
        title: 'Create',
        href: '/report-schedules/create',
    },
];

// Step navigation
type Step = 1 | 2 | 3;
const currentStep = ref<Step>(1);

// Form data
const scheduleName = ref('');
const selectedReportType = ref<string | null>(null);
const selectedColumns = ref<string[]>([]);
const selectedFilters = ref<Record<string, unknown>>({});
const selectedSort = ref<SortConfig[]>([]);
const selectedGroupBy = ref<string | null>(null);
const frequency = ref('daily');
const dayOfWeek = ref<number | null>(null);
const dayOfMonth = ref<string | null>(null);
const timeOfDay = ref('09:00');
const timezone = ref(props.defaultTimezone);
const distributionListId = ref<number | null>(null);
const format = ref('pdf');

// Configuration loaded from API
const availableFields = ref<ReportField[]>([]);
const availableFilters = ref<Record<string, FilterConfig>>({});
const calculatedFields = ref<ReportField[]>([]);

// Loading states
const isLoadingConfig = ref(false);
const isSubmitting = ref(false);

// Form errors
const formErrors = ref<Record<string, string>>({});

// Location options for cascading filters
const roomOptions = ref<Array<{ id: number; name: string }>>([]);
const rowOptions = ref<Array<{ id: number; name: string }>>([]);

/**
 * Select input class for styled selects
 */
const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50';

/**
 * Step labels
 */
const steps = [
    { number: 1 as const, label: 'Configure Report', icon: FileBarChart },
    { number: 2 as const, label: 'Schedule', icon: Calendar },
    { number: 3 as const, label: 'Distribution', icon: Send },
];

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
 * Selected report type label
 */
const selectedReportTypeLabel = computed(() => {
    if (!selectedReportType.value) return '';
    const type = props.reportTypes.find(
        (t) => t.value === selectedReportType.value,
    );
    return type?.label || selectedReportType.value;
});

/**
 * Selected distribution list
 */
const selectedDistributionList = computed(() => {
    return props.distributionLists.find(
        (l) => l.id === distributionListId.value,
    );
});

/**
 * Can proceed to step 2
 */
const canProceedToStep2 = computed(() => {
    return selectedReportType.value && selectedColumns.value.length > 0;
});

/**
 * Can proceed to step 3
 */
const canProceedToStep3 = computed(() => {
    // Validate frequency-specific fields
    if (frequency.value === 'weekly' && dayOfWeek.value === null) return false;
    if (frequency.value === 'monthly' && !dayOfMonth.value) return false;
    if (!timeOfDay.value || !timezone.value) return false;
    return true;
});

/**
 * Can submit form
 */
const canSubmit = computed(() => {
    return (
        scheduleName.value.trim() && distributionListId.value && format.value
    );
});

/**
 * Build schedule display string for preview
 */
const schedulePreviewDisplay = computed(() => {
    let display = '';
    if (frequency.value === 'daily') {
        display = `Daily at ${timeOfDay.value}`;
    } else if (frequency.value === 'weekly' && dayOfWeek.value !== null) {
        const days = [
            'Sunday',
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
        ];
        display = `Weekly on ${days[dayOfWeek.value]} at ${timeOfDay.value}`;
    } else if (frequency.value === 'monthly' && dayOfMonth.value) {
        if (dayOfMonth.value === 'last') {
            display = `Monthly on the last day at ${timeOfDay.value}`;
        } else {
            const day = parseInt(dayOfMonth.value);
            const suffix = getOrdinalSuffix(day);
            display = `Monthly on the ${day}${suffix} at ${timeOfDay.value}`;
        }
    }
    return display ? `${display} ${timezone.value}` : '';
});

/**
 * Get ordinal suffix
 */
function getOrdinalSuffix(n: number): string {
    if (n >= 11 && n <= 13) return 'th';
    switch (n % 10) {
        case 1:
            return 'st';
        case 2:
            return 'nd';
        case 3:
            return 'rd';
        default:
            return 'th';
    }
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
    } catch (error) {
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
    if (location.type === 'datacenter') {
        roomOptions.value = [];
        rowOptions.value = [];

        if (location.id) {
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
 * Go to next step
 */
function nextStep() {
    if (currentStep.value === 1 && canProceedToStep2.value) {
        currentStep.value = 2;
    } else if (currentStep.value === 2 && canProceedToStep3.value) {
        currentStep.value = 3;
    }
}

/**
 * Go to previous step
 */
function prevStep() {
    if (currentStep.value > 1) {
        currentStep.value = (currentStep.value - 1) as Step;
    }
}

/**
 * Handle distribution list selection
 */
function handleDistributionListChange(event: Event) {
    const target = event.target as HTMLSelectElement;
    distributionListId.value = target.value ? parseInt(target.value, 10) : null;
}

/**
 * Submit the form
 */
async function handleSubmit() {
    if (!canSubmit.value) return;

    isSubmitting.value = true;
    formErrors.value = {};

    const formData = {
        name: scheduleName.value.trim(),
        distribution_list_id: distributionListId.value,
        report_type: selectedReportType.value,
        report_configuration: {
            columns: selectedColumns.value,
            filters: selectedFilters.value,
            sort: selectedSort.value,
            group_by: selectedGroupBy.value,
        },
        frequency: frequency.value,
        day_of_week: dayOfWeek.value,
        day_of_month: dayOfMonth.value,
        time_of_day: timeOfDay.value,
        timezone: timezone.value,
        format: format.value,
    };

    router.post(store.url(), formData, {
        onError: (errors) => {
            formErrors.value = errors;
            // Navigate back to appropriate step if there are errors
            if (errors.name || errors.distribution_list_id || errors.format) {
                currentStep.value = 3;
            } else if (
                errors.frequency ||
                errors.day_of_week ||
                errors.day_of_month ||
                errors.time_of_day ||
                errors.timezone
            ) {
                currentStep.value = 2;
            } else if (errors.report_type || errors.report_configuration) {
                currentStep.value = 1;
            }
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
}
</script>

<template>
    <Head title="Create Scheduled Report" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Header -->
            <div class="flex items-center gap-4">
                <Link :href="index.url()">
                    <Button variant="ghost" size="icon">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                </Link>
                <HeadingSmall
                    title="Create Scheduled Report"
                    description="Set up automated report generation with email delivery."
                />
            </div>

            <!-- Step Indicator -->
            <nav
                class="flex items-center justify-center gap-2 py-2"
                aria-label="Schedule creation steps"
            >
                <ol class="flex items-center gap-2">
                    <template v-for="(step, idx) in steps" :key="step.number">
                        <li class="flex items-center gap-2">
                            <div
                                class="flex items-center gap-2"
                                :class="{
                                    'text-primary': currentStep >= step.number,
                                    'text-muted-foreground':
                                        currentStep < step.number,
                                }"
                            >
                                <div
                                    class="flex size-8 items-center justify-center rounded-full border-2 text-sm font-medium transition-colors"
                                    :class="{
                                        'border-primary bg-primary text-primary-foreground':
                                            currentStep === step.number,
                                        'border-primary bg-primary/10 text-primary':
                                            currentStep > step.number,
                                        'border-muted-foreground/30':
                                            currentStep < step.number,
                                    }"
                                >
                                    <Check
                                        v-if="currentStep > step.number"
                                        class="size-4"
                                    />
                                    <span v-else>{{ step.number }}</span>
                                </div>
                                <span
                                    class="hidden text-sm font-medium sm:inline"
                                >
                                    {{ step.label }}
                                </span>
                            </div>
                        </li>
                        <li v-if="idx < steps.length - 1" aria-hidden="true">
                            <ChevronRight
                                class="size-4 text-muted-foreground/50"
                            />
                        </li>
                    </template>
                </ol>
            </nav>

            <!-- Step Content -->
            <div class="mx-auto w-full max-w-4xl">
                <!-- Step 1: Configure Report -->
                <div v-if="currentStep === 1" class="space-y-6">
                    <!-- Report Type Selector -->
                    <ReportTypeSelector
                        :report-types="reportTypes"
                        :selected-type="selectedReportType"
                        :loading="isLoadingConfig"
                        @select="handleReportTypeSelect"
                    />

                    <!-- Configuration Form (shown after type selection) -->
                    <template v-if="selectedReportType && !isLoadingConfig">
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
                        </div>
                    </template>

                    <!-- Loading State -->
                    <template v-else-if="isLoadingConfig">
                        <Card>
                            <CardHeader>
                                <Skeleton class="h-5 w-40" />
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-4">
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

                    <!-- Step 1 Navigation -->
                    <div class="flex justify-between pt-4">
                        <Link :href="index.url()">
                            <Button variant="outline"> Cancel </Button>
                        </Link>
                        <Button
                            :disabled="!canProceedToStep2"
                            @click="nextStep"
                        >
                            Next: Schedule
                            <ArrowRight class="ml-2 h-4 w-4" />
                        </Button>
                    </div>
                </div>

                <!-- Step 2: Schedule Configuration -->
                <div v-if="currentStep === 2" class="space-y-6">
                    <ScheduleFrequencySelector
                        :frequency="frequency"
                        :day-of-week="dayOfWeek"
                        :day-of-month="dayOfMonth"
                        :time-of-day="timeOfDay"
                        :timezone="timezone"
                        :frequencies="frequencies"
                        :timezones="timezones"
                        :errors="formErrors"
                        @update:frequency="frequency = $event"
                        @update:day-of-week="dayOfWeek = $event"
                        @update:day-of-month="dayOfMonth = $event"
                        @update:time-of-day="timeOfDay = $event"
                        @update:timezone="timezone = $event"
                    />

                    <!-- Step 2 Navigation -->
                    <div class="flex justify-between pt-4">
                        <Button variant="outline" @click="prevStep">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Back
                        </Button>
                        <Button
                            :disabled="!canProceedToStep3"
                            @click="nextStep"
                        >
                            Next: Distribution
                            <ArrowRight class="ml-2 h-4 w-4" />
                        </Button>
                    </div>
                </div>

                <!-- Step 3: Distribution and Summary -->
                <div v-if="currentStep === 3" class="space-y-6">
                    <!-- Schedule Name -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <FileText class="h-5 w-5" />
                                Schedule Details
                            </CardTitle>
                            <CardDescription>
                                Give your schedule a name and choose the output
                                format.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2">
                                <Label for="schedule-name"
                                    >Schedule Name
                                    <span class="text-destructive"
                                        >*</span
                                    ></Label
                                >
                                <Input
                                    id="schedule-name"
                                    v-model="scheduleName"
                                    type="text"
                                    placeholder="e.g., Weekly Capacity Report"
                                    :aria-invalid="!!formErrors.name"
                                />
                                <InputError :message="formErrors.name" />
                            </div>

                            <div class="space-y-2">
                                <Label
                                    >Report Format
                                    <span class="text-destructive"
                                        >*</span
                                    ></Label
                                >
                                <div class="flex gap-4">
                                    <label
                                        v-for="formatOption in formats"
                                        :key="formatOption.value"
                                        class="flex cursor-pointer items-center space-x-2"
                                    >
                                        <input
                                            type="radio"
                                            :value="formatOption.value"
                                            v-model="format"
                                            name="format"
                                            class="h-4 w-4 text-primary focus:ring-primary"
                                        />
                                        <span class="text-sm">{{
                                            formatOption.label
                                        }}</span>
                                    </label>
                                </div>
                                <InputError :message="formErrors.format" />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Distribution List Selection -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Mail class="h-5 w-5" />
                                Distribution List
                            </CardTitle>
                            <CardDescription>
                                Select the recipient group who will receive this
                                report.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div
                                v-if="distributionLists.length === 0"
                                class="rounded-lg border border-dashed p-6 text-center"
                            >
                                <Mail
                                    class="mx-auto h-8 w-8 text-muted-foreground"
                                />
                                <p class="mt-2 text-sm text-muted-foreground">
                                    No distribution lists available. Please
                                    create one first.
                                </p>
                                <Link
                                    href="/distribution-lists/create"
                                    class="mt-4 inline-block"
                                >
                                    <Button variant="outline" size="sm">
                                        Create Distribution List
                                    </Button>
                                </Link>
                            </div>

                            <div v-else class="space-y-2">
                                <Label for="distribution-list"
                                    >Select Distribution List
                                    <span class="text-destructive"
                                        >*</span
                                    ></Label
                                >
                                <select
                                    id="distribution-list"
                                    :value="distributionListId ?? ''"
                                    :class="selectClass"
                                    @change="handleDistributionListChange"
                                >
                                    <option value="" disabled>
                                        Choose a distribution list
                                    </option>
                                    <option
                                        v-for="list in distributionLists"
                                        :key="list.id"
                                        :value="list.id"
                                    >
                                        {{ list.name }} ({{
                                            list.members_count
                                        }}
                                        {{
                                            list.members_count === 1
                                                ? 'recipient'
                                                : 'recipients'
                                        }})
                                    </option>
                                </select>
                                <InputError
                                    :message="formErrors.distribution_list_id"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Schedule Summary Preview -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Schedule Summary</CardTitle>
                            <CardDescription>
                                Review your schedule configuration before
                                creating.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                                <div>
                                    <dt
                                        class="font-medium text-muted-foreground"
                                    >
                                        Report Type
                                    </dt>
                                    <dd class="mt-1">
                                        {{ selectedReportTypeLabel }}
                                    </dd>
                                </div>
                                <div>
                                    <dt
                                        class="font-medium text-muted-foreground"
                                    >
                                        Columns
                                    </dt>
                                    <dd class="mt-1">
                                        {{ selectedColumns.length }} selected
                                    </dd>
                                </div>
                                <div>
                                    <dt
                                        class="font-medium text-muted-foreground"
                                    >
                                        Schedule
                                    </dt>
                                    <dd class="mt-1">
                                        {{ schedulePreviewDisplay }}
                                    </dd>
                                </div>
                                <div>
                                    <dt
                                        class="font-medium text-muted-foreground"
                                    >
                                        Format
                                    </dt>
                                    <dd class="mt-1">
                                        {{ format.toUpperCase() }}
                                    </dd>
                                </div>
                                <div v-if="selectedDistributionList">
                                    <dt
                                        class="font-medium text-muted-foreground"
                                    >
                                        Recipients
                                    </dt>
                                    <dd class="mt-1">
                                        {{ selectedDistributionList.name }}
                                        ({{
                                            selectedDistributionList.members_count
                                        }})
                                    </dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>

                    <!-- Step 3 Navigation -->
                    <div class="flex justify-between pt-4">
                        <Button variant="outline" @click="prevStep">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Back
                        </Button>
                        <Button
                            :disabled="!canSubmit || isSubmitting"
                            @click="handleSubmit"
                        >
                            <Spinner v-if="isSubmitting" class="mr-2 h-4 w-4" />
                            {{
                                isSubmitting ? 'Creating...' : 'Create Schedule'
                            }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
