<script setup lang="ts">
import {
    destroy,
    index,
    toggle,
} from '@/actions/App/Http/Controllers/ReportScheduleController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import ExecutionHistoryTable from '@/components/ReportSchedules/ExecutionHistoryTable.vue';
import ScheduleStatusBadge from '@/components/ReportSchedules/ScheduleStatusBadge.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowLeft,
    Calendar,
    Clock,
    FileText,
    Globe,
    History,
    Mail,
    RefreshCw,
    Trash2,
    Users,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface DistributionListInfo {
    id: number;
    name: string;
    members_count: number;
    members?: Array<{ id: number; email: string }>;
}

interface ReportConfiguration {
    columns: string[];
    filters: Record<string, unknown>;
    sort: Array<{ column: string; direction: 'asc' | 'desc' }>;
    group_by: string | null;
}

interface Execution {
    id: number;
    status: 'pending' | 'success' | 'failed';
    started_at: string | null;
    completed_at: string | null;
    error_message: string | null;
    file_size_bytes: number | null;
    recipients_count: number | null;
    duration_seconds: number | null;
}

interface ScheduleData {
    id: number;
    name: string;
    report_type: string;
    report_type_label: string;
    report_configuration: ReportConfiguration;
    frequency: string;
    frequency_label: string;
    schedule_display: string;
    day_of_week: number | null;
    day_of_month: string | null;
    time_of_day: string;
    timezone: string;
    format: string;
    format_label: string;
    is_enabled: boolean;
    consecutive_failures: number;
    next_run_at: string | null;
    last_run_at: string | null;
    last_run_status: string | null;
    distribution_list: DistributionListInfo;
    created_at: string;
}

interface Props {
    schedule: ScheduleData;
    recentExecutions: Execution[];
    canToggle: boolean;
    canDelete: boolean;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Scheduled Reports',
        href: '/report-schedules',
    },
    {
        title: props.schedule.name,
        href: `/report-schedules/${props.schedule.id}`,
    },
];

// Delete confirmation dialog state
const showDeleteDialog = ref(false);
const isDeleting = ref(false);

// Toggle loading state
const isToggling = ref(false);

// Execution history loading state
const isLoadingHistory = ref(false);

/**
 * Format a date string for display
 */
const formatDate = (dateString: string | null): string => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

/**
 * Check if schedule needs re-enabling (disabled due to failures)
 */
const needsReEnable = computed(() => {
    return (
        !props.schedule.is_enabled && props.schedule.consecutive_failures >= 3
    );
});

/**
 * Handle toggle enabled/disabled
 */
const handleToggle = (newValue: boolean) => {
    isToggling.value = true;

    router.patch(
        toggle.url(props.schedule.id),
        {
            is_enabled: newValue,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                isToggling.value = false;
            },
        },
    );
};

/**
 * Handle delete action
 */
