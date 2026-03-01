<script setup lang="ts">
import { computed } from 'vue';

interface CableTypeOption {
    value: string;
    label: string;
}

interface Props {
    modelValue: string | null;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string | null): void;
}>();

/**
 * Available cable types matching CableType enum
 */
const cableTypes: CableTypeOption[] = [
    { value: 'cat5e', label: 'Cat5e' },
    { value: 'cat6', label: 'Cat6' },
    { value: 'cat6a', label: 'Cat6a' },
    { value: 'fiber_sm', label: 'Fiber SM' },
    { value: 'fiber_mm', label: 'Fiber MM' },
    { value: 'power_c13', label: 'C13' },
    { value: 'power_c14', label: 'C14' },
    { value: 'power_c19', label: 'C19' },
    { value: 'power_c20', label: 'C20' },
];

const selectedValue = computed({
    get: () => props.modelValue ?? '',
    set: (value: string) => emit('update:modelValue', value || null),
});

const selectClass = 'flex h-8 w-full rounded-md border border-input bg-transparent px-2 py-1 text-xs shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50';
</script>

<template>
    <select
        v-model="selectedValue"
        :class="selectClass"
    >
        <option value="">Cable type</option>
        <option
            v-for="type in cableTypes"
            :key="type.value"
            :value="type.value"
        >
            {{ type.label }}
        </option>
    </select>
</template>
