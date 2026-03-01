<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { computed } from 'vue';

interface Props {
    /** Audit name */
    name: string;
    /** Audit description */
    description: string;
    /** Due date in YYYY-MM-DD format */
    dueDate: string;
    /** Validation error for name field */
    nameError?: string;
    /** Validation error for description field */
    descriptionError?: string;
    /** Validation error for due_date field */
    dueDateError?: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:name': [value: string];
    'update:description': [value: string];
    'update:dueDate': [value: string];
}>();

// Computed properties for two-way binding
const nameValue = computed({
    get: () => props.name,
    set: (value: string) => emit('update:name', value),
});

const descriptionValue = computed({
    get: () => props.description,
    set: (value: string) => emit('update:description', value),
});

const dueDateValue = computed({
    get: () => props.dueDate,
    set: (value: string) => emit('update:dueDate', value),
});

/**
 * Get today's date in YYYY-MM-DD format for min date attribute
 */
const minDate = computed(() => {
    const today = new Date();
    return today.toISOString().split('T')[0];
});
</script>

<template>
    <div class="space-y-4">
        <HeadingSmall
            title="Audit Details"
            description="Enter the audit name, description, and target completion date."
        />

        <div class="grid gap-4 sm:grid-cols-2">
            <!-- Name field (required) -->
            <div class="grid gap-2 sm:col-span-2">
                <Label for="audit-name">
                    Name <span class="text-red-500">*</span>
                </Label>
                <Input
                    id="audit-name"
                    v-model="nameValue"
                    name="name"
                    type="text"
                    required
                    placeholder="Enter a descriptive audit name"
                    :aria-invalid="!!nameError"
                    :aria-describedby="
                        nameError ? 'audit-name-error' : undefined
                    "
                />
                <InputError id="audit-name-error" :message="nameError" />
            </div>

            <!-- Description field (optional) -->
            <div class="grid gap-2 sm:col-span-2">
                <Label for="audit-description"> Description </Label>
                <Textarea
                    id="audit-description"
                    v-model="descriptionValue"
                    name="description"
                    placeholder="Add additional context, instructions, or notes for this audit (optional)"
                    rows="3"
                    :aria-invalid="!!descriptionError"
                    :aria-describedby="
                        descriptionError ? 'audit-description-error' : undefined
                    "
                />
                <InputError
                    id="audit-description-error"
                    :message="descriptionError"
                />
            </div>

            <!-- Due date field (required) -->
            <div class="grid gap-2 sm:col-span-1">
                <Label for="audit-due-date">
                    Due Date <span class="text-red-500">*</span>
                </Label>
                <Input
                    id="audit-due-date"
                    v-model="dueDateValue"
                    name="due_date"
                    type="date"
                    required
                    :min="minDate"
                    :aria-invalid="!!dueDateError"
                    :aria-describedby="
                        dueDateError ? 'audit-due-date-error' : undefined
                    "
                />
                <InputError id="audit-due-date-error" :message="dueDateError" />
                <p class="text-xs text-muted-foreground">
                    Target completion date for this audit
                </p>
            </div>
        </div>
    </div>
</template>
