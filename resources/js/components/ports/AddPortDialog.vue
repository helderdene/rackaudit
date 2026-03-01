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
    typeOptions: PortTypeOption[];
    subtypeOptions: PortSubtypeOption[];
    statusOptions: PortStatusOption[];
    directionOptions: PortDirectionOption[];
}

const props = defineProps<Props>();

const isOpen = ref(false);
const selectedType = ref<PortTypeValue | ''>('');

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

// Reset subtype and direction when type changes
watch(selectedType, () => {
    // Form will handle the reset via default values
});

// Form action URL
const formAction = computed(() => {
    return `/devices/${props.deviceId}/ports`;
});

// Handle successful submission
const handleSuccess = () => {
    isOpen.value = false;
    selectedType.value = '';
};
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogTrigger as-child>
            <slot>
                <Button size="sm">Add Port</Button>
            </slot>
        </DialogTrigger>
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Add Port</DialogTitle>
                <DialogDescription>
                    Add a new port to this device. Select the type to see
                    available subtypes and directions.
                </DialogDescription>
            </DialogHeader>

            <Form
                :action="formAction"
                method="post"
                class="space-y-4"
                @success="handleSuccess"
                v-slot="{ errors, processing }"
            >
                <!-- Label -->
                <div class="grid gap-2">
                    <Label for="add-port-label"
                        >Label <span class="text-red-500">*</span></Label
                    >
                    <Input
                        id="add-port-label"
                        name="label"
                        type="text"
                        placeholder="e.g., eth0, port1, PSU-A"
                        required
                    />
                    <InputError :message="errors.label" />
                </div>

                <!-- Type -->
                <div class="grid gap-2">
                    <Label for="add-port-type"
                        >Type <span class="text-red-500">*</span></Label
                    >
                    <select
                        id="add-port-type"
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
                    <Label for="add-port-subtype"
                        >Subtype <span class="text-red-500">*</span></Label
                    >
                    <select
                        id="add-port-subtype"
                        name="subtype"
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
                    <Label for="add-port-direction">Direction</Label>
                    <select
                        id="add-port-direction"
                        name="direction"
                        :disabled="!selectedType"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option value="">
                            {{
                                selectedType
                                    ? 'Default direction'
                                    : 'Select type first'
                            }}
                        </option>
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
                    <Label for="add-port-status">Status</Label>
                    <select
                        id="add-port-status"
                        name="status"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option value="">Default (Available)</option>
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
                        {{ processing ? 'Adding...' : 'Add Port' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
