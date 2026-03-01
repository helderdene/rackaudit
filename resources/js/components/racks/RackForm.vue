<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Form, router } from '@inertiajs/vue3';
import RackController from '@/actions/App/Http/Controllers/RackController';
import RowController from '@/actions/App/Http/Controllers/RowController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { DatacenterReference, RoomReference, RowReference, RackData, SelectOption, PduOption } from '@/types/rooms';

interface Props {
    mode: 'create' | 'edit';
    datacenter: DatacenterReference;
    room: RoomReference;
    row: RowReference;
    rack?: Partial<RackData>;
    nextPosition?: number;
    uHeightOptions: SelectOption[];
    statusOptions: SelectOption[];
    pduOptions: PduOption[];
    selectedPduIds?: number[];
}

const props = withDefaults(defineProps<Props>(), {
    rack: () => ({
        name: '',
        position: 0,
        u_height: null,
        serial_number: null,
        status: '',
        manufacturer: null,
        model: null,
        depth: null,
        installation_date: null,
        location_notes: null,
        specs: null,
    }),
    nextPosition: 1,
    selectedPduIds: () => [],
});

// Track selected PDUs for multi-select
const selectedPdus = ref<number[]>(props.selectedPduIds || props.rack?.pdu_ids || []);

// Watch for prop changes in edit mode
watch(() => props.selectedPduIds, (newVal) => {
    if (newVal) {
        selectedPdus.value = newVal;
    }
}, { immediate: true });

// Determine form action based on mode
const formAction = computed(() => {
    if (props.mode === 'create') {
        return {
            action: RackController.store.url({
                datacenter: props.datacenter.id,
                room: props.room.id,
                row: props.row.id,
            }),
            method: 'post' as const,
        };
    }
    return {
        action: RackController.update.url({
            datacenter: props.datacenter.id,
            room: props.room.id,
            row: props.row.id,
            rack: props.rack.id!,
        }),
        method: 'post' as const,
    };
});

// Cancel navigation - go back to Row show page
const handleCancel = () => {
    router.get(RowController.show.url({
        datacenter: props.datacenter.id,
        room: props.room.id,
        row: props.row.id,
    }));
};

// Default position for new racks
const defaultPosition = computed(() => {
    if (props.mode === 'create') {
        return props.nextPosition;
    }
    return props.rack?.position ?? 0;
});

// Default U-height value
const defaultUHeight = computed(() => {
    if (props.rack?.u_height !== null && props.rack?.u_height !== undefined) {
        return props.rack.u_height.toString();
    }
    return '';
});

// Toggle PDU selection
const togglePdu = (pduId: number) => {
    const index = selectedPdus.value.indexOf(pduId);
    if (index === -1) {
        selectedPdus.value.push(pduId);
    } else {
        selectedPdus.value.splice(index, 1);
    }
};

// Check if PDU is selected
const isPduSelected = (pduId: number) => {
    return selectedPdus.value.includes(pduId);
};

