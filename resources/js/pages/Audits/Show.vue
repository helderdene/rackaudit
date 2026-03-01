<script setup lang="ts">
import AuditController from '@/actions/App/Http/Controllers/AuditController';
import AuditReportController from '@/actions/App/Http/Controllers/AuditReportController';
import DatacenterController from '@/actions/App/Http/Controllers/DatacenterController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    Building2,
    Calendar,
    CheckCircle,
    ClipboardCheck,
    Download,
    FileBarChart,
    FileText,
    HelpCircle,
    Loader2,
    Play,
    Users,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface ProgressStats {
    total: number;
    verified: number;
    discrepant: number;
    pending: number;
    completed: number;
    progress_percentage: number;
    // Inventory-specific fields
    not_found?: number;
    empty_racks_total?: number;
    empty_racks_verified?: number;
}

interface ReportData {
    id: number;
    generated_at: string | null;
    generated_at_iso: string | null;
    generator_name: string | null;
    file_size_bytes: number;
    file_size_formatted: string;
    download_url: string;
}

interface AuditData {
    id: number;
    name: string;
    description: string | null;
    due_date: string | null;
    type: string;
    type_label: string;
    scope_type: string;
    scope_type_label: string;
    status: string;
    status_label: string;
    datacenter: {
        id: number;
        name: string;
        formatted_location: string;
    };
    room: {
        id: number;
        name: string;
    } | null;
    implementation_file: {
        id: number;
        original_name: string;
        version_number: number;
    } | null;
    assignees: {
        id: number;
        name: string;
        email: string;
    }[];
    creator: {
        id: number;
        name: string;
    } | null;
    racks_count: number;
    devices_count: number;
    created_at: string | null;
    updated_at: string | null;
}

interface Props {
    audit: AuditData;
    can_start_audit: boolean;
    can_continue_audit: boolean;
    can_start_inventory_audit: boolean;
    can_continue_inventory_audit: boolean;
    progress_stats: ProgressStats | null;
    can_generate_report: boolean;
    reports: ReportData[];
}

const props = defineProps<Props>();

const isStarting = ref(false);
const isGeneratingReport = ref(false);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Audits',
        href: AuditController.index.url(),
    },
    {
        title: props.audit.name,
        href: AuditController.show.url(props.audit.id),
    },
];

// Determine if this is an inventory audit
const isInventoryAudit = computed(() => props.audit.type === 'inventory');
const isConnectionAudit = computed(() => props.audit.type === 'connection');

// Get the correct continue URL based on audit type
const continueAuditUrl = computed(() => {
    if (isInventoryAudit.value) {
        return AuditController.inventoryExecute.url(props.audit.id);
    }
    return AuditController.execute.url(props.audit.id);
});

// Get status badge classes
const getStatusBadgeClass = (status: string): string => {
    const baseClasses =
        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium';
    switch (status) {
        case 'pending':
            return `${baseClasses} bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200`;
        case 'in_progress':
            return `${baseClasses} bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200`;
        case 'completed':
            return `${baseClasses} bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200`;
        case 'cancelled':
            return `${baseClasses} bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300`;
        default:
            return `${baseClasses} bg-gray-100 text-gray-800`;
    }
};

// Get type badge classes
const getTypeBadgeClass = (type: string): string => {
    const baseClasses =
        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium';
    switch (type) {
        case 'connection':
            return `${baseClasses} bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200`;
        case 'inventory':
            return `${baseClasses} bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200`;
        default:
            return `${baseClasses} bg-gray-100 text-gray-800`;
    }
};

// Format date for display
const formatDate = (dateString: string | null): string => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const formatDateTime = (dateString: string | null): string => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

/**
 * Start the audit execution
 */
function startAudit(): void {
    isStarting.value = true;
    router.post(
        AuditController.startExecution.url(props.audit.id),
        {},
        {
            onFinish: () => {
                isStarting.value = false;
            },
        },
    );
}

/**
 * Generate a new report for this audit
 */
function generateReport(): void {
    isGeneratingReport.value = true;
    router.post(
        AuditReportController.generate.url(props.audit.id),
        {},
        {
            onFinish: () => {
                isGeneratingReport.value = false;
            },
        },
    );
}

// Check if we should show start button (for either audit type)
const canStartAnyAudit = computed(
    () => props.can_start_audit || props.can_start_inventory_audit,
);
const canContinueAnyAudit = computed(
    () => props.can_continue_audit || props.can_continue_inventory_audit,
);

