<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import FindingController from '@/actions/App/Http/Controllers/FindingController';
import AuditController from '@/actions/App/Http/Controllers/AuditController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import EvidenceUpload from '@/components/EvidenceUpload.vue';
import CategorySelect from '@/components/CategorySelect.vue';
import WorkflowProgressIndicator from '@/components/WorkflowProgressIndicator.vue';
import QuickActionButtons from '@/components/QuickActionButtons.vue';
import StatusTransitionTimeline from '@/components/StatusTransitionTimeline.vue';
import DueDateIndicator from '@/components/DueDateIndicator.vue';
import RealtimeToastContainer from '@/components/notifications/RealtimeToastContainer.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { useRealtimeUpdates } from '@/composables/useRealtimeUpdates';
import { type BreadcrumbItem } from '@/types';
import type { RealtimeUpdate } from '@/types/realtime';
import { AlertCircle, Building2, User, Tag, FileText, Clock, CheckCircle, Calendar, Timer, Lightbulb } from 'lucide-vue-next';
import type {
    FindingsShowProps,
    FindingSeverityValue,
    FindingStatusValue,
} from '@/types/finding';
import axios from 'axios';

const props = defineProps<FindingsShowProps>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Findings',
        href: FindingController.index.url(),
    },
    {
        title: props.finding.title,
        href: FindingController.show.url(props.finding.id),
    },
];

// Get datacenter ID from audit if available
const datacenterId = computed(() => props.finding.audit?.datacenter?.id ?? null);

// Track whether there's a conflict (another user modified this finding)
const hasConflict = ref(false);

// Real-time updates integration
const {
    pendingUpdates,
    dismissUpdate,
    clearUpdates,
    onDataChange,
} = useRealtimeUpdates(datacenterId.value);

// Register handler for finding changes - detect if this specific finding was modified
onDataChange('finding', (data) => {
    // Check if this finding was modified by another user
    if (data.entityId === props.finding.id) {
        hasConflict.value = true;
    }
    console.log('Finding changed:', data);
});

// Transform pending updates to mark conflicts
const updatesWithConflicts = computed<RealtimeUpdate[]>(() => {
    return pendingUpdates.value.map((update) => ({
        ...update,
        isConflict: update.entityType === 'finding' && update.entityId === props.finding.id,
    }));
});

// Handle toast dismissal
function handleDismissUpdate(id: string): void {
    dismissUpdate(id);
    // If dismissing a conflict update, reset conflict state
    const update = pendingUpdates.value.find((u) => u.id === id);
    if (update && update.entityType === 'finding' && update.entityId === props.finding.id) {
        hasConflict.value = false;
    }
}

// Handle toast refresh
function handleRefresh(): void {
    clearUpdates();
    hasConflict.value = false;
    router.reload();
}

// Handle clear all updates
function handleClearAll(): void {
    clearUpdates();
    hasConflict.value = false;
}

// Edit form
const form = useForm({
    status: props.finding.status,
    severity: props.finding.severity,
    finding_category_id: props.finding.category?.id || null,
    assigned_to: props.finding.assignee?.id || null,
    resolution_notes: props.finding.resolution_notes || '',
    due_date: props.finding.due_date || '',
});

// Track if resolution notes are required
const resolutionNotesRequired = computed(() => form.status === 'resolved');

// Character count for resolution notes
const resolutionNotesCharCount = computed(() => form.resolution_notes.length);

// Check if resolution notes meet minimum length
const resolutionNotesValid = computed(() => {
    if (!resolutionNotesRequired.value) return true;
    return form.resolution_notes.length >= 10;
});

// Quick action processing state
const quickActionProcessing = ref(false);

// Submit the form
const submitForm = () => {
    form.put(FindingController.update.url(props.finding.id), {
        preserveScroll: true,
        onSuccess: () => {
            // Form was saved successfully
        },
    });
};

// Handle category selection (including newly created categories)
const handleCategoryChange = (categoryId: number | null) => {
    form.finding_category_id = categoryId;
};

// Handle quick action transition
const handleQuickTransition = async (targetStatus: FindingStatusValue, notes?: string) => {
    quickActionProcessing.value = true;
    try {
        await axios.post(`/findings/${props.finding.id}/transition`, {
            target_status: targetStatus,
            notes: notes,
        });

        // Reload page to get updated data
        router.reload();
    } catch (error) {
        console.error('Quick transition failed:', error);
    } finally {
        quickActionProcessing.value = false;
    }
};

