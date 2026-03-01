<script setup lang="ts">
import ParseConnectionsButton from '@/components/expected-connections/ParseConnectionsButton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    CheckCircle,
    ChevronDown,
    Download,
    Eye,
    Filter,
    GitCompare,
    History,
    Scale,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ApproveImplementationFileDialog from './ApproveImplementationFileDialog.vue';
import DeleteImplementationFileDialog from './DeleteImplementationFileDialog.vue';
import FileTypeIcon from './FileTypeIcon.vue';
import RestoreVersionDialog from './RestoreVersionDialog.vue';
import VersionCompareDialog from './VersionCompareDialog.vue';
import type { VersionFile } from './VersionHistoryDialog.vue';
import VersionHistoryDialog from './VersionHistoryDialog.vue';

export interface ImplementationFile {
    id: number;
    file_name: string;
    original_name: string;
    description: string | null;
    mime_type: string;
    formatted_file_size: string;
    file_type_label: string;
    uploader: {
        id: number;
        name: string;
    } | null;
    version_number: number;
    has_multiple_versions: boolean;
    is_latest_version: boolean;
    version_group_id: number;
    created_at: string;
    download_url: string;
    preview_url?: string;
    // Approval fields
    approval_status: 'pending_approval' | 'approved';
    approved_at: string | null;
    approver: { id: number; name: string } | null;
    can_approve: boolean;
    approve_url?: string;
    // Expected connections field
    has_confirmed_connections?: boolean;
}

type ApprovalFilter = 'all' | 'pending_approval' | 'approved';

interface Props {
    files: ImplementationFile[];
    canUpload: boolean;
    canDelete: boolean;
    datacenterId: number;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'file-deleted', fileId: number): void;
    (e: 'files-updated'): void;
}>();

// Get current user ID for separation of duties check
const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id);

// Filter state
const approvalFilter = ref<ApprovalFilter>('all');

// Computed filtered files
const filteredFiles = computed(() => {
    if (approvalFilter.value === 'all') {
        return props.files;
    }
    return props.files.filter(
        (file) => file.approval_status === approvalFilter.value,
    );
});

// Filter options
const filterOptions: { value: ApprovalFilter; label: string }[] = [
    { value: 'all', label: 'All Files' },
    { value: 'pending_approval', label: 'Pending Approval' },
    { value: 'approved', label: 'Approved' },
];

const currentFilterLabel = computed(() => {
    const option = filterOptions.find((o) => o.value === approvalFilter.value);
    return option?.label ?? 'All Files';
});

// State for tracking which file's dialogs are open
const restoreDialogOpen = ref(false);
const restoreVersion = ref<VersionFile | null>(null);
const restoreFileId = ref<number | null>(null);
const restoreFileName = ref<string>('');

const compareDialogOpen = ref(false);
const compareFileId = ref<number | null>(null);
const compareFileName = ref<string>('');
const compareVersions = ref<VersionFile[]>([]);
const compareLeftVersion = ref<VersionFile | null>(null);
const compareRightVersion = ref<VersionFile | null>(null);
const isLoadingVersions = ref(false);

// Approval dialog state
const approveDialogOpen = ref(false);
const approveFile = ref<ImplementationFile | null>(null);

/**
 * Formats a date string to relative time (e.g., "2 hours ago")
 */
const formatRelativeTime = (dateString: string): string => {
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

    const diffInWeeks = Math.floor(diffInDays / 7);
    if (diffInWeeks < 4) {
        return `${diffInWeeks} week${diffInWeeks !== 1 ? 's' : ''} ago`;
    }

    const diffInMonths = Math.floor(diffInDays / 30);
    if (diffInMonths < 12) {
        return `${diffInMonths} month${diffInMonths !== 1 ? 's' : ''} ago`;
    }

    const diffInYears = Math.floor(diffInDays / 365);
    return `${diffInYears} year${diffInYears !== 1 ? 's' : ''} ago`;
};

/**
 * Formats a date string to a user-friendly format (e.g., "Jan 15, 2025")
 */
const formatDate = (dateString: string): string => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

/**
 * Checks if a file supports preview (PDF or images)
 */
