<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { CheckCircle, XCircle, AlertTriangle } from 'lucide-vue-next';

interface VerificationData {
    id: number;
    comparison_status: string;
    comparison_status_label: string;
    verification_status: string;
    source_device: {
        id: number;
        name: string;
    } | null;
    source_port: {
        id: number;
        label: string;
    } | null;
    dest_device: {
        id: number;
        name: string;
    } | null;
    dest_port: {
        id: number;
        label: string;
    } | null;
    row_number: number | null;
}

interface DiscrepancyTypeOption {
    value: string;
    label: string;
}

interface Props {
    open: boolean;
    verification: VerificationData | null;
    discrepancyTypes: DiscrepancyTypeOption[];
    isLoading: boolean;
    error: string | null;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'submit', data: { action: 'verified' | 'discrepant'; discrepancyType?: string; notes: string }): void;
}>();

// Shared select styles
const selectClass = 'flex h-10 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50';

// Form state
const action = ref<'verified' | 'discrepant'>('verified');
const discrepancyType = ref<string>('');
const notes = ref<string>('');

// Validation
const notesError = ref<string | null>(null);

// Computed
const connectionDescription = computed(() => {
    if (!props.verification) return '';

    const parts: string[] = [];
    if (props.verification.row_number) {
        parts.push(`Row ${props.verification.row_number}`);
    }
    if (props.verification.source_device?.name && props.verification.source_port?.label) {
        parts.push(`${props.verification.source_device.name}:${props.verification.source_port.label}`);
    }
    if (props.verification.dest_device?.name && props.verification.dest_port?.label) {
        parts.push(`${props.verification.dest_device.name}:${props.verification.dest_port.label}`);
    }
    return parts.join(' - ') || 'Connection';
});

const showDiscrepancyTypeSelect = computed(() => action.value === 'discrepant');

const isSubmitDisabled = computed(() => {
    if (props.isLoading) return true;
    if (action.value === 'discrepant') {
        if (!discrepancyType.value) return true;
        if (!notes.value.trim()) return true;
    }
    return false;
});

// Reset form when dialog opens
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            action.value = 'verified';
            discrepancyType.value = '';
            notes.value = '';
            notesError.value = null;
        }
    }
);

// Clear notes error when action changes
watch(action, () => {
    notesError.value = null;
});

/**
 * Validate and submit the form
 */
function handleSubmit(): void {
    notesError.value = null;

    // Validate notes for discrepant
    if (action.value === 'discrepant' && !notes.value.trim()) {
        notesError.value = 'Notes are required when marking as discrepant.';
        return;
    }

    emit('submit', {
        action: action.value,
        discrepancyType: action.value === 'discrepant' ? discrepancyType.value : undefined,
        notes: notes.value.trim(),
    });
}

/**
 * Close the dialog
 */
function handleClose(): void {
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Verify Connection</DialogTitle>
                <DialogDescription>
                    {{ connectionDescription }}
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 py-4">
                <!-- Connection info summary -->
                <div
                    v-if="verification"
                    class="rounded-lg border bg-muted/30 p-3 text-sm"
                >
                    <div class="flex items-center gap-2">
                        <span class="font-medium">Comparison Result:</span>
                        <span
                            :class="{
                                'text-green-600 dark:text-green-400': verification.comparison_status === 'matched',
                                'text-yellow-600 dark:text-yellow-400': verification.comparison_status === 'mismatched',
                                'text-red-600 dark:text-red-400': ['missing', 'unexpected'].includes(verification.comparison_status),
                                'text-purple-600 dark:text-purple-400': verification.comparison_status === 'conflicting',
                            }"
                        >
                            {{ verification.comparison_status_label }}
                        </span>
                    </div>
                </div>

                <!-- Action Selection -->
                <div class="space-y-3">
                    <Label class="text-sm font-medium">Verification Result</Label>
                    <div data-tour="action-buttons" class="grid grid-cols-2 gap-3">
                        <button
                            type="button"
                            class="flex items-center gap-3 rounded-lg border p-3 text-left transition-colors hover:bg-muted/50"
                            :class="{
                                'border-green-500 bg-green-50 dark:bg-green-900/20': action === 'verified',
                            }"
                            @click="action = 'verified'"
                        >
                            <CheckCircle
                                class="size-5"
                                :class="action === 'verified' ? 'text-green-600' : 'text-muted-foreground'"
                            />
                            <div>
                                <div class="font-medium">Verified</div>
                                <div class="text-xs text-muted-foreground">Connection confirmed</div>
                            </div>
                        </button>
                        <button
                            type="button"
                            class="flex items-center gap-3 rounded-lg border p-3 text-left transition-colors hover:bg-muted/50"
                            :class="{
                                'border-red-500 bg-red-50 dark:bg-red-900/20': action === 'discrepant',
                            }"
                            @click="action = 'discrepant'"
                        >
                            <XCircle
                                class="size-5"
                                :class="action === 'discrepant' ? 'text-red-600' : 'text-muted-foreground'"
                            />
                            <div>
                                <div class="font-medium">Discrepant</div>
                                <div class="text-xs text-muted-foreground">Issue found</div>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Discrepancy Type (when discrepant selected) -->
                <div v-if="showDiscrepancyTypeSelect" class="space-y-2">
                    <Label for="discrepancy-type" class="text-sm font-medium">
                        Discrepancy Type <span class="text-red-500">*</span>
                    </Label>
                    <select
                        id="discrepancy-type"
                        v-model="discrepancyType"
                        :class="selectClass"
                    >
                        <option value="">Select type...</option>
                        <option
                            v-for="type in discrepancyTypes"
                            :key="type.value"
                            :value="type.value"
                        >
                            {{ type.label }}
                        </option>
                    </select>
                </div>

                <!-- Notes -->
                <div class="space-y-2">
                    <Label for="notes" class="text-sm font-medium">
                        Notes
                        <span v-if="action === 'discrepant'" class="text-red-500">*</span>
                        <span v-else class="text-muted-foreground">(optional)</span>
                    </Label>
                    <Textarea
                        id="notes"
                        v-model="notes"
                        :placeholder="
                            action === 'discrepant'
                                ? 'Describe the discrepancy...'
                                : 'Add any notes about this verification...'
                        "
                        rows="3"
                        :class="{ 'border-red-500': notesError }"
                    />
                    <p v-if="notesError" class="text-sm text-red-500">
                        {{ notesError }}
                    </p>
                </div>

                <!-- Error message -->
                <div
                    v-if="error"
                    class="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400"
                >
                    <AlertTriangle class="size-4 shrink-0" />
                    {{ error }}
                </div>
            </div>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary" :disabled="isLoading" @click="handleClose">
                        Cancel
                    </Button>
                </DialogClose>
                <Button
                    :disabled="isSubmitDisabled"
                    :class="{
                        'bg-green-600 hover:bg-green-700': action === 'verified',
                        'bg-red-600 hover:bg-red-700': action === 'discrepant',
                    }"
                    @click="handleSubmit"
                >
                    <Spinner v-if="isLoading" class="mr-2 size-4" />
                    <template v-else>
                        <CheckCircle v-if="action === 'verified'" class="mr-1 size-4" />
                        <XCircle v-else class="mr-1 size-4" />
                    </template>
                    {{ action === 'verified' ? 'Mark as Verified' : 'Mark as Discrepant' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
