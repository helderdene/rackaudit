<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { File as FileIcon, FileImage, Upload, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Props {
    currentFloorPlanUrl?: string | null;
    currentFloorPlanPath?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
    currentFloorPlanUrl: null,
    currentFloorPlanPath: null,
});

const emit = defineEmits<{
    (e: 'fileSelected', file: File | null): void;
    (e: 'fileRemoved'): void;
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const selectedFile = ref<File | null>(null);
const previewUrl = ref<string | null>(null);
const isRemoving = ref(false);
const validationError = ref<string | null>(null);

const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
const ALLOWED_TYPES = [
    'image/png',
    'image/jpeg',
    'image/jpg',
    'application/pdf',
];
const ALLOWED_EXTENSIONS = ['.png', '.jpg', '.jpeg', '.pdf'];

// Determine if we have a current floor plan to display
const hasCurrentFloorPlan = computed(() => {
    return !!props.currentFloorPlanUrl && !isRemoving.value;
});

// Determine if the current file is a PDF
const isCurrentPdf = computed(() => {
    if (!props.currentFloorPlanPath) {
        return false;
    }
    return props.currentFloorPlanPath.toLowerCase().endsWith('.pdf');
});

// Determine if the selected file is a PDF
const isSelectedPdf = computed(() => {
    if (!selectedFile.value) {
        return false;
    }
    return selectedFile.value.type === 'application/pdf';
});

// Format file size for display
const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) {
        return bytes + ' bytes';
    } else if (bytes < 1024 * 1024) {
        return (bytes / 1024).toFixed(1) + ' KB';
    } else {
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }
};

// Validate the selected file
const validateFile = (file: File): boolean => {
    validationError.value = null;

    // Check file type
    if (!ALLOWED_TYPES.includes(file.type)) {
        validationError.value = `Invalid file type. Please upload a PNG, JPG, JPEG, or PDF file.`;
        return false;
    }

    // Check file size
    if (file.size > MAX_FILE_SIZE) {
        validationError.value = `File size exceeds 10MB limit. Current size: ${formatFileSize(file.size)}`;
        return false;
    }

    return true;
};

// Handle file selection
const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    if (!file) {
        return;
    }

    // Validate file
    if (!validateFile(file)) {
        target.value = '';
        return;
    }

    selectedFile.value = file;
    isRemoving.value = false;

    // Create preview for images
    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = (e) => {
            previewUrl.value = e.target?.result as string;
        };
        reader.readAsDataURL(file);
    } else {
        previewUrl.value = null;
    }

    emit('fileSelected', file);
};

// Handle file removal
const handleRemove = () => {
    if (selectedFile.value) {
        // Clear selected file
        selectedFile.value = null;
        previewUrl.value = null;
        if (fileInput.value) {
            fileInput.value.value = '';
        }
        emit('fileSelected', null);
    } else if (hasCurrentFloorPlan.value) {
        // Mark current floor plan for removal
        isRemoving.value = true;
        emit('fileRemoved');
    }
};

// Trigger file input click
const openFileDialog = () => {
    fileInput.value?.click();
};

// Reset removal state when selecting a new file
watch(selectedFile, (newFile) => {
    if (newFile) {
        isRemoving.value = false;
    }
});
</script>

<template>
    <div class="space-y-4">
        <!-- Hidden file input -->
        <input
            ref="fileInput"
            type="file"
            name="floor_plan"
            :accept="ALLOWED_EXTENSIONS.join(',')"
            class="hidden"
            @change="handleFileSelect"
        />

        <!-- Current/Preview Display -->
        <div
            v-if="hasCurrentFloorPlan || selectedFile"
            class="relative rounded-lg border bg-muted/30 p-4"
        >
            <!-- Image preview -->
            <div
                v-if="
                    (hasCurrentFloorPlan && !isCurrentPdf) ||
                    (selectedFile && !isSelectedPdf)
                "
                class="space-y-3"
            >
                <img
                    :src="previewUrl || currentFloorPlanUrl"
                    :alt="selectedFile?.name || 'Floor plan'"
                    class="max-h-64 rounded-md object-contain"
                />
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">
                        {{ selectedFile?.name || 'Current floor plan' }}
                        <span v-if="selectedFile" class="ml-2">
                            ({{ formatFileSize(selectedFile.size) }})
                        </span>
                    </p>
                    <div class="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="openFileDialog"
                        >
                            Replace
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            @click="handleRemove"
                        >
                            <X class="size-4" />
                            Remove
                        </Button>
                    </div>
                </div>
            </div>

            <!-- PDF preview -->
            <div
                v-else-if="
                    (hasCurrentFloorPlan && isCurrentPdf) ||
                    (selectedFile && isSelectedPdf)
                "
                class="space-y-3"
            >
                <div class="flex items-center gap-3 rounded-md bg-muted p-4">
                    <FileIcon class="size-10 text-muted-foreground" />
                    <div class="flex-1">
                        <p class="font-medium">
                            {{
                                selectedFile?.name || 'Current floor plan (PDF)'
                            }}
                        </p>
                        <p
                            v-if="selectedFile"
                            class="text-sm text-muted-foreground"
                        >
                            {{ formatFileSize(selectedFile.size) }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="openFileDialog"
                    >
                        Replace
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        size="sm"
                        @click="handleRemove"
                    >
                        <X class="size-4" />
                        Remove
                    </Button>
                </div>
            </div>
        </div>

        <!-- Upload Prompt -->
        <div
            v-else
            class="flex flex-col items-center justify-center rounded-lg border-2 border-dashed p-8 transition-colors hover:border-primary/50 hover:bg-muted/30"
            @click="openFileDialog"
            role="button"
            tabindex="0"
            @keydown.enter="openFileDialog"
            @keydown.space.prevent="openFileDialog"
        >
            <div class="flex flex-col items-center gap-2 text-center">
                <div class="rounded-full bg-muted p-3">
                    <Upload class="size-6 text-muted-foreground" />
                </div>
                <div class="space-y-1">
                    <p class="text-sm font-medium">
                        {{
                            isRemoving
                                ? 'Upload a new floor plan'
                                : 'Click to upload a floor plan'
                        }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        PNG, JPG, JPEG, or PDF (max 10MB)
                    </p>
                </div>
                <Button type="button" variant="outline" size="sm" class="mt-2">
                    <FileImage class="mr-2 size-4" />
                    Select File
                </Button>
            </div>
        </div>

        <!-- Validation Error -->
        <p
            v-if="validationError"
            class="text-sm text-red-600 dark:text-red-500"
        >
            {{ validationError }}
        </p>
    </div>
</template>
