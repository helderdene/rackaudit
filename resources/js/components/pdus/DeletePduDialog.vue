<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import PduController from '@/actions/App/Http/Controllers/PduController';
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

interface Props {
    datacenterId: number;
    roomId: number;
    pduId: number;
    pduName: string;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
});

const isDeleting = ref(false);
const isOpen = ref(false);

const handleDelete = () => {
    isDeleting.value = true;

    router.delete(PduController.destroy.url({ datacenter: props.datacenterId, room: props.roomId, pdu: props.pduId }), {
        preserveScroll: true,
        onSuccess: () => {
            isOpen.value = false;
            isDeleting.value = false;
        },
        onError: () => {
            isDeleting.value = false;
        },
    });
};
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogTrigger as-child>
            <slot>
                <Button
                    variant="destructive"
                    size="sm"
                    :disabled="disabled"
                >
                    Delete
                </Button>
            </slot>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader class="space-y-3">
                <DialogTitle>Delete PDU</DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete
                    <span class="font-semibold">{{ pduName }}</span>?
                    This action cannot be undone.
                </DialogDescription>
            </DialogHeader>

            <div
                class="rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
            >
                <div class="relative space-y-0.5 text-red-600 dark:text-red-100">
                    <p class="font-medium">Warning</p>
                    <p class="text-sm">
                        The PDU will be permanently removed from the system.
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
                    {{ isDeleting ? 'Deleting...' : 'Delete PDU' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