const supportsPreview = (mimeType: string): boolean => {
    return mimeType === 'application/pdf' || mimeType.startsWith('image/');
};

/**
 * Opens preview in a new browser tab
 */
const openPreview = (file: ImplementationFile): void => {
    if (file.preview_url) {
        window.open(file.preview_url, '_blank');
    }
};

/**
 * Initiates file download
 */
const downloadFile = (file: ImplementationFile): void => {
    window.location.href = file.download_url;
};

/**
 * Check if current user is the uploader
 */
const isUploader = (file: ImplementationFile): boolean => {
    return file.uploader?.id === currentUserId.value;
};

/**
 * Check if the file can show the Compare Connections button
 * Requires: approved status and has confirmed expected connections
 */
const canCompareConnections = (file: ImplementationFile): boolean => {
    return (
        file.approval_status === 'approved' &&
        file.has_confirmed_connections === true
    );
};

/**
 * Open approve dialog for a file
 */
const openApproveDialog = (file: ImplementationFile): void => {
    approveFile.value = file;
    approveDialogOpen.value = true;
};

/**
 * Handle successful file approval
 */
const handleFileApproved = (): void => {
    approveDialogOpen.value = false;
    approveFile.value = null;
    // Refresh the page to get updated file list
    router.reload({ only: ['implementationFiles'] });
    emit('files-updated');
};

/**
 * Handle file deletion
 */
const handleFileDeleted = (fileId: number): void => {
    emit('file-deleted', fileId);
};

/**
 * Handle restore dialog open from version history
 */
const handleOpenRestoreDialog = (version: VersionFile): void => {
    restoreVersion.value = version;
    restoreFileId.value = version.id;
    restoreFileName.value = version.original_name;
    restoreDialogOpen.value = true;
};

/**
 * Handle version restored
 */
const handleVersionRestored = (_newVersion: VersionFile): void => {
    restoreDialogOpen.value = false;
    restoreVersion.value = null;
    // Refresh the page to show the updated file list
    router.reload({ only: ['implementationFiles'] });
    emit('files-updated');
};

/**
 * Handle compare dialog open from version history
 */
const handleOpenCompareDialog = (
    leftVersion: VersionFile,
    rightVersion: VersionFile,
): void => {
    compareLeftVersion.value = leftVersion;
    compareRightVersion.value = rightVersion;
    // We need to fetch all versions for the comparison dialog dropdowns
    fetchVersionsForCompare(leftVersion.id, leftVersion.original_name);
};

/**
 * Open compare dialog directly from file list
 */
const openCompareDialog = async (file: ImplementationFile): Promise<void> => {
    if (!file.has_multiple_versions) return;

    isLoadingVersions.value = true;
    compareFileId.value = file.id;
    compareFileName.value = file.original_name;

    try {
        const response = await axios.get<{ data: VersionFile[] }>(
            `/datacenters/${props.datacenterId}/implementation-files/${file.id}/versions`,
        );
        compareVersions.value = response.data.data;

        // Default: compare latest with previous version
        if (compareVersions.value.length >= 2) {
            compareLeftVersion.value = compareVersions.value[0];
            compareRightVersion.value = compareVersions.value[1];
        }

        compareDialogOpen.value = true;
    } catch (error) {
        console.error('Failed to fetch versions for comparison:', error);
    } finally {
        isLoadingVersions.value = false;
    }
};

/**
 * Fetch versions for compare dialog when opened from version history
 */
const fetchVersionsForCompare = async (
    fileId: number,
    fileName: string,
): Promise<void> => {
    isLoadingVersions.value = true;
    compareFileId.value = fileId;
    compareFileName.value = fileName;

    try {
        const response = await axios.get<{ data: VersionFile[] }>(
            `/datacenters/${props.datacenterId}/implementation-files/${fileId}/versions`,
        );
        compareVersions.value = response.data.data;
        compareDialogOpen.value = true;
    } catch (error) {
        console.error('Failed to fetch versions for comparison:', error);
    } finally {
        isLoadingVersions.value = false;
    }
};

/**
 * Handle compare dialog close
 */
