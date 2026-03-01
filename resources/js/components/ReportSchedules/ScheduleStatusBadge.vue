<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import {
    AlertTriangle,
    CheckCircle2,
    PauseCircle,
    XCircle,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    isEnabled: boolean;
    lastRunStatus?: string | null;
    consecutiveFailures?: number;
}

const props = withDefaults(defineProps<Props>(), {
    lastRunStatus: null,
    consecutiveFailures: 0,
});

/**
 * Determine the status state based on enabled/disabled and failure count
 */
const statusState = computed<'active' | 'disabled' | 'failed' | 'warning'>(
    () => {
        if (!props.isEnabled) {
            return 'disabled';
        }

        if (props.consecutiveFailures >= 3) {
            return 'failed';
        }

        if (props.consecutiveFailures > 0) {
            return 'warning';
        }

        if (props.lastRunStatus === 'failed') {
            return 'warning';
        }

        return 'active';
    },
);

/**
 * Badge variant based on status state
 */
const badgeVariant = computed(() => {
    switch (statusState.value) {
        case 'active':
            return 'default';
        case 'disabled':
            return 'secondary';
        case 'failed':
            return 'destructive';
        case 'warning':
            return 'outline';
        default:
            return 'secondary';
    }
});

/**
 * Badge class overrides for specific colors
 */
const badgeClass = computed(() => {
    switch (statusState.value) {
        case 'active':
            return 'bg-green-100 text-green-800 hover:bg-green-100 dark:bg-green-900/30 dark:text-green-400';
        case 'warning':
            return 'border-amber-500 bg-amber-50 text-amber-700 hover:bg-amber-50 dark:bg-amber-900/30 dark:text-amber-400 dark:border-amber-700';
        default:
            return '';
    }
});

/**
 * Status label text
 */
const statusLabel = computed(() => {
    switch (statusState.value) {
        case 'active':
            return 'Active';
        case 'disabled':
            return 'Disabled';
        case 'failed':
            return 'Failed';
        case 'warning':
            return 'Warning';
        default:
            return 'Unknown';
    }
});

/**
 * Icon component based on status
 */
const statusIcon = computed(() => {
    switch (statusState.value) {
        case 'active':
            return CheckCircle2;
        case 'disabled':
            return PauseCircle;
        case 'failed':
            return XCircle;
        case 'warning':
            return AlertTriangle;
        default:
            return PauseCircle;
    }
});

/**
 * Show failure count if there are failures
 */
const showFailureCount = computed(() => {
    return props.consecutiveFailures > 0;
});
</script>

<template>
    <div class="flex items-center gap-2">
        <Badge :variant="badgeVariant" :class="badgeClass">
            <component :is="statusIcon" class="mr-1 h-3 w-3" />
            {{ statusLabel }}
        </Badge>
        <span
            v-if="showFailureCount"
            class="text-xs text-muted-foreground"
            :title="`${consecutiveFailures} consecutive failure(s)`"
        >
            ({{ consecutiveFailures }} failure{{
                consecutiveFailures !== 1 ? 's' : ''
            }})
        </span>
    </div>
</template>
