<script setup lang="ts">
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { computed } from 'vue';

interface ScopeTypeOption {
    value: string;
    label: string;
}

interface Props {
    modelValue: string;
    scopeTypes: ScopeTypeOption[];
    error?: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

/**
 * Scope type descriptions that help users understand each scope level
 */
const scopeDescriptions: Record<string, string> = {
    datacenter: 'Audit all racks and devices within the entire datacenter',
    room: 'Audit all racks and devices within a specific room',
    racks: 'Audit specific racks and optionally selected devices',
};

const selectedScope = computed({
    get: () => props.modelValue,
    set: (value: string) => emit('update:modelValue', value),
});

const isSelected = (scope: string): boolean => {
    return selectedScope.value === scope;
};

const selectScope = (scope: string): void => {
    selectedScope.value = scope;
};
</script>

<template>
    <div class="space-y-3">
        <div class="grid gap-3 sm:grid-cols-3">
            <label
                v-for="scope in scopeTypes"
                :key="scope.value"
                :for="`scope-type-${scope.value}`"
                class="cursor-pointer"
            >
                <Card
                    :class="[
                        'relative h-full transition-all duration-200',
                        'hover:border-primary/50 hover:shadow-md',
                        isSelected(scope.value)
                            ? 'border-primary shadow-md ring-2 ring-primary/20'
                            : 'border-border',
                    ]"
                >
                    <input
                        :id="`scope-type-${scope.value}`"
                        v-model="selectedScope"
                        type="radio"
                        name="scope-type"
                        :value="scope.value"
                        class="sr-only"
                        @change="selectScope(scope.value)"
                    />

                    <CardHeader class="pb-2">
                        <div class="flex items-start gap-3">
                            <!-- Selection indicator -->
                            <div
                                :class="[
                                    'mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 transition-colors',
                                    isSelected(scope.value)
                                        ? 'border-primary bg-primary'
                                        : 'border-muted-foreground/30',
                                ]"
                            >
                                <svg
                                    v-if="isSelected(scope.value)"
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
                                <CardTitle class="text-sm font-medium">
                                    {{ scope.label }}
                                </CardTitle>
                            </div>

                            <!-- Scope icon -->
                            <div
                                :class="[
                                    'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg transition-colors',
                                    isSelected(scope.value)
                                        ? 'bg-primary/10 text-primary'
                                        : 'bg-muted text-muted-foreground',
                                ]"
                            >
                                <!-- Datacenter icon -->
                                <svg
                                    v-if="scope.value === 'datacenter'"
                                    class="h-4 w-4"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                                    />
                                </svg>
                                <!-- Room icon -->
                                <svg
                                    v-else-if="scope.value === 'room'"
                                    class="h-4 w-4"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"
                                    />
                                </svg>
                                <!-- Racks icon -->
                                <svg
                                    v-else-if="scope.value === 'racks'"
                                    class="h-4 w-4"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"
                                    />
                                </svg>
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent class="pt-0">
                        <CardDescription class="text-xs leading-relaxed">
                            {{ scopeDescriptions[scope.value] || '' }}
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
