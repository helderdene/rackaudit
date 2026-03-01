<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import DueDateIndicator from '@/components/DueDateIndicator.vue';
import type { ActiveAuditProgress } from '@/types/dashboard';

interface Props {
    audits: ActiveAuditProgress[];
}

defineProps<Props>();

/**
 * Get the progress bar color class based on overdue/due soon status.
 */
const getProgressBarClass = (audit: ActiveAuditProgress): string => {
    if (audit.isOverdue) {
        return 'bg-red-500 dark:bg-red-400';
    }
    if (audit.isDueSoon) {
        return 'bg-amber-500 dark:bg-amber-400';
    }
    return 'bg-blue-600 dark:bg-blue-500';
};

/**
 * Get the border class for the audit card based on status.
 */
const getCardBorderClass = (audit: ActiveAuditProgress): string => {
    if (audit.isOverdue) {
        return 'border-red-200 dark:border-red-800';
    }
    if (audit.isDueSoon) {
        return 'border-amber-200 dark:border-amber-800';
    }
    return '';
};
</script>

<template>
    <Card v-if="audits.length > 0">
        <CardHeader>
            <CardTitle>Active Audits Progress</CardTitle>
        </CardHeader>
        <CardContent>
            <div class="space-y-4">
                <div
                    v-for="audit in audits"
                    :key="audit.id"
                    :class="[
                        'rounded-lg border p-4',
                        getCardBorderClass(audit),
                    ]"
                >
                    <!-- Header: Audit name and due date indicator -->
                    <div class="mb-2 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <span class="font-medium">{{ audit.name }}</span>
                        <DueDateIndicator
                            v-if="audit.dueDate"
                            :due-date="audit.dueDate"
                            :is-overdue="audit.isOverdue"
                            :is-due-soon="audit.isDueSoon"
                        />
                        <span
                            v-else
                            class="text-sm text-muted-foreground"
                        >
                            No due date
                        </span>
                    </div>

                    <!-- Progress bar -->
                    <div class="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                        <div
                            :class="[
                                'h-2 rounded-full transition-all',
                                getProgressBarClass(audit),
                            ]"
                            :style="{ width: `${audit.progressPercentage}%` }"
                        ></div>
                    </div>

                    <!-- Footer: Audit type, datacenter, and percentage -->
                    <div class="mt-2 flex items-center justify-between text-xs text-muted-foreground">
                        <span>{{ audit.type_label }} - {{ audit.datacenter }}</span>
                        <span class="font-medium">{{ audit.progressPercentage }}%</span>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