// Get "Next Steps" guidance text based on current status
const getNextStepsGuidance = computed(() => {
    switch (props.finding.status) {
        case 'open':
            return 'This finding is awaiting action. Click "Start Working" to begin working on it, or "Defer" if it needs to be addressed later.';
        case 'in_progress':
            return 'Work is in progress on this finding. When ready, click "Submit for Review" to have it reviewed, or "Defer" if work needs to be paused.';
        case 'pending_review':
            return 'This finding is ready for review. Click "Approve & Close" to mark it as resolved (requires resolution notes).';
        case 'deferred':
            return 'This finding has been deferred. Click "Reopen" to resume work on it.';
        case 'resolved':
            return 'This finding has been resolved. If issues resurface, an administrator can click "Reopen" to reopen it.';
        default:
            return '';
    }
});

// Get severity badge classes
const getSeverityBadgeClass = (severity: FindingSeverityValue): string => {
    const baseClasses = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium';
    switch (severity) {
        case 'critical':
            return `${baseClasses} bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400`;
        case 'high':
            return `${baseClasses} bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400`;
        case 'medium':
            return `${baseClasses} bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400`;
        case 'low':
            return `${baseClasses} bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400`;
        default:
            return `${baseClasses} bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300`;
    }
};

// Get status badge classes
const getStatusBadgeClass = (status: FindingStatusValue): string => {
    const baseClasses = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium';
    switch (status) {
        case 'open':
            return `${baseClasses} bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400`;
        case 'in_progress':
            return `${baseClasses} bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400`;
        case 'pending_review':
            return `${baseClasses} bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400`;
        case 'deferred':
            return `${baseClasses} bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400`;
        case 'resolved':
            return `${baseClasses} bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400`;
        default:
            return `${baseClasses} bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300`;
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
        hour: '2-digit',
        minute: '2-digit',
    });
};

// Common select styling for dropdowns
const selectClass = 'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';

// Check if user is admin (for showing reopen on resolved)
const isAdmin = computed(() => {
    // We can infer admin status from whether "reopen" action exists when status is resolved
    return props.quickActions.some(
        (action) => action.action === 'reopen' && props.finding.status === 'resolved'
    );
});
</script>

