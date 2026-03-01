<script setup lang="ts">
/**
 * TimePeriodFilter Component
 *
 * Dropdown filter for selecting time periods for dashboard charts.
 * Emits the selected value on change for use by parent components.
 */

import { computed } from 'vue';

interface TimePeriodOption {
    value: string;
    label: string;
}

interface Props {
    modelValue: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const timePeriodOptions: TimePeriodOption[] = [
    { value: '7_days', label: 'Last 7 days' },
    { value: '30_days', label: 'Last 30 days' },
    { value: '90_days', label: 'Last 90 days' },
];

const selectedValue = computed({
    get: () => props.modelValue,
    set: (value: string) => emit('update:modelValue', value),
});

// Common select styling matching Dashboard datacenter filter with touch-friendly sizing
// Added min-h-10 for 40px touch target and md:min-w-36 for tablet landscape readability
const selectClass =
    'flex h-9 min-h-10 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring dark:border-input dark:bg-transparent dark:text-foreground';
</script>

<template>
    <select
        v-model="selectedValue"
        :class="selectClass"
        class="w-full sm:w-auto md:min-w-36"
        aria-label="Filter by time period"
    >
        <option v-for="option in timePeriodOptions" :key="option.value" :value="option.value">
            {{ option.label }}
        </option>
    </select>
</template>
