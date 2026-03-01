<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Skeleton } from '@/components/ui/skeleton';
import { debounce } from '@/lib/utils';

interface RackOption {
    id: number;
    name: string;
    position: string | null;
    row_name: string | null;
}

interface Props {
    /** Selected rack IDs */
    modelValue: number[];
    /** Room ID to filter racks by (optional) */
    roomId: number | null;
    /** Datacenter ID for fetching all racks when no room is selected */
    datacenterId: number | null;
    /** Error message for rack selection */
    error?: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:modelValue': [value: number[]];
}>();

// Internal state
const racks = ref<RackOption[]>([]);
const isLoading = ref(false);
const searchQuery = ref('');

// Computed for v-model binding
const selectedRackIds = computed({
    get: () => props.modelValue,
    set: (value: number[]) => emit('update:modelValue', value),
});

// Filtered racks based on search
const filteredRacks = computed(() => {
    if (!searchQuery.value) {
        return racks.value;
    }
    const query = searchQuery.value.toLowerCase();
    return racks.value.filter(rack =>
        rack.name.toLowerCase().includes(query) ||
        rack.row_name?.toLowerCase().includes(query) ||
        rack.position?.toLowerCase().includes(query)
    );
});

// Check if a rack is selected
const isRackSelected = (rackId: number): boolean => {
    return selectedRackIds.value.includes(rackId);
};

// Toggle rack selection
const toggleRack = (rackId: number): void => {
    const currentIds = [...selectedRackIds.value];
    const index = currentIds.indexOf(rackId);

    if (index > -1) {
        currentIds.splice(index, 1);
    } else {
        currentIds.push(rackId);
    }

    selectedRackIds.value = currentIds;
};

// Select all visible racks
const selectAllVisible = (): void => {
    const visibleIds = filteredRacks.value.map(rack => rack.id);
    const newIds = [...new Set([...selectedRackIds.value, ...visibleIds])];
    selectedRackIds.value = newIds;
};

// Clear all selections
const clearSelection = (): void => {
    selectedRackIds.value = [];
};

// Get selected rack names for display
const selectedRackNames = computed(() => {
    return racks.value
        .filter(rack => selectedRackIds.value.includes(rack.id))
        .map(rack => rack.name);
});

// Watch for room changes and fetch racks
watch(() => props.roomId, async (newRoomId) => {
    if (newRoomId) {
        await fetchRacksForRoom(newRoomId);
    } else if (props.datacenterId) {
        // If no room selected but datacenter is, clear racks
        // The parent component should handle showing all racks when needed
        racks.value = [];
        emit('update:modelValue', []);
    }
});

// Also watch datacenter changes
watch(() => props.datacenterId, () => {
    if (!props.roomId) {
        racks.value = [];
        emit('update:modelValue', []);
    }
});

/**
 * Fetch racks for the selected room
 */
async function fetchRacksForRoom(roomId: number): Promise<void> {
    isLoading.value = true;
    try {
        const response = await fetch(`/api/audits/rooms/${roomId}/racks`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('Failed to fetch racks');
        }

        const data = await response.json();
        racks.value = data.data || [];

        // Clear any previously selected racks that are not in the new room
        const validIds = racks.value.map(r => r.id);
        const newSelection = selectedRackIds.value.filter(id => validIds.includes(id));
        if (newSelection.length !== selectedRackIds.value.length) {
            emit('update:modelValue', newSelection);
        }
    } catch (error) {
        console.error('Error fetching racks:', error);
        racks.value = [];
    } finally {
        isLoading.value = false;
    }
}

// Debounced search handler
const handleSearchInput = debounce((value: string) => {
    searchQuery.value = value;
}, 200);
</script>

<template>
    <div class="space-y-3">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <Label>
                Racks <span class="text-red-500">*</span>
            </Label>
            <div class="flex items-center gap-2">
                <button
                    v-if="filteredRacks.length > 0 && !isLoading"
                    type="button"
                    class="text-xs text-primary hover:underline"
                    @click="selectAllVisible"
                >
                    Select all
                </button>
                <span v-if="selectedRackIds.length > 0 && !isLoading" class="text-xs text-muted-foreground">|</span>
                <button
                    v-if="selectedRackIds.length > 0 && !isLoading"
                    type="button"
                    class="text-xs text-muted-foreground hover:text-foreground hover:underline"
                    @click="clearSelection"
                >
                    Clear selection
                </button>
            </div>
        </div>

        <!-- Search input -->
        <div class="relative">
            <Input
                type="text"
                placeholder="Search racks..."
                :model-value="searchQuery"
                :disabled="isLoading"
                @update:model-value="handleSearchInput"
                class="pr-8"
            />
            <svg
                class="absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                />
            </svg>
        </div>

        <!-- Loading state with skeleton -->
        <div v-if="isLoading" class="space-y-2 rounded-md border border-input p-3">
            <div class="flex items-center gap-2">
                <Spinner class="h-4 w-4" />
                <span class="text-sm text-muted-foreground">Loading racks...</span>
            </div>
            <div class="space-y-2">
                <Skeleton class="h-8 w-full" />
                <Skeleton class="h-8 w-full" />
                <Skeleton class="h-8 w-3/4" />
            </div>
        </div>

        <!-- Empty state -->
        <div v-else-if="!props.roomId && !props.datacenterId" class="rounded-md border border-dashed border-input py-8 text-center text-sm text-muted-foreground">
            Select a room to view available racks
        </div>

        <div v-else-if="racks.length === 0" class="rounded-md border border-dashed border-input py-8 text-center text-sm text-muted-foreground">
            No racks available in the selected room
        </div>

        <div v-else-if="filteredRacks.length === 0" class="rounded-md border border-dashed border-input py-8 text-center text-sm text-muted-foreground">
            No racks match your search
        </div>

        <!-- Rack list - responsive grid for touch devices -->
        <div
            v-else
            class="max-h-64 space-y-1 overflow-y-auto rounded-md border border-input p-2 sm:max-h-72"
        >
            <label
                v-for="rack in filteredRacks"
                :key="rack.id"
                :for="`rack-${rack.id}`"
                class="flex cursor-pointer touch-manipulation items-center gap-3 rounded-md px-2 py-2 hover:bg-muted/50 active:bg-muted/70 sm:py-1.5"
            >
                <Checkbox
                    :id="`rack-${rack.id}`"
                    :checked="isRackSelected(rack.id)"
                    class="h-5 w-5 sm:h-4 sm:w-4"
                    @update:checked="toggleRack(rack.id)"
                />
                <div class="flex flex-1 flex-col gap-0.5 sm:flex-row sm:items-center sm:justify-between sm:gap-2">
                    <span class="text-sm font-medium">{{ rack.name }}</span>
                    <span v-if="rack.row_name" class="text-xs text-muted-foreground">
                        Row: {{ rack.row_name }}
                    </span>
                </div>
            </label>
        </div>

        <!-- Selection summary - responsive wrapping -->
        <div v-if="selectedRackIds.length > 0" class="flex flex-wrap items-center gap-2">
            <Badge variant="secondary" class="font-normal">
                {{ selectedRackIds.length }} {{ selectedRackIds.length === 1 ? 'rack' : 'racks' }} selected
            </Badge>
            <span v-if="selectedRackNames.length <= 3" class="hidden text-xs text-muted-foreground sm:inline">
                ({{ selectedRackNames.join(', ') }})
            </span>
        </div>

        <!-- Error message -->
        <p v-if="error" class="text-sm text-destructive">
            {{ error }}
        </p>
    </div>
</template>