const handleCompareDialogClose = (): void => {
    compareDialogOpen.value = false;
    compareVersions.value = [];
    compareLeftVersion.value = null;
    compareRightVersion.value = null;
    compareFileId.value = null;
};
</script>

<template>
    <div class="w-full">
        <!-- Filter dropdown -->
        <div v-if="files.length > 0" class="mb-4 flex items-center justify-end">
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button variant="outline" size="sm" class="gap-2">
                        <Filter class="size-4" />
                        {{ currentFilterLabel }}
                        <ChevronDown class="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem
                        v-for="option in filterOptions"
                        :key="option.value"
                        :class="{
                            'bg-accent': approvalFilter === option.value,
                        }"
                        @click="approvalFilter = option.value"
                    >
                        {{ option.label }}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>

        <!-- Empty state -->
        <div
            v-if="files.length === 0"
            class="flex flex-col items-center justify-center py-12 text-center"
        >
            <div
                class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-muted"
            >
                <FileTypeIcon
                    mime-type="application/pdf"
                    class="size-6 text-muted-foreground"
                />
            </div>
            <h3 class="text-sm font-medium">No implementation files</h3>
            <p class="mt-1 text-sm text-muted-foreground">
                {{
                    canUpload
                        ? 'Upload implementation specification documents to get started.'
                        : 'No files have been uploaded yet.'
                }}
            </p>
        </div>

        <!-- Filtered empty state -->
        <div
            v-else-if="filteredFiles.length === 0"
            class="flex flex-col items-center justify-center py-12 text-center"
        >
            <div
                class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-muted"
            >
                <Filter class="size-6 text-muted-foreground" />
            </div>
            <h3 class="text-sm font-medium">No matching files</h3>
            <p class="mt-1 text-sm text-muted-foreground">
                No files match the current filter. Try selecting a different
                filter option.
            </p>
        </div>

        <!-- File list table -->
        <div v-else class="overflow-hidden rounded-md border">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th
                                class="h-10 px-4 text-left font-medium text-muted-foreground"
                            >
                                Name
                            </th>
                            <th
                                class="hidden h-10 px-4 text-left font-medium text-muted-foreground sm:table-cell"
                            >
                                Type
                            </th>
                            <th
                                class="hidden h-10 px-4 text-left font-medium text-muted-foreground md:table-cell"
                            >
                                Size
                            </th>
                            <th
                                class="hidden h-10 px-4 text-left font-medium text-muted-foreground lg:table-cell"
                            >
                                Uploaded By
                            </th>
                            <th
                                class="hidden h-10 px-4 text-left font-medium text-muted-foreground md:table-cell"
                            >
                                Date
                            </th>
                            <th
                                class="h-10 w-[350px] px-4 text-left font-medium text-muted-foreground"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="file in filteredFiles"
                            :key="file.id"
                            class="border-b transition-colors last:border-b-0 hover:bg-muted/50"
                        >
                            <!-- Name column with file type icon on mobile -->
                            <td class="p-4">
                                <div class="flex items-center gap-3">
                                    <FileTypeIcon
                                        :mime-type="file.mime_type"
                                        class="sm:hidden"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <p
                                                class="truncate font-medium"
                                                :title="file.original_name"
                                            >
                                                {{ file.original_name }}
                                            </p>
                                            <!-- Version badge for files with multiple versions -->
                                            <Badge
                                                v-if="
                                                    file.has_multiple_versions
                                                "
                                                variant="secondary"
                                                class="shrink-0 text-xs"
                                            >
                                                v{{ file.version_number }}
                                            </Badge>
                                            <!-- Approval status badge -->
                                            <Badge
                                                v-if="
                                                    file.approval_status ===
                                                    'pending_approval'
                                                "
                                                variant="warning"
                                                class="shrink-0 text-xs"
                                            >
                                                Pending Approval
                                            </Badge>
                                            <TooltipProvider
                                                v-else-if="
                                                    file.approval_status ===
                                                    'approved'
                                                "
                                                :delay-duration="0"
                                            >
                                                <Tooltip>
                                                    <TooltipTrigger as-child>
                                                        <Badge
                                                            variant="success"
                                                            class="shrink-0 cursor-help text-xs"
                                                        >
                                                            Approved
                                                        </Badge>
                                                    </TooltipTrigger>
                                                    <TooltipContent
                                                        v-if="
                                                            file.approver &&
                                                            file.approved_at
                                                        "
                                                    >
                                                        <p>
                                                            Approved by
                                                            {{
                                                                file.approver
                                                                    .name
                                                            }}
                                                            on
                                                            {{
                                                                formatDate(
                                                                    file.approved_at,
                                                                )
                                                            }}
                                                        </p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        </div>
                                        <p
                                            v-if="file.description"
                                            class="mt-0.5 truncate text-xs text-muted-foreground"
                                            :title="file.description"
                                        >
                                            {{ file.description }}
                                        </p>
                                        <!-- Approver info for approved files (visible in expanded view) -->
                                        <p
                                            v-if="
                                                file.approval_status ===
                                                    'approved' &&
                                                file.approver &&
                                                file.approved_at
                                            "
                                            class="mt-0.5 text-xs text-muted-foreground lg:hidden"
                                        >
                                            Approved by
                                            {{ file.approver.name }} on
                                            {{ formatDate(file.approved_at) }}
                                        </p>
                                        <!-- Mobile: Show size and date inline -->
                                        <div
                                            class="mt-1 flex items-center gap-2 text-xs text-muted-foreground sm:hidden"
                                        >
                                            <span>{{
                                                file.formatted_file_size
                                            }}</span>
                                            <span>-</span>
                                            <span>{{
                                                formatRelativeTime(
                                                    file.created_at,
                                                )
                                            }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Type column (hidden on mobile) -->
                            <td class="hidden p-4 sm:table-cell">
                                <div class="flex items-center gap-2">
                                    <FileTypeIcon :mime-type="file.mime_type" />
                                    <span class="text-muted-foreground">{{
                                        file.file_type_label
                                    }}</span>
                                </div>
                            </td>

                            <!-- Size column (hidden on mobile/tablet) -->
                            <td
                                class="hidden p-4 text-muted-foreground md:table-cell"
                            >
                                {{ file.formatted_file_size }}
                            </td>

                            <!-- Uploaded By column (hidden on smaller screens) -->
                            <td class="hidden p-4 lg:table-cell">
                                <span v-if="file.uploader">{{
                                    file.uploader.name
                                }}</span>
                                <span v-else class="text-muted-foreground"
                                    >Unknown</span
                                >
                            </td>

                            <!-- Date column (hidden on mobile) -->
                            <td
                                class="hidden p-4 text-muted-foreground md:table-cell"
                            >
                                {{ formatRelativeTime(file.created_at) }}
                            </td>

                            <!-- Actions column -->
                            <td class="p-4">
                                <div
                                    class="flex flex-col gap-1 sm:flex-row sm:flex-wrap sm:items-center sm:gap-2"
                                >
                                    <!-- Download button -->
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="h-8 gap-1"
                                        @click="downloadFile(file)"
                                    >
                                        <Download class="size-3.5" />
                                        <span class="sr-only sm:not-sr-only"
                                            >Download</span
                                        >
                                    </Button>

                                    <!-- Preview button (PDF and images) -->
                                    <Button
                                        v-if="supportsPreview(file.mime_type)"
                                        variant="outline"
                                        size="sm"
                                        class="h-8 gap-1"
                                        @click="openPreview(file)"
                                    >
                                        <Eye class="size-3.5" />
                                        <span class="sr-only sm:not-sr-only"
                                            >Preview</span
                                        >
                                    </Button>

                                    <!-- Parse Connections button (only for approved Excel/CSV files) -->
                                    <ParseConnectionsButton
                                        :implementation-file-id="file.id"
                                        :datacenter-id="datacenterId"
                                        :mime-type="file.mime_type"
                                        :is-approved="
                                            file.approval_status === 'approved'
                                        "
                                    />

                                    <!-- Compare Connections button (only for approved files with confirmed connections) -->
                                    <Link
                                        v-if="canCompareConnections(file)"
                                        :href="`/implementation-files/${file.id}/comparison`"
                                    >
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="h-8 gap-1"
                                        >
                                            <Scale class="size-3.5" />
                                            <span class="sr-only sm:not-sr-only"
                                                >Compare</span
                                            >
                                        </Button>
                                    </Link>

                                    <!-- Approve button (only for authorized users on pending files) -->
                                    <TooltipProvider
                                        v-if="
                                            file.approval_status ===
                                                'pending_approval' &&
                                            file.can_approve
                                        "
                                        :delay-duration="0"
                                    >
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <span>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        class="h-8 gap-1"
                                                        :disabled="
                                                            isUploader(file)
                                                        "
                                                        @click="
                                                            !isUploader(file) &&
                                                            openApproveDialog(
                                                                file,
                                                            )
                                                        "
                                                    >
                                                        <CheckCircle
                                                            class="size-3.5"
                                                        />
                                                        <span
                                                            class="sr-only sm:not-sr-only"
                                                            >Approve</span
                                                        >
                                                    </Button>
                                                </span>
                                            </TooltipTrigger>
                                            <TooltipContent
                                                v-if="isUploader(file)"
                                            >
                                                <p>
                                                    You cannot approve files you
                                                    uploaded
                                                </p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>

                                    <!-- History button (for all files) -->
                                    <VersionHistoryDialog
                                        :file-id="file.id"
                                        :file-name="file.original_name"
                                        :datacenter-id="datacenterId"
                                        :can-restore="canUpload"
                                        @open-restore-dialog="
                                            handleOpenRestoreDialog
                                        "
                                        @open-compare-dialog="
                                            handleOpenCompareDialog
                                        "
                                    >
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="h-8 gap-1"
                                        >
                                            <History class="size-3.5" />
                                            <span class="sr-only sm:not-sr-only"
                                                >History</span
                                            >
                                        </Button>
                                    </VersionHistoryDialog>

                                    <!-- Compare Versions button (only for files with multiple versions) -->
                                    <Button
                                        v-if="file.has_multiple_versions"
                                        variant="outline"
                                        size="sm"
                                        class="h-8 gap-1"
                                        :disabled="
                                            isLoadingVersions &&
                                            compareFileId === file.id
                                        "
                                        @click="openCompareDialog(file)"
                                    >
                                        <GitCompare class="size-3.5" />
                                        <span class="sr-only sm:not-sr-only"
                                            >Versions</span
                                        >
                                    </Button>

                                    <!-- Delete button (with confirmation dialog) -->
                                    <DeleteImplementationFileDialog
                                        v-if="canDelete"
                                        :file-id="file.id"
                                        :file-name="file.original_name"
                                        :datacenter-id="datacenterId"
                                        @file-deleted="handleFileDeleted"
                                    >
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="h-8 text-destructive hover:bg-destructive hover:text-destructive-foreground"
                                        >
                                            Delete
                                        </Button>
                                    </DeleteImplementationFileDialog>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Restore Version Dialog -->
        <RestoreVersionDialog
            v-if="restoreVersion"
            :is-open="restoreDialogOpen"
            :file-id="restoreVersion.id"
            :version-number="restoreVersion.version_number"
            :datacenter-id="datacenterId"
            :file-name="restoreFileName"
            @update:is-open="restoreDialogOpen = $event"
            @version-restored="handleVersionRestored"
            @close="
                restoreDialogOpen = false;
                restoreVersion = null;
            "
        />

        <!-- Version Compare Dialog -->
        <VersionCompareDialog
            v-if="compareFileId"
            :is-open="compareDialogOpen"
            :file-id="compareFileId"
            :file-name="compareFileName"
            :versions="compareVersions"
            :initial-left-version="compareLeftVersion"
            :initial-right-version="compareRightVersion"
            @update:is-open="compareDialogOpen = $event"
            @close="handleCompareDialogClose"
        />

        <!-- Approve Implementation File Dialog -->
        <ApproveImplementationFileDialog
            v-if="approveFile"
            :is-open="approveDialogOpen"
            :file="approveFile"
            :datacenter-id="datacenterId"
            @update:is-open="approveDialogOpen = $event"
            @file-approved="handleFileApproved"
            @close="
                approveDialogOpen = false;
                approveFile = null;
            "
        />
    </div>
</template>
