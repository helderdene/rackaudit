<script setup lang="ts">
import RoomController from '@/actions/App/Http/Controllers/RoomController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    DatacenterReference,
    RoomData,
    RoomTypeOption,
} from '@/types/rooms';
import { Form, router } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    mode: 'create' | 'edit';
    datacenter: DatacenterReference;
    room?: Partial<RoomData>;
    roomTypes: RoomTypeOption[];
}

const props = withDefaults(defineProps<Props>(), {
    room: () => ({
        name: '',
        description: null,
        square_footage: null,
        type: '',
    }),
});

// Determine form action based on mode
const formAction = computed(() => {
    if (props.mode === 'create') {
        return {
            action: RoomController.store.url(props.datacenter.id),
            method: 'post' as const,
        };
    }
    return {
        action: RoomController.update.url({
            datacenter: props.datacenter.id,
            room: props.room.id!,
        }),
        method: 'post' as const,
    };
});

// Cancel navigation
const handleCancel = () => {
    if (props.mode === 'create') {
        router.get(RoomController.index.url(props.datacenter.id));
    } else {
        router.get(
            RoomController.show.url({
                datacenter: props.datacenter.id,
                room: props.room.id!,
            }),
        );
    }
};
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

        <!-- Room Details Section -->
        <div class="space-y-4">
            <HeadingSmall
                title="Room Details"
                description="Enter the room name, type, and optional details."
            />

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="name"
                        >Name <span class="text-red-500">*</span></Label
                    >
                    <Input
                        id="name"
                        name="name"
                        type="text"
                        :default-value="room.name ?? ''"
                        required
                        placeholder="Enter room name"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label for="type"
                        >Room Type <span class="text-red-500">*</span></Label
                    >
                    <select
                        id="type"
                        name="type"
                        :value="room.type ?? ''"
                        required
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option value="" disabled>Select a room type</option>
                        <option
                            v-for="typeOption in roomTypes"
                            :key="typeOption.value"
                            :value="typeOption.value"
                        >
                            {{ typeOption.label }}
                        </option>
                    </select>
                    <InputError :message="errors.type" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label for="description">Description</Label>
                    <textarea
                        id="description"
                        name="description"
                        :value="room.description ?? ''"
                        rows="3"
                        placeholder="Enter room description (optional)"
                        class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    />
                    <InputError :message="errors.description" />
                </div>

                <div class="grid gap-2">
                    <Label for="square_footage">Square Footage</Label>
                    <Input
                        id="square_footage"
                        name="square_footage"
                        type="number"
                        step="0.01"
                        min="0"
                        :default-value="room.square_footage?.toString() ?? ''"
                        placeholder="e.g., 2500.50"
                    />
                    <InputError :message="errors.square_footage" />
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
                          ? 'Create Room'
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
