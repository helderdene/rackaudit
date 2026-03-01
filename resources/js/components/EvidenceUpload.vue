<script setup lang="ts">
import FindingEvidenceController from '@/actions/App/Http/Controllers/FindingEvidenceController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import type { FindingEvidenceData } from '@/types/finding';
import { router } from '@inertiajs/vue3';
import {
    AlertCircle,
    Download,
    FileText,
    Plus,
    Trash2,
    Upload,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    findingId: number;
    evidence: FindingEvidenceData[];
    canEdit: boolean;
}

const props = defineProps<Props>();

// File upload state
const fileInput = ref<HTMLInputElement | null>(null);
const selectedFile = ref<File | null>(null);
const uploadProgress = ref(0);
const isUploading = ref(false);
const uploadError = ref('');

// Text note state
const showTextNoteForm = ref(false);
const textNoteContent = ref('');
const isSubmittingNote = ref(false);
const noteError = ref('');

// Delete confirmation
const evidenceToDelete = ref<FindingEvidenceData | null>(null);
const showDeleteDialog = ref(false);
const isDeleting = ref(false);

// Accepted file types
const acceptedFileTypes = '.jpg,.jpeg,.png,.gif,.pdf,.doc,.docx';
const maxFileSizeMB = 10;
const maxFileSizeBytes = maxFileSizeMB * 1024 * 1024;

// Trigger file input click
const triggerFileSelect = () => {
    fileInput.value?.click();
};

// Handle file selection
const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    if (!file) return;

    uploadError.value = '';

    // Validate file size
    if (file.size > maxFileSizeBytes) {
        uploadError.value = `File size exceeds ${maxFileSizeMB}MB limit.`;
        selectedFile.value = null;
        return;
    }

    // Validate file type
    const extension = '.' + file.name.split('.').pop()?.toLowerCase();
    if (!acceptedFileTypes.includes(extension)) {
        uploadError.value =
            'Invalid file type. Accepted: images, PDFs, and Word documents.';
        selectedFile.value = null;
        return;
    }

    selectedFile.value = file;
};

// Upload the selected file
const uploadFile = () => {
    if (!selectedFile.value) return;

    isUploading.value = true;
    uploadProgress.value = 0;
    uploadError.value = '';

    const formData = new FormData();
    formData.append('type', 'file');
    formData.append('file', selectedFile.value);

    router.post(
        FindingEvidenceController.store.url(props.findingId),
        formData,
        {
            forceFormData: true,
            preserveScroll: true,
            onProgress: (progress) => {
                if (progress.percentage) {
                    uploadProgress.value = progress.percentage;
                }
            },
            onSuccess: () => {
                selectedFile.value = null;
                uploadProgress.value = 0;
                if (fileInput.value) {
                    fileInput.value.value = '';
                }
            },
            onError: (errors) => {
                uploadError.value =
                    errors.file || 'Upload failed. Please try again.';
            },
            onFinish: () => {
                isUploading.value = false;
            },
        },
    );
};

// Cancel file selection
const cancelFileSelection = () => {
    selectedFile.value = null;
    uploadError.value = '';
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

// Submit text note
const submitTextNote = () => {
    if (!textNoteContent.value.trim()) {
        noteError.value = 'Please enter some text.';
        return;
    }

    isSubmittingNote.value = true;
    noteError.value = '';

    router.post(
        FindingEvidenceController.store.url(props.findingId),
        {
            type: 'text',
            content: textNoteContent.value.trim(),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                textNoteContent.value = '';
                showTextNoteForm.value = false;
            },
            onError: (errors) => {
                noteError.value = errors.content || 'Failed to save note.';
            },
            onFinish: () => {
                isSubmittingNote.value = false;
            },
        },
    );
};

// Cancel text note
const cancelTextNote = () => {
    textNoteContent.value = '';
    showTextNoteForm.value = false;
    noteError.value = '';
};

// Confirm delete evidence
const confirmDelete = (evidence: FindingEvidenceData) => {
    evidenceToDelete.value = evidence;
    showDeleteDialog.value = true;
};

// Delete evidence
const deleteEvidence = () => {
    if (!evidenceToDelete.value) return;

    isDeleting.value = true;

    router.delete(
        FindingEvidenceController.destroy.url({
            finding: props.findingId,
            evidence: evidenceToDelete.value.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                showDeleteDialog.value = false;
                evidenceToDelete.value = null;
            },
            onFinish: () => {
                isDeleting.value = false;
            },
        },
    );
};

// Close delete dialog
const closeDeleteDialog = () => {
    showDeleteDialog.value = false;
    evidenceToDelete.value = null;
};

// Get download URL for file evidence
const getDownloadUrl = (evidence: FindingEvidenceData): string => {
    return `/storage/${evidence.file_path}`;
};

// Format file size
const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};

// Format date
const formatDate = (dateString: string | null): string => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// Computed values
const fileEvidence = computed(() =>
    props.evidence.filter((e) => e.type === 'file'),
);
const textEvidence = computed(() =>
    props.evidence.filter((e) => e.type === 'text'),
);
const hasEvidence = computed(() => props.evidence.length > 0);
</script>

