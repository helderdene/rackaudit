<script setup lang="ts">
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
import axios from 'axios';
import { AlertCircle, CheckCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import type { ImplementationFile } from './ImplementationFileList.vue';

interface Props {
    isOpen: boolean;
    file: ImplementationFile;
    datacenterId: number;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:isOpen', value: boolean): void;
    (e: 'file-approved'): void;
    (e: 'close'): void;
}>();

const isApproving = ref(false);
const error = ref<string | null>(null);

/**
 * Formats a date string to a user-friendly format
 */
const formatDate = (dateString: string): string => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

/**
 * Handle the approve action - calls the API to approve the file
 */
const handleApprove = async () => {
    isApproving.value = true;
    error.value = null;

    try {
        await axios.post(
            `/datacenters/${props.datacenterId}/implementation-files/${props.file.id}/approve`,
        );

        // Emit the file-approved event
        emit('file-approved');

        // Close the dialog
        emit('update:isOpen', false);
        emit('close');
    } catch (err) {
        const axiosError = err as {
            response?: {
                status?: number;
                data?: { message?: string; errors?: { file?: string[] } };
            };
        };

        if (axiosError.response?.status === 403) {
            error.value = 'You do not have permission to approve this file.';
        } else if (axiosError.response?.status === 422) {
            // Validation error (e.g., file already approved)
            const validationErrors = axiosError.response?.data?.errors;
            if (validationErrors?.file) {
                error.value = validationErrors.file[0];
            } else {
                error.value =
                    axiosError.response?.data?.message ||
                    'This file cannot be approved.';
            }
        } else if (axiosError.response?.status === 404) {
            error.value = 'The file could not be found.';
        } else {
            error.value =
                axiosError.response?.data?.message ||
                'Failed to approve file. Please try again.';
        }
    } finally {
        isApproving.value = false;
    }
};

/**
 * Handle dialog close
 */
const handleClose = () => {
    if (!isApproving.value) {
        error.value = null;
        emit('update:isOpen', false);
        emit('close');
    }
};

/**
 * Computed property for the dialog open state
 */
const dialogOpen = computed({
    get: () => props.isOpen,
    set: (value: boolean) => {
        if (!value) {
            handleClose();
        }
    },
});
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader class="space-y-3">
                <DialogTitle class="flex items-center gap-2">
                    <CheckCircle class="size-5 text-green-600" />
                    Approve Implementation File
                </DialogTitle>
                <DialogDescription>
                    Are you sure you want to approve this file?
                </DialogDescription>
            </DialogHeader>

            <!-- File details -->
            <div class="rounded-lg border bg-muted/50 p-4">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="font-medium text-muted-foreground">
                            File Name
                        </dt>
                        <dd class="text-right">{{ file.original_name }}</dd>
                    </div>
                    <div v-if="file.uploader" class="flex justify-between">
                        <dt class="font-medium text-muted-foreground">
                            Uploaded By
                        </dt>
                        <dd class="text-right">{{ file.uploader.name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="font-medium text-muted-foreground">
                            Date Uploaded
                        </dt>
                        <dd class="text-right">
                            {{ formatDate(file.created_at) }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="font-medium text-muted-foreground">
                            File Type
                        </dt>
                        <dd class="text-right">{{ file.file_type_label }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="font-medium text-muted-foreground">Size</dt>
                        <dd class="text-right">
                            {{ file.formatted_file_size }}
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Info message -->
            <div
                class="rounded-lg border border-green-100 bg-green-50 p-4 dark:border-green-200/10 dark:bg-green-700/10"
            >
                <div
                    class="relative space-y-0.5 text-green-600 dark:text-green-100"
                >
                    <p class="font-medium">What happens when you approve</p>
                    <p class="text-sm">
                        Approving this file marks it as reviewed and
                        authoritative for datacenter audits. The uploader will
                        be notified that their file has been approved.
                    </p>
                </div>
            </div>

            <!-- Error message -->
            <div
                v-if="error"
                class="rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
            >
                <div
                    class="flex items-start gap-2 text-red-600 dark:text-red-100"
                >
                    <AlertCircle class="mt-0.5 size-4 shrink-0" />
                    <p class="text-sm">{{ error }}</p>
                </div>
            </div>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button
                        variant="secondary"
                        :disabled="isApproving"
                        @click="handleClose"
                    >
                        Cancel
                    </Button>
                </DialogClose>

                <Button
                    :disabled="isApproving"
                    class="bg-green-600 text-white hover:bg-green-700"
                    @click="handleApprove"
                >
                    <CheckCircle
                        v-if="isApproving"
                        class="mr-2 size-4 animate-spin"
                    />
                    {{ isApproving ? 'Approving...' : 'Approve File' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
