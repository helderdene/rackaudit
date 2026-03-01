<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { AlertCircle, File, Upload, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    acceptedTypes?: string[];
    maxSizeMB?: number;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    acceptedTypes: () => ['.csv', '.xlsx'],
    maxSizeMB: 10,
    disabled: false,
});

const emit = defineEmits<{
    (e: 'file-selected', file: File): void;
    (e: 'file-removed'): void;
    (e: 'validation-error', message: string): void;
}>();

const selectedFile = ref<File | null>(null);
const isDragOver = ref(false);
const validationError = ref<string | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);

const acceptString = computed(() => props.acceptedTypes.join(','));

const maxSizeBytes = computed(() => props.maxSizeMB * 1024 * 1024);

const formattedFileSize = computed(() => {
    if (!selectedFile.value) return '';
    const bytes = selectedFile.value.size;
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
});

const validateFile = (file: File): boolean => {
    validationError.value = null;

    // Check file extension
    const fileName = file.name.toLowerCase();
    const hasValidExtension = props.acceptedTypes.some((ext) =>
        fileName.endsWith(ext.toLowerCase()),
    );

    if (!hasValidExtension) {
        const errorMsg = `Invalid file type. Please upload a ${props.acceptedTypes.join(' or ')} file.`;
        validationError.value = errorMsg;
        emit('validation-error', errorMsg);
        return false;
    }

    // Check file size
    if (file.size > maxSizeBytes.value) {
        const errorMsg = `File size exceeds ${props.maxSizeMB}MB limit.`;
        validationError.value = errorMsg;
        emit('validation-error', errorMsg);
        return false;
    }

    return true;
};

const handleFile = (file: File) => {
    if (props.disabled) return;

    if (validateFile(file)) {
        selectedFile.value = file;
        emit('file-selected', file);
    }
};

const handleDrop = (event: DragEvent) => {
    event.preventDefault();
    isDragOver.value = false;

    if (props.disabled) return;

    const files = event.dataTransfer?.files;
    if (files && files.length > 0) {
        handleFile(files[0]);
    }
};

const handleDragOver = (event: DragEvent) => {
    event.preventDefault();
    if (!props.disabled) {
        isDragOver.value = true;
    }
};

const handleDragLeave = () => {
    isDragOver.value = false;
};

const handleInputChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
        handleFile(input.files[0]);
    }
};

const openFileBrowser = () => {
    if (props.disabled) return;
    fileInputRef.value?.click();
};

const removeFile = () => {
    selectedFile.value = null;
    validationError.value = null;
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
    emit('file-removed');
};

defineExpose({
    selectedFile,
    removeFile,
});
</script>

<template>
    <div class="w-full">
        <!-- Hidden file input -->
        <input
            ref="fileInputRef"
            type="file"
            :accept="acceptString"
            class="hidden"
            :disabled="disabled"
            @change="handleInputChange"
        />

        <!-- Dropzone area -->
        <div
            v-if="!selectedFile"
            class="relative flex min-h-[200px] cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed transition-colors"
            :class="{
                'border-primary bg-primary/5': isDragOver,
                'border-muted-foreground/25 hover:border-muted-foreground/50 hover:bg-muted/50':
                    !isDragOver && !disabled,
                'cursor-not-allowed border-muted-foreground/20 bg-muted/30':
                    disabled,
            }"
            @drop="handleDrop"
            @dragover="handleDragOver"
            @dragleave="handleDragLeave"
            @click="openFileBrowser"
        >
            <div class="flex flex-col items-center gap-4 px-6 py-8 text-center">
                <div
                    class="flex h-14 w-14 items-center justify-center rounded-full"
                    :class="{
                        'bg-primary/10 text-primary': isDragOver,
                        'bg-muted text-muted-foreground': !isDragOver,
                    }"
                >
                    <Upload class="h-6 w-6" />
                </div>

                <div class="space-y-1">
                    <p class="text-sm font-medium">
                        <span v-if="isDragOver">Drop file here</span>
                        <span v-else
                            >Drag and drop your file here, or click to
                            browse</span
                        >
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Accepts {{ acceptedTypes.join(', ') }} files (max
                        {{ maxSizeMB }}MB)
                    </p>
                </div>

                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="disabled"
                    @click.stop="openFileBrowser"
                >
                    Browse Files
                </Button>
            </div>
        </div>

        <!-- Selected file display -->
        <div
            v-else
            class="flex items-center justify-between gap-4 rounded-lg border bg-muted/30 p-4"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary"
                >
                    <File class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium">
                        {{ selectedFile.name }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ formattedFileSize }}
                    </p>
                </div>
            </div>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                :disabled="disabled"
                @click="removeFile"
            >
                <X class="h-4 w-4" />
                <span class="sr-only">Remove file</span>
            </Button>
        </div>

        <!-- Validation error -->
        <div
            v-if="validationError"
            class="mt-2 flex items-center gap-2 text-sm text-destructive"
        >
            <AlertCircle class="h-4 w-4 shrink-0" />
            <span>{{ validationError }}</span>
        </div>
    </div>
</template>
