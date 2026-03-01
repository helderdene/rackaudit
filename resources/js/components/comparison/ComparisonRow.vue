<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    AlertTriangle,
    CheckCircle,
    XCircle,
    AlertCircle,
    ArrowRightLeft,
} from 'lucide-vue-next';
import type { ComparisonResultData } from '@/types/comparison';

interface Props {
    comparison: ComparisonResultData;
    rowNumber: number;
}

const props = defineProps<Props>();

/**
 * Get the status badge styling based on discrepancy type
 */
function getStatusBadgeClass(): string {
    if (props.comparison.is_acknowledged) {
        return 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400';
    }

    switch (props.comparison.discrepancy_type) {
        case 'matched':
            return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
        case 'missing':
            return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
        case 'unexpected':
            return 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400';
        case 'mismatched':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';
        case 'conflicting':
            return 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400';
        default:
            return '';
    }
}

/**
 * Get the icon component for the discrepancy type
 */
function getStatusIcon(): typeof CheckCircle | typeof XCircle | typeof AlertTriangle | typeof AlertCircle | typeof ArrowRightLeft {
    switch (props.comparison.discrepancy_type) {
        case 'matched':
            return CheckCircle;
        case 'missing':
            return XCircle;
        case 'unexpected':
            return AlertCircle;
        case 'mismatched':
            return ArrowRightLeft;
        case 'conflicting':
            return AlertTriangle;
        default:
            return AlertCircle;
    }
}

/**
 * Format the destination port display with actual value in parentheses when different.
 * Example: "Port-A1 (Actual: Port-B2)"
 */
function formatDestPortDisplay(): string {
    const expected = props.comparison.dest_port;
    const actual = props.comparison.actual_dest_port;

    if (!expected && !actual) {
        return '-';
    }

    // For unexpected connections, only show the actual
    if (props.comparison.discrepancy_type === 'unexpected' && actual) {
        return actual.label;
    }

    // For missing connections, only show the expected
    if (props.comparison.discrepancy_type === 'missing' && expected) {
        return expected.label;
    }

    // For mismatched, show expected with actual in parentheses
    if (props.comparison.discrepancy_type === 'mismatched' && expected && actual) {
        if (expected.id !== actual.id) {
            return `${expected.label}`;
        }
    }

    // Default: show expected port label
    return expected?.label ?? actual?.label ?? '-';
}

/**
 * Check if we should show the actual value in parentheses
 */
function showActualDifference(): boolean {
    const expected = props.comparison.dest_port;
    const actual = props.comparison.actual_dest_port;

    return (
        props.comparison.discrepancy_type === 'mismatched' &&
        expected !== null &&
        actual !== null &&
        expected.id !== actual.id
    );
}

/**
 * Get the cable type to display
 */
function getCableTypeDisplay(): string {
    const expectedCable = props.comparison.expected_connection?.cable_type_label;
    const actualCable = props.comparison.actual_connection?.cable_type_label;

    // For matched or mismatched, show actual if available
    if (actualCable && (props.comparison.discrepancy_type === 'matched' || props.comparison.discrepancy_type === 'mismatched')) {
        return actualCable;
    }

    // For unexpected, show actual
    if (props.comparison.discrepancy_type === 'unexpected' && actualCable) {
        return actualCable;
    }

    // For missing, show expected
    if (props.comparison.discrepancy_type === 'missing' && expectedCable) {
        return expectedCable;
    }

    return expectedCable ?? actualCable ?? '-';
}

/**
 * Check if the row has conflicts to display warning
 */
function hasConflicts(): boolean {
    return (
        props.comparison.discrepancy_type === 'conflicting' &&
        props.comparison.conflict_info !== null
    );
}

/**
 * Get the destination device display with actual in parentheses when different
 */
function formatDestDeviceDisplay(): string {
    const expected = props.comparison.dest_device;

    if (!expected) {
        // For unexpected, try to get device from actual connection
        if (props.comparison.actual_dest_port?.type) {
            return props.comparison.actual_dest_port?.label ?? '-';
        }
        return '-';
    }

    return expected.name;
}

const StatusIcon = getStatusIcon();
</script>

<template>
    <!-- Row Number -->
    <td class="px-3 py-3 text-muted-foreground">
        {{ rowNumber }}
    </td>

    <!-- Source Device -->
    <td class="px-3 py-3">
        <div class="flex items-center gap-2">
            <span v-if="comparison.source_device?.name" class="font-medium">
                {{ comparison.source_device.name }}
            </span>
            <span v-else class="text-muted-foreground">-</span>

            <!-- Conflict warning icon -->
            <TooltipProvider v-if="hasConflicts()" :delay-duration="0">
                <Tooltip>
                    <TooltipTrigger as-child>
                        <AlertTriangle class="size-4 text-purple-600 dark:text-purple-400" />
                    </TooltipTrigger>
                    <TooltipContent class="max-w-xs">
                        <p class="font-medium">Conflicting Expectations</p>
                        <ul class="mt-1 text-xs">
                            <li
                                v-for="file in comparison.conflict_info?.conflicting_files"
                                :key="file.file_id"
                            >
                                {{ file.file_name }}: {{ file.dest_port_label }}
                            </li>
                        </ul>
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>
        </div>
    </td>

    <!-- Source Port -->
    <td class="px-3 py-3">
        <span v-if="comparison.source_port?.label">
            {{ comparison.source_port.label }}
        </span>
        <span v-else class="text-muted-foreground">-</span>
    </td>

    <!-- Dest Device -->
    <td class="px-3 py-3">
        <span v-if="comparison.dest_device?.name" class="font-medium">
            {{ comparison.dest_device.name }}
        </span>
        <span v-else-if="comparison.discrepancy_type === 'unexpected'" class="font-medium">
            {{ comparison.actual_dest_port ? 'Unknown Device' : '-' }}
        </span>
        <span v-else class="text-muted-foreground">-</span>
    </td>

    <!-- Dest Port -->
    <td class="px-3 py-3">
        <div class="flex flex-col">
            <span>{{ formatDestPortDisplay() }}</span>

            <!-- Show actual value when different (for mismatched) -->
            <span v-if="showActualDifference()" class="text-xs text-amber-600 dark:text-amber-400">
                (Actual: {{ comparison.actual_dest_port?.label }})
            </span>
        </div>
    </td>

    <!-- Cable Type -->
    <td class="px-3 py-3 text-muted-foreground">
        {{ getCableTypeDisplay() }}
    </td>

    <!-- Status -->
    <td class="px-3 py-3">
        <div class="flex items-center gap-2">
            <Badge :class="getStatusBadgeClass()">
                <component :is="StatusIcon" class="mr-1 size-3" />
                {{ comparison.discrepancy_type_label }}
            </Badge>

            <!-- Acknowledged badge -->
            <Badge
                v-if="comparison.is_acknowledged"
                variant="secondary"
                class="text-xs"
            >
                <TooltipProvider :delay-duration="0">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <span class="flex items-center gap-1">
                                <CheckCircle class="size-3" />
                                Ack'd
                            </span>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p class="font-medium">Acknowledged</p>
                            <p v-if="comparison.acknowledgment?.notes" class="mt-1 text-xs">
                                {{ comparison.acknowledgment.notes }}
                            </p>
                            <p v-if="comparison.acknowledgment?.acknowledged_by_name" class="mt-1 text-xs text-muted-foreground">
                                By: {{ comparison.acknowledgment.acknowledged_by_name }}
                            </p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </Badge>
        </div>
    </td>
</template>
