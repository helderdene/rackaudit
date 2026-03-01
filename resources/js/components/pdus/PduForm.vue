<script setup lang="ts">
import { computed } from 'vue';
import { Form, router } from '@inertiajs/vue3';
import PduController from '@/actions/App/Http/Controllers/PduController';
import RoomController from '@/actions/App/Http/Controllers/RoomController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { DatacenterReference, RoomReference, PduData, RowReference, SelectOption } from '@/types/rooms';

interface Props {
    mode: 'create' | 'edit';
    datacenter: DatacenterReference;
    room: RoomReference;
    pdu?: Partial<PduData>;
    rows: RowReference[];
    phaseOptions: SelectOption[];
    statusOptions: SelectOption[];
}

const props = withDefaults(defineProps<Props>(), {
    pdu: () => ({
        name: '',
        model: null,
        manufacturer: null,
        total_capacity_kw: null,
        voltage: null,
        phase: '',
        circuit_count: 1,
        status: '',
        room_id: null,
        row_id: null,
    }),
});

// Determine form action based on mode
const formAction = computed(() => {
    if (props.mode === 'create') {
        return {
            action: PduController.store.url({ datacenter: props.datacenter.id, room: props.room.id }),
            method: 'post' as const,
        };
    }
    return {
        action: PduController.update.url({ datacenter: props.datacenter.id, room: props.room.id, pdu: props.pdu.id! }),
        method: 'post' as const,
    };
});

// Cancel navigation
const handleCancel = () => {
    router.get(RoomController.show.url({ datacenter: props.datacenter.id, room: props.room.id }));
};

// Get assignment value for display
const assignmentValue = computed(() => {
    if (props.pdu.row_id) {
        return props.pdu.row_id.toString();
    }
    return '';
});
</script>

<template>
    <Form
        :action="formAction.action"
        :method="formAction.method"
        class="space-y-8"
        v-slot="{ errors, processing, recentlySuccessful }"
    >
        <!-- Hidden method field for PUT request in edit mode -->
        <input v-if="mode === 'edit'" type="hidden" name="_method" value="PUT" />

        <!-- PDU Details Section -->
        <div class="space-y-4">
            <HeadingSmall
                title="PDU Details"
                description="Enter the PDU name, model, and specifications."
            />

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="name">Name <span class="text-red-500">*</span></Label>
                    <Input
                        id="name"
                        name="name"
                        type="text"
                        :default-value="pdu.name ?? ''"
                        required
                        placeholder="Enter PDU name (e.g., PDU-A1-001)"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="model">Model</Label>
                    <Input
                        id="model"
                        name="model"
                        type="text"
                        :default-value="pdu.model ?? ''"
                        placeholder="Enter model number"
                    />
                    <InputError :message="errors.model" />
                </div>

                <div class="grid gap-2">
                    <Label for="manufacturer">Manufacturer</Label>
                    <Input
                        id="manufacturer"
                        name="manufacturer"
                        type="text"
                        :default-value="pdu.manufacturer ?? ''"
                        placeholder="Enter manufacturer name"
                    />
                    <InputError :message="errors.manufacturer" />
                </div>
            </div>
        </div>

        <!-- Power Specifications Section -->
        <div class="space-y-4">
            <HeadingSmall
                title="Power Specifications"
                description="Enter power capacity and electrical specifications."
            />

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="total_capacity_kw">Total Capacity (kW)</Label>
                    <Input
                        id="total_capacity_kw"
                        name="total_capacity_kw"
                        type="number"
                        step="0.01"
                        min="0"
                        :default-value="pdu.total_capacity_kw?.toString() ?? ''"
                        placeholder="e.g., 50.5"
                    />
                    <InputError :message="errors.total_capacity_kw" />
                </div>

                <div class="grid gap-2">
                    <Label for="voltage">Voltage (V)</Label>
                    <Input
                        id="voltage"
                        name="voltage"
                        type="number"
                        min="0"
                        :default-value="pdu.voltage?.toString() ?? ''"
                        placeholder="e.g., 208, 480"
                    />
                    <InputError :message="errors.voltage" />
                </div>

                <div class="grid gap-2">
                    <Label for="phase">Phase <span class="text-red-500">*</span></Label>
                    <select
                        id="phase"
                        name="phase"
                        :value="pdu.phase ?? ''"
                        required
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option value="" disabled>Select phase</option>
                        <option
                            v-for="option in phaseOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="errors.phase" />
                </div>

                <div class="grid gap-2">
                    <Label for="circuit_count">Circuit Count <span class="text-red-500">*</span></Label>
                    <Input
                        id="circuit_count"
                        name="circuit_count"
                        type="number"
                        min="1"
                        :default-value="pdu.circuit_count?.toString() ?? '1'"
                        required
                        placeholder="e.g., 42"
                    />
                    <InputError :message="errors.circuit_count" />
                </div>
            </div>
        </div>

        <!-- Assignment and Status Section -->
        <div class="space-y-4">
            <HeadingSmall
                title="Assignment and Status"
                description="Assign the PDU to room level or a specific row."
            />

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="row_id">Assignment Level</Label>
                    <select
                        id="row_id"
                        name="row_id"
                        :value="assignmentValue"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option value="">Room Level</option>
                        <option
                            v-for="row in rows"
                            :key="row.id"
                            :value="row.id"
                        >
                            Row: {{ row.name }}
                        </option>
                    </select>
                    <p class="text-xs text-muted-foreground">
                        Select "Room Level" to assign PDU to the entire room, or select a specific row.
                    </p>
                    <InputError :message="errors.row_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="status">Status <span class="text-red-500">*</span></Label>
                    <select
                        id="status"
                        name="status"
                        :value="pdu.status ?? ''"
                        required
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option value="" disabled>Select status</option>
                        <option
                            v-for="option in statusOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="errors.status" />
                </div>
            </div>
        </div>

        <!-- Submit Buttons -->
        <div class="flex items-center gap-4">
            <Button :disabled="processing" type="submit">
                {{ processing ? 'Saving...' : (mode === 'create' ? 'Create PDU' : 'Save Changes') }}
            </Button>
            <Button
                type="button"
                variant="outline"
                :disabled="processing"
                @click="handleCancel"
            >
                Cancel
            </Button>

            <Transition
                enter-active-class="transition ease-in-out"
                enter-from-class="opacity-0"
                leave-active-class="transition ease-in-out"
                leave-to-class="opacity-0"
            >
                <p v-show="recentlySuccessful" class="text-sm text-neutral-600 dark:text-neutral-400">
                    Saved.
                </p>
            </Transition>
        </div>
    </Form>
</template>
