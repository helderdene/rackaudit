<script setup lang="ts">
import {
    create,
    destroy,
    show,
    toggle,
} from '@/actions/App/Http/Controllers/ReportScheduleController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import ScheduleStatusBadge from '@/components/ReportSchedules/ScheduleStatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Switch } from '@/components/ui/switch';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Calendar, Clock, Eye, FileText, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

interface DistributionListInfo {
    id: number;
    name: string;
    members_count: number;
}

interface ScheduleData {
    id: number;
    name: string;
    report_type: string;
    report_type_label: string;
    frequency: string;
    frequency_label: string;
    schedule_display: string;
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
    schedules: ScheduleData[];
    canCreate: boolean;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Scheduled Reports',
        href: '/report-schedules',
    },
];

// Delete confirmation dialog state
const showDeleteDialog = ref(false);
const scheduleToDelete = ref<ScheduleData | null>(null);
const isDeleting = ref(false);

// Toggle loading state per schedule
const togglingScheduleId = ref<number | null>(null);

/**
 * Format a date string for display
 */
const formatDate = (dateString: string | null): string => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

/**
 * Format a date string for relative time display
 */
const formatRelativeDate = (dateString: string | null): string => {
    if (!dateString) return 'Not scheduled';
    const date = new Date(dateString);
    const now = new Date();
    const diff = date.getTime() - now.getTime();
    const diffDays = Math.floor(diff / (1000 * 60 * 60 * 24));
    const diffHours = Math.floor(diff / (1000 * 60 * 60));
    const diffMinutes = Math.floor(diff / (1000 * 60));

    if (diff < 0) {
        return 'Overdue';
    }

    if (diffMinutes < 60) {
        return `in ${diffMinutes}m`;
    }

    if (diffHours < 24) {
        return `in ${diffHours}h`;
    }

    if (diffDays < 7) {
        return `in ${diffDays}d`;
    }

    return formatDate(dateString);
};

/**
 * Confirm delete dialog
 */
const confirmDelete = (schedule: ScheduleData) => {
    scheduleToDelete.value = schedule;
    showDeleteDialog.value = true;
};

/**
 * Handle delete action
 */