// Get completion message based on audit type
const completionMessage = computed(() => {
    if (isInventoryAudit.value) {
        return 'All devices have been verified! The audit is complete.';
    }
    return 'All connections have been verified! The audit is complete.';
});
</script>

<template>
    <Head :title="audit.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <HeadingSmall
                        :title="audit.name"
                        :description="
                            audit.description || 'No description provided.'
                        "
                    />
                    <div class="mt-2 flex gap-2">
                        <span :class="getTypeBadgeClass(audit.type)">
                            {{ audit.type_label }}
                        </span>
                        <span :class="getStatusBadgeClass(audit.status)">
                            {{ audit.status_label }}
                        </span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <!-- Generate Report Button -->
                    <Button
                        v-if="can_generate_report"
                        :disabled="isGeneratingReport"
                        variant="outline"
                        @click="generateReport"
                    >
                        <Loader2
                            v-if="isGeneratingReport"
                            class="mr-1 size-4 animate-spin"
                        />
                        <FileBarChart v-else class="mr-1 size-4" />
                        {{
                            isGeneratingReport
                                ? 'Generating...'
                                : 'Generate Report'
                        }}
                    </Button>

                    <!-- Start Audit Button (works for both audit types) -->
                    <Button
                        v-if="canStartAnyAudit"
                        :disabled="isStarting"
                        class="bg-green-600 hover:bg-green-700"
                        @click="startAudit"
                    >
                        <Play class="mr-1 size-4" />
                        {{ isStarting ? 'Starting...' : 'Start Audit' }}
                    </Button>

                    <!-- Continue Audit Button (works for both audit types) -->
                    <Link v-if="canContinueAnyAudit" :href="continueAuditUrl">
                        <Button class="bg-blue-600 hover:bg-blue-700">
                            <ArrowRight class="mr-1 size-4" />
                            Continue Audit
                        </Button>
                    </Link>

                    <Link :href="AuditController.index.url()">
                        <Button variant="outline">Back to List</Button>
                    </Link>
                </div>
            </div>

            <!-- Progress Card (shown when audit has progress stats) -->
            <Card v-if="progress_stats && progress_stats.total > 0">
                <CardHeader class="pb-3">
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <ClipboardCheck class="size-5" />
                        Verification Progress
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-wrap gap-4 md:gap-6">
                        <div class="flex items-center gap-2">
                            <span
                                class="text-sm font-medium text-muted-foreground"
                                >Total:</span
                            >
                            <Badge variant="secondary" class="text-base">{{
                                progress_stats.total
                            }}</Badge>
                        </div>
                        <div class="flex items-center gap-2">
                            <CheckCircle class="size-4 text-green-600" />
                            <span
                                class="text-sm font-medium text-muted-foreground"
                                >Verified:</span
                            >
                            <Badge class="bg-green-600 text-base">{{
                                progress_stats.verified
                            }}</Badge>
                        </div>
                        <!-- Not Found (inventory audits only) -->
                        <div
                            v-if="
                                isInventoryAudit &&
                                progress_stats.not_found !== undefined
                            "
                            class="flex items-center gap-2"
                        >
                            <XCircle class="size-4 text-red-600" />
                            <span
                                class="text-sm font-medium text-muted-foreground"
                                >Not Found:</span
                            >
                            <Badge variant="destructive" class="text-base">{{
                                progress_stats.not_found
                            }}</Badge>
                        </div>
                        <div class="flex items-center gap-2">
                            <AlertTriangle class="size-4 text-yellow-600" />
                            <span
                                class="text-sm font-medium text-muted-foreground"
                                >Discrepant:</span
                            >
                            <Badge variant="warning" class="text-base">{{
                                progress_stats.discrepant
                            }}</Badge>
                        </div>
                        <div class="flex items-center gap-2">
                            <HelpCircle class="size-4 text-gray-500" />
                            <span
                                class="text-sm font-medium text-muted-foreground"
                                >Pending:</span
                            >
                            <Badge variant="outline" class="text-base">{{
                                progress_stats.pending
                            }}</Badge>
                        </div>
                    </div>

                    <!-- Progress bar -->
                    <div class="mt-4">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-muted-foreground"
                                >Completion</span
                            >
                            <span class="font-medium"
                                >{{
                                    progress_stats.progress_percentage.toFixed(
                                        1,
                                    )
                                }}%</span
                            >
                        </div>
                        <div
                            class="mt-2 h-2 w-full overflow-hidden rounded-full bg-secondary"
                        >
                            <div
                                class="h-full bg-green-600 transition-all duration-300"
                                :style="{
                                    width: `${progress_stats.progress_percentage}%`,
                                }"
                            />
                        </div>
                    </div>

                    <!-- Empty racks info (inventory audits only) -->
                    <div
                        v-if="
                            isInventoryAudit &&
                            progress_stats.empty_racks_total &&
                            progress_stats.empty_racks_total > 0
                        "
                        class="mt-3 text-sm text-muted-foreground"
                    >
                        Empty racks verified:
                        {{ progress_stats.empty_racks_verified }} /
                        {{ progress_stats.empty_racks_total }}
                    </div>

                    <!-- Completion message -->
                    <div
                        v-if="progress_stats.pending === 0"
                        class="mt-4 flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400"
                    >
                        <CheckCircle class="size-4 shrink-0" />
                        <span>{{ completionMessage }}</span>
                    </div>
                </CardContent>
            </Card>

            <!-- Content Grid -->
            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Basic Info Card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-lg">
                            <ClipboardCheck class="size-5" />
                            Audit Details
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-2">
                            <dt
                                class="text-sm font-medium text-muted-foreground"
                            >
                                Type
                            </dt>
                            <dd class="text-sm">{{ audit.type_label }}</dd>
                        </div>
                        <div class="grid gap-2">
                            <dt
                                class="text-sm font-medium text-muted-foreground"
                            >
                                Scope
                            </dt>
                            <dd class="text-sm">
                                {{ audit.scope_type_label }}
                            </dd>
                        </div>
                        <div class="grid gap-2">
                            <dt
                                class="text-sm font-medium text-muted-foreground"
                            >
                                Status
                            </dt>
                            <dd>
                                <span
                                    :class="getStatusBadgeClass(audit.status)"
                                >
                                    {{ audit.status_label }}
                                </span>
                            </dd>
                        </div>
                        <div v-if="audit.creator" class="grid gap-2">
                            <dt
                                class="text-sm font-medium text-muted-foreground"
                            >
                                Created By
                            </dt>
                            <dd class="text-sm">{{ audit.creator.name }}</dd>
                        </div>
                    </CardContent>
                </Card>

                <!-- Datacenter Card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-lg">
                            <Building2 class="size-5" />
                            Location
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-2">
                            <dt
                                class="text-sm font-medium text-muted-foreground"
                            >
                                Datacenter
                            </dt>
                            <dd class="text-sm">
                                <Link
                                    :href="
                                        DatacenterController.show.url(
                                            audit.datacenter.id,
                                        )
                                    "
                                    class="text-primary hover:underline"
                                >
                                    {{ audit.datacenter.name }}
                                </Link>
                                <span
                                    class="block text-xs text-muted-foreground"
                                    >{{
                                        audit.datacenter.formatted_location
                                    }}</span
                                >
                            </dd>
                        </div>
                        <div v-if="audit.room" class="grid gap-2">
                            <dt
                                class="text-sm font-medium text-muted-foreground"
                            >
                                Room
                            </dt>
                            <dd class="text-sm">{{ audit.room.name }}</dd>
                        </div>
                        <div v-if="audit.racks_count > 0" class="grid gap-2">
                            <dt
                                class="text-sm font-medium text-muted-foreground"
                            >
                                Racks Selected
                            </dt>
                            <dd class="text-sm">{{ audit.racks_count }}</dd>
                        </div>
                        <div v-if="audit.devices_count > 0" class="grid gap-2">
                            <dt
                                class="text-sm font-medium text-muted-foreground"
                            >
                                Devices Selected
                            </dt>
                            <dd class="text-sm">{{ audit.devices_count }}</dd>
                        </div>
                    </CardContent>
                </Card>

                <!-- Timeline Card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-lg">
                            <Calendar class="size-5" />
                            Timeline
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-2">
                            <dt
                                class="text-sm font-medium text-muted-foreground"
                            >
                                Due Date
                            </dt>
                            <dd class="text-sm">
                                {{ formatDate(audit.due_date) }}
                            </dd>
                        </div>
                        <div class="grid gap-2">
                            <dt
                                class="text-sm font-medium text-muted-foreground"
                            >
                                Created
                            </dt>
                            <dd class="text-sm text-muted-foreground">
                                {{ formatDateTime(audit.created_at) }}
                            </dd>
                        </div>
                        <div class="grid gap-2">
                            <dt
                                class="text-sm font-medium text-muted-foreground"
                            >
                                Last Updated
                            </dt>
                            <dd class="text-sm text-muted-foreground">
                                {{ formatDateTime(audit.updated_at) }}
                            </dd>
                        </div>
                    </CardContent>
                </Card>

                <!-- Assignees Card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-lg">
                            <Users class="size-5" />
                            Assignees
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="audit.assignees.length > 0"
                            class="space-y-3"
                        >
                            <div
                                v-for="assignee in audit.assignees"
                                :key="assignee.id"
                                class="flex flex-col"
                            >
                                <span class="text-sm font-medium">{{
                                    assignee.name
                                }}</span>
                                <span class="text-xs text-muted-foreground">{{
                                    assignee.email
                                }}</span>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">
                            No assignees.
                        </p>
                    </CardContent>
                </Card>

                <!-- Implementation File Card (for connection audits) -->
                <Card v-if="isConnectionAudit">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-lg">
                            <FileText class="size-5" />
                            Implementation File
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="audit.implementation_file"
                            class="grid gap-2"
                        >
                            <dt
                                class="text-sm font-medium text-muted-foreground"
                            >
                                File
                            </dt>
                            <dd class="text-sm">
                                {{ audit.implementation_file.original_name }}
                                <span class="text-xs text-muted-foreground">
                                    (v{{
                                        audit.implementation_file
                                            .version_number
                                    }})
                                </span>
                            </dd>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">
                            No implementation file linked.
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Report History Card -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <FileBarChart class="size-5" />
                        Report History
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="reports.length > 0">
                        <!-- Mobile card view -->
                        <div class="space-y-3 md:hidden">
                            <div
                                v-for="report in reports"
                                :key="report.id"
                                class="rounded-lg border bg-card p-4 shadow-sm"
                            >
                                <div
                                    class="mb-2 flex items-start justify-between gap-2"
                                >
                                    <div class="flex-1">
                                        <p class="text-sm font-medium">
                                            {{ report.generated_at }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            by
                                            {{
                                                report.generator_name ||
                                                'Unknown'
                                            }}
                                        </p>
                                    </div>
                                    <span
                                        class="text-xs text-muted-foreground"
                                        >{{ report.file_size_formatted }}</span
                                    >
                                </div>
                                <a
                                    :href="report.download_url"
                                    class="inline-block"
                                >
                                    <Button variant="outline" size="sm">
                                        <Download class="mr-1 size-3" />
                                        Download
                                    </Button>
                                </a>
                            </div>
                        </div>

                        <!-- Desktop table view -->
                        <div
                            class="hidden overflow-hidden rounded-md border md:block"
                        >
                            <table class="w-full text-sm">
                                <thead class="border-b bg-muted/50">
                                    <tr>
                                        <th
                                            class="h-10 px-4 text-left font-medium text-muted-foreground"
                                        >
                                            Generated Date
                                        </th>
                                        <th
                                            class="h-10 px-4 text-left font-medium text-muted-foreground"
                                        >
                                            Generated By
                                        </th>
                                        <th
                                            class="h-10 px-4 text-left font-medium text-muted-foreground"
                                        >
                                            File Size
                                        </th>
                                        <th
                                            class="h-10 w-[100px] px-4 text-left font-medium text-muted-foreground"
                                        >
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="report in reports"
                                        :key="report.id"
                                        class="border-b transition-colors hover:bg-muted/50"
                                    >
                                        <td class="p-4">
                                            {{ report.generated_at }}
                                        </td>
                                        <td class="p-4">
                                            {{
                                                report.generator_name ||
                                                'Unknown'
                                            }}
                                        </td>
                                        <td class="p-4">
                                            {{ report.file_size_formatted }}
                                        </td>
                                        <td class="p-4">
                                            <a :href="report.download_url">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Download
                                                        class="mr-1 size-3"
                                                    />
                                                    Download
                                                </Button>
                                            </a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div
                        v-else
                        class="flex flex-col items-center justify-center py-8 text-center"
                    >
                        <FileBarChart
                            class="mb-2 size-8 text-muted-foreground/50"
                        />
                        <p class="text-sm text-muted-foreground">
                            No reports have been generated yet.
                        </p>
                        <p
                            v-if="can_generate_report"
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            Click "Generate Report" to create your first report.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
