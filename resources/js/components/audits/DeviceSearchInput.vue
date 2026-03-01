<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Search, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Props {
    modelValue?: string;
    placeholder?: string;
    debounceMs?: number;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: '',
    placeholder: 'Search by device name, asset tag, or serial number...',
    debounceMs: 300,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
    (e: 'search', query: string): void;
}>();

// Local search value
const localValue = ref(props.modelValue);

// Debounce timer
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

// Sync local value with prop
watch(
    () => props.modelValue,
    (newValue) => {
        localValue.value = newValue;
    },
);

/**
 * Handle input change with debounce
 */
function handleInput(event: Event): void {
    const value = (event.target as HTMLInputElement).value;
    localValue.value = value;
    emit('update:modelValue', value);

    // Debounce the search emission
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    debounceTimer = setTimeout(() => {
        emit('search', value);
    }, props.debounceMs);
}

/**
 * Handle immediate search (on Enter key or button click)
 */
function handleSearch(): void {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }
    emit('search', localValue.value);
}

/**
 * Clear search input
 */
function handleClear(): void {
    localValue.value = '';
    emit('update:modelValue', '');
    emit('search', '');
}

/**
 * Handle Enter key press
 */
function handleKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter') {
        handleSearch();
    }
    if (event.key === 'Escape' && localValue.value) {
        handleClear();
    }
}
</script>

<template>
    <div class="relative flex items-center gap-2">
        <div class="relative flex-1">
            <Search
                class="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                :value="localValue"
                type="text"
                :placeholder="placeholder"
                class="pr-8 pl-9"
                @input="handleInput"
                @keydown="handleKeydown"
            />
            <button
                v-if="localValue"
                type="button"
                class="absolute top-1/2 right-2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                @click="handleClear"
            >
                <X class="size-4" />
                <span class="sr-only">Clear search</span>
            </button>
        </div>
        <Button
            variant="outline"
            size="sm"
            class="hidden sm:flex"
            @click="handleSearch"
        >
            Search
        </Button>
    </div>
</template>
