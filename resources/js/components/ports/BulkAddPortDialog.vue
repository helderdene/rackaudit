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
    directionOptions: PortDirectionOption[];
}

const props = defineProps<Props>();

const isOpen = ref(false);
const selectedType = ref<PortTypeValue | ''>('');
const prefix = ref('');
const startNumber = ref<number | null>(1);
const endNumber = ref<number | null>(24);

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

// Generate preview of labels
const labelPreview = computed(() => {
    if (!prefix.value || !startNumber.value || !endNumber.value) {
        return '';
    }
    if (startNumber.value > endNumber.value) {
        return '';
    }

    const start = startNumber.value;
    const end = endNumber.value;
    const count = end - start + 1;

    if (count <= 0 || count > 100) {
        return '';
    }

    const labels: string[] = [];

    if (count <= 5) {
        // Show all labels
        for (let i = start; i <= end; i++) {
            labels.push(`${prefix.value}${i}`);
        }
        return labels.join(', ');
    } else {
        // Show first 2, ellipsis, last 2
        labels.push(`${prefix.value}${start}`);
        labels.push(`${prefix.value}${start + 1}`);
        labels.push('...');
        labels.push(`${prefix.value}${end - 1}`);
        labels.push(`${prefix.value}${end}`);
        return `${labels.join(', ')} (${count} ports)`;
    }
});

// Form action URL
const formAction = computed(() => {
    return `/devices/${props.deviceId}/ports/bulk`;
});

// Handle successful submission
const handleSuccess = () => {
    isOpen.value = false;
    selectedType.value = '';
    prefix.value = '';
    startNumber.value = 1;
    endNumber.value = 24;
};

// Reset form when dialog closes
watch(isOpen, (newVal) => {
    if (!newVal) {
        selectedType.value = '';
        prefix.value = '';
        startNumber.value = 1;
        endNumber.value = 24;
    }
});
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogTrigger as-child>
            <slot>
                <Button size="sm" variant="outline">Bulk Add</Button>
            </slot>
        </DialogTrigger>
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Bulk Add Ports</DialogTitle>
                <DialogDescription>
                    Create multiple ports at once using a naming pattern. All
                    ports will have the same type and settings.
                </DialogDescription>
            </DialogHeader>

            <Form
                :action="formAction"
                method="post"
                class="space-y-4"
                @success="handleSuccess"
                v-slot="{ errors, processing }"
            >
                <!-- Prefix -->
                <div class="grid gap-2">
                    <Label for="bulk-port-prefix"
                        >Label Prefix <span class="text-red-500">*</span></Label
                    >
                    <Input
                        id="bulk-port-prefix"
                        name="prefix"
                        type="text"
                        v-model="prefix"
                        placeholder="e.g., eth, port, ge"
                        required
                    />
                    <InputError :message="errors.prefix" />
                </div>

                <!-- Number Range -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="bulk-port-start"
                            >Start Number
                            <span class="text-red-500">*</span></Label
                        >
                        <Input
                            id="bulk-port-start"
                            name="start_number"
                            type="number"
                            v-model.number="startNumber"
                            min="1"
                            required
                        />
                        <InputError :message="errors.start_number" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="bulk-port-end"
                            >End Number
                            <span class="text-red-500">*</span></Label
                        >
                        <Input
                            id="bulk-port-end"
                            name="end_number"
                            type="number"
                            v-model.number="endNumber"
                            min="1"
                            required
                        />
                        <InputError :message="errors.end_number" />
                    </div>
                </div>

                <!-- Preview -->
                <div
                    v-if="labelPreview"
                    class="rounded-md border bg-muted/50 p-3"
                >
                    <p class="text-xs font-medium text-muted-foreground">
                        Preview:
                    </p>
                    <p class="mt-1 font-mono text-sm">{{ labelPreview }}</p>
                </div>

                <!-- Type -->
                <div class="grid gap-2">
                    <Label for="bulk-port-type"
                        >Type <span class="text-red-500">*</span></Label
                    >
                    <select
                        id="bulk-port-type"
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
                    <Label for="bulk-port-subtype"
                        >Subtype <span class="text-red-500">*</span></Label
                    >
                    <select
                        id="bulk-port-subtype"
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
                    <Label for="bulk-port-direction">Direction</Label>
                    <select
                        id="bulk-port-direction"
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
                        {{ processing ? 'Creating...' : 'Create Ports' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
