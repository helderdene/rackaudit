<script setup lang="ts">
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
import type { ConnectionWithPorts } from '@/types/connections';
import { router } from '@inertiajs/vue3';
import { AlertTriangle } from 'lucide-vue-next';
import { ref } from 'vue';

interface Props {
    /** Connection to be deleted */
    connection: ConnectionWithPorts;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'deleted'): void;
}>();

const isDeleting = ref(false);
const isOpen = ref(false);

const handleDelete = () => {
    isDeleting.value = true;

    router.delete(`/connections/${props.connection.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            isOpen.value = false;
            isDeleting.value = false;
            emit('deleted');
        },
        onError: () => {
            isDeleting.value = false;
        },
    });
};

// Get source device name for display
const sourceDeviceName =
    props.connection.source_port.device?.name || 'Unknown Device';
const sourcePortLabel = props.connection.source_port.label;

// Get destination device name for display
const destDeviceName =
    props.connection.destination_port.device?.name || 'Unknown Device';
const destPortLabel = props.connection.destination_port.label;
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogTrigger as-child>
            <slot>
                <Button
                    variant="ghost"
                    size="sm"
                    class="text-destructive hover:text-destructive"
                >
                    Delete
                </Button>
            </slot>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader class="space-y-3">
                <DialogTitle class="flex items-center gap-2">
                    <AlertTriangle class="size-5 text-destructive" />
                    Delete Connection
                </DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete this connection? This action
                    cannot be undone.
                </DialogDescription>
            </DialogHeader>

            <!-- Connection Summary -->
            <div class="rounded-lg border bg-muted/30 p-4 dark:bg-muted/20">
                <div class="space-y-2 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="text-muted-foreground">From:</span>
                        <span class="font-medium">
                            {{ sourceDeviceName }} ({{ sourcePortLabel }})
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-muted-foreground">To:</span>
                        <span class="font-medium">
                            {{ destDeviceName }} ({{ destPortLabel }})
                        </span>
                    </div>
                    <div
                        v-if="connection.cable_type_label"
                        class="flex items-center gap-2"
                    >
                        <span class="text-muted-foreground">Cable:</span>
                        <span class="font-medium">
                            {{ connection.cable_type_label }}
                            {{
                                connection.cable_length
                                    ? `(${connection.cable_length}m)`
                                    : ''
                            }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Warning -->
            <div
                class="rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
            >
                <div
                    class="relative space-y-0.5 text-red-600 dark:text-red-100"
                >
                    <p class="font-medium">Warning</p>
                    <p class="text-sm">
                        Both ports will be set back to "Available" status. The
                        connection record will be permanently removed.
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
                    {{ isDeleting ? 'Deleting...' : 'Delete Connection' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
