<script setup lang="ts">
import { index } from '@/actions/App/Http/Controllers/BulkImportController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import ImportErrorSummary from '@/components/imports/ImportErrorSummary.vue';
import ImportProgress from '@/components/imports/ImportProgress.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Calendar, FileSpreadsheet, User } from 'lucide-vue-next';
import { computed } from 'vue';

interface BulkImportData {
    id: number;
    entity_type: string | null;
    entity_type_label: string | null;
    file_name: string;
    status: string;
    status_label: string;
    total_rows: number | null;
    processed_rows: number | null;
    success_count: number | null;
    failure_count: number | null;
    progress_percentage: number | null;
    has_errors: boolean;
    has_error_report: boolean;
    user?: {
        id: number;
        name: string;
    };
    created_at: string;
    started_at: string | null;
    completed_at: string | null;
}

interface Props {
    import: BulkImportData;
}

const props = defineProps<Props>();

// Use computed to access the import prop (since 'import' is a reserved word in templates)
const bulkImport = computed(() => props.import);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Imports',
        href: '/imports',
    },
    {
        title: props.import.file_name,
        href: `/imports/${props.import.id}`,
    },
];

// Format date for display
const formatDate = (dateString: string | null): string => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// Handle import status update
const handleStatusUpdate = (data: BulkImportData) => {
    // Could be used to trigger a page refresh or other action
    console.log('Import status updated:', data.status);
};

// Handle import completion
const handleCompleted = (data: BulkImportData) => {
    // Could show a notification or trigger a page refresh
    console.log('Import completed:', data.status);
};
</script>

<template>
    <Head :title="`Import: ${bulkImport.file_name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <HeadingSmall
                    title="Import Details"
                    :description="`Viewing import: ${bulkImport.file_name}`"
                />
                <Link :href="index.url()">
                    <Button variant="outline">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to Imports
                    </Button>
                </Link>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Progress Card -->
                <Card>
                    <CardHeader>
                        <CardTitle>Import Progress</CardTitle>
                        <CardDescription>
                            Real-time status and progress of your import.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ImportProgress
                            :import="{
                                id: bulkImport.id,
                                status: bulkImport.status,
                                status_label: bulkImport.status_label,
                                total_rows: bulkImport.total_rows ?? 0,
                                processed_rows: bulkImport.processed_rows ?? 0,
                                success_count: bulkImport.success_count ?? 0,
                                failure_count: bulkImport.failure_count ?? 0,
                                progress_percentage:
                                    bulkImport.progress_percentage ?? 0,
                                has_errors: bulkImport.has_errors,
                            }"
                            @status-updated="handleStatusUpdate"
                            @completed="handleCompleted"
                        />
                    </CardContent>
                </Card>

                <!-- Details Card -->
                <Card>
                    <CardHeader>
                        <CardTitle>Import Information</CardTitle>
                        <CardDescription>
                            Details about this import operation.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <dl class="space-y-4">
                            <div class="flex items-start gap-3">
                                <FileSpreadsheet
                                    class="mt-0.5 h-4 w-4 text-muted-foreground"
                                />
                                <div>
                                    <dt class="text-sm font-medium">
                                        File Name
                                    </dt>
                                    <dd class="text-sm text-muted-foreground">
                                        {{ bulkImport.file_name }}
                                    </dd>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <div
                                    class="mt-0.5 flex h-4 w-4 items-center justify-center text-xs font-bold text-muted-foreground"
                                >
                                    T
                                </div>
                                <div>
                                    <dt class="text-sm font-medium">
                                        Entity Type
                                    </dt>
                                    <dd class="text-sm text-muted-foreground">
                                        {{
                                            bulkImport.entity_type_label ||
                                            'Auto-detected'
                                        }}
                                    </dd>
                                </div>
                            </div>

                            <div
                                v-if="bulkImport.user"
                                class="flex items-start gap-3"
                            >
                                <User
                                    class="mt-0.5 h-4 w-4 text-muted-foreground"
                                />
                                <div>
                                    <dt class="text-sm font-medium">
                                        Imported By
                                    </dt>
                                    <dd class="text-sm text-muted-foreground">
                                        {{ bulkImport.user.name }}
                                    </dd>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <Calendar
                                    class="mt-0.5 h-4 w-4 text-muted-foreground"
                                />
                                <div>
                                    <dt class="text-sm font-medium">Created</dt>
                                    <dd class="text-sm text-muted-foreground">
                                        {{ formatDate(bulkImport.created_at) }}
                                    </dd>
                                </div>
                            </div>

                            <div
                                v-if="bulkImport.started_at"
                                class="flex items-start gap-3"
                            >
                                <Calendar
                                    class="mt-0.5 h-4 w-4 text-muted-foreground"
                                />
                                <div>
                                    <dt class="text-sm font-medium">Started</dt>
                                    <dd class="text-sm text-muted-foreground">
                                        {{ formatDate(bulkImport.started_at) }}
                                    </dd>
                                </div>
                            </div>

                            <div
                                v-if="bulkImport.completed_at"
                                class="flex items-start gap-3"
                            >
                                <Calendar
                                    class="mt-0.5 h-4 w-4 text-muted-foreground"
                                />
                                <div>
                                    <dt class="text-sm font-medium">
                                        Completed
                                    </dt>
                                    <dd class="text-sm text-muted-foreground">
                                        {{
                                            formatDate(bulkImport.completed_at)
                                        }}
                                    </dd>
                                </div>
                            </div>
                        </dl>
                    </CardContent>
                </Card>
            </div>

            <!-- Error Summary -->
            <ImportErrorSummary
                v-if="bulkImport.failure_count && bulkImport.failure_count > 0"
                :import-id="bulkImport.id"
                :failure-count="bulkImport.failure_count"
                :has-error-report="bulkImport.has_error_report"
            />
        </div>
    </AppLayout>
</template>
