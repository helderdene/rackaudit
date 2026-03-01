<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { CheckCircle, AlertTriangle } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { store as acknowledgeStore } from '@/actions/App/Http/Controllers/Api/DiscrepancyAcknowledgmentController';
import type { ComparisonResultData, DiscrepancyTypeValue } from '@/types/comparison';

interface Props {
    /** The discrepancy to acknowledge */
    discrepancy: ComparisonResultData | null;
    /** Whether the dialog is open */
    open?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    open: false,
});

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'acknowledged'): void;
}>();

const isSubmitting = ref(false);
const notes = ref('');
const error = ref<string | null>(null);

const isOpen = computed({
    get: () => props.open,
    set: (value: boolean) => emit('update:open', value),
});

/**
 * Get the status badge styling based on discrepancy type
 */
function getStatusBadgeClass(type: DiscrepancyTypeValue): string {
    switch (type) {
        case 'missing':
            return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
        case 'unexpected':
            return 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400';
        case 'mismatched':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';
        case 'conflicting':
            return 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400';
        default:
            return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400';
    }
}

/**
 * Get the source device and port display text
 */
const sourceDisplay = computed(() => {
    if (!props.discrepancy) return '-';
    const deviceName = props.discrepancy.source_device?.name ?? 'Unknown Device';
    const portLabel = props.discrepancy.source_port?.label ?? 'Unknown Port';
    return `${deviceName} (${portLabel})`;
});

/**
 * Get the destination device and port display text
 */
const destDisplay = computed(() => {
    if (!props.discrepancy) return '-';
    const deviceName = props.discrepancy.dest_device?.name ?? 'Unknown Device';
    const portLabel = props.discrepancy.dest_port?.label ?? props.discrepancy.actual_dest_port?.label ?? 'Unknown Port';
    return `${deviceName} (${portLabel})`;
});

/**
 * Get additional info text for mismatched connections
 */
const mismatchInfo = computed(() => {
    if (!props.discrepancy || props.discrepancy.discrepancy_type !== 'mismatched') return null;
    if (!props.discrepancy.actual_dest_port) return null;

    const expectedPort = props.discrepancy.dest_port?.label ?? 'Unknown';
    const actualPort = props.discrepancy.actual_dest_port.label;

    if (expectedPort !== actualPort) {
        return `Expected: ${expectedPort}, Actual: ${actualPort}`;
    }
    return null;
});

/**
 * Handle the acknowledgment form submission
 */
async function handleSubmit(): Promise<void> {
    if (!props.discrepancy) return;

    isSubmitting.value = true;
    error.value = null;

    // Build the request payload
    const payload: {
        expected_connection_id?: number;
        connection_id?: number;
        discrepancy_type: string;
        notes?: string;
    } = {
        discrepancy_type: props.discrepancy.discrepancy_type,
    };

    // Add the appropriate ID based on discrepancy type
    if (props.discrepancy.expected_connection?.id) {
        payload.expected_connection_id = props.discrepancy.expected_connection.id;
    }
    if (props.discrepancy.actual_connection?.id) {
        payload.connection_id = props.discrepancy.actual_connection.id;
    }

    // Add notes if provided
    if (notes.value.trim()) {
        payload.notes = notes.value.trim();
    }

    try {
        const response = await fetch(acknowledgeStore.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.message || 'Failed to acknowledge discrepancy.');
        }

        // Success - close dialog and emit event
        isOpen.value = false;
        notes.value = '';
        emit('acknowledged');
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'An unexpected error occurred.';
    } finally {
        isSubmitting.value = false;
    }
}

/**
 * Get CSRF token from cookies
 */
function getCsrfToken(): string {
    const match = document.cookie.match(/(^|;)\s*XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[2]) : '';
}

/**
 * Reset the dialog state when closed
 */
function handleOpenChange(open: boolean): void {
    if (!open) {
        notes.value = '';
        error.value = null;
    }
    isOpen.value = open;
}
</script>

<template>
    <Dialog :open="isOpen" @update:open="handleOpenChange">
        <DialogContent class="sm:max-w-md">
            <DialogHeader class="space-y-3">
                <DialogTitle class="flex items-center gap-2">
                    <CheckCircle class="size-5 text-muted-foreground" />
                    Acknowledge Discrepancy
                </DialogTitle>
                <DialogDescription>
                    Mark this discrepancy as reviewed. You can add optional notes
                    to explain why the discrepancy is being acknowledged.
                </DialogDescription>
            </DialogHeader>

            <!-- Discrepancy Summary -->
            <div v-if="discrepancy" class="space-y-4">
                <div class="rounded-lg border bg-muted/30 p-4 dark:bg-muted/20">
                    <div class="space-y-3 text-sm">
                        <!-- Status Badge -->
                        <div class="flex items-center gap-2">
                            <span class="text-muted-foreground">Status:</span>
                            <Badge :class="getStatusBadgeClass(discrepancy.discrepancy_type)">
                                {{ discrepancy.discrepancy_type_label }}
                            </Badge>
                        </div>

                        <!-- Source -->
                        <div class="flex items-center gap-2">
                            <span class="text-muted-foreground">From:</span>
                            <span class="font-medium">{{ sourceDisplay }}</span>
                        </div>

                        <!-- Destination -->
                        <div class="flex items-center gap-2">
                            <span class="text-muted-foreground">To:</span>
                            <span class="font-medium">{{ destDisplay }}</span>
                        </div>

                        <!-- Mismatch Info -->
                        <div
                            v-if="mismatchInfo"
                            class="flex items-start gap-2 text-amber-600 dark:text-amber-400"
                        >
                            <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                            <span class="text-xs">{{ mismatchInfo }}</span>
                        </div>

                        <!-- Conflict Info -->
                        <div
                            v-if="discrepancy.conflict_info"
                            class="text-purple-600 dark:text-purple-400"
                        >
                            <div class="flex items-start gap-2">
                                <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                                <div class="text-xs">
                                    <p class="font-medium">Conflicting expectations:</p>
                                    <ul class="mt-1 list-inside list-disc">
                                        <li
                                            v-for="file in discrepancy.conflict_info.conflicting_files"
                                            :key="file.file_id"
                                        >
                                            {{ file.file_name }}: {{ file.dest_port_label }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notes Field -->
                <div class="space-y-2">
                    <Label for="notes">Notes (optional)</Label>
                    <Textarea
                        id="notes"
                        v-model="notes"
                        placeholder="Add a note explaining why this discrepancy is being acknowledged..."
                        class="min-h-[100px]"
                        :disabled="isSubmitting"
                    />
                </div>

                <!-- Error Message -->
                <div
                    v-if="error"
                    class="rounded-lg border border-red-100 bg-red-50 p-3 text-sm text-red-600 dark:border-red-200/10 dark:bg-red-700/10 dark:text-red-100"
                >
                    {{ error }}
                </div>
            </div>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary" :disabled="isSubmitting">
                        Cancel
                    </Button>
                </DialogClose>

                <Button
                    :disabled="isSubmitting || !discrepancy"
                    @click="handleSubmit"
                >
                    {{ isSubmitting ? 'Acknowledging...' : 'Acknowledge' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
