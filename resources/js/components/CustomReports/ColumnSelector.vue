<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Calculator } from 'lucide-vue-next';
import { computed, nextTick, ref, watchEffect } from 'vue';

// Store refs to category checkboxes for indeterminate state
const categoryCheckboxRefs = ref<Record<string, HTMLInputElement | null>>({});

/**
 * TypeScript interfaces
 */
interface ReportField {
    key: string;
    display_name: string;
    category: string;
    is_calculated?: boolean;
    data_type?: string;
}

interface Props {
    availableColumns: Record<string, ReportField[]>;
    selectedColumns: string[];
    calculatedFields?: ReportField[];
}

const props = withDefaults(defineProps<Props>(), {
    calculatedFields: () => [],
});

const emit = defineEmits<{
    (e: 'update:selectedColumns', value: string[]): void;
}>();

// Ref for managing keyboard navigation within categories
const focusedCategory = ref<string | null>(null);
const focusedFieldIndex = ref<number>(-1);

/**
 * Get the set of calculated field keys for quick lookup
 */
const calculatedFieldKeys = computed(() => {
    return new Set(props.calculatedFields.map((f) => f.key));
});

/**
 * Get sorted category names for consistent display order
 */
const sortedCategories = computed(() => {
    return Object.keys(props.availableColumns).sort((a, b) => {
        // Put 'Calculated' category last
        if (a === 'Calculated') return 1;
        if (b === 'Calculated') return -1;
        return a.localeCompare(b);
    });
});

/**
 * Count of selected columns
 */
const selectedCount = computed(() => props.selectedColumns.length);

/**
 * Total available columns count
 */
const totalColumnsCount = computed(() => {
    return Object.values(props.availableColumns).reduce(
        (sum, fields) => sum + fields.length,
        0,
    );
});

/**
 * Check if all columns in a category are selected
 */
function isCategoryAllSelected(category: string): boolean {
    const categoryFields = props.availableColumns[category] || [];
    return (
        categoryFields.length > 0 &&
        categoryFields.every((field) =>
            props.selectedColumns.includes(field.key),
        )
    );
}

/**
 * Check if some (but not all) columns in a category are selected
 */
function isCategorySomeSelected(category: string): boolean {
    const categoryFields = props.availableColumns[category] || [];
    const selectedInCategory = categoryFields.filter((field) =>
        props.selectedColumns.includes(field.key),
    );
    return (
        selectedInCategory.length > 0 &&
        selectedInCategory.length < categoryFields.length
    );
}

/**
 * Toggle column selection
 */
function toggleColumn(fieldKey: string) {
    const newSelected = [...props.selectedColumns];
    const index = newSelected.indexOf(fieldKey);

    if (index === -1) {
        newSelected.push(fieldKey);
    } else {
        newSelected.splice(index, 1);
    }

    emit('update:selectedColumns', newSelected);
}

/**
 * Select all columns in a category
 */
function selectAllInCategory(category: string) {
    const categoryFields = props.availableColumns[category] || [];
    const categoryKeys = categoryFields.map((f) => f.key);
    const newSelected = new Set([...props.selectedColumns, ...categoryKeys]);
    emit('update:selectedColumns', Array.from(newSelected));
}

/**
 * Deselect all columns in a category
 */
function deselectAllInCategory(category: string) {
    const categoryFields = props.availableColumns[category] || [];
    const categoryKeys = new Set(categoryFields.map((f) => f.key));
    const newSelected = props.selectedColumns.filter(
        (key) => !categoryKeys.has(key),
    );
    emit('update:selectedColumns', newSelected);
}

/**
 * Toggle select/deselect all in a category
 */
function toggleCategory(category: string) {
    if (isCategoryAllSelected(category)) {
        deselectAllInCategory(category);
    } else {
        selectAllInCategory(category);
    }
}

/**
 * Select all columns
 */
function selectAll() {
    const allKeys = Object.values(props.availableColumns).flatMap((fields) =>
        fields.map((f) => f.key),
    );
    emit('update:selectedColumns', allKeys);
}

/**
 * Deselect all columns
 */
function deselectAll() {
    emit('update:selectedColumns', []);
}

/**
 * Handle keyboard navigation within field list
 */
function handleFieldKeydown(
    event: KeyboardEvent,
    category: string,
    currentIndex: number,
) {
    const categoryFields = props.availableColumns[category] || [];

    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            if (currentIndex < categoryFields.length - 1) {
                focusFieldInCategory(category, currentIndex + 1);
            }
            break;
        case 'ArrowUp':
            event.preventDefault();
            if (currentIndex > 0) {
                focusFieldInCategory(category, currentIndex - 1);
            }
            break;
        case ' ':
        case 'Enter':
            event.preventDefault();
            toggleColumn(categoryFields[currentIndex].key);
            break;
    }
}

/**
 * Focus a specific field checkbox in a category
 */
function focusFieldInCategory(category: string, index: number) {
    focusedCategory.value = category;
    focusedFieldIndex.value = index;

    nextTick(() => {
        const categoryFields = props.availableColumns[category] || [];
        if (index >= 0 && index < categoryFields.length) {
            const fieldKey = categoryFields[index].key;
            const checkbox = document.getElementById(`field-${fieldKey}`);
            if (checkbox) {
                // Focus the checkbox trigger within the component
                const trigger =
                    checkbox.closest('[role="checkbox"]') || checkbox;
                (trigger as HTMLElement)?.focus();
            }
        }
    });
}

/**
 * Get aria description for a field
 */
