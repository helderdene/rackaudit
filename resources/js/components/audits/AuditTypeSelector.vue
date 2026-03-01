<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface TypeOption {
    value: string;
    label: string;
}

interface Props {
    modelValue: string;
    auditTypes: TypeOption[];
    error?: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

/**
 * Type descriptions that help users understand each audit type
 */
const typeDescriptions: Record<string, string> = {
    connection: 'Verify physical connections match the approved implementation file',
    inventory: 'Verify documented devices exist physically and are in correct positions',
};

/**
 * Icons for each audit type (using simple SVG paths)
 */
const typeIcons: Record<string, string> = {
    connection: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z',
    inventory: 'M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z',
};

const selectedType = computed({
    get: () => props.modelValue,
    set: (value: string) => emit('update:modelValue', value),
});

const isSelected = (type: string): boolean => {
    return selectedType.value === type;
};

const selectType = (type: string): void => {
    selectedType.value = type;
};
</script>

<template>
    <div class="space-y-3">
        <div class="grid gap-4 sm:grid-cols-2">
            <label
                v-for="type in auditTypes"
                :key="type.value"
                :for="`audit-type-${type.value}`"
                class="cursor-pointer"
            >
                <Card
                    :class="[
                        'relative transition-all duration-200',
                        'hover:border-primary/50 hover:shadow-md',
                        isSelected(type.value)
                            ? 'border-primary ring-2 ring-primary/20 shadow-md'
                            : 'border-border',
                    ]"
                >
                    <input
                        :id="`audit-type-${type.value}`"
                        v-model="selectedType"
                        type="radio"
                        name="audit-type"
                        :value="type.value"
                        class="sr-only"
                        @change="selectType(type.value)"
                    />

                    <CardHeader class="pb-2">
                        <div class="flex items-start gap-3">
                            <!-- Selection indicator -->
                            <div
                                :class="[
                                    'mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 transition-colors',
                                    isSelected(type.value)
                                        ? 'border-primary bg-primary'
                                        : 'border-muted-foreground/30',
                                ]"
                            >
                                <svg
                                    v-if="isSelected(type.value)"
                                    class="h-3 w-3 text-primary-foreground"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="3"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>
                            </div>

                            <div class="flex-1">
                                <CardTitle class="text-base">
                                    {{ type.label }} Audit
                                </CardTitle>
                            </div>

                            <!-- Type icon -->
                            <div
                                :class="[
                                    'flex h-10 w-10 shrink-0 items-center justify-center rounded-lg transition-colors',
                                    isSelected(type.value)
                                        ? 'bg-primary/10 text-primary'
                                        : 'bg-muted text-muted-foreground',
                                ]"
                            >
                                <svg
                                    v-if="type.value === 'connection'"
                                    class="h-5 w-5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"
                                    />
                                </svg>
                                <svg
                                    v-else-if="type.value === 'inventory'"
                                    class="h-5 w-5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
                                    />
                                </svg>
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent class="pt-0">
                        <CardDescription class="text-sm leading-relaxed">
                            {{ typeDescriptions[type.value] || '' }}
                        </CardDescription>
                    </CardContent>
                </Card>
            </label>
        </div>

        <!-- Error message -->
        <p v-if="error" class="text-sm text-destructive">
            {{ error }}
        </p>
    </div>
</template>
