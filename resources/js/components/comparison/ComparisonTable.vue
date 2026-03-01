<script setup lang="ts">
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    Plus,
    Trash2,
    CheckCircle,
    Eye,
} from 'lucide-vue-next';
import ComparisonRow from './ComparisonRow.vue';
import ComparisonStatistics from './ComparisonStatistics.vue';
import AcknowledgeDiscrepancyDialog from './AcknowledgeDiscrepancyDialog.vue';
import type { ComparisonResultData, ComparisonStatistics as ComparisonStatsType } from '@/types/comparison';

interface Props {
    comparisons: ComparisonResultData[];
    statistics: ComparisonStatsType;
    isLoading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    isLoading: false,
});

const emit = defineEmits<{
    (e: 'create-connection', comparison: ComparisonResultData): void;
    (e: 'delete-connection', comparison: ComparisonResultData): void;
    (e: 'acknowledge', comparison: ComparisonResultData): void;
    (e: 'refresh'): void;
    (e: 'view-details', comparison: ComparisonResultData): void;
}>();

/**
 * State for the acknowledge discrepancy dialog
 */
const isAcknowledgeDialogOpen = ref(false);
const selectedDiscrepancy = ref<ComparisonResultData | null>(null);

/**
 * Get CSS classes for a comparison row based on discrepancy type.
 * Uses border-l-4 pattern from ConnectionReviewTable.vue for visual status indication.
 */
function getRowClasses(comparison: ComparisonResultData): string {
    // If acknowledged, use muted styling
    if (comparison.is_acknowledged) {
        return 'bg-muted/30 opacity-60 border-l-4 border-l-gray-400';
    }

    switch (comparison.discrepancy_type) {
        case 'matched':
            return 'bg-green-50/30 dark:bg-green-900/10 border-l-4 border-l-green-500';
        case 'missing':
            return 'bg-red-50/30 dark:bg-red-900/10 border-l-4 border-l-red-500';
        case 'unexpected':
            return 'bg-orange-50/30 dark:bg-orange-900/10 border-l-4 border-l-orange-500';
        case 'mismatched':
            return 'bg-amber-50/30 dark:bg-amber-900/10 border-l-4 border-l-amber-500';
        case 'conflicting':
            return 'bg-purple-50/30 dark:bg-purple-900/10 border-l-4 border-l-purple-500';
        default:
            return '';
    }
}

/**
 * Determine which action buttons to show for each discrepancy type.
 */
function getAvailableActions(comparison: ComparisonResultData): {
    showCreateConnection: boolean;
    showDeleteConnection: boolean;
    showAcknowledge: boolean;
    showViewDetails: boolean;
} {
    const discrepancyType = comparison.discrepancy_type;
    const isAcknowledged = comparison.is_acknowledged;

    return {
        // "Create Connection" button for "Missing" status only
        showCreateConnection: discrepancyType === 'missing' && !isAcknowledged,
        // "Delete Connection" button for "Unexpected" status only
        showDeleteConnection: discrepancyType === 'unexpected' && !isAcknowledged,
        // "Acknowledge" button for all non-matched, non-acknowledged statuses
        showAcknowledge: discrepancyType !== 'matched' && !isAcknowledged,
        // "View Details" button for matched connections
        showViewDetails: discrepancyType === 'matched',
    };
}

function handleCreateConnection(comparison: ComparisonResultData): void {
    emit('create-connection', comparison);
}

function handleDeleteConnection(comparison: ComparisonResultData): void {
    emit('delete-connection', comparison);
}

/**
 * Open the acknowledge discrepancy dialog with the selected discrepancy.
 */
function handleAcknowledge(comparison: ComparisonResultData): void {
    selectedDiscrepancy.value = comparison;
    isAcknowledgeDialogOpen.value = true;
    // Also emit the event for parent components that may want to handle it
    emit('acknowledge', comparison);
}

/**
 * Handle successful acknowledgment - close dialog and refresh table.
 */
function handleAcknowledged(): void {
    selectedDiscrepancy.value = null;
    emit('refresh');
}

function handleViewDetails(comparison: ComparisonResultData): void {
    emit('view-details', comparison);
}

const hasComparisons = computed(() => props.comparisons.length > 0);
</script>