const handleDelete = () => {
    isDeleting.value = true;
    router.delete(destroy.url(props.schedule.id), {
        onSuccess: () => {
            showDeleteDialog.value = false;
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
};

/**
 * Handle re-enable after failures
 */
const handleReEnable = () => {
    handleToggle(true);
};

/**
 * Get column display names (simplified - just show column keys)
 */
const columnsList = computed(() => {
    return props.schedule.report_configuration.columns.join(', ');
});

/**
 * Check if there are filters applied
 */
const hasFilters = computed(() => {
    const filters = props.schedule.report_configuration.filters;
    return filters && Object.keys(filters).length > 0;
});

/**
 * Format filters for display
 */
const formattedFilters = computed(() => {
    const filters = props.schedule.report_configuration.filters;
    if (!filters) return [];

    return Object.entries(filters)
        .filter(
            ([, value]) =>
                value !== null && value !== undefined && value !== '',
        )
        .map(([key, value]) => ({
            key: key
                .replace(/_/g, ' ')
                .replace(/\b\w/g, (l) => l.toUpperCase()),
            value: String(value),
        }));
});

/**
 * Check if there are sort rules
 */
const hasSort = computed(() => {
    return props.schedule.report_configuration.sort?.length > 0;
});

/**
 * Format sort rules for display
 */
const formattedSort = computed(() => {
    const sort = props.schedule.report_configuration.sort;
    if (!sort?.length) return '';

    return sort.map((s) => `${s.column} (${s.direction})`).join(', ');
});
</script>

<template>
    <Head :title="schedule.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-4">
                    <Link :href="index.url()">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <div>
                        <HeadingSmall
                            :title="schedule.name"
                            :description="schedule.schedule_display"
                        />
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <ScheduleStatusBadge
                        :is-enabled="schedule.is_enabled"
                        :last-run-status="schedule.last_run_status"
                        :consecutive-failures="schedule.consecutive_failures"
                    />
                    <Button
                        v-if="canDelete"
                        variant="outline"
                        class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                        @click="showDeleteDialog = true"
                    >
                        <Trash2 class="mr-2 h-4 w-4" />
                        Delete
                    </Button>
                </div>
            </div>

            <!-- Re-enable Alert -->
            <Alert v-if="needsReEnable" variant="destructive">
                <AlertCircle class="h-4 w-4" />
                <AlertTitle>Schedule Disabled</AlertTitle>
                <AlertDescription
                    class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                >
                    <span>
                        This schedule was disabled after
                        {{ schedule.consecutive_failures }} consecutive
                        failures. Re-enabling will reset the failure count and
                        calculate the next run time.
                    </span>
                    <Button
                        v-if="canToggle"
                        variant="outline"
                        size="sm"
                        class="shrink-0"
                        :disabled="isToggling"
                        @click="handleReEnable"
                    >
                        <RefreshCw
                            class="mr-2 h-4 w-4"
                            :class="{ 'animate-spin': isToggling }"
                        />
                        Re-enable Schedule
                    </Button>
                </AlertDescription>
            </Alert>

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Main Content -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Report Configuration Card -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <FileText class="h-5 w-5" />
                                Report Configuration
                            </CardTitle>
                            <CardDescription>
                                The report settings captured when this schedule
                                was created.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <dt
                                        class="text-sm font-medium text-muted-foreground"
                                    >
                                        Report Type
                                    </dt>
                                    <dd class="mt-1">
                                        <Badge variant="outline">
                                            {{ schedule.report_type_label }}
                                        </Badge>
                                    </dd>
                                </div>
                                <div>
                                    <dt
                                        class="text-sm font-medium text-muted-foreground"
                                    >
                                        Format
                                    </dt>
                                    <dd class="mt-1">
                                        <Badge
                                            :variant="
                                                schedule.format === 'pdf'
                                                    ? 'default'
                                                    : 'secondary'
                                            "
                                        >
                                            {{ schedule.format_label }}
                                        </Badge>
                                    </dd>
                                </div>
                            </div>

                            <Separator />

                            <div>
                                <dt
                                    class="text-sm font-medium text-muted-foreground"
                                >
                                    Columns
                                </dt>
                                <dd class="mt-1 text-sm">{{ columnsList }}</dd>
                            </div>

                            <div v-if="hasFilters">
                                <dt
                                    class="text-sm font-medium text-muted-foreground"
                                >
                                    Filters
                                </dt>
                                <dd class="mt-1">
                                    <div class="flex flex-wrap gap-2">
                                        <Badge
                                            v-for="filter in formattedFilters"
                                            :key="filter.key"
                                            variant="secondary"
                                        >
                                            {{ filter.key }}: {{ filter.value }}
                                        </Badge>
                                    </div>
                                </dd>
                            </div>

                            <div v-if="hasSort">
                                <dt
                                    class="text-sm font-medium text-muted-foreground"
                                >
                                    Sort
                                </dt>
                                <dd class="mt-1 text-sm">
                                    {{ formattedSort }}
                                </dd>
                            </div>

                            <div v-if="schedule.report_configuration.group_by">
                                <dt
                                    class="text-sm font-medium text-muted-foreground"
                                >
                                    Group By
                                </dt>
                                <dd class="mt-1 text-sm">
                                    {{ schedule.report_configuration.group_by }}
                                </dd>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Execution History Card -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <History class="h-5 w-5" />
                                Execution History
                            </CardTitle>
                            <CardDescription>
                                Recent report generation attempts and their
                                outcomes.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ExecutionHistoryTable
                                :executions="recentExecutions"
                                :loading="isLoadingHistory"
                            />
                        </CardContent>
                    </Card>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Enable/Disable Card -->
                    <Card v-if="canToggle && !needsReEnable">
                        <CardHeader>
                            <CardTitle>Schedule Status</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center justify-between">
                                <Label for="schedule-toggle" class="text-sm">
                                    {{
                                        schedule.is_enabled
                                            ? 'Schedule is enabled'
                                            : 'Schedule is disabled'
                                    }}
                                </Label>
                                <Switch
                                    id="schedule-toggle"
                                    :checked="schedule.is_enabled"
                                    :disabled="isToggling"
                                    @update:checked="handleToggle"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Schedule Details Card -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Calendar class="h-5 w-5" />
                                Schedule Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex items-center gap-2 text-sm">
                                <Clock class="h-4 w-4 text-muted-foreground" />
                                <span class="text-muted-foreground"
                                    >Frequency:</span
                                >
                                <span>{{ schedule.frequency_label }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-sm">
                                <Clock class="h-4 w-4 text-muted-foreground" />
                                <span class="text-muted-foreground">Time:</span>
                                <span>{{ schedule.time_of_day }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-sm">
                                <Globe class="h-4 w-4 text-muted-foreground" />
                                <span class="text-muted-foreground"
                                    >Timezone:</span
                                >
                                <span>{{ schedule.timezone }}</span>
                            </div>
                            <Separator />
                            <div class="flex items-center gap-2 text-sm">
                                <Clock class="h-4 w-4 text-muted-foreground" />
                                <span class="text-muted-foreground"
                                    >Next Run:</span
                                >
                                <span>{{
                                    formatDate(schedule.next_run_at)
                                }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-sm">
                                <Clock class="h-4 w-4 text-muted-foreground" />
                                <span class="text-muted-foreground"
                                    >Last Run:</span
                                >
                                <span>{{
                                    formatDate(schedule.last_run_at)
                                }}</span>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Distribution List Card -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Mail class="h-5 w-5" />
                                Recipients
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex items-center gap-2 text-sm">
                                <Users class="h-4 w-4 text-muted-foreground" />
                                <span class="font-medium">{{
                                    schedule.distribution_list.name
                                }}</span>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ schedule.distribution_list.members_count }}
                                {{
                                    schedule.distribution_list.members_count ===
                                    1
                                        ? 'recipient'
                                        : 'recipients'
                                }}
                            </p>

                            <!-- Show member emails if loaded -->
                            <div
                                v-if="
                                    schedule.distribution_list.members?.length
                                "
                                class="space-y-1"
                            >
                                <Separator />
                                <ul
                                    class="mt-2 space-y-1 text-sm text-muted-foreground"
                                >
                                    <li
                                        v-for="member in schedule
                                            .distribution_list.members"
                                        :key="member.id"
                                        class="flex items-center gap-2"
                                    >
                                        <Mail class="h-3 w-3" />
                                        {{ member.email }}
                                    </li>
                                </ul>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Created Date -->
                    <Card>
                        <CardContent class="pt-6">
                            <div class="text-sm text-muted-foreground">
                                Created {{ formatDate(schedule.created_at) }}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>

        <!-- Delete confirmation dialog -->
        <Dialog v-model:open="showDeleteDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Schedule</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete "{{ schedule.name }}"?
                        This action cannot be undone. Future scheduled runs will
                        be cancelled and execution history will be lost.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="showDeleteDialog = false"
                        :disabled="isDeleting"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        @click="handleDelete"
                        :disabled="isDeleting"
                    >
                        {{ isDeleting ? 'Deleting...' : 'Delete' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
