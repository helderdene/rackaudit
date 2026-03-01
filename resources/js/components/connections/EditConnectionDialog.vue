<script setup lang="ts">
import InputError from '@/components/InputError.vue';
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
import type { CableTypeOption, ConnectionWithPorts } from '@/types/connections';
import { getCableTypesForPortType } from '@/types/connections';
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface Props {
    /** Connection data with source and destination port info */
    connection: ConnectionWithPorts;
    /** Cable type options from backend */
    cableTypeOptions?: CableTypeOption[];
}

const props = withDefaults(defineProps<Props>(), {
    cableTypeOptions: () => [],
});

const isOpen = ref(false);

// Form field values - pre-filled with existing data
const selectedCableType = ref<string>(props.connection.cable_type || '');
const cableLength = ref<string>(
    props.connection.cable_length !== null
        ? String(props.connection.cable_length)
        : '',
);
const cableColor = ref<string>(props.connection.cable_color || '');
const pathNotes = ref<string>(props.connection.path_notes || '');

// Filter cable type options based on source port type
const filteredCableTypeOptions = computed(() => {
    if (props.connection.source_port?.type) {
        return getCableTypesForPortType(props.connection.source_port.type);
    }
    // Fallback to provided options if source port type not available
    return props.cableTypeOptions;
});

// Reset form when dialog opens
watch(isOpen, (newVal) => {
    if (newVal) {
        // Re-populate with current connection values when opening
        selectedCableType.value = props.connection.cable_type || '';
        cableLength.value =
            props.connection.cable_length !== null
                ? String(props.connection.cable_length)
                : '';
        cableColor.value = props.connection.cable_color || '';
        pathNotes.value = props.connection.path_notes || '';
    }
});

// Watch for connection prop changes
watch(
    () => props.connection,
    (newConnection) => {
        selectedCableType.value = newConnection.cable_type || '';
        cableLength.value =
            newConnection.cable_length !== null
                ? String(newConnection.cable_length)
                : '';
        cableColor.value = newConnection.cable_color || '';
        pathNotes.value = newConnection.path_notes || '';
    },
    { immediate: true },
);

// Form action URL
const formAction = computed(() => `/connections/${props.connection.id}`);

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
                <Button size="sm" variant="ghost">Edit</Button>
            </slot>
        </DialogTrigger>
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Edit Connection</DialogTitle>
                <DialogDescription>
                    Update cable properties for this connection. Source and
                    destination ports cannot be changed.
                </DialogDescription>
            </DialogHeader>

            <Form
                :action="formAction"
                method="post"
                class="space-y-4"
                @success="handleSuccess"
                v-slot="{ errors, processing }"
            >
                <!-- Hidden method field for PUT request -->
                <input type="hidden" name="_method" value="PUT" />

                <!-- Source/Destination Info (Read-only) -->
                <div class="rounded-lg border bg-muted/30 p-4 dark:bg-muted/20">
                    <div class="grid gap-3 text-sm">
                        <div>
                            <span class="text-muted-foreground">From:</span>
                            <span class="ml-2 font-medium">
                                {{
                                    connection.source_port.device?.name ||
                                    'Unknown'
                                }}
                                ({{ connection.source_port.label }})
                            </span>
                        </div>
                        <div>
                            <span class="text-muted-foreground">To:</span>
                            <span class="ml-2 font-medium">
                                {{
                                    connection.destination_port.device?.name ||
                                    'Unknown'
                                }}
                                ({{ connection.destination_port.label }})
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Cable Type -->
                <div class="grid gap-2">
                    <Label for="edit-cable-type">
                        Cable Type <span class="text-red-500">*</span>
                    </Label>
                    <select
                        id="edit-cable-type"
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
                    <Label for="edit-cable-length">
                        Cable Length (meters)
                        <span class="text-red-500">*</span>
                    </Label>
                    <Input
                        id="edit-cable-length"
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

                <!-- Cable Color -->
                <div class="grid gap-2">
                    <Label for="edit-cable-color">Cable Color</Label>
                    <Input
                        id="edit-cable-color"
                        name="cable_color"
                        type="text"
                        v-model="cableColor"
                        placeholder="e.g., blue, yellow, gray"
                    />
                    <InputError :message="errors.cable_color" />
                </div>

                <!-- Path Notes -->
                <div class="grid gap-2">
                    <Label for="edit-path-notes">Path Notes</Label>
                    <Textarea
                        id="edit-path-notes"
                        name="path_notes"
                        v-model="pathNotes"
                        placeholder="Optional notes about cable routing or path..."
                        rows="2"
                    />
                    <InputError :message="errors.path_notes" />
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
                    <Button type="submit" :disabled="processing">
                        {{ processing ? 'Saving...' : 'Save Changes' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
