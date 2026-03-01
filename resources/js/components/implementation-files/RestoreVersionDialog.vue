<script setup lang="ts">
import { ref, computed } from 'vue';
import axios from 'axios';
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
import { RotateCcw, AlertCircle } from 'lucide-vue-next';
import type { VersionFile } from './VersionHistoryDialog.vue';

interface Props {
    isOpen: boolean;
    fileId: number;
    versionNumber: number;
    datacenterId: number;
    fileName?: string;
}

const props = withDefaults(defineProps<Props>(), {
    fileName: 'this file',
});

const emit = defineEmits<{
    (e: 'update:isOpen', value: boolean): void;
    (e: 'version-restored', newVersion: VersionFile): void;
    (e: 'close'): void;
}>();

const isRestoring = ref(false);
const error = ref<string | null>(null);

/**
 * Handle the restore action - calls the API to create a new version
 */
const handleRestore = async () => {
    isRestoring.value = true;
    error.value = null;

    try {
        const response = await axios.post<{ data: VersionFile; message: string }>(
            `/datacenters/${props.datacenterId}/implementation-files/${props.fileId}/restore`
        );

        // Emit the version-restored event with the new version data
        emit('version-restored', response.data.data);

        // Close the dialog
        emit('update:isOpen', false);
        emit('close');
    } catch (err) {
        const axiosError = err as { response?: { status?: number; data?: { message?: string } } };

        if (axiosError.response?.status === 403) {
            error.value = 'You do not have permission to restore file versions.';
        } else if (axiosError.response?.status === 404) {
            error.value = 'The file version could not be found.';
        } else {
            error.value = axiosError.response?.data?.message || 'Failed to restore version. Please try again.';
        }
    } finally {
        isRestoring.value = false;
    }
};

/**
 * Handle dialog close
 */
const handleClose = () => {
    if (!isRestoring.value) {
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
                    <RotateCcw class="size-5" />
                    Restore Version {{ versionNumber }}
                </DialogTitle>
                <DialogDescription>
                    Are you sure you want to restore
                    <span class="font-semibold">{{ fileName }}</span>
                    to version {{ versionNumber }}?
                </DialogDescription>
            </DialogHeader>

            <div
                class="rounded-lg border border-blue-100 bg-blue-50 p-4 dark:border-blue-200/10 dark:bg-blue-700/10"
            >
                <div class="relative space-y-0.5 text-blue-600 dark:text-blue-100">
                    <p class="font-medium">What happens when you restore</p>
                    <p class="text-sm">
                        A new version will be created from the content of version {{ versionNumber }}.
                        This will become the latest version, and all previous versions will remain available in the history.
                    </p>
                </div>
            </div>

            <!-- Error message -->
            <div
                v-if="error"
                class="rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
            >
                <div class="flex items-start gap-2 text-red-600 dark:text-red-100">
                    <AlertCircle class="mt-0.5 size-4 shrink-0" />
                    <p class="text-sm">{{ error }}</p>
                </div>
            </div>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button
                        variant="secondary"
                        :disabled="isRestoring"
                        @click="handleClose"
                    >
                        Cancel
                    </Button>
                </DialogClose>

                <Button
                    :disabled="isRestoring"
                    @click="handleRestore"
                >
                    <RotateCcw
                        v-if="isRestoring"
                        class="mr-2 size-4 animate-spin"
                    />
                    {{ isRestoring ? 'Restoring...' : 'Restore Version' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
