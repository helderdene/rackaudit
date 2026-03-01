<script setup lang="ts">
import { store, update } from '@/actions/App/Http/Controllers/DeviceController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import KeyValueEditor from '@/components/KeyValueEditor.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { DeviceData, DeviceTypeOption, SelectOption } from '@/types/rooms';
import { Form, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface Props {
    mode: 'create' | 'edit';
    device?: Partial<DeviceData>;
    deviceTypeOptions: DeviceTypeOption[];
    lifecycleStatusOptions: SelectOption[];
    depthOptions: SelectOption[];
    widthTypeOptions: SelectOption[];
    rackFaceOptions: SelectOption[];
}

const props = withDefaults(defineProps<Props>(), {
    device: () => ({
        name: '',
        device_type: null,
        lifecycle_status: 'in_stock',
        u_height: 1,
        depth: 'standard',
        width_type: 'full',
        rack_face: 'front',
        specs: null,
    }),
});

// Track specs for key-value editor
const deviceSpecs = ref<Record<string, string>>(props.device.specs || {});

// Watch for device changes in edit mode
watch(
    () => props.device.specs,
    (newSpecs) => {
        if (newSpecs) {
            deviceSpecs.value = { ...newSpecs };
        }
    },
    { immediate: true },
);

// Determine form action based on mode
const formAction = computed(() => {
    if (props.mode === 'create') {
        return {
            action: store.url(),
            method: 'post' as const,
        };
    }
    return {
        action: update.url(props.device.id!),
        method: 'post' as const,
    };
});

// Cancel navigation - go back to devices list
const handleCancel = () => {
    router.get('/devices');
};

// Get default U height from selected device type
const selectedDeviceTypeId = ref(props.device.device_type?.id || '');

const handleDeviceTypeChange = (event: Event) => {
    const target = event.target as HTMLSelectElement;
    selectedDeviceTypeId.value = target.value;

    // Find the device type and update U height if creating
    if (props.mode === 'create') {
        const deviceType = props.deviceTypeOptions.find(
            (dt) => dt.id === Number(target.value),
        );
        if (deviceType) {
            defaultUHeight.value = deviceType.default_u_size;
        }
    }
};

// Default values
const defaultUHeight = ref(props.device.u_height || 1);

// Generate U size options (1-48)
const uSizeOptions = Array.from({ length: 48 }, (_, i) => ({
    value: i + 1,
    label: `${i + 1}U`,
}));
</script>

<template>
    <Form
        :action="formAction.action"
        :method="formAction.method"
        class="space-y-8"
        v-slot="{ errors, processing, recentlySuccessful }"
    >
        <!-- Hidden method field for PUT request in edit mode -->
        <input
            v-if="mode === 'edit'"
            type="hidden"
            name="_method"
            value="PUT"
        />

        <!-- Hidden specs field with JSON data -->
        <input
            type="hidden"
            name="specs"
            :value="JSON.stringify(deviceSpecs)"
        />

        <!-- Device Basic Details Section -->
        <div class="space-y-4">
            <HeadingSmall
                title="Device Details"
                description="Enter the basic device information."
            />

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="name"
                        >Name <span class="text-red-500">*</span></Label
                    >
                    <Input
                        id="name"
                        name="name"
                        type="text"
                        :default-value="device.name ?? ''"
                        required
                        placeholder="Enter device name"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="device_type_id"
                        >Device Type <span class="text-red-500">*</span></Label
                    >
                    <select
                        id="device_type_id"
                        name="device_type_id"
                        :value="device.device_type?.id?.toString() ?? ''"
                        required
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                        @change="handleDeviceTypeChange"
                    >
                        <option value="" disabled>Select device type</option>
                        <option
                            v-for="option in deviceTypeOptions"
                            :key="option.id"
                            :value="option.id"
                        >
                            {{ option.name }} ({{ option.default_u_size }}U)
                        </option>
                    </select>
                    <InputError :message="errors.device_type_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="lifecycle_status"
                        >Lifecycle Status
                        <span class="text-red-500">*</span></Label
                    >
                    <select
                        id="lifecycle_status"
                        name="lifecycle_status"
                        :value="device.lifecycle_status ?? 'in_stock'"
                        required
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <option value="" disabled>Select status</option>
                        <option
                            v-for="option in lifecycleStatusOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="errors.lifecycle_status" />
                </div>

                <div class="grid gap-2">
                    <Label for="serial_number">Serial Number</Label>
                    <Input
                        id="serial_number"
                        name="serial_number"
                        type="text"
                        :default-value="device.serial_number ?? ''"
                        placeholder="Enter serial number (optional)"
                    />
                    <InputError :message="errors.serial_number" />
                </div>

                <div class="grid gap-2">
                    <Label for="manufacturer">Manufacturer</Label>
                    <Input
                        id="manufacturer"
                        name="manufacturer"
                        type="text"
                        :default-value="device.manufacturer ?? ''"
                        placeholder="e.g., Dell, HP, Cisco"
                    />
                    <InputError :message="errors.manufacturer" />
                </div>

                <div class="grid gap-2">
                    <Label for="model">Model</Label>
                    <Input
                        id="model"
                        name="model"
                        type="text"
                        :default-value="device.model ?? ''"
                        placeholder="e.g., PowerEdge R750"
                    />
                    <InputError :message="errors.model" />
                </div>
            </div>

            <!-- Asset Tag (display only in edit mode) -->
            <div v-if="mode === 'edit' && device.asset_tag" class="grid gap-2">
                <Label>Asset Tag</Label>
                <div
                    class="flex h-9 items-center rounded-md border border-input bg-muted px-3 py-1 font-mono text-sm"
                >
                    {{ device.asset_tag }}
                </div>
                <p class="text-xs text-muted-foreground">
                    Asset tags are auto-generated and cannot be changed.
                </p>
            </div>
        </div>

        <!-- Physical Dimensions Section -->
        <div class="space-y-4">
            <HeadingSmall
                title="Physical Dimensions"
                description="Define the physical properties for rack placement."
            />

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="grid gap-2">
                    <Label for="u_height"
                        >U Height <span class="text-red-500">*</span></Label
                    >
                    <select
                        id="u_height"
                        name="u_height"
                        :value="defaultUHeight.toString()"
                        required
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <option
                            v-for="option in uSizeOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="errors.u_height" />
                </div>

                <div class="grid gap-2">
                    <Label for="depth"
                        >Depth <span class="text-red-500">*</span></Label
                    >
                    <select
                        id="depth"
                        name="depth"
                        :value="device.depth ?? 'standard'"
                        required
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <option
                            v-for="option in depthOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="errors.depth" />
                </div>

                <div class="grid gap-2">
                    <Label for="width_type"
                        >Width Type <span class="text-red-500">*</span></Label
                    >
                    <select
                        id="width_type"
                        name="width_type"
                        :value="device.width_type ?? 'full'"
                        required
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <option
                            v-for="option in widthTypeOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="errors.width_type" />
                </div>

                <div class="grid gap-2">
                    <Label for="rack_face"
                        >Rack Face <span class="text-red-500">*</span></Label
                    >
                    <select
                        id="rack_face"
                        name="rack_face"
                        :value="device.rack_face ?? 'front'"
                        required
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <option
                            v-for="option in rackFaceOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="errors.rack_face" />
                </div>
            </div>
        </div>

        <!-- Warranty Information Section -->
        <div class="space-y-4">
            <HeadingSmall
                title="Warranty Information"
                description="Track purchase and warranty dates."
            />

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="grid gap-2">
                    <Label for="purchase_date">Purchase Date</Label>
                    <Input
                        id="purchase_date"
                        name="purchase_date"
                        type="date"
                        :default-value="device.purchase_date ?? ''"
                    />
                    <InputError :message="errors.purchase_date" />
                </div>

                <div class="grid gap-2">
                    <Label for="warranty_start_date">Warranty Start Date</Label>
                    <Input
                        id="warranty_start_date"
                        name="warranty_start_date"
                        type="date"
                        :default-value="device.warranty_start_date ?? ''"
                    />
                    <InputError :message="errors.warranty_start_date" />
                </div>

                <div class="grid gap-2">
                    <Label for="warranty_end_date">Warranty End Date</Label>
                    <Input
                        id="warranty_end_date"
                        name="warranty_end_date"
                        type="date"
                        :default-value="device.warranty_end_date ?? ''"
                    />
                    <InputError :message="errors.warranty_end_date" />
                </div>
            </div>
        </div>

        <!-- Specifications Section -->
        <div class="space-y-4">
            <HeadingSmall
                title="Specifications"
                description="Add custom key-value specifications for this device (e.g., CPU, RAM, Storage)."
            />

            <KeyValueEditor v-model="deviceSpecs" />
            <InputError :message="errors.specs" />
        </div>

        <!-- Notes Section -->
        <div class="space-y-4">
            <HeadingSmall
                title="Additional Notes"
                description="Any additional information about this device."
            />

            <div class="grid gap-2">
                <Label for="notes">Notes</Label>
                <Textarea
                    id="notes"
                    name="notes"
                    :default-value="device.notes ?? ''"
                    placeholder="Enter any additional notes..."
                    rows="3"
                />
                <InputError :message="errors.notes" />
            </div>
        </div>

        <!-- Submit Buttons -->
        <div class="flex items-center gap-4">
            <Button :disabled="processing" type="submit">
                {{
                    processing
                        ? 'Saving...'
                        : mode === 'create'
                          ? 'Create Device'
                          : 'Save Changes'
                }}
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
                <p
                    v-show="recentlySuccessful"
                    class="text-sm text-neutral-600 dark:text-neutral-400"
                >
                    Saved.
                </p>
            </Transition>
        </div>
    </Form>
</template>
