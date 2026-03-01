<script setup lang="ts">
import FindingCategoryController from '@/actions/App/Http/Controllers/FindingCategoryController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { FilterOption } from '@/types/finding';
import { AlertCircle, Loader2, Plus, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    modelValue: number | null;
    categoryOptions: FilterOption[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: number | null): void;
}>();

// State
const showCreateForm = ref(false);
const newCategoryName = ref('');
const isCreating = ref(false);
const createError = ref('');

// Local categories list that includes newly created categories
const localCategories = ref<FilterOption[]>([...props.categoryOptions]);

// Update local categories when props change
const allCategories = computed(() => {
    // Start with props categories, then add any locally created ones that aren't in props
    const propCategoryIds = new Set(props.categoryOptions.map((c) => c.value));
    const newCategories = localCategories.value.filter(
        (c) => !propCategoryIds.has(c.value),
    );
    return [...props.categoryOptions, ...newCategories];
});

// Handle category selection change
const handleChange = (event: Event) => {
    const target = event.target as HTMLSelectElement;
    const value = target.value;

    if (value === 'create-new') {
        showCreateForm.value = true;
        // Reset select to current value
        target.value = props.modelValue?.toString() || '';
        return;
    }

    emit('update:modelValue', value ? parseInt(value, 10) : null);
};

// Create new category
const createCategory = async () => {
    if (!newCategoryName.value.trim()) {
        createError.value = 'Category name is required.';
        return;
    }

    isCreating.value = true;
    createError.value = '';

    try {
        const response = await fetch(FindingCategoryController.store.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                name: newCategoryName.value.trim(),
            }),
        });

        const data = await response.json();

        if (!response.ok) {
            if (data.errors?.name) {
                createError.value =
                    data.errors.name[0] || 'Category name already exists.';
            } else if (data.message) {
                createError.value = data.message;
            } else {
                createError.value =
                    'Failed to create category. Please try again.';
            }
            return;
        }

        // Add new category to local list
        const newCategory: FilterOption = {
            value: data.data.id,
            label: data.data.name,
        };
        localCategories.value.push(newCategory);

        // Select the new category
        emit('update:modelValue', data.data.id);

        // Reset form
        newCategoryName.value = '';
        showCreateForm.value = false;
    } catch {
        createError.value = 'Network error. Please try again.';
    } finally {
        isCreating.value = false;
    }
};

// Cancel category creation
const cancelCreate = () => {
    newCategoryName.value = '';
    showCreateForm.value = false;
    createError.value = '';
};

// Get CSRF token from cookie
const getCsrfToken = (): string => {
    const name = 'XSRF-TOKEN=';
    const decodedCookie = decodeURIComponent(document.cookie);
    const cookies = decodedCookie.split(';');
    for (let cookie of cookies) {
        cookie = cookie.trim();
        if (cookie.indexOf(name) === 0) {
            return cookie.substring(name.length, cookie.length);
        }
    }
    return '';
};

// Common select styling
const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';
</script>

<template>
    <div class="space-y-2">
        <!-- Category Select Dropdown -->
        <select
            :value="modelValue?.toString() || ''"
            :class="selectClass"
            @change="handleChange"
        >
            <option value="">No Category</option>
            <option
                v-for="option in allCategories"
                :key="option.value"
                :value="option.value"
            >
                {{ option.label }}
            </option>
            <option value="create-new" class="font-medium text-primary">
                + Create new category...
            </option>
        </select>

        <!-- Inline Create Form -->
        <div v-if="showCreateForm" class="space-y-3 rounded-lg border p-3">
            <div class="flex items-center gap-2">
                <Input
                    v-model="newCategoryName"
                    placeholder="New category name..."
                    class="flex-1"
                    @keyup.enter="createCategory"
                    @keyup.escape="cancelCreate"
                    :disabled="isCreating"
                />
                <Button
                    size="sm"
                    @click="createCategory"
                    :disabled="isCreating || !newCategoryName.trim()"
                >
                    <Loader2
                        v-if="isCreating"
                        class="mr-1 size-4 animate-spin"
                    />
                    <Plus v-else class="mr-1 size-4" />
                    {{ isCreating ? 'Creating...' : 'Create' }}
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    @click="cancelCreate"
                    :disabled="isCreating"
                >
                    <X class="size-4" />
                </Button>
            </div>
            <div
                v-if="createError"
                class="flex items-center gap-1 text-xs text-destructive"
            >
                <AlertCircle class="size-3" />
                {{ createError }}
            </div>
        </div>
    </div>
</template>
