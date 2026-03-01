<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import DeviceSelectionStep from './DeviceSelectionStep.vue';
import ConnectionReviewStep from './ConnectionReviewStep.vue';
import DestinationSelectionStep from './DestinationSelectionStep.vue';
import ConfirmationStep from './ConfirmationStep.vue';
import { store } from '@/actions/App/Http/Controllers/EquipmentMoveController';
import { ArrowLeft, ArrowRight, Check, AlertTriangle, Loader2 } from 'lucide-vue-next';
import type { DeviceData } from '@/types/rooms';
import type { LocationHierarchy } from '@/composables/useDestinationPicker';

interface DeviceWithConnections extends DeviceData {
    connections?: ConnectionData[];
    has_pending_move?: boolean;
}

interface ConnectionData {
    id: number;
    source_port_label: string;
    destination_port_label: string;
    destination_device_name: string;
    cable_type: string | null;
    cable_length: string | null;
    cable_color: string | null;
}

interface Props {
    isOpen: boolean;
    device?: DeviceWithConnections | null;
    locationHierarchy?: LocationHierarchy;
}

const props = withDefaults(defineProps<Props>(), {
    device: null,
    locationHierarchy: undefined,
});

const emit = defineEmits<{
    close: [];
    complete: [moveId: number];
}>();

// Wizard state
const currentStep = ref(1);
const totalSteps = 4;

// Form data
const selectedDevice = ref<DeviceWithConnections | null>(props.device || null);
const connectionsAcknowledged = ref(false);
const destinationData = ref<{
    destination_rack_id: number | null;
    destination_start_u: number | null;
    destination_rack_face: string;
    destination_width_type: string;
}>({
    destination_rack_id: null,
    destination_start_u: null,
    destination_rack_face: 'front',
    destination_width_type: 'full',
});
const operatorNotes = ref('');

// UI state
const isSubmitting = ref(false);
const error = ref<string | null>(null);
const submitSuccess = ref(false);
const createdMoveId = ref<number | null>(null);

// Step labels for progress indicator
const stepLabels = ['Select Device', 'Review Connections', 'Select Destination', 'Confirm'];

// Watch for device prop changes
watch(
    () => props.device,
    (newDevice) => {
        if (newDevice) {
            selectedDevice.value = newDevice;
        }
    },
    { immediate: true },
);

// Reset wizard when dialog opens/closes
watch(
    () => props.isOpen,
    (isOpen) => {
        if (!isOpen) {
            resetWizard();
        } else if (props.device) {
            // If opened with a pre-selected device, start at step 2
            selectedDevice.value = props.device;
            currentStep.value = selectedDevice.value.connections?.length ? 2 : 3;
        }
    },
);

/**
 * Check if current step can proceed to next
 */
const canProceed = computed(() => {
    switch (currentStep.value) {
        case 1:
            return selectedDevice.value !== null && !selectedDevice.value.has_pending_move;
        case 2:
            // If device has connections, must acknowledge
            const hasConnections = (selectedDevice.value?.connections?.length ?? 0) > 0;
            return !hasConnections || connectionsAcknowledged.value;
        case 3:
            return (
                destinationData.value.destination_rack_id !== null &&
                destinationData.value.destination_start_u !== null
            );
        case 4:
            return true;
        default:
            return false;
    }
});

/**
 * Navigate to next step
 */
function nextStep(): void {
    if (currentStep.value < totalSteps && canProceed.value) {
        currentStep.value++;
        error.value = null;
    }
}

/**
 * Navigate to previous step
 */
function prevStep(): void {
    if (currentStep.value > 1) {
        currentStep.value--;
        error.value = null;
    }
}

/**
 * Handle device selection from Step 1
 */
function handleDeviceSelected(device: DeviceWithConnections): void {
    selectedDevice.value = device;
    connectionsAcknowledged.value = false;
}

/**
 * Handle connection acknowledgment from Step 2
 */
function handleConnectionsAcknowledged(acknowledged: boolean): void {
    connectionsAcknowledged.value = acknowledged;
}

/**
 * Handle destination selection from Step 3
 */
function handleDestinationSelected(data: typeof destinationData.value): void {
    destinationData.value = data;
}

/**
 * Handle operator notes from Step 4
 */
function handleNotesChanged(notes: string): void {
    operatorNotes.value = notes;
}

/**
 * Submit move request
 */
async function submitMoveRequest(): Promise<void> {
    if (!selectedDevice.value || !destinationData.value.destination_rack_id) {
        error.value = 'Please complete all required fields.';
        return;
    }

    isSubmitting.value = true;
    error.value = null;

    try {
        const response = await axios.post(store.url(), {
            device_id: selectedDevice.value.id,
            destination_rack_id: destinationData.value.destination_rack_id,
            destination_start_u: destinationData.value.destination_start_u,
            destination_rack_face: destinationData.value.destination_rack_face,
            destination_width_type: destinationData.value.destination_width_type,
            operator_notes: operatorNotes.value || null,
        });

        submitSuccess.value = true;
        createdMoveId.value = response.data.data.id;

        // Emit completion event
        emit('complete', response.data.data.id);

        // Reload page data after short delay
        setTimeout(() => {
            router.reload({ preserveScroll: true });
        }, 1500);
    } catch (err: unknown) {
        const axiosError = err as {
            response?: { data?: { message?: string; errors?: Record<string, string[]> } };
        };
        if (axiosError.response?.data?.errors) {
            const errors = Object.values(axiosError.response.data.errors).flat();
            error.value = errors.join(' ');
        } else {
            error.value = axiosError.response?.data?.message || 'Failed to create move request.';
        }
    } finally {
        isSubmitting.value = false;
    }
}

