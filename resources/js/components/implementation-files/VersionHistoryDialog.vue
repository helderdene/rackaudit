<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import axios from 'axios';
import { Download, Eye, GitCompare, History, RotateCcw } from 'lucide-vue-next';
import { ref, watch } from 'vue';

export interface VersionFile {
    id: number;
    file_name: string;
    original_name: string;
    description: string | null;
    mime_type: string;
    formatted_file_size: string;
    file_type_label: string;
    version_number: number;
    version_group_id: number;
    is_latest_version: boolean;
    has_multiple_versions: boolean;
    uploader: {
        id: number;
        name: string;
    } | null;
    created_at: string;
    download_url: string;
    preview_url?: string;
}

interface Props {
    fileId: number;
    fileName: string;
    datacenterId: number;
    canRestore?: boolean;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    canRestore: false,
    disabled: false,
});

const emit = defineEmits<{
    (e: 'version-restored', newVersion: VersionFile): void;
    (e: 'close'): void;
    (e: 'open-restore-dialog', version: VersionFile): void;
    (
        e: 'open-compare-dialog',
        leftVersion: VersionFile,
        rightVersion: VersionFile,
    ): void;
}>();

const isOpen = ref(false);
const isLoading = ref(false);
const versions = ref<VersionFile[]>([]);
const error = ref<string | null>(null);

/**
 * Fetch versions from the API when the modal opens
 */
const fetchVersions = async () => {
    isLoading.value = true;
    error.value = null;

    try {
        const response = await axios.get<{ data: VersionFile[] }>(
            `/datacenters/${props.datacenterId}/implementation-files/${props.fileId}/versions`,
        );
        versions.value = response.data.data;
    } catch (err) {
        const axiosError = err as {
            response?: { data?: { message?: string } };
        };
        error.value =
            axiosError.response?.data?.message ||
            'Failed to load version history.';
    } finally {
        isLoading.value = false;
    }
};

/**
 * Refresh the versions list (called after a restore)
 */
const refreshVersions = () => {
    if (isOpen.value) {
        fetchVersions();
    }
};

/**
 * Watch for modal open/close
 */
watch(isOpen, (open) => {
    if (open) {
        fetchVersions();
    } else {
        emit('close');
    }
});

/**
 * Formats a date string to relative time (e.g., "2 hours ago") or formatted date
 */
