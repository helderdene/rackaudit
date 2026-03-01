<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import HierarchicalPortSelector from '@/components/connections/HierarchicalPortSelector.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type {
    AvailablePortOption,
    CableTypeOption,
    HierarchicalFilterOptions,
} from '@/types/connections';
import { getCableTypesForPortType } from '@/types/connections';
import type { PortData } from '@/types/ports';
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface Props {
    /** Source port that will be connected from */
    sourcePort: PortData;
    /** Device ID for the source port */
    deviceId: number;
    /** Hierarchical filter options for destination selection */
    filterOptions: HierarchicalFilterOptions;
    /** Cable type options from backend */
    cableTypeOptions: CableTypeOption[];
    /** Whether user has edit permissions */
    canEdit?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    canEdit: false,
});

const isOpen = ref(false);

// Selected destination port from HierarchicalPortSelector
const selectedDestinationPort = ref<AvailablePortOption | null>(null);

// Cable property form fields
const selectedCableType = ref<string>('');
const cableLength = ref<string>('');
const cableColor = ref<string>('');
const pathNotes = ref<string>('');

// Filter cable type options based on source port type
const filteredCableTypeOptions = computed(() => {
    return getCableTypesForPortType(props.sourcePort.type);
});

// Auto-select first cable type when destination port is selected
watch(selectedDestinationPort, (newPort) => {
    if (newPort && !selectedCableType.value) {
        const options = filteredCableTypeOptions.value;
        if (options.length > 0) {
            selectedCableType.value = options[0].value;
        }
    }
});

// Form action URL
const formAction = computed(() => '/connections');

// Check if form is ready to submit
const canSubmit = computed(() => {
    return (
        selectedDestinationPort.value !== null &&
        selectedCableType.value !== '' &&
        cableLength.value !== '' &&
        parseFloat(cableLength.value) > 0
    );
});

// Reset form when dialog opens/closes
watch(isOpen, (newVal) => {
    if (!newVal) {
        // Reset all form fields when dialog closes
        selectedDestinationPort.value = null;
        selectedCableType.value = '';
        cableLength.value = '';
        cableColor.value = '';
        pathNotes.value = '';
    }
});

// Handle successful submission
const handleSuccess = () => {
    isOpen.value = false;
};

// Select styling class matching existing patterns
const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50';
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogTrigger as-child>
            <slot>
                <Button v-if="canEdit" size="sm" variant="outline">
                    Connect
                </Button>
            </slot>
        </DialogTrigger>
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>Create Connection</DialogTitle>
                <DialogDescription>
                    Connect this port to a destination port. Select the
                    destination from the hierarchy and configure cable
                    properties.
                </DialogDescription>
            </DialogHeader>

            <Form
                :action="formAction"
                method="post"
                class="space-y-6"
                @success="handleSuccess"
                v-slot="{ errors, processing }"
            >
                <!-- Hidden source port ID -->
                <input
                    type="hidden"
                    name="source_port_id"
                    :value="sourcePort.id"
                />
                <!-- Hidden destination port ID -->
                <input
                    type="hidden"
                    name="destination_port_id"
                    :value="selectedDestinationPort?.id || ''"
                />

                <!-- Source Port Info (Read-only) -->
                <div class="rounded-lg border bg-muted/30 p-4 dark:bg-muted/20">
                    <h4 class="mb-2 text-sm font-medium text-muted-foreground">
                        Source Port
                    </h4>
                    <div class="space-y-1">
                        <p class="text-sm font-medium">
                            {{ sourcePort.label }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ sourcePort.type_label }} -
                            {{ sourcePort.subtype_label }}
                        </p>
                    </div>
                </div>

                <!-- Destination Port Selection -->
                <div class="space-y-2">
                    <h4 class="text-sm font-medium">
                        Destination Port <span class="text-red-500">*</span>
                    </h4>
                    <HierarchicalPortSelector
                        v-model="selectedDestinationPort"
                        :filter-options="filterOptions"
                        :source-port-type="sourcePort.type"
                        :exclude-device-id="deviceId"
                    />
                    <InputError :message="errors.destination_port_id" />
                </div>

                <!-- Selected Destination Info -->
                <div
                    v-if="selectedDestinationPort"
                    class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20"
                >
                    <h4
                        class="mb-2 text-sm font-medium text-green-800 dark:text-green-200"
                    >
                        Selected Destination
                    </h4>
                    <div class="space-y-1">
                        <p
                            class="text-sm font-medium text-green-900 dark:text-green-100"
                        >
                            {{ selectedDestinationPort.device_name }}
                        </p>
                        <p class="text-xs text-green-700 dark:text-green-300">
                            Port: {{ selectedDestinationPort.label }}
                            {{
                                selectedDestinationPort.subtype_label
                                    ? `(${selectedDestinationPort.subtype_label})`
                                    : ''
                            }}
                        </p>
                    </div>
                </div>

                <!-- Cable Properties -->
                <div class="space-y-4 border-t pt-4">
                    <h4 class="text-sm font-medium">Cable Properties</h4>

                    <!-- Cable Type -->
                    <div class="grid gap-2">
                        <Label for="create-cable-type">
                            Cable Type <span class="text-red-500">*</span>
                        </Label>
                        <select
                            id="create-cable-type"
                            name="cable_type"
                            v-model="selectedCableType"
                            required
                            :class="selectClass"
                        >
                            <option value="" disabled>Select cable type</option>
                            <option
                                v-for="option in filteredCableTypeOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="errors.cable_type" />
                    </div>

                    <!-- Cable Length -->
                    <div class="grid gap-2">
                        <Label for="create-cable-length">
                            Cable Length (meters)
                            <span class="text-red-500">*</span>
                        </Label>
                        <Input
                            id="create-cable-length"
                            name="cable_length"
                            type="number"
                            step="0.1"
                            min="0.1"
                            v-model="cableLength"
                            placeholder="e.g., 3.5"
                            required
                        />
                        <InputError :message="errors.cable_length" />
                    </div>

                    <!-- Cable Color (Optional) -->
                    <div class="grid gap-2">
                        <Label for="create-cable-color">Cable Color</Label>
                        <Input
                            id="create-cable-color"
                            name="cable_color"
                            type="text"
                            v-model="cableColor"
                            placeholder="e.g., blue, yellow, gray"
                        />
                        <InputError :message="errors.cable_color" />
                    </div>

                    <!-- Path Notes (Optional) -->
                    <div class="grid gap-2">
                        <Label for="create-path-notes">Path Notes</Label>
                        <Textarea
                            id="create-path-notes"
                            name="path_notes"
                            v-model="pathNotes"
                            placeholder="Optional notes about cable routing or path..."
                            rows="2"
                        />
                        <InputError :message="errors.path_notes" />
                    </div>
                </div>

                <DialogFooter class="gap-2 pt-4">
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="secondary"
                            :disabled="processing"
                        >
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button type="submit" :disabled="processing || !canSubmit">
                        {{ processing ? 'Creating...' : 'Create Connection' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