/**
 * Reset wizard to initial state
 */
function resetWizard(): void {
    currentStep.value = 1;
    selectedDevice.value = props.device || null;
    connectionsAcknowledged.value = false;
    destinationData.value = {
        destination_rack_id: null,
        destination_start_u: null,
        destination_rack_face: 'front',
        destination_width_type: 'full',
    };
    operatorNotes.value = '';
    isSubmitting.value = false;
    error.value = null;
    submitSuccess.value = false;
    createdMoveId.value = null;
}

/**
 * Handle dialog close
 */
function handleClose(): void {
    emit('close');
}

/**
 * Handle dialog open state change
 */
function handleOpenChange(open: boolean): void {
    if (!open) {
        emit('close');
    }
}

/**
 * Get CSRF token from cookies
 */
function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}
</script>

<template>
    <Dialog :open="isOpen" @update:open="handleOpenChange">
        <DialogContent
            class="max-h-[90vh] w-full max-w-3xl overflow-y-auto sm:max-w-2xl lg:max-w-4xl"
            :show-close-button="!isSubmitting"
        >
            <DialogHeader>
                <DialogTitle>Move Equipment</DialogTitle>
                <DialogDescription>
                    Follow the steps below to request a device move.
                </DialogDescription>
            </DialogHeader>

            <!-- Progress Indicator -->
            <div class="mb-6">
                <div class="flex items-center justify-between">
                    <template v-for="(label, index) in stepLabels" :key="index">
                        <div class="flex flex-col items-center">
                            <div
                                :class="[
                                    'flex h-8 w-8 items-center justify-center rounded-full text-sm font-medium transition-colors',
                                    index + 1 < currentStep
                                        ? 'bg-primary text-primary-foreground'
                                        : index + 1 === currentStep
                                          ? 'bg-primary text-primary-foreground ring-2 ring-primary ring-offset-2'
                                          : 'bg-muted text-muted-foreground',
                                ]"
                            >
                                <Check v-if="index + 1 < currentStep" class="h-4 w-4" />
                                <span v-else>{{ index + 1 }}</span>
                            </div>
                            <span
                                :class="[
                                    'mt-1 hidden text-xs sm:block',
                                    index + 1 <= currentStep ? 'text-foreground' : 'text-muted-foreground',
                                ]"
                            >
                                {{ label }}
                            </span>
                        </div>
                        <div
                            v-if="index < stepLabels.length - 1"
                            :class="[
                                'mx-2 h-0.5 flex-1',
                                index + 1 < currentStep ? 'bg-primary' : 'bg-muted',
                            ]"
                        />
                    </template>
                </div>
            </div>

            <!-- Step Content -->
            <div class="min-h-[300px]">
                <!-- Step 1: Device Selection -->
                <DeviceSelectionStep
                    v-if="currentStep === 1"
                    :selected-device="selectedDevice"
                    @device-selected="handleDeviceSelected"
                />

                <!-- Step 2: Connection Review -->
                <ConnectionReviewStep
                    v-else-if="currentStep === 2"
                    :device="selectedDevice"
                    :is-acknowledged="connectionsAcknowledged"
                    @acknowledged-changed="handleConnectionsAcknowledged"
                />

                <!-- Step 3: Destination Selection -->
                <DestinationSelectionStep
                    v-else-if="currentStep === 3"
                    :device="selectedDevice"
                    :location-hierarchy="locationHierarchy"
                    :initial-destination="destinationData"
                    @destination-changed="handleDestinationSelected"
                />

                <!-- Step 4: Confirmation -->
                <ConfirmationStep
                    v-else-if="currentStep === 4"
                    :device="selectedDevice"
                    :destination="destinationData"
                    :operator-notes="operatorNotes"
                    :is-submitting="isSubmitting"
                    :is-success="submitSuccess"
                    :created-move-id="createdMoveId"
                    @notes-changed="handleNotesChanged"
                />
            </div>

            <!-- Error Alert -->
            <Alert v-if="error" variant="destructive" class="mt-4">
                <AlertTriangle class="h-4 w-4" />
                <AlertDescription>{{ error }}</AlertDescription>
            </Alert>

            <!-- Footer Actions -->
            <div class="mt-6 flex items-center justify-between border-t pt-4">
                <Button
                    v-if="currentStep > 1 && !submitSuccess"
                    variant="outline"
                    :disabled="isSubmitting"
                    @click="prevStep"
                >
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Back
                </Button>
                <div v-else />

                <div class="flex gap-2">
                    <Button
                        v-if="!submitSuccess"
                        variant="ghost"
                        :disabled="isSubmitting"
                        @click="handleClose"
                    >
                        Cancel
                    </Button>

                    <Button
                        v-if="currentStep < totalSteps && !submitSuccess"
                        :disabled="!canProceed || isSubmitting"
                        @click="nextStep"
                    >
                        Next
                        <ArrowRight class="ml-2 h-4 w-4" />
                    </Button>

                    <Button
                        v-else-if="currentStep === totalSteps && !submitSuccess"
                        :disabled="!canProceed || isSubmitting"
                        @click="submitMoveRequest"
                    >
                        <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                        {{ isSubmitting ? 'Submitting...' : 'Submit Move Request' }}
                    </Button>

                    <Button v-else-if="submitSuccess" @click="handleClose">
                        Close
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
