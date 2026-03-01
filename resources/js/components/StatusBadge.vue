<script setup lang="ts">
import { computed } from 'vue';
import { Badge, type BadgeVariants } from '@/components/ui/badge';

interface Props {
    status: 'active' | 'inactive' | 'suspended';
}

const props = defineProps<Props>();

const variantMap: Record<string, BadgeVariants['variant']> = {
    active: 'success',
    inactive: 'warning',
    suspended: 'destructive',
};

const labelMap: Record<string, string> = {
    active: 'Active',
    inactive: 'Inactive',
    suspended: 'Suspended',
};

const variant = computed(() => variantMap[props.status] || 'default');
const label = computed(() => labelMap[props.status] || props.status);
</script>

<template>
    <Badge :variant="variant">{{ label }}</Badge>
</template>