<template>
    <Head :title="finding.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <HeadingSmall
                        :title="finding.title"
                        :description="finding.description || 'No description provided.'"
                    />
                    <div class="mt-2 flex flex-wrap gap-2">
                        <span :class="getSeverityBadgeClass(finding.severity)">
                            {{ finding.severity_label }}
                        </span>
                        <span :class="getStatusBadgeClass(finding.status)">
                            {{ finding.status_label }}
                        </span>
                        <span v-if="finding.category" class="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground">
                            {{ finding.category.name }}
                        </span>
                        <DueDateIndicator
                            v-if="finding.due_date"
                            :due-date="finding.due_date"
                            :is-overdue="finding.is_overdue"
                            :is-due-soon="finding.is_due_soon"
                        />
                    </div>
                </div>
                <div class="flex gap-2">
                    <Link :href="FindingController.index.url()">
                        <Button variant="outline">Back to List</Button>
                    </Link>
                </div>
            </div>

            <!-- Workflow Progress Indicator -->
            <Card>
                <CardHeader class="pb-4">
                    <CardTitle class="text-lg">Workflow Progress</CardTitle>
                </CardHeader>
                <CardContent>
                    <WorkflowProgressIndicator :current-status="finding.status" />
                </CardContent>
            </Card>

            <!-- Quick Actions & Next Steps Guidance -->
            <Card v-if="canEdit && quickActions.length > 0">
                <CardHeader class="pb-4">
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <Lightbulb class="size-5" />
                        Quick Actions
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <QuickActionButtons
                        :current-status="finding.status"
                        :is-admin="isAdmin"
                        :quick-actions="quickActions"
                        :processing="quickActionProcessing"
                        @transition="handleQuickTransition"
                    />
                    <p v-if="getNextStepsGuidance" class="text-sm text-muted-foreground">
                        {{ getNextStepsGuidance }}
                    </p>
                </CardContent>
            </Card>

            <!-- Time Metrics -->
            <div v-if="timeMetrics.time_to_first_response || timeMetrics.total_resolution_time" class="grid gap-4 sm:grid-cols-2">
                <Card v-if="timeMetrics.time_to_first_response">
                    <CardContent class="flex items-center gap-3 p-4">
                        <div class="flex size-10 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30">
                            <Timer class="size-5 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Time to First Response</p>
                            <p class="text-lg font-semibold">{{ timeMetrics.time_to_first_response }}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card v-if="timeMetrics.total_resolution_time">
                    <CardContent class="flex items-center gap-3 p-4">
                        <div class="flex size-10 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                            <CheckCircle class="size-5 text-green-600 dark:text-green-400" />
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Total Resolution Time</p>
                            <p class="text-lg font-semibold">{{ timeMetrics.total_resolution_time }}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Content Grid -->
            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Finding Details Card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-lg">
                            <AlertCircle class="size-5" />
                            Finding Details
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Title</dt>
                            <dd class="text-sm">{{ finding.title }}</dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Description</dt>
                            <dd class="text-sm whitespace-pre-line">{{ finding.description || '-' }}</dd>
                        </div>
                        <div v-if="finding.discrepancy_type_label" class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Discrepancy Type</dt>
                            <dd class="text-sm">{{ finding.discrepancy_type_label }}</dd>
                        </div>
                        <div v-if="finding.due_date" class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Due Date</dt>
                            <dd class="flex items-center gap-2 text-sm">
                                <Calendar class="size-4 text-muted-foreground" />
                                {{ finding.due_date }}
                                <span v-if="finding.is_overdue" class="text-red-600 dark:text-red-400">(Overdue)</span>
                                <span v-else-if="finding.is_due_soon" class="text-amber-600 dark:text-amber-400">(Due Soon)</span>
                            </dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Created</dt>
                            <dd class="text-sm text-muted-foreground">{{ formatDate(finding.created_at) }}</dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Last Updated</dt>
                            <dd class="text-sm text-muted-foreground">{{ formatDate(finding.updated_at) }}</dd>
                        </div>
                    </CardContent>
                </Card>

                <!-- Audit & Assignment Card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-lg">
                            <Building2 class="size-5" />
                            Audit & Assignment
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Audit</dt>
                            <dd class="text-sm">
                                <Link
                                    v-if="finding.audit"
                                    :href="AuditController.show.url(finding.audit.id)"
                                    class="text-primary hover:underline"
                                >
                                    {{ finding.audit.name }}
                                </Link>
                                <span v-else>-</span>
                            </dd>
                        </div>
                        <div v-if="finding.audit?.datacenter" class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Datacenter</dt>
                            <dd class="text-sm">{{ finding.audit.datacenter.name }}</dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Assignee</dt>
                            <dd class="text-sm">
                                <div v-if="finding.assignee" class="flex items-center gap-2">
                                    <User class="size-4 text-muted-foreground" />
                                    <span>{{ finding.assignee.name }}</span>
                                    <span class="text-xs text-muted-foreground">({{ finding.assignee.email }})</span>
                                </div>
                                <span v-else class="italic text-muted-foreground">Unassigned</span>
                            </dd>
                        </div>
                    </CardContent>
                </Card>

                <!-- Resolution Info Card (shown when resolved) -->
                <Card v-if="finding.status === 'resolved' || finding.resolution_notes">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-lg">
                            <CheckCircle class="size-5 text-green-600" />
                            Resolution Information
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="finding.resolved_at" class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Resolved At</dt>
                            <dd class="text-sm">{{ formatDate(finding.resolved_at) }}</dd>
                        </div>
                        <div v-if="finding.resolved_by" class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Resolved By</dt>
                            <dd class="text-sm">{{ finding.resolved_by.name }}</dd>
                        </div>
                        <div v-if="finding.resolution_notes" class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Resolution Notes</dt>
                            <dd class="text-sm whitespace-pre-line">{{ finding.resolution_notes }}</dd>
                        </div>
                    </CardContent>
                </Card>

                <!-- Status Transition Timeline -->
                <StatusTransitionTimeline :transitions="statusTransitions" />

                <!-- Edit Form Card (only if canEdit) -->
                <Card v-if="canEdit" class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-lg">
                            <FileText class="size-5" />
                            Update Finding
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submitForm" class="space-y-6">
                            <div class="grid gap-6 md:grid-cols-2">
                                <!-- Status -->
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-foreground">Status</label>
                                    <select
                                        v-model="form.status"
                                        :class="selectClass"
                                    >
                                        <option
                                            v-for="(label, value) in allowedTransitions"
                                            :key="value"
                                            :value="value"
                                        >
                                            {{ label }}
                                        </option>
                                    </select>
                                    <p v-if="form.errors.status" class="text-xs text-destructive">{{ form.errors.status }}</p>
                                </div>

                                <!-- Severity -->
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-foreground">Severity</label>
                                    <select
                                        v-model="form.severity"
                                        :class="selectClass"
                                    >
                                        <option
                                            v-for="option in severityOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                    <p v-if="form.errors.severity" class="text-xs text-destructive">{{ form.errors.severity }}</p>
                                </div>

                                <!-- Category -->
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-foreground">Category</label>
                                    <CategorySelect
                                        :model-value="form.finding_category_id"
                                        :category-options="categoryOptions"
                                        @update:model-value="handleCategoryChange"
                                    />
                                    <p v-if="form.errors.finding_category_id" class="text-xs text-destructive">{{ form.errors.finding_category_id }}</p>
                                </div>

                                <!-- Assignee -->
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-foreground">Assignee</label>
                                    <select
                                        v-model="form.assigned_to"
                                        :class="selectClass"
                                    >
                                        <option :value="null">Unassigned</option>
                                        <option
                                            v-for="option in assigneeOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                    <p v-if="form.errors.assigned_to" class="text-xs text-destructive">{{ form.errors.assigned_to }}</p>
                                </div>

                                <!-- Due Date -->
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-foreground">Due Date</label>
                                    <Input
                                        v-model="form.due_date"
                                        type="date"
                                        :min="new Date().toISOString().split('T')[0]"
                                    />
                                    <p v-if="form.errors.due_date" class="text-xs text-destructive">{{ form.errors.due_date }}</p>
                                </div>
                            </div>

                            <!-- Resolution Notes -->
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-foreground">
                                    Resolution Notes
                                    <span v-if="resolutionNotesRequired" class="text-destructive">*</span>
                                </label>
                                <Textarea
                                    v-model="form.resolution_notes"
                                    placeholder="Enter resolution notes..."
                                    :class="{ 'border-destructive': form.errors.resolution_notes || (resolutionNotesRequired && !resolutionNotesValid) }"
                                    rows="4"
                                />
                                <div class="flex justify-between text-xs">
                                    <div>
                                        <p v-if="resolutionNotesRequired && !resolutionNotesValid" class="text-amber-600 dark:text-amber-400">
                                            Minimum 10 characters required when resolving.
                                        </p>
                                        <p v-else-if="resolutionNotesRequired" class="text-muted-foreground">
                                            Resolution notes are required when marking as Resolved.
                                        </p>
                                        <p v-if="form.errors.resolution_notes" class="text-destructive">{{ form.errors.resolution_notes }}</p>
                                    </div>
                                    <span :class="resolutionNotesRequired && resolutionNotesCharCount < 10 ? 'text-amber-600' : 'text-muted-foreground'">
                                        {{ resolutionNotesCharCount }} characters
                                    </span>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex justify-end">
                                <Button
                                    type="submit"
                                    :disabled="form.processing || (resolutionNotesRequired && !resolutionNotesValid)"
                                >
                                    {{ form.processing ? 'Saving...' : 'Save Changes' }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <!-- Evidence Section -->
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-lg">
                            <Tag class="size-5" />
                            Evidence
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <EvidenceUpload
                            :finding-id="finding.id"
                            :evidence="finding.evidence"
                            :can-edit="canEdit"
                        />
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Real-time Toast Container with conflict detection -->
        <RealtimeToastContainer
            :updates="updatesWithConflicts"
            @dismiss="handleDismissUpdate"
            @refresh="handleRefresh"
            @clear-all="handleClearAll"
        />
    </AppLayout>
</template>
