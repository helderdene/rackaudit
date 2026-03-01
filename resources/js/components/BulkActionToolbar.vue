<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { FilterOption, FindingStatusValue } from '@/types/finding';
import {
    ArrowRightCircle,
    ChevronDown,
    Loader2,
    Pause,
    UserPlus,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    selectedCount: number;
    assigneeOptions: FilterOption[];
    statusOptions: FilterOption[];
    processingAssign?: boolean;
    processingStatus?: boolean;
}

interface Emits {
    (e: 'assign', userId: number): void;
    (e: 'changeStatus', status: FindingStatusValue): void;
    (e: 'defer'): void;
    (e: 'clear'): void;
}

const props = withDefaults(defineProps<Props>(), {
    processingAssign: false,
    processingStatus: false,
});
const emit = defineEmits<Emits>();

// Track dropdown open state
const assignDropdownOpen = ref(false);
const statusDropdownOpen = ref(false);

// Filter status options to show reasonable workflow transitions for bulk operations
const bulkStatusOptions = computed(() => {
    // Allow: Open, In Progress, Deferred for bulk operations
    // Resolved is excluded as it requires individual resolution notes
    const allowedStatuses = [
        'open',
        'in_progress',
        'pending_review',
        'deferred',
    ];
    return props.statusOptions.filter((option) =>
        allowedStatuses.includes(option.value as string),
    );
});

// Handle assign selection
const handleAssign = (userId: number) => {
    emit('assign', userId);
    assignDropdownOpen.value = false;
};

// Handle status change selection
const handleStatusChange = (status: string) => {
    if (status === 'deferred') {
        emit('defer');
    } else {
        emit('changeStatus', status as FindingStatusValue);
    }
    statusDropdownOpen.value = false;
};

// Handle clear selection
const handleClear = () => {
    emit('clear');
};

// Check if any operation is processing
const isProcessing = computed(
    () => props.processingAssign || props.processingStatus,
);
</script>

<template>
    <div
        v-if="selectedCount > 0"
        class="flex flex-wrap items-center gap-3 rounded-lg border bg-muted/50 p-3"
    >
        <!-- Selection count -->
        <div class="flex items-center gap-2 text-sm font-medium">
            <span
                class="flex size-6 items-center justify-center rounded-full bg-primary text-xs text-primary-foreground"
            >
                {{ selectedCount }}
            </span>
            <span class="text-muted-foreground">
                finding{{ selectedCount === 1 ? '' : 's' }} selected
            </span>
        </div>

        <div class="h-4 w-px bg-border" />

        <!-- Assign dropdown -->
        <DropdownMenu v-model:open="assignDropdownOpen">
            <DropdownMenuTrigger as-child>
                <Button variant="outline" size="sm" :disabled="isProcessing">
                    <Loader2
                        v-if="processingAssign"
                        class="mr-1 size-4 animate-spin"
                    />
                    <UserPlus v-else class="mr-1 size-4" />
                    Assign to...
                    <ChevronDown class="ml-1 size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="start"
                class="max-h-64 w-56 overflow-y-auto"
            >
                <DropdownMenuLabel>Select Assignee</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    v-for="option in assigneeOptions"
                    :key="option.value"
                    @click="handleAssign(option.value as number)"
                >
                    {{ option.label }}
                </DropdownMenuItem>
                <DropdownMenuItem v-if="assigneeOptions.length === 0" disabled>
                    No users available
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>

        <!-- Status change dropdown -->
        <DropdownMenu v-model:open="statusDropdownOpen">
            <DropdownMenuTrigger as-child>
                <Button variant="outline" size="sm" :disabled="isProcessing">
                    <Loader2
                        v-if="processingStatus"
                        class="mr-1 size-4 animate-spin"
                    />
                    <ArrowRightCircle v-else class="mr-1 size-4" />
                    Change Status...
                    <ChevronDown class="ml-1 size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" class="w-48">
                <DropdownMenuLabel>Select Status</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    v-for="option in bulkStatusOptions"
                    :key="option.value"
                    @click="handleStatusChange(option.value as string)"
                >
                    {{ option.label }}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>

        <!-- Defer All button -->
        <Button
            variant="secondary"
            size="sm"
            :disabled="isProcessing"
            @click="$emit('defer')"
        >
            <Loader2 v-if="processingStatus" class="mr-1 size-4 animate-spin" />
            <Pause v-else class="mr-1 size-4" />
            Defer All
        </Button>

        <div class="h-4 w-px bg-border" />

        <!-- Clear selection -->
        <Button
            variant="ghost"
            size="sm"
            :disabled="isProcessing"
            @click="handleClear"
        >
            <X class="mr-1 size-4" />
            Clear Selection
        </Button>
    </div>
</template>