<template>
    <div class="space-y-4">
        <!-- Summary Statistics -->
        <ComparisonStatistics :statistics="statistics" />

        <!-- Loading State -->
        <div v-if="isLoading" class="flex items-center justify-center py-12">
            <Spinner class="size-8" />
        </div>

        <!-- Data Table -->
        <div v-else-if="hasComparisons" class="overflow-hidden rounded-lg border">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="h-10 w-12 px-3 text-left font-medium text-muted-foreground">#</th>
                            <th class="h-10 px-3 text-left font-medium text-muted-foreground">Source Device</th>
                            <th class="h-10 px-3 text-left font-medium text-muted-foreground">Source Port</th>
                            <th class="h-10 px-3 text-left font-medium text-muted-foreground">Dest Device</th>
                            <th class="h-10 px-3 text-left font-medium text-muted-foreground">Dest Port</th>
                            <th class="h-10 w-24 px-3 text-left font-medium text-muted-foreground">Cable Type</th>
                            <th class="h-10 w-28 px-3 text-left font-medium text-muted-foreground">Status</th>
                            <th class="h-10 w-36 px-3 text-left font-medium text-muted-foreground">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(comparison, index) in comparisons"
                            :key="comparison.expected_connection?.id ?? comparison.actual_connection?.id ?? index"
                            class="border-b transition-colors hover:bg-muted/50 last:border-b-0"
                            :class="getRowClasses(comparison)"
                        >
                            <ComparisonRow
                                :comparison="comparison"
                                :row-number="index + 1"
                            />

                            <!-- Actions -->
                            <td class="px-3 py-3">
                                <div class="flex gap-1">
                                    <!-- Create Connection (Missing only) -->
                                    <Button
                                        v-if="getAvailableActions(comparison).showCreateConnection"
                                        size="sm"
                                        variant="ghost"
                                        class="h-7 px-2 text-green-600 hover:bg-green-50 hover:text-green-700 dark:text-green-400 dark:hover:bg-green-900/20"
                                        title="Create Connection"
                                        @click="handleCreateConnection(comparison)"
                                    >
                                        <Plus class="mr-1 size-3.5" />
                                        Create
                                    </Button>

                                    <!-- Delete Connection (Unexpected only) -->
                                    <Button
                                        v-if="getAvailableActions(comparison).showDeleteConnection"
                                        size="sm"
                                        variant="ghost"
                                        class="h-7 px-2 text-destructive hover:bg-red-50 hover:text-destructive dark:hover:bg-red-900/20"
                                        title="Delete Connection"
                                        @click="handleDeleteConnection(comparison)"
                                    >
                                        <Trash2 class="mr-1 size-3.5" />
                                        Delete
                                    </Button>

                                    <!-- Acknowledge (All non-matched) -->
                                    <Button
                                        v-if="getAvailableActions(comparison).showAcknowledge"
                                        size="sm"
                                        variant="ghost"
                                        class="h-7 px-2 text-muted-foreground hover:text-foreground"
                                        title="Acknowledge Discrepancy"
                                        @click="handleAcknowledge(comparison)"
                                    >
                                        <CheckCircle class="mr-1 size-3.5" />
                                        Ack
                                    </Button>

                                    <!-- View Details (Matched only) -->
                                    <Button
                                        v-if="getAvailableActions(comparison).showViewDetails"
                                        size="sm"
                                        variant="ghost"
                                        class="h-7 px-2 text-muted-foreground hover:text-foreground"
                                        title="View Connection Details"
                                        @click="handleViewDetails(comparison)"
                                    >
                                        <Eye class="mr-1 size-3.5" />
                                        View
                                    </Button>

                                    <!-- No actions available -->
                                    <span
                                        v-if="
                                            !getAvailableActions(comparison).showCreateConnection &&
                                            !getAvailableActions(comparison).showDeleteConnection &&
                                            !getAvailableActions(comparison).showAcknowledge &&
                                            !getAvailableActions(comparison).showViewDetails
                                        "
                                        class="text-xs text-muted-foreground"
                                    >
                                        -
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Empty State -->
        <div
            v-else-if="!isLoading"
            class="flex flex-col items-center justify-center py-12 text-center"
        >
            <CheckCircle class="mb-4 size-12 text-muted-foreground/50" />
            <h3 class="text-lg font-medium">No comparison results</h3>
            <p class="mt-1 text-sm text-muted-foreground">
                No expected connections found for comparison.
            </p>
        </div>

        <!-- Acknowledge Discrepancy Dialog -->
        <AcknowledgeDiscrepancyDialog
            v-model:open="isAcknowledgeDialogOpen"
            :discrepancy="selectedDiscrepancy"
            @acknowledged="handleAcknowledged"
        />
    </div>
</template>
