<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Layers } from 'lucide-vue-next';

/**
 * TypeScript interfaces
 */
interface ColumnOption {
    key: string;
    display_name: string;
}

interface Props {
    availableColumns: ColumnOption[];
    groupBy: string | null;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:groupBy', value: string | null): void;
}>();

// Common select styling
const selectClass = 'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring dark:border-input dark:bg-transparent dark:text-foreground';

// Local state
const localGroupBy = ref<string>(props.groupBy || '');

/**
 * Check if grouping is active
 */
const isGrouped = computed(() => !!localGroupBy.value);

/**
 * Get the display name for the currently selected group field
 */
const selectedGroupDisplayName = computed(() => {
    if (!localGroupBy.value) return null;
    const column = props.availableColumns.find(c => c.key === localGroupBy.value);
    return column?.display_name || localGroupBy.value;
});

/**
 * Handle group by selection change
 */
function handleGroupByChange(event: Event) {
    const target = event.target as HTMLSelectElement;
    localGroupBy.value = target.value;
    emit('update:groupBy', target.value || null);
}

// Sync with external changes
watch(() => props.groupBy, (newValue) => {
    localGroupBy.value = newValue || '';
});

// Reset group by when available columns change (column no longer available)
watch(() => props.availableColumns, (newColumns) => {
    const availableKeys = newColumns.map(c => c.key);
    if (localGroupBy.value && !availableKeys.includes(localGroupBy.value)) {
        localGroupBy.value = '';
        emit('update:groupBy', null);
    }
}, { deep: true });
</script>

<template>
    <Card>
        <CardHeader class="pb-2">
            <CardTitle class="flex items-center gap-2 text-sm font-medium">
                <Layers class="size-4" />
                Group By
                <span
                    v-if="isGrouped"
                    class="rounded-md bg-primary/10 px-1.5 py-0.5 text-xs font-normal text-primary"
                >
                    {{ selectedGroupDisplayName }}
                </span>
            </CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
            <div class="space-y-2">
                <Label
                    for="group-by-select"
                    class="text-xs text-muted-foreground"
                >
                    Select a field to group results by (optional)
                </Label>
                <select
                    id="group-by-select"
                    :value="localGroupBy"
                    :class="selectClass"
                    @change="handleGroupByChange"
                >
                    <option value="">No grouping</option>
                    <option
                        v-for="column in availableColumns"
                        :key="column.key"
                        :value="column.key"
                    >
                        {{ column.display_name }}
                    </option>
                </select>
                <p class="text-xs text-muted-foreground">
                    <template v-if="isGrouped">
                        Results will be grouped by <strong>{{ selectedGroupDisplayName }}</strong> with subtotals for numeric fields.
                    </template>
                    <template v-else>
                        Results will be displayed in a flat list without grouping.
                    </template>
                </p>
            </div>
        </CardContent>
    </Card>
</template>
