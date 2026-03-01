<script setup lang="ts">
import RoomController from '@/actions/App/Http/Controllers/RoomController';
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
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Props {
    datacenterId: number;
    roomId: number;
    roomName: string;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
});

const isDeleting = ref(false);
const isOpen = ref(false);

const handleDelete = () => {
    isDeleting.value = true;

    router.delete(
        RoomController.destroy.url({
            datacenter: props.datacenterId,
            room: props.roomId,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                isOpen.value = false;
                isDeleting.value = false;
            },
            onError: () => {
                isDeleting.value = false;
            },
        },
    );
};
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogTrigger as-child>
            <slot>
                <Button variant="destructive" size="sm" :disabled="disabled">
                    Delete
                </Button>
            </slot>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader class="space-y-3">
                <DialogTitle>Delete Room</DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete
                    <span class="font-semibold">{{ roomName }}</span
                    >? This action cannot be undone.
                </DialogDescription>
            </DialogHeader>

            <div
                class="rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
            >
                <div
                    class="relative space-y-0.5 text-red-600 dark:text-red-100"
                >
                    <p class="font-medium">Warning</p>
                    <p class="text-sm">
                        The room will be permanently removed from the system.
                        All associated rows and PDUs will also be deleted.
                    </p>
                </div>
            </div>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary" :disabled="isDeleting">
                        Cancel
                    </Button>
                </DialogClose>

                <Button
                    variant="destructive"
                    :disabled="isDeleting"
                    @click="handleDelete"
                >
                    {{ isDeleting ? 'Deleting...' : 'Delete Room' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
