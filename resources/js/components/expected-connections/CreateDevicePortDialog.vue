<script setup lang="ts">
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { AlertCircle, Plus } from 'lucide-vue-next';
import axios from 'axios';
import { createDevicePort } from '@/actions/App/Http/Controllers/ExpectedConnectionController';

interface Props {
    isOpen: boolean;
    connectionId: number;
    target: 'source' | 'dest';
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:isOpen', value: boolean): void;
    (e: 'created'): void;
}>();

// Form state
const deviceName = ref('');
const portLabel = ref('');
const isSubmitting = ref(false);
const submitError = ref<string | null>(null);

// Validation state
const deviceNameError = ref<string | null>(null);
const portLabelError = ref<string | null>(null);

/**
 * Reset form state
 */
function resetForm(): void {
    deviceName.value = '';
    portLabel.value = '';
    deviceNameError.value = null;
    portLabelError.value = null;
    submitError.value = null;
}

/**
 * Validate the form
 */
function validateForm(): boolean {
    let isValid = true;
    deviceNameError.value = null;
    portLabelError.value = null;

    if (!deviceName.value.trim()) {
        deviceNameError.value = 'Device name is required.';
        isValid = false;
    } else if (deviceName.value.trim().length < 2) {
        deviceNameError.value = 'Device name must be at least 2 characters.';
        isValid = false;
    }

    if (!portLabel.value.trim()) {
        portLabelError.value = 'Port label is required.';
        isValid = false;
    }

    return isValid;
}

/**
 * Handle form submission
 */
async function handleSubmit(): Promise<void> {
    if (!validateForm()) {
        return;
    }

    isSubmitting.value = true;
    submitError.value = null;

    try {
        await axios.post(createDevicePort.url(props.connectionId), {
            device_name: deviceName.value.trim(),
            port_label: portLabel.value.trim(),
            target: props.target,
        });

        emit('created');
        resetForm();
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.data?.message) {
            submitError.value = error.response.data.message;
        } else if (axios.isAxiosError(error) && error.response?.data?.errors) {
            const errors = error.response.data.errors;
            if (errors.device_name) {
                deviceNameError.value = errors.device_name[0];
            }
            if (errors.port_label) {
                portLabelError.value = errors.port_label[0];
            }
        } else {
            submitError.value = 'Failed to create device and port. Please try again.';
        }
    } finally {
        isSubmitting.value = false;
    }
}

/**
 * Handle dialog close
 */
function handleClose(): void {
    if (!isSubmitting.value) {
        resetForm();
        emit('update:isOpen', false);
    }
}

// Reset form when dialog opens
watch(() => props.isOpen, (open) => {
    if (open) {
        resetForm();
    }
});
</script>

<template>
    <Dialog :open="isOpen" @update:open="handleClose">
        <DialogContent class="sm:max-w-md">
            <DialogHeader class="space-y-2">
                <DialogTitle class="flex items-center gap-2">
                    <Plus class="size-5" />
                    Create Device & Port
                </DialogTitle>
                <DialogDescription>
                    Create a new device and port for this
                    {{ target === 'source' ? 'source' : 'destination' }} connection.
                    The device will be created if it doesn't exist.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4 py-4" @submit.prevent="handleSubmit">
                <!-- Device Name -->
                <div class="space-y-2">
                    <Label for="device-name">Device Name</Label>
                    <Input
                        id="device-name"
                        v-model="deviceName"
                        placeholder="e.g., Server-001"
                        :disabled="isSubmitting"
                        :class="{ 'border-destructive': deviceNameError }"
                    />
                    <p v-if="deviceNameError" class="text-xs text-destructive">
                        {{ deviceNameError }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        If a device with this name exists, it will be used instead of creating a new one.
                    </p>
                </div>

                <!-- Port Label -->
                <div class="space-y-2">
                    <Label for="port-label">Port Label</Label>
                    <Input
                        id="port-label"
                        v-model="portLabel"
                        placeholder="e.g., eth0 or port-1"
                        :disabled="isSubmitting"
                        :class="{ 'border-destructive': portLabelError }"
                    />
                    <p v-if="portLabelError" class="text-xs text-destructive">
                        {{ portLabelError }}
                    </p>
                </div>

                <!-- Info Box -->
                <div class="rounded-lg border bg-muted/30 p-3 text-sm">
                    <p class="font-medium">Note:</p>
                    <ul class="mt-1 list-inside list-disc space-y-1 text-xs text-muted-foreground">
                        <li>New devices will be created with default settings</li>
                        <li>Ports will be created as Ethernet type by default</li>
                        <li>You can edit device details later from the Devices page</li>
                    </ul>
                </div>

                <!-- Error Message -->
                <div
                    v-if="submitError"
                    class="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400"
                >
                    <AlertCircle class="mt-0.5 size-4 shrink-0" />
                    <span>{{ submitError }}</span>
                </div>
            </form>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary" :disabled="isSubmitting" @click="handleClose">
                        Cancel
                    </Button>
                </DialogClose>

                <Button
                    :disabled="isSubmitting"
                    @click="handleSubmit"
                >
                    <Spinner v-if="isSubmitting" class="mr-2 size-4" />
                    {{ isSubmitting ? 'Creating...' : 'Create Device & Port' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
