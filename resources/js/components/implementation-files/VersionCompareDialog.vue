<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ChevronDown, GitCompare, Loader2, AlertCircle, ZoomIn, ZoomOut } from 'lucide-vue-next';
import type { VersionFile } from './VersionHistoryDialog.vue';

interface Props {
    isOpen: boolean;
    fileId: number;
    initialLeftVersion?: VersionFile | null;
    initialRightVersion?: VersionFile | null;
    versions: VersionFile[];
    fileName?: string;
}

const props = withDefaults(defineProps<Props>(), {
    initialLeftVersion: null,
    initialRightVersion: null,
    fileName: 'File',
});

const emit = defineEmits<{
    (e: 'update:isOpen', value: boolean): void;
    (e: 'close'): void;
}>();

// Selected version IDs for left and right panels
const leftVersionId = ref<number | null>(null);
const rightVersionId = ref<number | null>(null);

// Loading states for each viewer
const leftLoading = ref(true);
const rightLoading = ref(true);

// Error states for each viewer
const leftError = ref(false);
const rightError = ref(false);

// Zoom states for images
const leftZoomed = ref(false);
const rightZoomed = ref(false);

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

/**
 * Get selected version objects based on IDs
 */
const leftVersion = computed(() => {
    return props.versions.find(v => v.id === leftVersionId.value) || null;
});

const rightVersion = computed(() => {
    return props.versions.find(v => v.id === rightVersionId.value) || null;
});

/**
 * Check if file type supports comparison preview
 */
const supportsPreview = (mimeType: string): boolean => {
    return (
        mimeType === 'application/pdf' ||
        mimeType.startsWith('image/')
    );
};

/**
 * Check if file type is PDF
 */
const isPdf = (mimeType: string): boolean => {
    return mimeType === 'application/pdf';
};

/**
 * Check if file type is image
 */
const isImage = (mimeType: string): boolean => {
    return mimeType.startsWith('image/');
};

/**
 * Format date for dropdown display
 */
const formatDate = (dateString: string): string => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

/**
 * Handle dialog close
 */
const handleClose = () => {
    emit('update:isOpen', false);
    emit('close');
};

/**
 * Handle image load completion
 */
const handleLeftLoad = () => {
    leftLoading.value = false;
    leftError.value = false;
};

const handleRightLoad = () => {
    rightLoading.value = false;
    rightError.value = false;
};

/**
 * Handle image/PDF load error
 */
const handleLeftError = () => {
    leftLoading.value = false;
    leftError.value = true;
};

const handleRightError = () => {
    rightLoading.value = false;
    rightError.value = true;
};

/**
 * Toggle zoom for images
 */
const toggleLeftZoom = () => {
    leftZoomed.value = !leftZoomed.value;
};

const toggleRightZoom = () => {
    rightZoomed.value = !rightZoomed.value;
};

/**
 * Initialize default versions when dialog opens or versions change
 */
watch(
    () => [props.isOpen, props.versions],
    ([isOpen]) => {
        if (isOpen && props.versions.length > 0) {
            // Set initial versions if provided, otherwise use defaults
            if (props.initialLeftVersion) {
                leftVersionId.value = props.initialLeftVersion.id;
            } else {
                // Default: latest version (first in list, ordered by version_number desc)
                leftVersionId.value = props.versions[0]?.id || null;
            }

            if (props.initialRightVersion) {
                rightVersionId.value = props.initialRightVersion.id;
            } else {
                // Default: previous version (second in list)
                rightVersionId.value = props.versions[1]?.id || null;
            }

            // Reset states
            leftLoading.value = true;
            rightLoading.value = true;
            leftError.value = false;
            rightError.value = false;
            leftZoomed.value = false;
            rightZoomed.value = false;
        }
    },
    { immediate: true }
);

/**
 * Reset loading state when version selection changes
 */
watch(leftVersionId, () => {
    leftLoading.value = true;
    leftError.value = false;
    leftZoomed.value = false;
});

