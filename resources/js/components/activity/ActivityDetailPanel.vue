<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent } from '@/components/ui/card';

interface Props {
    oldValues: Record<string, unknown> | null;
    newValues: Record<string, unknown> | null;
}

const props = defineProps<Props>();

type ChangeType = 'added' | 'removed' | 'changed' | 'unchanged';

interface ChangeItem {
    key: string;
    type: ChangeType;
    oldValue: unknown;
    newValue: unknown;
}

const changes = computed<ChangeItem[]>(() => {
    const result: ChangeItem[] = [];
    const allKeys = new Set<string>();

    if (props.oldValues) {
        Object.keys(props.oldValues).forEach((key) => allKeys.add(key));
    }
    if (props.newValues) {
        Object.keys(props.newValues).forEach((key) => allKeys.add(key));
    }

    allKeys.forEach((key) => {
        const oldVal = props.oldValues?.[key];
        const newVal = props.newValues?.[key];

        const hasOld = props.oldValues !== null && key in (props.oldValues || {});
        const hasNew = props.newValues !== null && key in (props.newValues || {});

        let type: ChangeType;

        if (!hasOld && hasNew) {
            type = 'added';
        } else if (hasOld && !hasNew) {
            type = 'removed';
        } else if (JSON.stringify(oldVal) !== JSON.stringify(newVal)) {
            type = 'changed';
        } else {
            type = 'unchanged';
        }

        result.push({
            key,
            type,
            oldValue: oldVal,
            newValue: newVal,
        });
    });

    return result.sort((a, b) => {
        const order: Record<ChangeType, number> = { added: 0, removed: 1, changed: 2, unchanged: 3 };
        return order[a.type] - order[b.type];
    });
});

const formatValue = (value: unknown): string => {
    if (value === null || value === undefined) {
        return 'null';
    }
    if (typeof value === 'object') {
        return JSON.stringify(value, null, 2);
    }
    return String(value);
};

const getRowClass = (type: ChangeType): string => {
    const classes: Record<ChangeType, string> = {
        added: 'bg-green-50 dark:bg-green-900/20',
        removed: 'bg-red-50 dark:bg-red-900/20',
        changed: 'bg-yellow-50 dark:bg-yellow-900/20',
        unchanged: '',
    };
    return classes[type];
};

const getIndicatorClass = (type: ChangeType): string => {
    const classes: Record<ChangeType, string> = {
        added: 'bg-green-500',
        removed: 'bg-red-500',
        changed: 'bg-yellow-500',
        unchanged: 'bg-gray-300 dark:bg-gray-600',
    };
    return classes[type];
};

const getTypeLabel = (type: ChangeType): string => {
    const labels: Record<ChangeType, string> = {
        added: 'Added',
        removed: 'Removed',
        changed: 'Modified',
        unchanged: 'Unchanged',
    };
    return labels[type];
};

const isEmpty = computed(() => {
    return props.oldValues === null && props.newValues === null;
});

const isCreated = computed(() => {
    return props.oldValues === null && props.newValues !== null;
});

const isDeleted = computed(() => {
    return props.oldValues !== null && props.newValues === null;
});
</script>

<template>
    <Card class="mt-2">
        <CardContent class="py-4">
            <div v-if="isEmpty" class="text-muted-foreground text-sm">
                No change data available
            </div>
            <div v-else>
                <div class="mb-3 flex items-center gap-4 text-xs">
                    <div class="flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-full bg-green-500"></span>
                        <span class="text-muted-foreground">Added</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-full bg-red-500"></span>
                        <span class="text-muted-foreground">Removed</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-full bg-yellow-500"></span>
                        <span class="text-muted-foreground">Modified</span>
                    </div>
                </div>

                <div v-if="isCreated" class="mb-2 text-sm text-green-700 dark:text-green-400">
                    Record created with the following values:
                </div>
                <div v-else-if="isDeleted" class="mb-2 text-sm text-red-700 dark:text-red-400">
                    Record deleted with the following values:
                </div>

                <div class="overflow-hidden rounded-md border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium">Field</th>
                                <th class="px-3 py-2 text-left font-medium">Status</th>
                                <th v-if="!isCreated" class="px-3 py-2 text-left font-medium">Old Value</th>
                                <th v-if="!isDeleted" class="px-3 py-2 text-left font-medium">New Value</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="change in changes"
                                :key="change.key"
                                :class="getRowClass(change.type)"
                            >
                                <td class="px-3 py-2 font-mono text-xs">{{ change.key }}</td>
                                <td class="px-3 py-2">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span
                                            class="h-2 w-2 rounded-full"
                                            :class="getIndicatorClass(change.type)"
                                        ></span>
                                        <span class="text-xs">{{ getTypeLabel(change.type) }}</span>
                                    </span>
                                </td>
                                <td v-if="!isCreated" class="px-3 py-2 font-mono text-xs">
                                    <pre class="whitespace-pre-wrap break-all">{{ formatValue(change.oldValue) }}</pre>
                                </td>
                                <td v-if="!isDeleted" class="px-3 py-2 font-mono text-xs">
                                    <pre class="whitespace-pre-wrap break-all">{{ formatValue(change.newValue) }}</pre>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
