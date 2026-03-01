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
import type {
    PortData,
    PortDirectionOption,
    PortStatusOption,
    PortSubtypeOption,
    PortTypeOption,
    PortTypeValue,
} from '@/types/ports';
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface Props {
    deviceId: number;
    port: PortData;
    typeOptions: PortTypeOption[];
    subtypeOptions: PortSubtypeOption[];
    statusOptions: PortStatusOption[];
    directionOptions: PortDirectionOption[];
}

const props = defineProps<Props>();

const isOpen = ref(false);
const selectedType = ref<PortTypeValue>(props.port.type);

// Watch for port prop changes to update selectedType
watch(
    () => props.port,
    (newPort) => {
        selectedType.value = newPort.type;
    },
    { immediate: true },
);

// Reset selected type when dialog opens
watch(isOpen, (newVal) => {
    if (newVal) {
        selectedType.value = props.port.type;
    }
});

// Filter subtypes based on selected type
const filteredSubtypeOptions = computed(() => {
    if (!selectedType.value) {
        return [];
    }
    return props.subtypeOptions.filter(
        (opt) => opt.type === selectedType.value,
    );
});

// Filter directions based on selected type
const filteredDirectionOptions = computed(() => {
    if (!selectedType.value) {
        return [];
    }
    return props.directionOptions.filter((opt) =>
        opt.types.includes(selectedType.value as PortTypeValue),
    );
});

// Form action URL
const formAction = computed(() => {
    return `/devices/${props.deviceId}/ports/${props.port.id}`;
});

// Handle successful submission
const handleSuccess = () => {
    isOpen.value = false;
};
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
                <DialogTitle>Edit Port</DialogTitle>
                <DialogDescription>
                    Update the port configuration. Changing the type will reset
                    subtype and direction options.
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

                <!-- Label -->
                <div class="grid gap-2">
                    <Label for="edit-port-label"
                        >Label <span class="text-red-500">*</span></Label
                    >
                    <Input
                        id="edit-port-label"
                        name="label"
                        type="text"
                        :default-value="port.label"
                        placeholder="e.g., eth0, port1, PSU-A"
                        required
                    />
                    <InputError :message="errors.label" />
                </div>

                <!-- Type -->
                <div class="grid gap-2">
                    <Label for="edit-port-type"
                        >Type <span class="text-red-500">*</span></Label
                    >
                    <select
                        id="edit-port-type"
                        name="type"
                        v-model="selectedType"
                        required
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option value="" disabled>Select type</option>
                        <option
                            v-for="option in typeOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="errors.type" />
                </div>

                <!-- Subtype (filtered by type) -->
                <div class="grid gap-2">
                    <Label for="edit-port-subtype"
                        >Subtype <span class="text-red-500">*</span></Label
                    >
                    <select
                        id="edit-port-subtype"
                        name="subtype"
                        :value="selectedType === port.type ? port.subtype : ''"
                        required
                        :disabled="!selectedType"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option value="" disabled>
                            {{
                                selectedType
                                    ? 'Select subtype'
                                    : 'Select type first'
                            }}
                        </option>
                        <option
                            v-for="option in filteredSubtypeOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="errors.subtype" />
                </div>

                <!-- Direction (filtered by type) -->
                <div class="grid gap-2">
                    <Label for="edit-port-direction">Direction</Label>
                    <select
                        id="edit-port-direction"
                        name="direction"
                        :value="
                            selectedType === port.type ? port.direction : ''
                        "
                        :disabled="!selectedType"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option value="">Default direction</option>
                        <option
                            v-for="option in filteredDirectionOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="errors.direction" />
                </div>

                <!-- Status -->
                <div class="grid gap-2">
                    <Label for="edit-port-status">Status</Label>
                    <select
                        id="edit-port-status"
                        name="status"
                        :value="port.status"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    >
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
