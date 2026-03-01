<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Plus, Trash2 } from 'lucide-vue-next';

interface Props {
    modelValue: Record<string, string>;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:modelValue': [value: Record<string, string>];
}>();

// Convert object to array of key-value pairs for editing
interface KeyValuePair {
    id: number;
    key: string;
    value: string;
}

let nextId = 0;

const pairs = ref<KeyValuePair[]>([]);

// Flag to prevent re-initialization when we emit changes
let isEmitting = false;

// Initialize pairs from modelValue
const initializePairs = () => {
    const entries = Object.entries(props.modelValue || {});
    if (entries.length === 0) {
        pairs.value = [];
    } else {
        pairs.value = entries.map(([key, value]) => ({
            id: nextId++,
            key,
            value: String(value),
        }));
    }
};

// Initialize on mount
onMounted(() => {
    initializePairs();
});

// Watch for external changes to modelValue (skip if we caused the change)
watch(() => props.modelValue, () => {
    if (isEmitting) {
        isEmitting = false;
        return;
    }
    initializePairs();
}, { deep: true });

// Convert pairs back to object and emit
const updateModelValue = () => {
    const result: Record<string, string> = {};
    for (const pair of pairs.value) {
        if (pair.key.trim()) {
            result[pair.key.trim()] = pair.value;
        }
    }
    isEmitting = true;
    emit('update:modelValue', result);
};

// Add new pair
const addPair = () => {
    pairs.value.push({
        id: nextId++,
        key: '',
        value: '',
    });
};

// Remove pair
const removePair = (id: number) => {
    const index = pairs.value.findIndex(p => p.id === id);
    if (index !== -1) {
        pairs.value.splice(index, 1);
        updateModelValue();
    }
};

// Update key
const updateKey = (id: number, newKey: string) => {
    const pair = pairs.value.find(p => p.id === id);
    if (pair) {
        pair.key = newKey;
        updateModelValue();
    }
};

// Update value
const updateValue = (id: number, newValue: string) => {
    const pair = pairs.value.find(p => p.id === id);
    if (pair) {
        pair.value = newValue;
        updateModelValue();
    }
};
</script>

<template>
    <div class="space-y-3">
        <!-- Existing pairs -->
        <div
            v-for="pair in pairs"
            :key="pair.id"
            class="flex items-center gap-2"
        >
            <Input
                :model-value="pair.key"
                type="text"
                placeholder="Key (e.g., CPU)"
                class="flex-1"
                @update:model-value="updateKey(pair.id, $event as string)"
            />
            <Input
                :model-value="pair.value"
                type="text"
                placeholder="Value (e.g., 32 cores)"
                class="flex-1"
                @update:model-value="updateValue(pair.id, $event as string)"
            />
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="shrink-0 text-muted-foreground hover:text-destructive"
                @click="removePair(pair.id)"
            >
                <Trash2 class="size-4" />
            </Button>
        </div>

        <!-- Empty state -->
        <div
            v-if="pairs.length === 0"
            class="rounded-md border border-dashed p-4 text-center text-sm text-muted-foreground"
        >
            No specifications added yet. Click the button below to add one.
        </div>

        <!-- Add button -->
        <Button
            type="button"
            variant="outline"
            size="sm"
            @click="addPair"
        >
            <Plus class="mr-2 size-4" />
            Add Specification
        </Button>
    </div>
</template>
