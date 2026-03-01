<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    CheckCircle,
    XCircle,
    AlertTriangle,
    Lock,
    ClipboardCheck,
    Minus,
    Plus,
    AlertCircle,
    RefreshCw,
} from 'lucide-vue-next';

interface VerificationData {
    id: number;
    comparison_status: string;
    comparison_status_label: string;
    verification_status: string;
    verification_status_label: string;
    discrepancy_type: string | null;
    discrepancy_type_label: string | null;
    source_device: {
        id: number;
        name: string;
        asset_tag: string | null;
    } | null;
    source_port: {
        id: number;
        label: string;
        type: string | null;
        type_label: string | null;
    } | null;
    dest_device: {
        id: number;
        name: string;
        asset_tag: string | null;
    } | null;
    dest_port: {
        id: number;
        label: string;
        type: string | null;
        type_label: string | null;
    } | null;
    expected_connection: {
        id: number;
        row_number: number;
    } | null;
    actual_connection: {
        id: number;
    } | null;
    row_number: number | null;
    notes: string | null;
    verified_by: {
        id: number;
        name: string;
    } | null;
    verified_at: string | null;
    locked_by: {
        id: number;
        name: string;
    } | null;
    locked_at: string | null;
    is_locked: boolean;
}

interface DiscrepancyTypeOption {
    value: string;
    label: string;
}

interface Props {
    verifications: VerificationData[];
    selectedIds: Set<number>;
    discrepancyTypes: DiscrepancyTypeOption[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'toggle-selection', id: number): void;
    (e: 'toggle-all'): void;
    (e: 'open-action', verification: VerificationData): void;
}>();

// Computed
const selectableVerifications = computed(() =>
    props.verifications.filter((v) => v.verification_status === 'pending' && !v.is_locked)
);

const allSelected = computed(() => {
    if (selectableVerifications.value.length === 0) return false;
    return selectableVerifications.value.every((v) => props.selectedIds.has(v.id));
});

const someSelected = computed(
    () => props.selectedIds.size > 0 && !allSelected.value
);

/**
 * Get row classes based on comparison status
 */
function getRowClasses(verification: VerificationData): string {
    // Already verified - use subtle green
    if (verification.verification_status === 'verified') {
        return 'bg-green-50/50 dark:bg-green-900/10';
    }

    // Already marked as discrepant - use subtle red
    if (verification.verification_status === 'discrepant') {
        return 'bg-red-50/50 dark:bg-red-900/10';
    }

    // Locked by another user - gray out
    if (verification.is_locked) {
        return 'bg-muted/50 opacity-75';
    }

    // Based on comparison status
    switch (verification.comparison_status) {
        case 'matched':
            return 'bg-green-50/30 dark:bg-green-900/10 border-l-4 border-l-green-500';
        case 'mismatched':
            return 'bg-yellow-50/30 dark:bg-yellow-900/10 border-l-4 border-l-yellow-500';
        case 'missing':
        case 'unexpected':
            return 'bg-red-50/30 dark:bg-red-900/10 border-l-4 border-l-red-500';
        case 'conflicting':
            return 'bg-purple-50/30 dark:bg-purple-900/10 border-l-4 border-l-purple-500';
        default:
            return '';
    }
}

/**
 * Get comparison status icon
 */
function getComparisonIcon(status: string) {
    switch (status) {
        case 'matched':
            return CheckCircle;
        case 'mismatched':
            return RefreshCw;
        case 'missing':
            return Minus;
        case 'unexpected':
            return Plus;
        case 'conflicting':
            return AlertCircle;
        default:
            return AlertTriangle;
    }
}

/**
 * Get comparison status color class
 */
function getComparisonColorClass(status: string): string {
    switch (status) {
        case 'matched':
            return 'text-green-600 dark:text-green-400';
        case 'mismatched':
            return 'text-yellow-600 dark:text-yellow-400';
        case 'missing':
        case 'unexpected':
            return 'text-red-600 dark:text-red-400';
        case 'conflicting':
            return 'text-purple-600 dark:text-purple-400';
        default:
            return 'text-gray-600 dark:text-gray-400';
    }
}

/**
 * Get verification status badge variant
 */
function getVerificationBadgeVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'verified':
            return 'default';
        case 'discrepant':
            return 'destructive';
        default:
            return 'outline';
    }
}

/**
 * Format verified at date
 */
