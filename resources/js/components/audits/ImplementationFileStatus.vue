<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Spinner } from '@/components/ui/spinner';
import { Badge } from '@/components/ui/badge';

interface ImplementationFile {
    id: number;
    original_name: string;
    version_number: number;
}

interface ImplementationFileStatusResponse {
    has_approved_file: boolean;
    implementation_file: ImplementationFile | null;
    error_message: string | null;
    implementation_files_url: string;
}

interface Props {
    /** Selected datacenter ID to check for implementation files */
    datacenterId: number | null;
    /** Selected audit type (only shown for 'connection' type) */
    auditType: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    /** Emitted when the implementation file status changes */
    'status-changed': [hasApprovedFile: boolean];
}>();

// State
const isLoading = ref(false);
const status = ref<ImplementationFileStatusResponse | null>(null);
const error = ref<string | null>(null);

// Only show this component for connection audits
const shouldShow = computed(() => {
    return props.auditType === 'connection' && props.datacenterId !== null;
});

// Computed properties for display
const hasApprovedFile = computed(() => status.value?.has_approved_file ?? false);
const implementationFile = computed(() => status.value?.implementation_file ?? null);
const errorMessage = computed(() => status.value?.error_message ?? null);
const implementationFilesUrl = computed(() => status.value?.implementation_files_url ?? '#');

/**
 * Fetch the implementation file status for the selected datacenter
 */
async function fetchImplementationFileStatus(datacenterId: number): Promise<void> {
    isLoading.value = true;
    error.value = null;

    try {
        const response = await fetch(`/api/audits/datacenters/${datacenterId}/implementation-file-status`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('Failed to fetch implementation file status');
        }

        status.value = await response.json();
        emit('status-changed', status.value?.has_approved_file ?? false);
    } catch (err) {
        console.error('Error fetching implementation file status:', err);
        error.value = 'Failed to check implementation file status. Please try again.';
        status.value = null;
        emit('status-changed', false);
    } finally {
        isLoading.value = false;
    }
}

// Watch for datacenter changes and fetch status
watch(
    () => [props.datacenterId, props.auditType],
    ([newDatacenterId, newAuditType]) => {
        if (newAuditType === 'connection' && newDatacenterId) {
            fetchImplementationFileStatus(newDatacenterId as number);
        } else {
            // Reset status when switching away from connection audit or clearing datacenter
            status.value = null;
            error.value = null;
            if (newAuditType !== 'connection') {
                emit('status-changed', true); // Non-connection audits don't need implementation files
            }
        }
    },
    { immediate: true }
);
</script>

<template>
    <div v-if="shouldShow" class="space-y-3">
        <!-- Loading State -->
        <div v-if="isLoading" class="flex items-center gap-2 text-sm text-muted-foreground">
            <Spinner class="h-4 w-4" />
            <span>Checking for approved implementation file...</span>
        </div>

        <!-- Error State (fetch error) -->
        <Alert v-else-if="error" variant="destructive">
            <svg
                class="h-4 w-4"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                />
            </svg>
            <AlertTitle>Error</AlertTitle>
            <AlertDescription>{{ error }}</AlertDescription>
        </Alert>

        <!-- Success State: Approved file exists -->
        <Alert v-else-if="hasApprovedFile && implementationFile" variant="default" class="border-green-500/50 bg-green-50 dark:bg-green-950/50">
            <svg
                class="h-4 w-4 text-green-600 dark:text-green-400"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                />
            </svg>
            <AlertTitle class="text-green-900 dark:text-green-200">Implementation File Found</AlertTitle>
            <AlertDescription class="text-green-800 dark:text-green-300">
                <div class="flex flex-wrap items-center gap-2">
                    <span>This audit will use:</span>
                    <Badge variant="secondary" class="font-mono text-xs">
                        {{ implementationFile.original_name }}
                    </Badge>
                    <Badge variant="outline" class="text-xs">
                        v{{ implementationFile.version_number }}
                    </Badge>
                </div>
            </AlertDescription>
        </Alert>

        <!-- Error State: No approved file -->
        <Alert v-else-if="!hasApprovedFile && status !== null" variant="destructive">
            <svg
                class="h-4 w-4"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                />
            </svg>
            <AlertTitle>No Approved Implementation File</AlertTitle>
            <AlertDescription>
                <p class="mb-2">{{ errorMessage }}</p>
                <Link
                    :href="implementationFilesUrl"
                    class="inline-flex items-center gap-1 font-medium text-destructive underline underline-offset-4 hover:no-underline"
                >
                    Go to Implementation Files
                    <svg
                        class="h-3 w-3"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                        />
                    </svg>
                </Link>
            </AlertDescription>
        </Alert>
    </div>
</template>
