<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import FileDropzone from '@/components/imports/FileDropzone.vue';
import { Upload, AlertCircle, CheckCircle2, Download, FileSpreadsheet, FileText } from 'lucide-vue-next';
import { excel as excelTemplate, csv as csvTemplate } from '@/actions/App/Http/Controllers/ConnectionTemplateController';

interface Props {
    datacenterId: number;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
});

const emit = defineEmits<{
    (e: 'file-uploaded'): void;
}>();

const isOpen = ref(false);
const isUploading = ref(false);
const selectedFile = ref<File | null>(null);
const description = ref('');
const uploadProgress = ref(0);
const uploadError = ref<string | null>(null);
const uploadSuccess = ref(false);
const fileDropzoneRef = ref<InstanceType<typeof FileDropzone> | null>(null);

// Accepted file types for implementation files
const acceptedTypes = ['.pdf', '.xlsx', '.xls', '.csv', '.docx', '.txt'];

// Character count for description
const descriptionCharCount = computed(() => description.value.length);
const isDescriptionTooLong = computed(() => descriptionCharCount.value > 500);

const handleFileSelected = (file: File) => {
    selectedFile.value = file;
    uploadError.value = null;
    uploadSuccess.value = false;
};

const handleFileRemoved = () => {
    selectedFile.value = null;
    uploadError.value = null;
};

const handleValidationError = (message: string) => {
    uploadError.value = message;
};

const resetForm = () => {
    selectedFile.value = null;
    description.value = '';
    uploadProgress.value = 0;
    uploadError.value = null;
    uploadSuccess.value = false;
    if (fileDropzoneRef.value) {
        fileDropzoneRef.value.removeFile();
    }
};

const handleUpload = () => {
    if (!selectedFile.value) {
        uploadError.value = 'Please select a file to upload.';
        return;
    }

    if (isDescriptionTooLong.value) {
        uploadError.value = 'Description must be 500 characters or less.';
        return;
    }

    isUploading.value = true;
    uploadError.value = null;
    uploadProgress.value = 0;

    const formData = new FormData();
    formData.append('file', selectedFile.value);
    if (description.value.trim()) {
        formData.append('description', description.value.trim());
    }

    router.post(`/datacenters/${props.datacenterId}/implementation-files`, formData, {
        forceFormData: true,
        preserveScroll: true,
        onProgress: (progress) => {
            if (progress.percentage) {
                uploadProgress.value = progress.percentage;
            }
        },
        onSuccess: () => {
            uploadSuccess.value = true;
            isUploading.value = false;
            emit('file-uploaded');

            // Close dialog after a brief success message
            setTimeout(() => {
                isOpen.value = false;
                resetForm();
            }, 1500);
        },
        onError: (errors) => {
            isUploading.value = false;
            uploadProgress.value = 0;

            // Extract error message
            if (typeof errors === 'object' && errors !== null) {
                const errorMessages = Object.values(errors);
                uploadError.value = errorMessages.length > 0
                    ? String(errorMessages[0])
                    : 'An error occurred while uploading the file.';
            } else {
                uploadError.value = 'An error occurred while uploading the file.';
            }
        },
    });
};

const handleDialogClose = () => {
    if (!isUploading.value) {
        resetForm();
    }
};

/**
 * Download Excel template
 */
const downloadExcelTemplate = () => {
    window.location.href = excelTemplate.url();
};

/**
 * Download CSV template
 */
const downloadCsvTemplate = () => {
    window.location.href = csvTemplate.url();
};
</script>

<template>
    <Dialog v-model:open="isOpen" @update:open="(open) => !open && handleDialogClose()">
        <DialogTrigger as-child>
            <slot>
                <Button :disabled="disabled" class="gap-2">
                    <Upload class="size-4" />
                    Upload File
                </Button>
            </slot>
        </DialogTrigger>
        <DialogContent class="sm:max-w-lg">
            <DialogHeader class="space-y-2">
                <DialogTitle>Upload Implementation File</DialogTitle>
                <DialogDescription>
                    Upload implementation specification documents (PDF, Excel, CSV, Word, or text files).
                    Maximum file size is 10MB.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 py-4">
                <!-- Template Download Section -->
                <div class="rounded-lg border border-dashed border-muted-foreground/25 bg-muted/30 p-4">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <Download class="size-5" />
                        </div>
                        <div class="flex-1 space-y-2">
                            <div>
                                <h4 class="text-sm font-medium">Connection Import Templates</h4>
                                <p class="text-xs text-muted-foreground">
                                    Download a template to prepare your connection data for parsing.
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="h-8 gap-1.5 text-xs"
                                    @click="downloadExcelTemplate"
                                >
                                    <FileSpreadsheet class="size-3.5" />
                                    Excel Template
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="h-8 gap-1.5 text-xs"
                                    @click="downloadCsvTemplate"
                                >
                                    <FileText class="size-3.5" />
                                    CSV Template
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- File Dropzone -->
                <FileDropzone
                    ref="fileDropzoneRef"
                    :accepted-types="acceptedTypes"
                    :max-size-m-b="10"
                    :disabled="isUploading"
                    @file-selected="handleFileSelected"
                    @file-removed="handleFileRemoved"
                    @validation-error="handleValidationError"
                />

                <!-- Description field -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <Label for="description">Description (optional)</Label>
                        <span
                            class="text-xs"
                            :class="isDescriptionTooLong ? 'text-destructive' : 'text-muted-foreground'"
                        >
                            {{ descriptionCharCount }}/500
                        </span>
                    </div>
                    <Textarea
                        id="description"
                        v-model="description"
                        placeholder="Add a brief description of this implementation file..."
                        :disabled="isUploading"
                        class="resize-none"
                        :class="{ 'border-destructive': isDescriptionTooLong }"
                        rows="3"
                    />
                </div>

                <!-- Upload progress -->
                <div v-if="isUploading" class="space-y-2">
                    <div class="flex items-center gap-2 text-sm text-muted-foreground">
                        <Spinner class="size-4" />
                        <span>Uploading... {{ uploadProgress }}%</span>
                    </div>
                    <div class="h-2 w-full overflow-hidden rounded-full bg-secondary">
                        <div
                            class="h-full bg-primary transition-all duration-300"
                            :style="{ width: `${uploadProgress}%` }"
                        />
                    </div>
                </div>

                <!-- Success message -->
                <div
                    v-if="uploadSuccess"
                    class="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400"
                >
                    <CheckCircle2 class="size-4 shrink-0" />
                    <span>File uploaded successfully!</span>
                </div>

                <!-- Error message -->
                <div
                    v-if="uploadError"
                    class="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400"
                >
                    <AlertCircle class="size-4 shrink-0" />
                    <span>{{ uploadError }}</span>
                </div>
            </div>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary" :disabled="isUploading">
                        Cancel
                    </Button>
                </DialogClose>

                <Button
                    :disabled="!selectedFile || isUploading || isDescriptionTooLong || uploadSuccess"
                    @click="handleUpload"
                >
                    <Spinner v-if="isUploading" class="mr-2 size-4" />
                    {{ isUploading ? 'Uploading...' : 'Upload File' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
