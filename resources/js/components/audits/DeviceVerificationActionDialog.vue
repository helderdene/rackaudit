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
import { CheckCircle, XCircle, AlertTriangle, Server } from 'lucide-vue-next';

interface DeviceData {
    id: number;
    name: string;
    asset_tag: string | null;
    serial_number: string | null;
    manufacturer: string | null;
    model: string | null;
    u_height: number;
    start_u: number | null;
}

interface RackData {
    id: number;
    name: string;
}

interface VerificationData {
    id: number;
    device: DeviceData | null;
    rack: RackData | null;
    verification_status: string;
    notes: string | null;
}

interface Props {
    open: boolean;
    verification: VerificationData | null;
    isLoading: boolean;
    error: string | null;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'submit', data: { action: 'verified' | 'not_found' | 'discrepant'; notes: string }): void;
}>();

// Form state
const action = ref<'verified' | 'not_found' | 'discrepant'>('verified');
const notes = ref<string>('');

// Validation
const notesError = ref<string | null>(null);

// Computed
const deviceDescription = computed(() => {
    if (!props.verification?.device) return 'Device';

    const parts: string[] = [];
    if (props.verification.device.name) {
        parts.push(props.verification.device.name);
    }
    if (props.verification.device.asset_tag) {
        parts.push(`(${props.verification.device.asset_tag})`);
    }
    return parts.join(' ') || 'Device';
});

const deviceDetails = computed(() => {
    if (!props.verification?.device) return null;

    return {
        name: props.verification.device.name,
        assetTag: props.verification.device.asset_tag,
        serialNumber: props.verification.device.serial_number,
        manufacturer: props.verification.device.manufacturer,
        model: props.verification.device.model,
        rack: props.verification.rack?.name,
        position: formatUPosition(props.verification.device.start_u, props.verification.device.u_height),
    };
});

const showNotesRequired = computed(() => action.value === 'not_found' || action.value === 'discrepant');

const isSubmitDisabled = computed(() => {
    if (props.isLoading) return true;
    if (showNotesRequired.value && !notes.value.trim()) return true;
    return false;
});

/**
 * Format U position for display
 */
function formatUPosition(startU: number | null, uHeight: number): string {
    if (startU === null) return '-';
    if (uHeight === 1) return `U${startU}`;
    return `U${startU}-${startU + uHeight - 1}`;
}

// Reset form when dialog opens
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            action.value = 'verified';
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

    // Validate notes for not_found and discrepant
    if (showNotesRequired.value && !notes.value.trim()) {
        notesError.value = 'Notes are required when marking device as not found or discrepant.';
        return;
    }

    emit('submit', {
        action: action.value,
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
                <DialogTitle>Verify Device</DialogTitle>
                <DialogDescription>
                    {{ deviceDescription }}
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 py-4">
                <!-- Device info summary -->
                <div
                    v-if="deviceDetails"
                    class="rounded-lg border bg-muted/30 p-3 text-sm"
                >
                    <div class="flex items-start gap-3">
                        <Server class="mt-0.5 size-4 text-muted-foreground" />
                        <div class="space-y-1">
                            <div class="font-medium">{{ deviceDetails.name }}</div>
                            <div v-if="deviceDetails.assetTag" class="text-muted-foreground">
                                Asset Tag: {{ deviceDetails.assetTag }}
                            </div>
                            <div v-if="deviceDetails.serialNumber" class="text-muted-foreground">
                                Serial: {{ deviceDetails.serialNumber }}
                            </div>
                            <div v-if="deviceDetails.manufacturer || deviceDetails.model" class="text-muted-foreground">
                                {{ [deviceDetails.manufacturer, deviceDetails.model].filter(Boolean).join(' ') }}
                            </div>
                            <div v-if="deviceDetails.rack" class="text-muted-foreground">
                                Location: {{ deviceDetails.rack }} @ {{ deviceDetails.position }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Selection -->
                <div class="space-y-3">
                    <Label class="text-sm font-medium">Verification Result</Label>
                    <div data-tour="verification-panel" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <!-- Verified -->
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
                                <div class="text-xs text-muted-foreground">Device confirmed</div>
                            </div>
                        </button>

                        <!-- Not Found -->
                        <button
                            type="button"
                            class="flex items-center gap-3 rounded-lg border p-3 text-left transition-colors hover:bg-muted/50"
                            :class="{
                                'border-red-500 bg-red-50 dark:bg-red-900/20': action === 'not_found',
                            }"
                            @click="action = 'not_found'"
                        >
                            <XCircle
                                class="size-5"
                                :class="action === 'not_found' ? 'text-red-600' : 'text-muted-foreground'"
                            />
                            <div>
                                <div class="font-medium">Not Found</div>
                                <div class="text-xs text-muted-foreground">Device missing</div>
                            </div>
                        </button>

                        <!-- Discrepant -->
                        <button
                            type="button"
                            class="flex items-center gap-3 rounded-lg border p-3 text-left transition-colors hover:bg-muted/50"
                            :class="{
                                'border-yellow-500 bg-yellow-50 dark:bg-yellow-900/20': action === 'discrepant',
                            }"
                            @click="action = 'discrepant'"
                        >
                            <AlertTriangle
                                class="size-5"
                                :class="action === 'discrepant' ? 'text-yellow-600' : 'text-muted-foreground'"
                            />
                            <div>
                                <div class="font-medium">Discrepant</div>
                                <div class="text-xs text-muted-foreground">Issue found</div>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Notes -->
                <div class="space-y-2">
                    <Label for="notes" class="text-sm font-medium">
                        Notes
                        <span v-if="showNotesRequired" class="text-red-500">*</span>
                        <span v-else class="text-muted-foreground">(optional)</span>
                    </Label>
                    <Textarea
                        id="notes"
                        v-model="notes"
                        :placeholder="
                            action === 'not_found'
                                ? 'Describe where the device should have been...'
                                : action === 'discrepant'
                                    ? 'Describe the discrepancy (wrong position, label mismatch, etc.)...'
                                    : 'Add any notes about this verification...'
                        "
                        rows="3"
                        :class="{ 'border-red-500': notesError }"
                    />
                    <p v-if="notesError" class="text-sm text-red-500">
                        {{ notesError }}
                    </p>
                    <p v-if="showNotesRequired" class="text-xs text-muted-foreground">
                        A finding will be automatically created when you submit.
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
                        'bg-red-600 hover:bg-red-700': action === 'not_found',
                        'bg-yellow-600 hover:bg-yellow-700': action === 'discrepant',
                    }"
                    @click="handleSubmit"
                >
                    <Spinner v-if="isLoading" class="mr-2 size-4" />
                    <template v-else>
                        <CheckCircle v-if="action === 'verified'" class="mr-1 size-4" />
                        <XCircle v-else-if="action === 'not_found'" class="mr-1 size-4" />
                        <AlertTriangle v-else class="mr-1 size-4" />
                    </template>
                    <span v-if="action === 'verified'">Mark as Verified</span>
                    <span v-else-if="action === 'not_found'">Mark as Not Found</span>
                    <span v-else>Mark as Discrepant</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