<template>
    <div class="space-y-6">
        <!-- Add Evidence Actions (only if canEdit) -->
        <div v-if="canEdit" class="flex flex-wrap gap-2">
            <input
                ref="fileInput"
                type="file"
                :accept="acceptedFileTypes"
                class="hidden"
                @change="handleFileSelect"
            />

            <Button
                variant="outline"
                size="sm"
                @click="triggerFileSelect"
                :disabled="isUploading"
            >
                <Upload class="mr-2 size-4" />
                Upload File
            </Button>

            <Button
                variant="outline"
                size="sm"
                @click="showTextNoteForm = true"
                :disabled="showTextNoteForm"
            >
                <Plus class="mr-2 size-4" />
                Add Note
            </Button>
        </div>

        <!-- File Type/Size Info -->
        <p v-if="canEdit" class="text-xs text-muted-foreground">
            Accepted files: images (JPG, PNG, GIF), PDFs, Word documents. Max
            size: {{ maxFileSizeMB }}MB.
        </p>

        <!-- Selected File Preview -->
        <div v-if="selectedFile" class="rounded-lg border border-dashed p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <FileText class="size-8 text-muted-foreground" />
                    <div>
                        <p class="text-sm font-medium">
                            {{ selectedFile.name }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ formatFileSize(selectedFile.size) }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        size="sm"
                        @click="uploadFile"
                        :disabled="isUploading"
                    >
                        {{ isUploading ? 'Uploading...' : 'Upload' }}
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="cancelFileSelection"
                        :disabled="isUploading"
                    >
                        Cancel
                    </Button>
                </div>
            </div>
            <!-- Upload progress bar -->
            <div v-if="isUploading" class="mt-3">
                <div
                    class="h-2 w-full overflow-hidden rounded-full bg-secondary"
                >
                    <div
                        class="h-full bg-primary transition-all duration-300"
                        :style="{ width: `${uploadProgress}%` }"
                    />
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ uploadProgress }}% uploaded
                </p>
            </div>
        </div>

        <!-- Upload Error -->
        <div
            v-if="uploadError"
            class="flex items-center gap-2 text-sm text-destructive"
        >
            <AlertCircle class="size-4" />
            {{ uploadError }}
        </div>

        <!-- Text Note Form -->
        <div v-if="showTextNoteForm" class="space-y-3 rounded-lg border p-4">
            <Textarea
                v-model="textNoteContent"
                placeholder="Enter your note..."
                class="min-h-[100px]"
            />
            <div v-if="noteError" class="text-xs text-destructive">
                {{ noteError }}
            </div>
            <div class="flex justify-end gap-2">
                <Button
                    variant="ghost"
                    size="sm"
                    @click="cancelTextNote"
                    :disabled="isSubmittingNote"
                >
                    Cancel
                </Button>
                <Button
                    size="sm"
                    @click="submitTextNote"
                    :disabled="isSubmittingNote"
                >
                    {{ isSubmittingNote ? 'Saving...' : 'Save Note' }}
                </Button>
            </div>
        </div>

        <!-- Evidence List -->
        <div v-if="hasEvidence" class="space-y-4">
            <!-- File Evidence -->
            <div v-if="fileEvidence.length > 0">
                <h4 class="mb-2 text-sm font-medium text-muted-foreground">
                    Files ({{ fileEvidence.length }})
                </h4>
                <div class="space-y-2">
                    <div
                        v-for="evidence in fileEvidence"
                        :key="evidence.id"
                        class="flex items-center justify-between rounded-lg border p-3"
                    >
                        <div class="flex items-center gap-3">
                            <FileText class="size-6 text-muted-foreground" />
                            <div>
                                <p class="text-sm font-medium">
                                    {{ evidence.original_filename }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ evidence.mime_type }} -
                                    {{ formatDate(evidence.created_at) }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <a
                                :href="getDownloadUrl(evidence)"
                                target="_blank"
                                class="inline-flex items-center gap-1 text-sm text-primary hover:underline"
                            >
                                <Download class="size-4" />
                                Download
                            </a>
                            <Button
                                v-if="canEdit"
                                variant="ghost"
                                size="sm"
                                class="text-destructive hover:text-destructive"
                                @click="confirmDelete(evidence)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Text Evidence -->
            <div v-if="textEvidence.length > 0">
                <h4 class="mb-2 text-sm font-medium text-muted-foreground">
                    Notes ({{ textEvidence.length }})
                </h4>
                <div class="space-y-2">
                    <div
                        v-for="evidence in textEvidence"
                        :key="evidence.id"
                        class="rounded-lg border p-3"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1">
                                <p class="text-sm whitespace-pre-line">
                                    {{ evidence.content }}
                                </p>
                                <p class="mt-2 text-xs text-muted-foreground">
                                    {{ formatDate(evidence.created_at) }}
                                </p>
                            </div>
                            <Button
                                v-if="canEdit"
                                variant="ghost"
                                size="sm"
                                class="shrink-0 text-destructive hover:text-destructive"
                                @click="confirmDelete(evidence)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="rounded-lg border border-dashed py-8 text-center">
            <FileText class="mx-auto size-12 text-muted-foreground/50" />
            <p class="mt-2 text-sm text-muted-foreground">
                No evidence attached to this finding.
            </p>
            <p v-if="canEdit" class="text-xs text-muted-foreground">
                Upload files or add notes to document evidence.
            </p>
        </div>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="showDeleteDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Evidence</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete this evidence?
                        {{
                            evidenceToDelete?.type === 'file'
                                ? 'The file will be permanently removed.'
                                : 'This note will be permanently deleted.'
                        }}
                        This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="closeDeleteDialog"
                        :disabled="isDeleting"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        @click="deleteEvidence"
                        :disabled="isDeleting"
                    >
                        {{ isDeleting ? 'Deleting...' : 'Delete' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
