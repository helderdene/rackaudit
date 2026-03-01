<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { ArrowDownUp, Plus, Trash2, ArrowUp, ArrowDown } from 'lucide-vue-next';

/**
 * TypeScript interfaces
 */
interface ColumnOption {
    key: string;
    display_name: string;
}

interface SortItem {
    column: string;
    direction: 'asc' | 'desc';
}

interface Props {
    availableColumns: ColumnOption[];
    sortConfig: SortItem[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:sortConfig', value: SortItem[]): void;
}>();

// Maximum number of sort columns
const MAX_SORT_COLUMNS = 3;

// Common select styling
const selectClass = 'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring dark:border-input dark:bg-transparent dark:text-foreground';

// Local sort configuration state
const localSortConfig = ref<SortItem[]>([...props.sortConfig]);

/**
 * Check if we can add more sort columns
 */
const canAddSort = computed(() => {
    return localSortConfig.value.length < MAX_SORT_COLUMNS && availableColumnsForSort.value.length > 0;
});

/**
 * Get columns available for sorting (not already selected)
 */
const availableColumnsForSort = computed(() => {
    const selectedColumns = localSortConfig.value.map(s => s.column);
    return props.availableColumns.filter(col => !selectedColumns.includes(col.key));
});

/**
 * Get display name for a column key
 */
function getColumnDisplayName(columnKey: string): string {
    const column = props.availableColumns.find(c => c.key === columnKey);
    return column?.display_name || columnKey;
}

/**
 * Add a new sort column
 */
function addSortColumn() {
    if (!canAddSort.value) return;

    const nextColumn = availableColumnsForSort.value[0];
    if (nextColumn) {
        localSortConfig.value.push({
            column: nextColumn.key,
            direction: 'desc',
        });
        emitSortConfig();
    }
}

/**
 * Remove a sort column by index
 */
function removeSortColumn(index: number) {
    localSortConfig.value.splice(index, 1);
    emitSortConfig();
}

/**
 * Toggle sort direction for a column
 */
function toggleDirection(index: number) {
    const current = localSortConfig.value[index];
    current.direction = current.direction === 'asc' ? 'desc' : 'asc';
    emitSortConfig();
}

/**
 * Update column selection for a sort item
 */
function updateColumn(index: number, columnKey: string) {
    localSortConfig.value[index].column = columnKey;
    emitSortConfig();
}

/**
 * Emit the updated sort configuration
 */
function emitSortConfig() {
    emit('update:sortConfig', [...localSortConfig.value]);
}

// Sync with external changes
watch(() => props.sortConfig, (newConfig) => {
    localSortConfig.value = [...newConfig];
}, { deep: true });

// Reset sort config when available columns change significantly
watch(() => props.availableColumns, (newColumns) => {
    // Remove any sort items that reference columns no longer available
    const availableKeys = newColumns.map(c => c.key);
    localSortConfig.value = localSortConfig.value.filter(
        item => availableKeys.includes(item.column)
    );
    emitSortConfig();
}, { deep: true });
</script>

<template>
    <Card>
        <CardHeader class="pb-2">
            <CardTitle class="flex items-center justify-between text-sm font-medium">
                <span class="flex items-center gap-2">
                    <ArrowDownUp class="size-4" />
                    Sort Configuration
                </span>
                <Button
                    v-if="canAddSort"
                    variant="ghost"
                    size="sm"
                    class="h-7 px-2 text-xs"
                    @click="addSortColumn"
                >
                    <Plus class="mr-1 size-3" />
                    Add Sort
                </Button>
            </CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
            <div v-if="localSortConfig.length === 0" class="py-4 text-center">
                <p class="text-sm text-muted-foreground">
                    No sort columns configured. Click "Add Sort" to add sorting.
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Results will be sorted by the first selected column (descending) by default.
                </p>
            </div>

            <div v-else class="space-y-3">
                <div
                    v-for="(sortItem, index) in localSortConfig"
                    :key="index"
                    class="flex items-center gap-3 rounded-md border bg-muted/30 p-3"
                >
                    <!-- Priority indicator -->
                    <div class="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-medium text-primary">
                        {{ index + 1 }}
                    </div>

                    <!-- Column selector -->
                    <div class="flex-1">
                        <Label :for="`sort-column-${index}`" class="sr-only">
                            Sort Column {{ index + 1 }}
                        </Label>
                        <select
                            :id="`sort-column-${index}`"
                            :value="sortItem.column"
                            :class="selectClass"
                            @change="(e) => updateColumn(index, (e.target as HTMLSelectElement).value)"
                        >
                            <!-- Current selection -->
                            <option :value="sortItem.column">
                                {{ getColumnDisplayName(sortItem.column) }}
                            </option>
                            <!-- Other available columns -->
                            <option
                                v-for="col in availableColumnsForSort"
                                :key="col.key"
                                :value="col.key"
                            >
                                {{ col.display_name }}
                            </option>
                        </select>
                    </div>

                    <!-- Direction toggle -->
                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-9 w-20 gap-1"
                            :class="{
                                'border-primary bg-primary/5': true,
                            }"
                            @click="toggleDirection(index)"
                        >
                            <ArrowUp
                                v-if="sortItem.direction === 'asc'"
                                class="size-3.5"
                            />
                            <ArrowDown
                                v-else
                                class="size-3.5"
                            />
                            <span class="text-xs">
                                {{ sortItem.direction === 'asc' ? 'Asc' : 'Desc' }}
                            </span>
                        </Button>
                    </div>

                    <!-- Remove button -->
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-8 shrink-0 text-muted-foreground hover:text-destructive"
                        @click="removeSortColumn(index)"
                    >
                        <Trash2 class="size-4" />
                        <span class="sr-only">Remove sort column</span>
                    </Button>
                </div>

                <!-- Add more sort button (when less than max) -->
                <div
                    v-if="canAddSort"
                    class="flex items-center justify-center border-t pt-3"
                >
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-8 text-xs text-muted-foreground"
                        @click="addSortColumn"
                    >
                        <Plus class="mr-1 size-3" />
                        Add another sort column ({{ MAX_SORT_COLUMNS - localSortConfig.length }} remaining)
                    </Button>
                </div>

                <!-- Max reached message -->
                <p
                    v-if="localSortConfig.length >= MAX_SORT_COLUMNS"
                    class="text-center text-xs text-muted-foreground"
                >
                    Maximum of {{ MAX_SORT_COLUMNS }} sort columns reached
                </p>
            </div>
        </CardContent>
    </Card>
</template>