// Format specs object to JSON string for textarea
const specsJson = computed(() => {
    if (!props.rack?.specs) {
        return '';
    }
    try {
        return JSON.stringify(props.rack.specs, null, 2);
    } catch {
        return '';
    }
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

        <!-- Hidden fields for selected PDU IDs -->
        <input
            v-for="pduId in selectedPdus"
            :key="pduId"
            type="hidden"
            name="pdu_ids[]"
            :value="pduId"
        />

        <!-- Rack Details Section -->
        <div class="space-y-4">
            <HeadingSmall
                title="Rack Details"
                description="Enter the rack name, position, U-height, and status."
            />

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="name">Name <span class="text-red-500">*</span></Label>
                    <Input
                        id="name"
                        name="name"
                        type="text"
                        :default-value="rack.name ?? ''"
                        required
                        placeholder="Enter rack name"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="position">Position <span class="text-red-500">*</span></Label>
                    <Input
                        id="position"
                        name="position"
                        type="number"
                        min="0"
                        :default-value="defaultPosition.toString()"
                        required
                        placeholder="Enter position"
                    />
                    <InputError :message="errors.position" />
                </div>

                <div class="grid gap-2">
                    <Label for="u_height">U-Height <span class="text-red-500">*</span></Label>
                    <select
                        id="u_height"
                        name="u_height"
                        :value="defaultUHeight"
                        required
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option value="" disabled>Select U-Height</option>
                        <option
                            v-for="option in uHeightOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="errors.u_height" />
                </div>

                <div class="grid gap-2">
                    <Label for="status">Status <span class="text-red-500">*</span></Label>
                    <select
                        id="status"
                        name="status"
                        :value="rack.status ?? ''"
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

                <div class="grid gap-2 sm:col-span-2">
                    <Label for="serial_number">Serial Number</Label>
                    <Input
                        id="serial_number"
                        name="serial_number"
                        type="text"
                        :default-value="rack.serial_number ?? ''"
                        placeholder="Enter serial number (optional)"
                    />
                    <InputError :message="errors.serial_number" />
                </div>
            </div>
        </div>

        <!-- Physical Specifications Section -->
        <div class="space-y-4">
            <HeadingSmall
                title="Physical Specifications"
                description="Enter manufacturer, model, depth, and installation details."
            />

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="manufacturer">Manufacturer</Label>
                    <Input
                        id="manufacturer"
                        name="manufacturer"
                        type="text"
                        :default-value="rack.manufacturer ?? ''"
                        placeholder="e.g., APC, Dell, HP"
                    />
                    <InputError :message="errors.manufacturer" />
                </div>

                <div class="grid gap-2">
                    <Label for="model">Model</Label>
                    <Input
                        id="model"
                        name="model"
                        type="text"
                        :default-value="rack.model ?? ''"
                        placeholder="e.g., NetShelter SX"
                    />
                    <InputError :message="errors.model" />
                </div>

                <div class="grid gap-2">
                    <Label for="depth">Depth</Label>
                    <Input
                        id="depth"
                        name="depth"
                        type="text"
                        :default-value="rack.depth ?? ''"
                        placeholder="e.g., 1070mm"
                    />
                    <InputError :message="errors.depth" />
                </div>

                <div class="grid gap-2">
                    <Label for="installation_date">Installation Date</Label>
                    <Input
                        id="installation_date"
                        name="installation_date"
                        type="date"
                        :default-value="rack.installation_date ?? ''"
                    />
                    <InputError :message="errors.installation_date" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label for="location_notes">Location Notes</Label>
                    <Textarea
                        id="location_notes"
                        name="location_notes"
                        :default-value="rack.location_notes ?? ''"
                        placeholder="Additional location context (e.g., near fire exit, requires clearance check)"
                        rows="3"
                    />
                    <InputError :message="errors.location_notes" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label for="specs">Custom Specifications (JSON)</Label>
                    <Textarea
                        id="specs"
                        name="specs"
                        :default-value="specsJson"
                        placeholder='{"max_weight_kg": 1500, "cable_management": "vertical"}'
                        rows="4"
                        class="font-mono text-sm"
                    />
                    <p class="text-xs text-muted-foreground">
                        Enter custom key-value pairs as valid JSON. Leave empty if not needed.
                    </p>
                    <InputError :message="errors.specs" />
                </div>
            </div>
        </div>

        <!-- PDU Assignment Section -->
        <div class="space-y-4">
            <HeadingSmall
                title="PDU Assignment"
                description="Select PDUs to assign to this rack for power distribution. Multiple PDUs can be assigned."
            />

            <div class="grid gap-2">
                <Label>Available PDUs</Label>
                <div v-if="pduOptions.length === 0" class="rounded-md border border-dashed p-4 text-center text-sm text-muted-foreground">
                    No PDUs available for assignment. PDUs must be assigned to this room or row first.
                </div>
                <div v-else class="max-h-48 overflow-y-auto rounded-md border">
                    <div
                        v-for="pdu in pduOptions"
                        :key="pdu.id"
                        class="flex cursor-pointer items-center gap-3 border-b px-3 py-2 last:border-b-0 hover:bg-muted/50"
                        :class="{ 'bg-muted': isPduSelected(pdu.id) }"
                        @click="togglePdu(pdu.id)"
                    >
                        <input
                            type="checkbox"
                            :checked="isPduSelected(pdu.id)"
                            class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                            @click.stop="togglePdu(pdu.id)"
                        />
                        <div class="flex-1">
                            <p class="text-sm font-medium">{{ pdu.name }}</p>
                            <p v-if="pdu.model" class="text-xs text-muted-foreground">{{ pdu.model }}</p>
                        </div>
                    </div>
                </div>
                <p v-if="selectedPdus.length > 0" class="text-xs text-muted-foreground">
                    {{ selectedPdus.length }} PDU{{ selectedPdus.length === 1 ? '' : 's' }} selected
                </p>
                <InputError :message="errors['pdu_ids']" />
                <InputError :message="errors['pdu_ids.0']" />
            </div>
        </div>

        <!-- Submit Buttons -->
        <div class="flex items-center gap-4">
            <Button :disabled="processing" type="submit">
                {{ processing ? 'Saving...' : (mode === 'create' ? 'Create Rack' : 'Save Changes') }}
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
