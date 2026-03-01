<script setup lang="ts">
import { computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { index as auditHistoryIndex } from '@/actions/App/Http/Controllers/AuditHistoryReportController';
import { show as auditShow } from '@/actions/App/Http/Controllers/AuditController';
import { Badge, type BadgeVariants } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ChevronUp, ChevronDown, ChevronsUpDown, ExternalLink } from 'lucide-vue-next';

interface SeverityCounts {
    critical: number;
    high: number;
    medium: number;
    low: number;
}

interface AuditHistoryItem {
    id: number;
    name: string;
    type: string;
    type_label: string;
    datacenter_id: number;
    datacenter_name: string;
    completion_date: string;
    completion_date_formatted: string;
    total_findings: number;
    severity_counts: SeverityCounts;
    avg_resolution_time: number | null;
    avg_resolution_time_formatted: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedAudits {
    data: AuditHistoryItem[];
    links?: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
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
    audits: PaginatedAudits;
    filters: Filters;
}

const props = defineProps<Props>();

/**
 * Get type badge variant
 */
const getTypeBadgeVariant = (type: string): BadgeVariants['variant'] => {
    switch (type) {
        case 'connection':
            return 'info';
        case 'inventory':
            return 'secondary';
        default:
            return 'outline';
    }
};

/**
 * Get current filter params (without sort)
 */
const getCurrentParams = (): Record<string, string | undefined> => {
    const params: Record<string, string | undefined> = {};
    if (props.filters.time_range_preset) params.time_range_preset = props.filters.time_range_preset;
    if (props.filters.start_date) params.start_date = props.filters.start_date;
    if (props.filters.end_date) params.end_date = props.filters.end_date;
    if (props.filters.datacenter_id) params.datacenter_id = String(props.filters.datacenter_id);
    if (props.filters.audit_type) params.audit_type = props.filters.audit_type;
    return params;
};

/**
 * Handle sorting
 */
const handleSort = (column: string) => {
    const newDirection = props.filters.sort_by === column && props.filters.sort_direction === 'asc'
        ? 'desc'
        : props.filters.sort_by === column && props.filters.sort_direction === 'desc'
            ? 'asc'
            : 'desc';

    router.get(auditHistoryIndex.url(), {
        ...getCurrentParams(),
        sort_by: column,
        sort_direction: newDirection,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

/**
 * Get sort icon component
 */
const getSortIcon = (column: string) => {
    if (props.filters.sort_by !== column) return ChevronsUpDown;
    return props.filters.sort_direction === 'asc' ? ChevronUp : ChevronDown;
};

/**
 * Navigate to page
 */
const goToPage = (url: string | null) => {
    if (url) {
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }
};

/**
 * Build audit detail URL
 */
const getAuditUrl = (audit: AuditHistoryItem): string => {
    return auditShow.url({ audit: audit.id });
};

/**
 * Generate pagination links from Laravel paginator
 */
const paginationLinks = computed((): PaginationLink[] => {
    if (props.audits.links) {
        return props.audits.links;
    }

    // Generate basic links if not provided
    const links: PaginationLink[] = [];
    const { current_page, last_page } = props.audits;
    const baseUrl = auditHistoryIndex.url();
    const params = getCurrentParams();

    // Previous
    links.push({
        url: current_page > 1 ? `${baseUrl}?page=${current_page - 1}&${new URLSearchParams(params as Record<string, string>).toString()}` : null,
        label: '&laquo; Previous',
        active: false,
    });

    // Page numbers
    for (let i = 1; i <= last_page; i++) {
        if (
            i === 1 ||
            i === last_page ||
            (i >= current_page - 2 && i <= current_page + 2)
        ) {
            links.push({
                url: `${baseUrl}?page=${i}&${new URLSearchParams(params as Record<string, string>).toString()}`,
                label: String(i),
                active: i === current_page,
            });
        } else if (i === current_page - 3 || i === current_page + 3) {
            links.push({
                url: null,
                label: '...',
                active: false,
            });
        }
    }

    // Next
    links.push({
        url: current_page < last_page ? `${baseUrl}?page=${current_page + 1}&${new URLSearchParams(params as Record<string, string>).toString()}` : null,
        label: 'Next &raquo;',
        active: false,
    });

    return links;
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-base">Audit History</CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Mobile card view -->
            <div class="space-y-3 lg:hidden">
                <div
                    v-for="audit in audits.data"
                    :key="audit.id"
                    class="rounded-lg border bg-card p-4 shadow-sm"
                >
                    <div class="mb-3 flex items-start justify-between gap-2">
                        <Link
                            :href="getAuditUrl(audit)"
                            class="flex items-center gap-1 font-medium text-primary hover:underline"
                        >
                            {{ audit.name }}
                            <ExternalLink class="size-3" />
                        </Link>
                        <Badge :variant="getTypeBadgeVariant(audit.type)">
                            {{ audit.type_label }}
                        </Badge>
                    </div>
                    <div class="mb-2 space-y-1 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Datacenter:</span>
                            <span>{{ audit.datacenter_name }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Completed:</span>
                            <span>{{ audit.completion_date_formatted }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Findings:</span>
                            <span class="font-medium">{{ audit.total_findings }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Avg Resolution:</span>
                            <span>{{ audit.avg_resolution_time_formatted }}</span>
                        </div>
                    </div>
                    <!-- Severity breakdown badges -->
                    <div class="flex flex-wrap gap-1">
                        <span
                            v-if="audit.severity_counts.critical > 0"
                            class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400"
                        >
                            {{ audit.severity_counts.critical }} Critical
                        </span>
                        <span
                            v-if="audit.severity_counts.high > 0"
                            class="rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-700 dark:bg-orange-900/30 dark:text-orange-400"
                        >
                            {{ audit.severity_counts.high }} High
                        </span>
                        <span
                            v-if="audit.severity_counts.medium > 0"
                            class="rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400"
                        >
                            {{ audit.severity_counts.medium }} Med
                        </span>
                        <span
                            v-if="audit.severity_counts.low > 0"
                            class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-400"
                        >
                            {{ audit.severity_counts.low }} Low
                        </span>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-if="audits.data.length === 0" class="rounded-lg border border-dashed py-12 text-center text-muted-foreground">
                    No completed audits found.
                </div>
            </div>

            <!-- Desktop table view -->
            <div class="hidden lg:block">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/50">
                            <tr>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">
                                    Audit Name
                                </th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">
                                    Type
                                </th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">
                                    Datacenter
                                </th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">
                                    <button
                                        class="flex items-center gap-1 hover:text-foreground"
                                        @click="handleSort('completion_date')"
                                    >
                                        Completed
                                        <component :is="getSortIcon('completion_date')" class="size-4" />
                                    </button>
                                </th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">
                                    <button
                                        class="flex items-center gap-1 hover:text-foreground"
                                        @click="handleSort('total_findings')"
                                    >
                                        Total Findings
                                        <component :is="getSortIcon('total_findings')" class="size-4" />
                                    </button>
                                </th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">
                                    By Severity
                                </th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">
                                    <button
                                        class="flex items-center gap-1 hover:text-foreground"
                                        @click="handleSort('avg_resolution_time')"
                                    >
                                        Avg Resolution
                                        <component :is="getSortIcon('avg_resolution_time')" class="size-4" />
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="audit in audits.data"
                                :key="audit.id"
                                class="border-b transition-colors hover:bg-muted/50"
                            >
                                <td class="p-4">
                                    <Link
                                        :href="getAuditUrl(audit)"
                                        class="flex items-center gap-1 font-medium text-primary hover:underline"
                                    >
                                        {{ audit.name }}
                                        <ExternalLink class="size-3" />
                                    </Link>
                                </td>
                                <td class="p-4">
                                    <Badge :variant="getTypeBadgeVariant(audit.type)">
                                        {{ audit.type_label }}
                                    </Badge>
                                </td>
                                <td class="p-4 text-muted-foreground">
                                    {{ audit.datacenter_name }}
                                </td>
                                <td class="p-4 text-muted-foreground">
                                    {{ audit.completion_date_formatted }}
                                </td>
                                <td class="p-4">
                                    <span class="font-medium">{{ audit.total_findings }}</span>
                                </td>
                                <td class="p-4">
                                    <div class="flex flex-wrap gap-1">
                                        <span
                                            v-if="audit.severity_counts.critical > 0"
                                            class="rounded bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400"
                                        >
                                            {{ audit.severity_counts.critical }} C
                                        </span>
                                        <span
                                            v-if="audit.severity_counts.high > 0"
                                            class="rounded bg-orange-100 px-1.5 py-0.5 text-xs font-medium text-orange-700 dark:bg-orange-900/30 dark:text-orange-400"
                                        >
                                            {{ audit.severity_counts.high }} H
                                        </span>
                                        <span
                                            v-if="audit.severity_counts.medium > 0"
                                            class="rounded bg-yellow-100 px-1.5 py-0.5 text-xs font-medium text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400"
                                        >
                                            {{ audit.severity_counts.medium }} M
                                        </span>
                                        <span
                                            v-if="audit.severity_counts.low > 0"
                                            class="rounded bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-400"
                                        >
                                            {{ audit.severity_counts.low }} L
                                        </span>
                                        <span
                                            v-if="audit.total_findings === 0"
                                            class="text-xs text-muted-foreground"
                                        >
                                            -
                                        </span>
                                    </div>
                                </td>
                                <td class="p-4 text-muted-foreground">
                                    {{ audit.avg_resolution_time_formatted }}
                                </td>
                            </tr>
                            <tr v-if="audits.data.length === 0">
                                <td colspan="7" class="p-8 text-center text-muted-foreground">
                                    No completed audits found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="audits.last_page > 1" class="mt-4 flex flex-col items-center justify-between gap-4 sm:flex-row">
                <div class="text-sm text-muted-foreground">
                    Showing {{ (audits.current_page - 1) * audits.per_page + 1 }} to
                    {{ Math.min(audits.current_page * audits.per_page, audits.total) }} of
                    {{ audits.total }} audits
                </div>
                <div class="flex flex-wrap justify-center gap-1">
                    <Button
                        v-for="link in paginationLinks"
                        :key="link.label"
                        variant="outline"
                        size="sm"
                        :disabled="!link.url || link.active"
                        @click="goToPage(link.url)"
                        v-html="link.label"
                    />
                </div>
            </div>
        </CardContent>
    </Card>
</template>
