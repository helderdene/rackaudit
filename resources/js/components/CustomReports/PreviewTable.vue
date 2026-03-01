<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight, FileBarChart } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import TableLoadingSkeleton from './TableLoadingSkeleton.vue';

/**
 * Column definition for the preview table
 */
interface ColumnHeader {
    key: string;
    label: string;
}

/**
 * Pagination data structure
 */
interface Pagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

/**
 * Group data structure when groupBy is active
 */
interface GroupData {
    group_value: string;
    subtotals?: Record<string, number | string>;
    rows: Array<Record<string, unknown>>;
}

interface Props {
    columns: ColumnHeader[];
    data: Array<Record<string, unknown>>;
    pagination: Pagination;
    loading?: boolean;
    groupBy?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
    groupBy: null,
});

const emit = defineEmits<{
    (e: 'page-change', page: number): void;
}>();

/**
 * Calculate the range of records being displayed
 */
const displayRange = computed(() => {
    const start = (props.pagination.current_page - 1) * props.pagination.per_page + 1;
    const end = Math.min(props.pagination.current_page * props.pagination.per_page, props.pagination.total);
    return { start, end };
});

/**
 * Check if we have grouped data
 */
const isGrouped = computed(() => {
    return props.groupBy && props.data.length > 0 && '_group' in props.data[0];
});

/**
 * Transform data for grouped display
 */
const groupedData = computed((): GroupData[] => {
    if (!isGrouped.value || !props.groupBy) {
        return [];
    }

    // Data comes pre-grouped from the backend
    const groups: Map<string, GroupData> = new Map();

    for (const row of props.data) {
        const groupValue = String(row['_group'] ?? row[props.groupBy] ?? 'Unknown');

        if (!groups.has(groupValue)) {
            groups.set(groupValue, {
                group_value: groupValue,
                rows: [],
                subtotals: row['_subtotals'] as Record<string, number | string> | undefined,
            });
        }

        groups.get(groupValue)!.rows.push(row);
    }

    return Array.from(groups.values());
});

/**
 * Format cell value based on data type
 */
function formatCellValue(value: unknown, key: string): string {
    if (value === null || value === undefined) {
        return '-';
    }

    // Handle percentages
    if (key.includes('percent') || key.includes('utilization')) {
        const num = Number(value);
        return isNaN(num) ? String(value) : `${num.toFixed(1)}%`;
    }

    // Handle dates
    if (key.includes('date') && typeof value === 'string') {
        try {
            const date = new Date(value);
            if (!isNaN(date.getTime())) {
                return date.toLocaleDateString();
            }
        } catch {
            // Fall through to string conversion
        }
    }

    // Handle watts (power)
    if (key.includes('watts')) {
        const num = Number(value);
        if (!isNaN(num)) {
            return num >= 1000 ? `${(num / 1000).toFixed(1)}kW` : `${num}W`;
        }
    }

    // Handle numbers
    if (typeof value === 'number') {
        return value.toLocaleString();
    }

    return String(value);
}

/**
 * Get cell alignment class based on data type
 */
function getCellAlignment(key: string): string {
    // Numeric columns should be right-aligned
    if (
        key.includes('percent') ||
        key.includes('utilization') ||
        key.includes('count') ||
        key.includes('watts') ||
        key.includes('u_height') ||
        key.includes('u_space') ||
        key.includes('days')
    ) {
        return 'text-right';
    }
    return 'text-left';
}

/**
 * Navigate to a specific page
 */
function goToPage(page: number): void {
    if (page >= 1 && page <= props.pagination.last_page) {
        emit('page-change', page);
    }
}

/**
 * Generate pagination links for display
 */
const paginationLinks = computed(() => {
    const current = props.pagination.current_page;
    const last = props.pagination.last_page;
    const links: Array<{ page: number | null; label: string; active: boolean }> = [];

    // Always show first page
    if (last > 0) {
        links.push({ page: 1, label: '1', active: current === 1 });
    }

    // Show ellipsis if needed
    if (current > 3) {
        links.push({ page: null, label: '...', active: false });
    }

    // Show pages around current
    for (let i = Math.max(2, current - 1); i <= Math.min(last - 1, current + 1); i++) {
        if (i !== 1 && i !== last) {
            links.push({ page: i, label: String(i), active: current === i });
        }
    }

    // Show ellipsis if needed
    if (current < last - 2) {
        links.push({ page: null, label: '...', active: false });
    }

    // Always show last page
    if (last > 1) {
        links.push({ page: last, label: String(last), active: current === last });
    }

    return links;
});