const formatDate = (dateString: string): string => {
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

    if (diffInSeconds < 60) {
        return 'just now';
    }

    const diffInMinutes = Math.floor(diffInSeconds / 60);
    if (diffInMinutes < 60) {
        return `${diffInMinutes} minute${diffInMinutes !== 1 ? 's' : ''} ago`;
    }

    const diffInHours = Math.floor(diffInMinutes / 60);
    if (diffInHours < 24) {
        return `${diffInHours} hour${diffInHours !== 1 ? 's' : ''} ago`;
    }

    const diffInDays = Math.floor(diffInHours / 24);
    if (diffInDays < 7) {
        return `${diffInDays} day${diffInDays !== 1 ? 's' : ''} ago`;
    }

    // For older dates, show the actual date
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

/**
 * Check if a file supports preview (PDF or images)
 */
const supportsPreview = (mimeType: string): boolean => {
    return mimeType === 'application/pdf' || mimeType.startsWith('image/');
};

/**
 * Opens preview in a new browser tab
 */
const openPreview = (version: VersionFile): void => {
    if (version.preview_url) {
        window.open(version.preview_url, '_blank');
    }
};

/**
 * Initiates file download
 */
const downloadFile = (version: VersionFile): void => {
    window.location.href = version.download_url;
};

/**
 * Opens restore confirmation dialog
 */
const handleRestore = (version: VersionFile): void => {
    emit('open-restore-dialog', version);
};

/**
 * Opens comparison dialog with adjacent version
 */
const handleCompare = (version: VersionFile, index: number): void => {
    // Compare with the next version (older) if available
    const adjacentVersion = versions.value[index + 1];
    if (adjacentVersion) {
        emit('open-compare-dialog', version, adjacentVersion);
    }
};

/**
 * Check if version can be compared (has an older version)
 */
const canCompare = (index: number): boolean => {
    return index < versions.value.length - 1;
};

// Expose methods for parent components
defineExpose({
    refreshVersions,
});
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogTrigger as-child>
            <slot>
                <Button
                    variant="outline"
                    size="sm"
                    class="h-8 gap-1"
                    :disabled="disabled"
                >
                    <History class="size-3.5" />
                    <span class="sr-only sm:not-sr-only">History</span>
                </Button>
            </slot>
        </DialogTrigger>
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>Version History</DialogTitle>
                <p class="text-sm text-muted-foreground">
                    {{ fileName }}
                </p>
            </DialogHeader>

            <div class="mt-4 max-h-[60vh] overflow-y-auto">
                <!-- Loading state with skeletons -->
                <div v-if="isLoading" class="space-y-3">
                    <div
                        v-for="i in 3"
                        :key="i"
                        class="flex items-center justify-between rounded-lg border p-4"
                    >
                        <div class="flex-1 space-y-2">
                            <Skeleton class="h-4 w-24" />
                            <Skeleton class="h-3 w-48" />
                        </div>
                        <div class="flex gap-2">
                            <Skeleton class="h-8 w-20" />
                            <Skeleton class="h-8 w-20" />
                        </div>
                    </div>
                </div>

                <!-- Error state -->
                <div
                    v-else-if="error"
                    class="flex flex-col items-center justify-center py-8 text-center"
                >
                    <p class="text-sm text-destructive">{{ error }}</p>
                    <Button
                        variant="outline"
                        size="sm"
                        class="mt-4"
                        @click="fetchVersions"
                    >
                        Try Again
                    </Button>
                </div>

                <!-- Version list -->
                <div v-else-if="versions.length > 0" class="space-y-2">
                    <div
                        v-for="(version, index) in versions"
                        :key="version.id"
                        class="rounded-lg border p-4 transition-colors"
                        :class="{
                            'border-primary/50 bg-primary/5':
                                version.is_latest_version,
                            'hover:bg-muted/50': !version.is_latest_version,
                        }"
                    >
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <!-- Version info -->
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium">
                                        Version {{ version.version_number }}
                                    </span>
                                    <Badge
                                        v-if="version.is_latest_version"
                                        variant="success"
                                        class="text-xs"
                                    >
                                        Current
                                    </Badge>
                                </div>
                                <div
                                    class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground"
                                >
                                    <span>{{
                                        formatDate(version.created_at)
                                    }}</span>
                                    <span class="hidden sm:inline">-</span>
                                    <span v-if="version.uploader">{{
                                        version.uploader.name
                                    }}</span>
                                    <span v-else>Unknown user</span>
                                    <span class="hidden sm:inline">-</span>
                                    <span>{{
                                        version.formatted_file_size
                                    }}</span>
                                </div>
                            </div>

                            <!-- Action buttons -->
                            <div class="flex flex-wrap items-center gap-2">
                                <!-- Download button -->
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="h-8 gap-1"
                                    @click="downloadFile(version)"
                                >
                                    <Download class="size-3.5" />
                                    <span class="sr-only sm:not-sr-only"
                                        >Download</span
                                    >
                                </Button>

                                <!-- Preview button (PDF/images only) -->
                                <Button
                                    v-if="
                                        supportsPreview(version.mime_type) &&
                                        version.preview_url
                                    "
                                    variant="outline"
                                    size="sm"
                                    class="h-8 gap-1"
                                    @click="openPreview(version)"
                                >
                                    <Eye class="size-3.5" />
                                    <span class="sr-only sm:not-sr-only"
                                        >Preview</span
                                    >
                                </Button>

                                <!-- Compare button (if there's an older version) -->
                                <Button
                                    v-if="canCompare(index)"
                                    variant="outline"
                                    size="sm"
                                    class="h-8 gap-1"
                                    @click="handleCompare(version, index)"
                                >
                                    <GitCompare class="size-3.5" />
                                    <span class="sr-only sm:not-sr-only"
                                        >Compare</span
                                    >
                                </Button>

                                <!-- Restore button (hidden for current version) -->
                                <Button
                                    v-if="
                                        canRestore && !version.is_latest_version
                                    "
                                    variant="outline"
                                    size="sm"
                                    class="h-8 gap-1"
                                    @click="handleRestore(version)"
                                >
                                    <RotateCcw class="size-3.5" />
                                    <span class="sr-only sm:not-sr-only"
                                        >Restore</span
                                    >
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty state -->
                <div
                    v-else
                    class="flex flex-col items-center justify-center py-8 text-center"
                >
                    <History class="size-12 text-muted-foreground/50" />
                    <p class="mt-2 text-sm text-muted-foreground">
                        No version history available.
                    </p>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
