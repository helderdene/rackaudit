<script setup lang="ts">
import { Badge, type BadgeVariants } from '@/components/ui/badge';
import { computed } from 'vue';

interface Props {
    action: 'created' | 'updated' | 'deleted' | 'restored';
}

const props = defineProps<Props>();

const variantMap: Record<string, BadgeVariants['variant']> = {
    created: 'success',
    updated: 'warning',
    deleted: 'destructive',
    restored: 'info',
};

const labelMap: Record<string, string> = {
    created: 'Created',
    updated: 'Updated',
    deleted: 'Deleted',
    restored: 'Restored',
};

const variant = computed(() => variantMap[props.action] || 'default');
const label = computed(() => labelMap[props.action] || props.action);
</script>

<template>
    <Badge :variant="variant">{{ label }}</Badge>
</template>
