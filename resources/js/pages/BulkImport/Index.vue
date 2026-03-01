<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { create, show, downloadErrors } from '@/actions/App/Http/Controllers/BulkImportController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { PaginationLink, SelectOption } from '@/types/rooms';
import { Download, Plus, FileSpreadsheet, Clock, CheckCircle, XCircle, Loader2 } from 'lucide-vue-next';

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

interface PaginatedImports {
    data: BulkImportData[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    imports: PaginatedImports;
    entityTypeOptions: SelectOption[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Imports',
        href: '/imports',
    },
];

// Get status badge variant
const getStatusVariant = (status: string, failureCount: number | null): 'default' | 'secondary' | 'destructive' | 'success' | 'warning' => {
    switch (status) {
        case 'completed':
            return (failureCount && failureCount > 0) ? 'warning' : 'success';
        case 'failed':
            return 'destructive';
        case 'processing':
            return 'default';
        default:
            return 'secondary';
    }
};

// Get status icon
const getStatusIcon = (status: string) => {
    switch (status) {
        case 'completed':
            return CheckCircle;
        case 'failed':
            return XCircle;
        case 'processing':
            return Loader2;
        default:
            return Clock;
    }
};

// Format date for display
const formatDate = (dateString: string | null): string => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// Format progress for display
const formatProgress = (processed: number | null, total: number | null, percentage: number | null): string => {
    if (total === null || total === 0) return '-';
    const pct = percentage !== null ? percentage.toFixed(0) : '0';
    return `${processed ?? 0}/${total} (${pct}%)`;
};
</script>

<template>
    <Head title="Bulk Imports" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <HeadingSmall
                    title="Bulk Import"
                    description="Import datacenter infrastructure data from spreadsheets."
                />
                <Link :href="create.url()">
                    <Button>
                        <Plus class="mr-2 h-4 w-4" />
                        New Import
                    </Button>
                </Link>
            </div>

            <!-- Empty state -->
            <div
                v-if="imports.data.length === 0"
                class="flex flex-col items-center justify-center rounded-lg border border-dashed py-16"
            >
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-muted">
                    <FileSpreadsheet class="h-6 w-6 text-muted-foreground" />
                </div>
                <h3 class="mt-4 text-sm font-medium">No imports yet</h3>
                <p class="mt-1 text-center text-sm text-muted-foreground">
                    Start by importing a CSV or XLSX file with your datacenter data.
                </p>
                <Link :href="create.url()" class="mt-4">
                    <Button variant="outline" size="sm">
                        <Plus class="mr-2 h-4 w-4" />
                        Create your first import
                    </Button>
                </Link>
            </div>

            <!-- Import history table -->
            <div v-else class="overflow-hidden rounded-md border">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/50">
                            <tr>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">File</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Entity Type</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Status</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Progress</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Results</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Date</th>
                                <th class="h-12 w-[100px] px-4 text-left font-medium text-muted-foreground">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="importItem in imports.data"
                                :key="importItem.id"
                                class="border-b transition-colors hover:bg-muted/50"
                            >
                                <td class="p-4">
                                    <div class="flex items-center gap-2">
                                        <FileSpreadsheet class="h-4 w-4 text-muted-foreground" />
                                        <Link
                                            :href="show.url(importItem.id)"
                                            class="font-medium hover:underline"
                                        >
                                            {{ importItem.file_name }}
                                        </Link>
                                    </div>
                                </td>
                                <td class="p-4 text-muted-foreground">
                                    {{ importItem.entity_type_label || 'Auto-detect' }}
                                </td>
                                <td class="p-4">
                                    <div class="flex items-center gap-2">
                                        <component
                                            :is="getStatusIcon(importItem.status)"
                                            class="h-4 w-4"
                                            :class="{
                                                'animate-spin text-primary': importItem.status === 'processing',
                                                'text-muted-foreground': importItem.status === 'pending',
                                                'text-green-500': importItem.status === 'completed' && !importItem.has_errors,
                                                'text-yellow-500': importItem.status === 'completed' && importItem.has_errors,
                                                'text-red-500': importItem.status === 'failed',
                                            }"
                                        />
                                        <Badge :variant="getStatusVariant(importItem.status, importItem.failure_count)">
                                            {{ importItem.status_label }}
                                        </Badge>
                                    </div>
                                </td>
                                <td class="p-4 text-muted-foreground">
                                    <span v-if="importItem.status === 'pending'">-</span>
                                    <span v-else>
                                        {{ formatProgress(importItem.processed_rows, importItem.total_rows, importItem.progress_percentage) }}
                                    </span>
                                </td>
                                <td class="p-4">
                                    <div v-if="importItem.status === 'completed' || importItem.status === 'failed'" class="flex gap-3 text-sm">
                                        <span class="text-green-600 dark:text-green-400">
                                            {{ importItem.success_count ?? 0 }} success
                                        </span>
                                        <span v-if="(importItem.failure_count ?? 0) > 0" class="text-red-600 dark:text-red-400">
                                            {{ importItem.failure_count }} failed
                                        </span>
                                    </div>
                                    <span v-else class="text-muted-foreground">-</span>
                                </td>
                                <td class="p-4 text-muted-foreground">
                                    {{ formatDate(importItem.created_at) }}
                                </td>
                                <td class="p-4">
                                    <div class="flex items-center gap-2">
                                        <Link :href="show.url(importItem.id)">
                                            <Button variant="outline" size="sm">View</Button>
                                        </Link>
                                        <a
                                            v-if="importItem.has_error_report"
                                            :href="downloadErrors.url(importItem.id)"
                                            target="_blank"
                                        >
                                            <Button variant="ghost" size="icon-sm" title="Download error report">
                                                <Download class="h-4 w-4" />
                                            </Button>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="imports.last_page > 1" class="flex items-center justify-between">
                <p class="text-sm text-muted-foreground">
                    Showing {{ (imports.current_page - 1) * imports.per_page + 1 }} to
                    {{ Math.min(imports.current_page * imports.per_page, imports.total) }} of
                    {{ imports.total }} imports
                </p>
                <div class="flex gap-1">
                    <template v-for="link in imports.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-state
                            preserve-scroll
                        >
                            <Button
                                variant="outline"
                                size="sm"
                                :class="{ 'bg-muted': link.active }"
                                v-html="link.label"
                            />
                        </Link>
                        <Button
                            v-else
                            variant="outline"
                            size="sm"
                            disabled
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
