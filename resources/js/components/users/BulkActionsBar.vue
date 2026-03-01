<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import UserController from '@/actions/App/Http/Controllers/UserController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

interface Props {
    selectedUserIds: number[];
    currentUserId: number;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'clear-selection'): void;
}>();

const isConfirmOpen = ref(false);
const selectedStatus = ref<string>('');
const isProcessing = ref(false);

const statusOptions = [
    { value: 'active', label: 'Activate' },
    { value: 'inactive', label: 'Deactivate' },
    { value: 'suspended', label: 'Suspend' },
];

// Filter out current user from selection
const validUserIds = () => {
    return props.selectedUserIds.filter(id => id !== props.currentUserId);
};

const hasCurrentUser = () => {
    return props.selectedUserIds.includes(props.currentUserId);
};

const openConfirmDialog = (status: string) => {
    selectedStatus.value = status;
    isConfirmOpen.value = true;
};

const handleBulkStatusChange = () => {
    const userIds = validUserIds();
    if (userIds.length === 0) {
        isConfirmOpen.value = false;
        return;
    }

    isProcessing.value = true;

    router.post(
        UserController.bulkStatus.url(),
        {
            user_ids: userIds,
            status: selectedStatus.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                isConfirmOpen.value = false;
                isProcessing.value = false;
                emit('clear-selection');
            },
            onError: () => {
                isProcessing.value = false;
            },
        }
    );
};

const getStatusLabel = (status: string) => {
    return statusOptions.find(opt => opt.value === status)?.label || status;
};
</script>

<template>
    <div
        v-if="selectedUserIds.length > 0"
        class="fixed bottom-0 left-0 right-0 z-50 border-t bg-background p-4 shadow-lg md:left-[var(--sidebar-width)]"
    >
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <span class="text-sm font-medium">
                    {{ selectedUserIds.length }} user{{ selectedUserIds.length > 1 ? 's' : '' }} selected
                </span>
                <span v-if="hasCurrentUser()" class="text-sm text-muted-foreground">
                    (excluding yourself)
                </span>
            </div>

            <div class="flex items-center gap-2">
                <div class="flex items-center gap-1">
                    <span class="text-sm text-muted-foreground mr-2">Change status to:</span>
                    <Button
                        v-for="option in statusOptions"
                        :key="option.value"
                        variant="outline"
                        size="sm"
                        @click="openConfirmDialog(option.value)"
                    >
                        {{ option.label }}
                    </Button>
                </div>

                <Button
                    variant="ghost"
                    size="sm"
                    @click="$emit('clear-selection')"
                >
                    Clear selection
                </Button>
            </div>
        </div>

        <!-- Confirmation Dialog -->
        <Dialog v-model:open="isConfirmOpen">
            <DialogContent>
                <DialogHeader class="space-y-3">
                    <DialogTitle>Confirm Bulk Action</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to
                        <span class="font-semibold lowercase">{{ getStatusLabel(selectedStatus) }}</span>
                        {{ validUserIds().length }} user{{ validUserIds().length > 1 ? 's' : '' }}?
                    </DialogDescription>
                </DialogHeader>

                <div v-if="hasCurrentUser()" class="rounded-lg border border-yellow-100 bg-yellow-50 p-4 dark:border-yellow-200/10 dark:bg-yellow-700/10">
                    <div class="relative space-y-0.5 text-yellow-700 dark:text-yellow-100">
                        <p class="font-medium">Note</p>
                        <p class="text-sm">
                            Your account will be excluded from this action.
                        </p>
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" :disabled="isProcessing">
                            Cancel
                        </Button>
                    </DialogClose>

                    <Button
                        :disabled="isProcessing || validUserIds().length === 0"
                        @click="handleBulkStatusChange"
                    >
                        {{ isProcessing ? 'Processing...' : 'Confirm' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