/**
 * Simplified pagination links for mobile view
 */
const mobilePaginationLinks = computed(() => {
    const current = props.pagination.current_page;
    const last = props.pagination.last_page;

    // On mobile, just show current page indicator
    return [
        { page: current, label: String(current), active: true },
    ];
});

/**
 * Check if this is the first column (for sticky behavior)
 */
function isFirstColumn(index: number): boolean {
    return index === 0;
}
</script>

<template>
    <Card>
        <CardHeader class="pb-3">
            <div class="flex items-center justify-between">
                <CardTitle class="text-base">Preview Results</CardTitle>
                <span
                    class="text-sm text-muted-foreground"
                    aria-live="polite"
                >
                    {{ pagination.total.toLocaleString() }} record{{ pagination.total !== 1 ? 's' : '' }}
                </span>
            </div>
        </CardHeader>
        <CardContent>
            <!-- Loading Skeleton -->
            <div
                v-if="loading"
                role="status"
                aria-label="Loading preview data"
            >
                <TableLoadingSkeleton :column-count="columns.length" />
                <span class="sr-only">Loading preview data, please wait...</span>
            </div>

            <!-- Data Table -->
            <template v-else-if="data.length > 0">
                <!-- Responsive table container with horizontal scroll -->
                <div
                    class="relative -mx-4 overflow-x-auto sm:mx-0 sm:rounded-lg sm:border"
                    role="region"
                    aria-label="Preview data table"
                    tabindex="0"
                >
                    <table class="w-full min-w-[600px] text-sm">
                        <thead class="border-b bg-muted/50">
                            <tr>
                                <th
                                    v-for="(column, index) in columns"
                                    :key="column.key"
                                    :class="[
                                        'h-11 whitespace-nowrap px-4 font-medium text-muted-foreground',
                                        getCellAlignment(column.key),
                                        // Sticky first column on mobile
                                        isFirstColumn(index) ? 'sticky left-0 z-10 bg-muted/50 sm:static sm:z-auto' : '',
                                    ]"
                                    scope="col"
                                >
                                    {{ column.label }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Grouped data display -->
                            <template v-if="isGrouped">
                                <template v-for="group in groupedData" :key="group.group_value">
                                    <!-- Group Header -->
                                    <tr class="border-b bg-muted/30">
                                        <td
                                            :colspan="columns.length"
                                            class="p-3 font-medium"
                                        >
                                            <span class="text-primary">{{ groupBy }}</span>:
                                            {{ group.group_value }}
                                            <span class="ml-2 text-sm text-muted-foreground">
                                                ({{ group.rows.length }} record{{ group.rows.length !== 1 ? 's' : '' }})
                                            </span>
                                        </td>
                                    </tr>

                                    <!-- Group Rows -->
                                    <tr
                                        v-for="(row, rowIndex) in group.rows"
                                        :key="`${group.group_value}-${rowIndex}`"
                                        class="border-b transition-colors hover:bg-muted/50"
                                    >
                                        <td
                                            v-for="(column, colIndex) in columns"
                                            :key="column.key"
                                            :class="[
                                                'whitespace-nowrap p-4',
                                                getCellAlignment(column.key),
                                                // Sticky first column on mobile
                                                isFirstColumn(colIndex) ? 'sticky left-0 z-10 bg-background sm:static sm:z-auto' : '',
                                            ]"
                                        >
                                            {{ formatCellValue(row[column.key], column.key) }}
                                        </td>
                                    </tr>

                                    <!-- Group Subtotals -->
                                    <tr v-if="group.subtotals" class="border-b bg-muted/20 font-medium">
                                        <td
                                            v-for="(column, colIndex) in columns"
                                            :key="column.key"
                                            :class="[
                                                'whitespace-nowrap p-4',
                                                getCellAlignment(column.key),
                                                isFirstColumn(colIndex) ? 'sticky left-0 z-10 bg-muted/20 sm:static sm:z-auto' : '',
                                            ]"
                                        >
                                            <template v-if="colIndex === 0">
                                                Subtotal
                                            </template>
                                            <template v-else-if="group.subtotals && column.key in group.subtotals">
                                                {{ formatCellValue(group.subtotals[column.key], column.key) }}
                                            </template>
                                        </td>
                                    </tr>
                                </template>
                            </template>

                            <!-- Flat data display -->
                            <template v-else>
                                <tr
                                    v-for="(row, index) in data"
                                    :key="index"
                                    class="border-b transition-colors hover:bg-muted/50"
                                >
                                    <td
                                        v-for="(column, colIndex) in columns"
                                        :key="column.key"
                                        :class="[
                                            'whitespace-nowrap p-4',
                                            getCellAlignment(column.key),
                                            // Sticky first column on mobile
                                            isFirstColumn(colIndex) ? 'sticky left-0 z-10 bg-background sm:static sm:z-auto' : '',
                                        ]"
                                    >
                                        {{ formatCellValue(row[column.key], column.key) }}
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <nav
                    v-if="pagination.last_page > 1"
                    class="mt-4 border-t pt-4"
                    aria-label="Pagination navigation"
                >
                    <!-- Mobile Pagination: Compact layout -->
                    <div class="flex items-center justify-between sm:hidden">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="pagination.current_page === 1"
                            @click="goToPage(pagination.current_page - 1)"
                            aria-label="Go to previous page"
                        >
                            <ChevronLeft class="mr-1 size-4" aria-hidden="true" />
                            Prev
                        </Button>

                        <span class="text-sm text-muted-foreground" aria-current="page">
                            Page {{ pagination.current_page }} of {{ pagination.last_page }}
                        </span>

                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="pagination.current_page === pagination.last_page"
                            @click="goToPage(pagination.current_page + 1)"
                            aria-label="Go to next page"
                        >
                            Next
                            <ChevronRight class="ml-1 size-4" aria-hidden="true" />
                        </Button>
                    </div>

                    <!-- Desktop Pagination: Full layout -->
                    <div class="hidden sm:flex sm:flex-col sm:items-center sm:justify-between sm:gap-4 md:flex-row">
                        <p class="text-sm text-muted-foreground" aria-live="polite">
                            Showing {{ displayRange.start.toLocaleString() }} to {{ displayRange.end.toLocaleString() }}
                            of {{ pagination.total.toLocaleString() }} records
                        </p>

                        <div class="flex items-center gap-1">
                            <!-- First Page -->
                            <Button
                                variant="outline"
                                size="icon"
                                class="size-8"
                                :disabled="pagination.current_page === 1"
                                @click="goToPage(1)"
                                aria-label="Go to first page"
                            >
                                <ChevronsLeft class="size-4" aria-hidden="true" />
                            </Button>

                            <!-- Previous Page -->
                            <Button
                                variant="outline"
                                size="icon"
                                class="size-8"
                                :disabled="pagination.current_page === 1"
                                @click="goToPage(pagination.current_page - 1)"
                                aria-label="Go to previous page"
                            >
                                <ChevronLeft class="size-4" aria-hidden="true" />
                            </Button>

                            <!-- Page Numbers -->
                            <template v-for="link in paginationLinks" :key="link.label">
                                <Button
                                    v-if="link.page !== null"
                                    :variant="link.active ? 'default' : 'outline'"
                                    size="sm"
                                    class="size-8"
                                    :aria-current="link.active ? 'page' : undefined"
                                    :aria-label="`Go to page ${link.page}`"
                                    @click="goToPage(link.page)"
                                >
                                    {{ link.label }}
                                </Button>
                                <span
                                    v-else
                                    class="px-2 text-sm text-muted-foreground"
                                    aria-hidden="true"
                                >
                                    {{ link.label }}
                                </span>
                            </template>

                            <!-- Next Page -->
                            <Button
                                variant="outline"
                                size="icon"
                                class="size-8"
                                :disabled="pagination.current_page === pagination.last_page"
                                @click="goToPage(pagination.current_page + 1)"
                                aria-label="Go to next page"
                            >
                                <ChevronRight class="size-4" aria-hidden="true" />
                            </Button>

                            <!-- Last Page -->
                            <Button
                                variant="outline"
                                size="icon"
                                class="size-8"
                                :disabled="pagination.current_page === pagination.last_page"
                                @click="goToPage(pagination.last_page)"
                                aria-label="Go to last page"
                            >
                                <ChevronsRight class="size-4" aria-hidden="true" />
                            </Button>
                        </div>
                    </div>
                </nav>
            </template>

            <!-- Empty State -->
            <template v-else>
                <div class="py-12 text-center" role="status">
                    <FileBarChart class="mx-auto mb-4 size-12 text-muted-foreground/50" aria-hidden="true" />
                    <h3 class="text-lg font-medium">No data available</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        No records match your current configuration.
                    </p>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