function _getFieldAriaDescription(field: ReportField): string {
    const isCalculated = calculatedFieldKeys.value.has(field.key);
    const isSelected = props.selectedColumns.includes(field.key);
    let description = `${field.display_name} column`;
    if (isCalculated) {
        description += ', calculated field';
    }
    if (isSelected) {
        description += ', currently selected';
    }
    return description;
}

/**
 * Set category checkbox ref for indeterminate state management
 */
function setCategoryCheckboxRef(category: string, el: HTMLInputElement | null) {
    categoryCheckboxRefs.value[category] = el;
}

/**
 * Update indeterminate state for all category checkboxes
 */
watchEffect(() => {
    for (const category of sortedCategories.value) {
        const checkbox = categoryCheckboxRefs.value[category];
        if (checkbox) {
            checkbox.indeterminate = isCategorySomeSelected(category);
        }
    }
});
</script>

<template>
    <Card>
        <CardHeader
            class="flex flex-col gap-2 space-y-0 pb-2 sm:flex-row sm:items-center sm:justify-between"
        >
            <CardTitle class="text-base">Select Columns</CardTitle>
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm text-muted-foreground" aria-live="polite">
                    {{ selectedCount }} of {{ totalColumnsCount }} selected
                </span>
                <div class="flex gap-1">
                    <Button
                        v-if="selectedCount < totalColumnsCount"
                        variant="ghost"
                        size="sm"
                        @click="selectAll"
                        aria-label="Select all columns"
                    >
                        Select All
                    </Button>
                    <Button
                        v-if="selectedCount > 0"
                        variant="ghost"
                        size="sm"
                        @click="deselectAll"
                        aria-label="Deselect all columns"
                    >
                        Deselect All
                    </Button>
                </div>
            </div>
        </CardHeader>
        <CardContent>
            <div class="space-y-6" role="group" aria-label="Column selection">
                <!-- Category sections -->
                <fieldset
                    v-for="category in sortedCategories"
                    :key="category"
                    class="space-y-3"
                >
                    <!-- Category header with select all toggle -->
                    <div
                        class="flex items-center justify-between border-b pb-2"
                    >
                        <legend class="flex items-center gap-2">
                            <input
                                type="checkbox"
                                :id="`category-${category}`"
                                :ref="
                                    (el) =>
                                        setCategoryCheckboxRef(
                                            category,
                                            el as HTMLInputElement,
                                        )
                                "
                                :checked="isCategoryAllSelected(category)"
                                :aria-label="`Select all ${category} columns`"
                                class="size-4 cursor-pointer rounded border-input accent-primary"
                                @change="toggleCategory(category)"
                            />
                            <Label
                                :for="`category-${category}`"
                                class="cursor-pointer text-sm font-medium"
                            >
                                {{ category }}
                            </Label>
                            <Badge
                                variant="secondary"
                                class="ml-1 text-xs"
                                :aria-label="`${availableColumns[category]?.length || 0} columns in ${category}`"
                            >
                                {{ availableColumns[category]?.length || 0 }}
                            </Badge>
                        </legend>
                        <div
                            class="flex gap-1"
                            role="group"
                            :aria-label="`${category} category actions`"
                        >
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-6 px-2 text-xs"
                                :aria-label="`Select all columns in ${category}`"
                                @click="selectAllInCategory(category)"
                            >
                                All
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-6 px-2 text-xs"
                                :aria-label="`Deselect all columns in ${category}`"
                                @click="deselectAllInCategory(category)"
                            >
                                None
                            </Button>
                        </div>
                    </div>

                    <!-- Field checkboxes -->
                    <div
                        class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3"
                        role="group"
                        :aria-label="`${category} column options`"
                    >
                        <div
                            v-for="(field, fieldIndex) in availableColumns[
                                category
                            ]"
                            :key="field.key"
                            class="flex items-center gap-2"
                        >
                            <input
                                type="checkbox"
                                :id="`field-${field.key}`"
                                :checked="selectedColumns.includes(field.key)"
                                :aria-describedby="
                                    calculatedFieldKeys.has(field.key)
                                        ? `field-${field.key}-calc`
                                        : undefined
                                "
                                class="size-4 cursor-pointer rounded border-input accent-primary"
                                @change="toggleColumn(field.key)"
                                @keydown="
                                    (e: KeyboardEvent) =>
                                        handleFieldKeydown(
                                            e,
                                            category,
                                            fieldIndex,
                                        )
                                "
                            />
                            <Label
                                :for="`field-${field.key}`"
                                class="flex cursor-pointer items-center gap-1 text-sm"
                            >
                                {{ field.display_name }}
                                <!-- Calculated field indicator -->
                                <Calculator
                                    v-if="calculatedFieldKeys.has(field.key)"
                                    class="size-3 text-amber-500"
                                    aria-hidden="true"
                                />
                                <span
                                    v-if="calculatedFieldKeys.has(field.key)"
                                    :id="`field-${field.key}-calc`"
                                    class="sr-only"
                                >
                                    (calculated field)
                                </span>
                            </Label>
                        </div>
                    </div>
                </fieldset>

                <!-- Legend for calculated fields -->
                <div
                    v-if="calculatedFields.length > 0"
                    class="flex items-center gap-2 border-t pt-4 text-sm text-muted-foreground"
                    aria-hidden="true"
                >
                    <Calculator class="size-4 text-amber-500" />
                    <span
                        >Indicates a calculated field (value computed from other
                        data)</span
                    >
                </div>

                <!-- Validation message -->
                <p
                    v-if="selectedCount === 0"
                    class="text-center text-sm text-amber-600 dark:text-amber-500"
                    role="alert"
                    aria-live="assertive"
                >
                    Please select at least one column to continue.
                </p>
            </div>
        </CardContent>
    </Card>
</template>