watch(rightVersionId, () => {
    rightLoading.value = true;
    rightError.value = false;
    rightZoomed.value = false;
});
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <DialogContent class="max-w-[95vw] lg:max-w-7xl max-h-[90vh] overflow-hidden flex flex-col">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <GitCompare class="size-5" />
                    Compare Versions
                </DialogTitle>
                <p class="text-sm text-muted-foreground">
                    {{ fileName }}
                </p>
            </DialogHeader>

            <!-- Version Selection Dropdowns -->
            <div class="flex flex-col lg:flex-row gap-4 lg:gap-8">
                <!-- Left Version Selector -->
                <div class="flex-1">
                    <label class="text-sm font-medium text-muted-foreground mb-2 block">
                        Left Version
                    </label>
                    <div class="relative">
                        <select
                            v-model="leftVersionId"
                            class="w-full h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 appearance-none cursor-pointer pr-10"
                        >
                            <option
                                v-for="version in versions"
                                :key="version.id"
                                :value="version.id"
                            >
                                Version {{ version.version_number }} - {{ formatDate(version.created_at) }}
                            </option>
                        </select>
                        <ChevronDown class="absolute right-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground pointer-events-none" />
                    </div>
                </div>

                <!-- Right Version Selector -->
                <div class="flex-1">
                    <label class="text-sm font-medium text-muted-foreground mb-2 block">
                        Right Version
                    </label>
                    <div class="relative">
                        <select
                            v-model="rightVersionId"
                            class="w-full h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 appearance-none cursor-pointer pr-10"
                        >
                            <option
                                v-for="version in versions"
                                :key="version.id"
                                :value="version.id"
                            >
                                Version {{ version.version_number }} - {{ formatDate(version.created_at) }}
                            </option>
                        </select>
                        <ChevronDown class="absolute right-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground pointer-events-none" />
                    </div>
                </div>
            </div>

            <!-- Comparison Viewers -->
            <div class="flex-1 overflow-hidden mt-4">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 h-full">
                    <!-- Left Version Viewer -->
                    <div class="flex flex-col border rounded-lg overflow-hidden">
                        <!-- Version Label -->
                        <div class="bg-muted/50 px-4 py-2 border-b flex items-center justify-between">
                            <span class="font-medium text-sm">
                                Version {{ leftVersion?.version_number }}
                            </span>
                            <Button
                                v-if="leftVersion && isImage(leftVersion.mime_type) && !leftError"
                                variant="ghost"
                                size="sm"
                                class="h-7 w-7 p-0"
                                @click="toggleLeftZoom"
                            >
                                <ZoomIn v-if="!leftZoomed" class="size-4" />
                                <ZoomOut v-else class="size-4" />
                            </Button>
                        </div>

                        <!-- Content Area -->
                        <div class="flex-1 overflow-auto bg-muted/20 min-h-[300px] lg:min-h-[400px] relative">
                            <!-- Loading State -->
                            <div
                                v-if="leftLoading && leftVersion"
                                class="absolute inset-0 flex items-center justify-center"
                            >
                                <div class="text-center">
                                    <Loader2 class="size-8 animate-spin text-muted-foreground mx-auto" />
                                    <p class="mt-2 text-sm text-muted-foreground">Loading...</p>
                                </div>
                            </div>

                            <!-- Error State -->
                            <div
                                v-else-if="leftError"
                                class="absolute inset-0 flex items-center justify-center"
                            >
                                <div class="text-center">
                                    <AlertCircle class="size-8 text-destructive mx-auto" />
                                    <p class="mt-2 text-sm text-destructive">Failed to load content</p>
                                </div>
                            </div>

                            <!-- No Selection State -->
                            <div
                                v-else-if="!leftVersion"
                                class="absolute inset-0 flex items-center justify-center"
                            >
                                <p class="text-sm text-muted-foreground">Select a version</p>
                            </div>

                            <!-- PDF Viewer -->
                            <iframe
                                v-if="leftVersion && isPdf(leftVersion.mime_type) && leftVersion.preview_url"
                                :src="leftVersion.preview_url"
                                class="w-full h-full min-h-[300px] lg:min-h-[400px]"
                                :class="{ 'invisible': leftLoading }"
                                @load="handleLeftLoad"
                                @error="handleLeftError"
                            />

                            <!-- Image Viewer -->
                            <div
                                v-else-if="leftVersion && isImage(leftVersion.mime_type) && leftVersion.preview_url"
                                class="w-full h-full flex items-center justify-center p-4"
                                :class="{ 'overflow-auto': leftZoomed, 'overflow-hidden': !leftZoomed }"
                            >
                                <img
                                    :src="leftVersion.preview_url"
                                    :alt="`Version ${leftVersion.version_number}`"
                                    class="transition-all duration-200"
                                    :class="{
                                        'max-w-full max-h-full object-contain': !leftZoomed,
                                        'max-w-none cursor-zoom-out': leftZoomed,
                                        'invisible': leftLoading
                                    }"
                                    @load="handleLeftLoad"
                                    @error="handleLeftError"
                                    @click="toggleLeftZoom"
                                />
                            </div>

                            <!-- Unsupported Type -->
                            <div
                                v-else-if="leftVersion && !supportsPreview(leftVersion.mime_type)"
                                class="absolute inset-0 flex items-center justify-center"
                            >
                                <div class="text-center">
                                    <p class="text-sm text-muted-foreground">
                                        Preview not available for this file type
                                    </p>
                                    <p class="text-xs text-muted-foreground mt-1">
                                        {{ leftVersion.file_type_label }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Version Viewer -->
                    <div class="flex flex-col border rounded-lg overflow-hidden">
                        <!-- Version Label -->
                        <div class="bg-muted/50 px-4 py-2 border-b flex items-center justify-between">
                            <span class="font-medium text-sm">
                                Version {{ rightVersion?.version_number }}
                            </span>
                            <Button
                                v-if="rightVersion && isImage(rightVersion.mime_type) && !rightError"
                                variant="ghost"
                                size="sm"
                                class="h-7 w-7 p-0"
                                @click="toggleRightZoom"
                            >
                                <ZoomIn v-if="!rightZoomed" class="size-4" />
                                <ZoomOut v-else class="size-4" />
                            </Button>
                        </div>

                        <!-- Content Area -->
                        <div class="flex-1 overflow-auto bg-muted/20 min-h-[300px] lg:min-h-[400px] relative">
                            <!-- Loading State -->
                            <div
                                v-if="rightLoading && rightVersion"
                                class="absolute inset-0 flex items-center justify-center"
                            >
                                <div class="text-center">
                                    <Loader2 class="size-8 animate-spin text-muted-foreground mx-auto" />
                                    <p class="mt-2 text-sm text-muted-foreground">Loading...</p>
                                </div>
                            </div>

                            <!-- Error State -->
                            <div
                                v-else-if="rightError"
                                class="absolute inset-0 flex items-center justify-center"
                            >
                                <div class="text-center">
                                    <AlertCircle class="size-8 text-destructive mx-auto" />
                                    <p class="mt-2 text-sm text-destructive">Failed to load content</p>
                                </div>
                            </div>

                            <!-- No Selection State -->
                            <div
                                v-else-if="!rightVersion"
                                class="absolute inset-0 flex items-center justify-center"
                            >
                                <p class="text-sm text-muted-foreground">Select a version</p>
                            </div>

                            <!-- PDF Viewer -->
                            <iframe
                                v-if="rightVersion && isPdf(rightVersion.mime_type) && rightVersion.preview_url"
                                :src="rightVersion.preview_url"
                                class="w-full h-full min-h-[300px] lg:min-h-[400px]"
                                :class="{ 'invisible': rightLoading }"
                                @load="handleRightLoad"
                                @error="handleRightError"
                            />

                            <!-- Image Viewer -->
                            <div
                                v-else-if="rightVersion && isImage(rightVersion.mime_type) && rightVersion.preview_url"
                                class="w-full h-full flex items-center justify-center p-4"
                                :class="{ 'overflow-auto': rightZoomed, 'overflow-hidden': !rightZoomed }"
                            >
                                <img
                                    :src="rightVersion.preview_url"
                                    :alt="`Version ${rightVersion.version_number}`"
                                    class="transition-all duration-200"
                                    :class="{
                                        'max-w-full max-h-full object-contain': !rightZoomed,
                                        'max-w-none cursor-zoom-out': rightZoomed,
                                        'invisible': rightLoading
                                    }"
                                    @load="handleRightLoad"
                                    @error="handleRightError"
                                    @click="toggleRightZoom"
                                />
                            </div>

                            <!-- Unsupported Type -->
                            <div
                                v-else-if="rightVersion && !supportsPreview(rightVersion.mime_type)"
                                class="absolute inset-0 flex items-center justify-center"
                            >
                                <div class="text-center">
                                    <p class="text-sm text-muted-foreground">
                                        Preview not available for this file type
                                    </p>
                                    <p class="text-xs text-muted-foreground mt-1">
                                        {{ rightVersion.file_type_label }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