const handleDelete = () => {
    if (!scheduleToDelete.value) return;

    isDeleting.value = true;
    router.delete(destroy.url(scheduleToDelete.value.id), {
        onSuccess: () => {
            showDeleteDialog.value = false;
            scheduleToDelete.value = null;
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
};

/**
 * Cancel delete
 */
const cancelDelete = () => {
    showDeleteDialog.value = false;
    scheduleToDelete.value = null;
};

/**
 * Handle toggle enabled/disabled
 */
const handleToggle = (schedule: ScheduleData, newValue: boolean) => {
    togglingScheduleId.value = schedule.id;

    router.patch(
        toggle.url(schedule.id),
        {
            is_enabled: newValue,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                togglingScheduleId.value = null;
            },
        },
    );
};

/**
 * Get format badge variant
 */
const getFormatVariant = (
    format: string,
): 'default' | 'secondary' | 'outline' => {
    return format === 'pdf' ? 'default' : 'secondary';
};
</script>

<template>
    <Head title="Scheduled Reports" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <HeadingSmall
                    title="Scheduled Reports"
                    description="Manage automated report generation and email delivery."
                />
                <Link v-if="canCreate" :href="create.url()">
                    <Button>
                        <Plus class="mr-2 h-4 w-4" />
                        New Schedule
                    </Button>
                </Link>
            </div>

            <!-- Empty state -->
            <div
                v-if="schedules.length === 0"
                class="flex flex-col items-center justify-center rounded-lg border border-dashed py-16"
            >
                <div
                    class="flex h-14 w-14 items-center justify-center rounded-full bg-muted"
                >
                    <Calendar class="h-6 w-6 text-muted-foreground" />
                </div>
                <h3 class="mt-4 text-sm font-medium">No scheduled reports</h3>
                <p class="mt-1 text-center text-sm text-muted-foreground">
                    Create a schedule to automatically generate and send
                    reports.
                </p>
                <Link v-if="canCreate" :href="create.url()" class="mt-4">
                    <Button variant="outline" size="sm">
                        <Plus class="mr-2 h-4 w-4" />
                        Create your first schedule
                    </Button>
                </Link>
            </div>

            <!-- Schedules table -->
            <div v-else class="overflow-hidden rounded-md border">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/50">
                            <tr>
                                <th
                                    class="h-12 px-4 text-left font-medium text-muted-foreground"
                                >
                                    Name
                                </th>
                                <th
                                    class="h-12 px-4 text-left font-medium text-muted-foreground"
                                >
                                    Report Type
                                </th>
                                <th
                                    class="h-12 px-4 text-left font-medium text-muted-foreground"
                                >
                                    Schedule
                                </th>
                                <th
                                    class="h-12 px-4 text-left font-medium text-muted-foreground"
                                >
                                    Format
                                </th>
                                <th
                                    class="h-12 px-4 text-left font-medium text-muted-foreground"
                                >
                                    Status
                                </th>
                                <th
                                    class="h-12 px-4 text-left font-medium text-muted-foreground"
                                >
                                    Next Run
                                </th>
                                <th
                                    class="h-12 px-4 text-left font-medium text-muted-foreground"
                                >
                                    Enabled
                                </th>
                                <th
                                    class="h-12 w-[120px] px-4 text-left font-medium text-muted-foreground"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="schedule in schedules"
                                :key="schedule.id"
                                class="border-b transition-colors hover:bg-muted/50"
                                :class="{ 'opacity-60': !schedule.is_enabled }"
                            >
                                <td class="p-4">
                                    <div class="flex flex-col">
                                        <Link
                                            :href="show.url(schedule.id)"
                                            class="font-medium hover:underline"
                                        >
                                            {{ schedule.name }}
                                        </Link>
                                        <span
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                schedule.distribution_list.name
                                            }}
                                            ({{
                                                schedule.distribution_list
                                                    .members_count
                                            }}
                                            recipients)
                                        </span>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <Badge variant="outline">
                                        <FileText class="mr-1 h-3 w-3" />
                                        {{ schedule.report_type_label }}
                                    </Badge>
                                </td>
                                <td class="p-4">
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <span
                                                    class="cursor-help text-muted-foreground"
                                                >
                                                    {{
                                                        schedule.frequency_label
                                                    }}
                                                </span>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>
                                                    {{
                                                        schedule.schedule_display
                                                    }}
                                                </p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                </td>
                                <td class="p-4">
                                    <Badge
                                        :variant="
                                            getFormatVariant(schedule.format)
                                        "
                                    >
                                        {{ schedule.format_label }}
                                    </Badge>
                                </td>
                                <td class="p-4">
                                    <ScheduleStatusBadge
                                        :is-enabled="schedule.is_enabled"
                                        :last-run-status="
                                            schedule.last_run_status
                                        "
                                        :consecutive-failures="
                                            schedule.consecutive_failures
                                        "
                                    />
                                </td>
                                <td class="p-4">
                                    <TooltipProvider
                                        v-if="schedule.next_run_at"
                                    >
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <span
                                                    class="flex items-center gap-1 text-muted-foreground"
                                                >
                                                    <Clock class="h-3 w-3" />
                                                    {{
                                                        formatRelativeDate(
                                                            schedule.next_run_at,
                                                        )
                                                    }}
                                                </span>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>
                                                    {{
                                                        formatDate(
                                                            schedule.next_run_at,
                                                        )
                                                    }}
                                                </p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                    <span v-else class="text-muted-foreground"
                                        >-</span
                                    >
                                </td>
                                <td class="p-4">
                                    <Switch
                                        :checked="schedule.is_enabled"
                                        :disabled="
                                            togglingScheduleId === schedule.id
                                        "
                                        @update:checked="
                                            (value) =>
                                                handleToggle(schedule, value)
                                        "
                                    />
                                </td>
                                <td class="p-4">
                                    <div class="flex items-center gap-2">
                                        <Link :href="show.url(schedule.id)">
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                title="View details"
                                            >
                                                <Eye class="h-4 w-4" />
                                            </Button>
                                        </Link>
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            title="Delete"
                                            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                            @click="confirmDelete(schedule)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Delete confirmation dialog -->
        <Dialog v-model:open="showDeleteDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Schedule</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete "{{
                            scheduleToDelete?.name
                        }}"? This action cannot be undone. Future scheduled runs
                        will be cancelled.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="cancelDelete"
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
