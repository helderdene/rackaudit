<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { PortStatusValue } from '@/types/ports';
import { computed } from 'vue';

interface Props {
    status: PortStatusValue;
    label?: string;
}

const props = withDefaults(defineProps<Props>(), {
    label: '',
});

/**
 * Map status values to Badge variants:
 * - available: success (green)
 * - connected: secondary (blue)
 * - reserved: warning (yellow)
 * - disabled: destructive (red)
 */
const variant = computed(() => {
    switch (props.status) {
        case 'available':
            return 'success';
        case 'connected':
            return 'secondary';
        case 'reserved':
            return 'warning';
        case 'disabled':
            return 'destructive';
        default:
            return 'outline';
    }
});

/**
 * Get human-readable status label
 */
const statusLabel = computed(() => {
    if (props.label) {
        return props.label;
    }
    switch (props.status) {
        case 'available':
            return 'Available';
        case 'connected':
            return 'Connected';
        case 'reserved':
            return 'Reserved';
        case 'disabled':
            return 'Disabled';
        default:
            return 'Unknown';
    }
});
</script>

<template>
    <Badge :variant="variant">
        {{ statusLabel }}
    </Badge>
</template>
