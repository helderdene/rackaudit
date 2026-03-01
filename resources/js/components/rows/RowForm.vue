<script setup lang="ts">
import RoomController from '@/actions/App/Http/Controllers/RoomController';
import RowController from '@/actions/App/Http/Controllers/RowController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    DatacenterReference,
    RoomReference,
    RowData,
    SelectOption,
} from '@/types/rooms';
import { Form, router } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    mode: 'create' | 'edit';
    datacenter: DatacenterReference;
    room: RoomReference;
    row?: Partial<RowData>;
    nextPosition?: number;
    orientationOptions: SelectOption[];
    statusOptions: SelectOption[];
}

const props = withDefaults(defineProps<Props>(), {
    row: () => ({
        name: '',
        position: 0,
        orientation: '',
        status: '',
    }),
    nextPosition: 1,
});

// Determine form action based on mode
const formAction = computed(() => {
    if (props.mode === 'create') {
        return {
            action: RowController.store.url({
                datacenter: props.datacenter.id,
                room: props.room.id,
            }),
            method: 'post' as const,
        };
    }
    return {
        action: RowController.update.url({
            datacenter: props.datacenter.id,
            room: props.room.id,
            row: props.row.id!,
        }),
        method: 'post' as const,
    };
});

// Cancel navigation
const handleCancel = () => {
    router.get(
        RoomController.show.url({
            datacenter: props.datacenter.id,
            room: props.room.id,
        }),
    );
};

// Default position for new rows
const defaultPosition = computed(() => {
    if (props.mode === 'create') {
        return props.nextPosition;
    }
    return props.row?.position ?? 0;
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
        <input
            v-if="mode === 'edit'"
            type="hidden"
            name="_method"
            value="PUT"
        />

        <!-- Row Details Section -->
        <div class="space-y-4">
            <HeadingSmall
                title="Row Details"
                description="Enter the row name, position, orientation, and status."
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
                        :default-value="row.name ?? ''"
                        required
                        placeholder="Enter row name"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="position"
                        >Position <span class="text-red-500">*</span></Label
                    >
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
                    <Label for="orientation"
                        >Orientation <span class="text-red-500">*</span></Label
                    >
                    <select
                        id="orientation"
                        name="orientation"
                        :value="row.orientation ?? ''"
                        required
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option value="" disabled>Select orientation</option>
                        <option
                            v-for="option in orientationOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="errors.orientation" />
                </div>

                <div class="grid gap-2">
                    <Label for="status"
                        >Status <span class="text-red-500">*</span></Label
                    >
                    <select
                        id="status"
                        name="status"
                        :value="row.status ?? ''"
                        required
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
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
                {{
                    processing
                        ? 'Saving...'
                        : mode === 'create'
                          ? 'Create Row'
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
