<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
import { computed } from 'vue';

interface Props {
    columnCount?: number;
    rowCount?: number;
}

const props = withDefaults(defineProps<Props>(), {
    columnCount: 5,
    rowCount: 10,
});

/**
 * Generate array for iteration
 */
const columns = computed(() =>
    Array.from({ length: props.columnCount }, (_, i) => i),
);
const rows = computed(() =>
    Array.from({ length: props.rowCount }, (_, i) => i),
);

/**
 * Generate random width for skeleton cells to look more natural
 */
function getRandomWidth(index: number): string {
    const widths = ['w-16', 'w-20', 'w-24', 'w-28', 'w-32', 'w-36'];
    return widths[index % widths.length];
}

/**
 * Get header width based on column index
 */
function getHeaderWidth(index: number): string {
    const widths = ['w-20', 'w-24', 'w-28', 'w-32'];
    return widths[index % widths.length];
}
</script>

<template>
    <div
        class="space-y-4"
        role="status"
        aria-busy="true"
        aria-label="Loading table data"
    >
        <!-- Screen reader loading announcement -->
        <span class="sr-only" aria-live="polite" aria-atomic="true">
            Loading table data. Please wait...
        </span>

        <!-- Table Skeleton -->
        <div class="overflow-hidden rounded-lg border">
            <table class="w-full" aria-hidden="true">
                <!-- Header Row -->
                <thead class="bg-muted/50">
                    <tr>
                        <th
                            v-for="col in columns"
                            :key="`header-${col}`"
                            class="h-11 px-4 text-left"
                        >
                            <Skeleton :class="['h-4', getHeaderWidth(col)]" />
                        </th>
                    </tr>
                </thead>

                <!-- Body Rows -->
                <tbody>
                    <tr
                        v-for="row in rows"
                        :key="`row-${row}`"
                        class="border-b last:border-b-0"
                    >
                        <td
                            v-for="col in columns"
                            :key="`cell-${row}-${col}`"
                            class="p-4"
                        >
                            <Skeleton
                                :class="['h-4', getRandomWidth(row + col)]"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination Skeleton -->
        <div
            class="flex items-center justify-between border-t pt-4"
            aria-hidden="true"
        >
            <Skeleton class="h-4 w-48" />
            <div class="flex gap-2">
                <Skeleton
                    v-for="i in 5"
                    :key="`page-${i}`"
                    class="size-8 rounded-md"
                />
            </div>
        </div>
    </div>
</template>
