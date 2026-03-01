<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { create, show, download } from '@/actions/App/Http/Controllers/BulkExportController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { PaginationLink, SelectOption } from '@/types/rooms';
import { Download, Plus, FileSpreadsheet, Clock, CheckCircle, XCircle, Loader2 } from 'lucide-vue-next';

interface BulkExportData {
    id: number;
    entity_type: string | null;
    entity_type_label: string | null;
    format: string;
    file_name: string;
    status: string;
    status_label: string;
    total_rows: number | null;
    processed_rows: number | null;
    progress_percentage: number | null;
    download_url: string | null;
    user?: {
        id: number;
        name: string;
    };
    created_at: string;
    started_at: string | null;
    completed_at: string | null;
}

interface PaginatedExports {
    data: BulkExportData[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    exports: PaginatedExports;
    entityTypeOptions: SelectOption[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Exports',
        href: '/exports',
    },
];

// Get status badge variant
const getStatusVariant = (status: string): 'default' | 'secondary' | 'destructive' | 'success' | 'warning' => {
    switch (status) {
        case 'completed':
            return 'success';
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

// Format file type for display
const formatFileType = (format: string): string => {
    return format.toUpperCase();
};
</script>

<template>
    <Head title="Bulk Exports" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <HeadingSmall
                    title="Bulk Export"
                    description="Export datacenter infrastructure data to spreadsheets."
                />
                <Link :href="create.url()">
                    <Button>
                        <Plus class="mr-2 h-4 w-4" />
                        New Export
                    </Button>
                </Link>
            </div>

            <!-- Empty state -->
            <div
                v-if="exports.data.length === 0"
                class="flex flex-col items-center justify-center rounded-lg border border-dashed py-16"
            >
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-muted">
                    <Download class="h-6 w-6 text-muted-foreground" />
                </div>
                <h3 class="mt-4 text-sm font-medium">No exports yet</h3>
                <p class="mt-1 text-center text-sm text-muted-foreground">
                    Start by creating an export of your datacenter data.
                </p>
                <Link :href="create.url()" class="mt-4">
                    <Button variant="outline" size="sm">
                        <Plus class="mr-2 h-4 w-4" />
                        Create your first export
                    </Button>
                </Link>
            </div>

            <!-- Export history table -->
            <div v-else class="overflow-hidden rounded-md border">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/50">
                            <tr>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">File</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Entity Type</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Format</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Status</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Progress</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Date</th>
                                <th class="h-12 w-[120px] px-4 text-left font-medium text-muted-foreground">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="exportItem in exports.data"
                                :key="exportItem.id"
                                class="border-b transition-colors hover:bg-muted/50"
                            >
                                <td class="p-4">
                                    <div class="flex items-center gap-2">
                                        <FileSpreadsheet class="h-4 w-4 text-muted-foreground" />
                                        <Link
                                            :href="show.url(exportItem.id)"
                                            class="font-medium hover:underline"
                                        >
                                            {{ exportItem.file_name }}
                                        </Link>
                                    </div>
                                </td>
                                <td class="p-4 text-muted-foreground">
                                    {{ exportItem.entity_type_label || '-' }}
                                </td>
                                <td class="p-4">
                                    <Badge variant="outline">
                                        {{ formatFileType(exportItem.format) }}
                                    </Badge>
                                </td>
                                <td class="p-4">
                                    <div class="flex items-center gap-2">
                                        <component
                                            :is="getStatusIcon(exportItem.status)"
                                            class="h-4 w-4"
                                            :class="{
                                                'animate-spin text-primary': exportItem.status === 'processing',
                                                'text-muted-foreground': exportItem.status === 'pending',
                                                'text-green-500': exportItem.status === 'completed',
                                                'text-red-500': exportItem.status === 'failed',
                                            }"
                                        />
                                        <Badge :variant="getStatusVariant(exportItem.status)">
                                            {{ exportItem.status_label }}
                                        </Badge>
                                    </div>
                                </td>
                                <td class="p-4 text-muted-foreground">
                                    <span v-if="exportItem.status === 'pending'">-</span>
                                    <span v-else>
                                        {{ formatProgress(exportItem.processed_rows, exportItem.total_rows, exportItem.progress_percentage) }}
                                    </span>
                                </td>
                                <td class="p-4 text-muted-foreground">
                                    {{ formatDate(exportItem.created_at) }}
                                </td>
                                <td class="p-4">
                                    <div class="flex items-center gap-2">
                                        <Link :href="show.url(exportItem.id)">
                                            <Button variant="outline" size="sm">View</Button>
                                        </Link>
                                        <a
                                            v-if="exportItem.status === 'completed' && exportItem.download_url"
                                            :href="download.url(exportItem.id)"
                                            target="_blank"
                                        >
                                            <Button variant="ghost" size="icon-sm" title="Download export">
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
            <div v-if="exports.last_page > 1" class="flex items-center justify-between">
                <p class="text-sm text-muted-foreground">
                    Showing {{ (exports.current_page - 1) * exports.per_page + 1 }} to
                    {{ Math.min(exports.current_page * exports.per_page, exports.total) }} of
                    {{ exports.total }} exports
                </p>
                <div class="flex gap-1">
                    <template v-for="link in exports.links" :key="link.label">
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