function formatDate(dateString: string | null): string {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

/**
 * Check if verification can be acted upon
 */
function canAct(verification: VerificationData): boolean {
    return verification.verification_status === 'pending' && !verification.is_locked;
}
</script>

<template>
    <div class="overflow-hidden rounded-lg border">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b bg-muted/50">
                    <tr>
                        <th class="h-10 w-10 px-2">
                            <Checkbox
                                :checked="allSelected"
                                :indeterminate="someSelected"
                                @update:checked="emit('toggle-all')"
                            />
                        </th>
                        <th class="h-10 w-16 px-3 text-left font-medium text-muted-foreground">Row</th>
                        <th class="h-10 px-3 text-left font-medium text-muted-foreground">Source Device</th>
                        <th class="h-10 px-3 text-left font-medium text-muted-foreground">Source Port</th>
                        <th class="h-10 px-3 text-left font-medium text-muted-foreground">Dest Device</th>
                        <th class="h-10 px-3 text-left font-medium text-muted-foreground">Dest Port</th>
                        <th class="h-10 w-32 px-3 text-left font-medium text-muted-foreground">Comparison</th>
                        <th class="h-10 w-28 px-3 text-left font-medium text-muted-foreground">Status</th>
                        <th class="h-10 w-28 px-3 text-left font-medium text-muted-foreground">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="verification in verifications"
                        :key="verification.id"
                        class="border-b transition-colors hover:bg-muted/50 last:border-b-0"
                        :class="getRowClasses(verification)"
                    >
                        <!-- Checkbox -->
                        <td class="px-2 py-3">
                            <Checkbox
                                :checked="selectedIds.has(verification.id)"
                                :disabled="!canAct(verification)"
                                @update:checked="emit('toggle-selection', verification.id)"
                            />
                        </td>

                        <!-- Row Number -->
                        <td class="px-3 py-3 text-muted-foreground">
                            {{ verification.row_number ?? '-' }}
                        </td>

                        <!-- Source Device -->
                        <td class="px-3 py-3">
                            <div class="flex flex-col">
                                <span v-if="verification.source_device?.name" class="font-medium">
                                    {{ verification.source_device.name }}
                                </span>
                                <span v-else class="text-muted-foreground">-</span>
                                <span
                                    v-if="verification.source_device?.asset_tag"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ verification.source_device.asset_tag }}
                                </span>
                            </div>
                        </td>

                        <!-- Source Port -->
                        <td class="px-3 py-3">
                            <span v-if="verification.source_port?.label">
                                {{ verification.source_port.label }}
                            </span>
                            <span v-else class="text-muted-foreground">-</span>
                        </td>

                        <!-- Dest Device -->
                        <td class="px-3 py-3">
                            <div class="flex flex-col">
                                <span v-if="verification.dest_device?.name" class="font-medium">
                                    {{ verification.dest_device.name }}
                                </span>
                                <span v-else class="text-muted-foreground">-</span>
                                <span
                                    v-if="verification.dest_device?.asset_tag"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ verification.dest_device.asset_tag }}
                                </span>
                            </div>
                        </td>

                        <!-- Dest Port -->
                        <td class="px-3 py-3">
                            <span v-if="verification.dest_port?.label">
                                {{ verification.dest_port.label }}
                            </span>
                            <span v-else class="text-muted-foreground">-</span>
                        </td>

                        <!-- Comparison Result -->
                        <td class="px-3 py-3">
                            <div class="flex items-center gap-2">
                                <component
                                    :is="getComparisonIcon(verification.comparison_status)"
                                    class="size-4"
                                    :class="getComparisonColorClass(verification.comparison_status)"
                                />
                                <span :class="getComparisonColorClass(verification.comparison_status)">
                                    {{ verification.comparison_status_label }}
                                </span>
                            </div>
                        </td>

                        <!-- Verification Status -->
                        <td class="px-3 py-3">
                            <div class="flex flex-col gap-1">
                                <Badge :variant="getVerificationBadgeVariant(verification.verification_status)">
                                    {{ verification.verification_status_label }}
                                </Badge>
                                <!-- Show lock indicator -->
                                <TooltipProvider v-if="verification.is_locked" :delay-duration="0">
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <span class="flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400">
                                                <Lock class="size-3" />
                                                Locked
                                            </span>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>Locked by {{ verification.locked_by?.name }}</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                                <!-- Show verified by info -->
                                <span
                                    v-else-if="verification.verified_by"
                                    class="text-xs text-muted-foreground"
                                >
                                    by {{ verification.verified_by.name }}
                                </span>
                            </div>
                        </td>

                        <!-- Actions -->
                        <td class="px-3 py-3">
                            <div v-if="canAct(verification)">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="emit('open-action', verification)"
                                >
                                    <ClipboardCheck class="mr-1 size-3.5" />
                                    Verify
                                </Button>
                            </div>
                            <TooltipProvider v-else-if="verification.is_locked" :delay-duration="0">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <span class="text-xs text-muted-foreground">
                                            Locked by {{ verification.locked_by?.name }}
                                        </span>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Wait for this user to finish or for the lock to expire (5 min)</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                            <TooltipProvider v-else-if="verification.verification_status !== 'pending'" :delay-duration="0">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <span class="flex items-center gap-1 text-xs text-muted-foreground">
                                            <CheckCircle class="size-3" />
                                            {{ formatDate(verification.verified_at) }}
                                        </span>
                                    </TooltipTrigger>
                                    <TooltipContent v-if="verification.notes">
                                        <p class="max-w-xs">{{ verification.notes }}</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
