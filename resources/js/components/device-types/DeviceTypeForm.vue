<script setup lang="ts">
import { computed } from 'vue';
import { Form, router } from '@inertiajs/vue3';
import DeviceTypeController from '@/actions/App/Http/Controllers/DeviceTypeController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

interface DeviceTypeData {
    id?: number;
    name: string;
    description: string | null;
    default_u_size: number;
}

interface Props {
    mode: 'create' | 'edit';
    deviceType?: Partial<DeviceTypeData>;
}

const props = withDefaults(defineProps<Props>(), {
    deviceType: () => ({
        name: '',
        description: null,
        default_u_size: 1,
    }),
});

// Determine form action based on mode
const formAction = computed(() => {
    if (props.mode === 'create') {
        return {
            action: DeviceTypeController.store.url(),
            method: 'post' as const,
        };
    }
    return {
        action: DeviceTypeController.update.url(props.deviceType.id!),
        method: 'post' as const,
    };
});

// Cancel navigation - go back to device types list
const handleCancel = () => {
    router.get(DeviceTypeController.index.url());
};

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
        <input v-if="mode === 'edit'" type="hidden" name="_method" value="PUT" />

        <!-- Device Type Details Section -->
        <div class="space-y-4">
            <HeadingSmall
                title="Device Type Details"
                description="Enter the device type name, description, and default U size."
            />

            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="name">Name <span class="text-red-500">*</span></Label>
                    <Input
                        id="name"
                        name="name"
                        type="text"
                        :default-value="deviceType.name ?? ''"
                        required
                        placeholder="Enter device type name (e.g., Server, Switch)"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="description">Description</Label>
                    <Textarea
                        id="description"
                        name="description"
                        :default-value="deviceType.description ?? ''"
                        placeholder="Enter a description for this device type (optional)"
                        rows="3"
                    />
                    <InputError :message="errors.description" />
                </div>

                <div class="grid gap-2">
                    <Label for="default_u_size">Default U Size <span class="text-red-500">*</span></Label>
                    <select
                        id="default_u_size"
                        name="default_u_size"
                        :value="deviceType.default_u_size?.toString() ?? '1'"
                        required
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option
                            v-for="option in uSizeOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <p class="text-xs text-muted-foreground">
                        The default rack unit height for devices of this type.
                    </p>
                    <InputError :message="errors.default_u_size" />
                </div>
            </div>
        </div>

        <!-- Submit Buttons -->
        <div class="flex items-center gap-4">
            <Button :disabled="processing" type="submit">
                {{ processing ? 'Saving...' : (mode === 'create' ? 'Create Device Type' : 'Save Changes') }}
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
