<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { Cable, Server, Plug, ArrowRight } from 'lucide-vue-next';
import type { ComparisonResultData } from '@/types/comparison';

interface Props {
    open: boolean;
    comparison: ComparisonResultData | null;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const isOpen = computed({
    get: () => props.open,
    set: (value) => emit('update:open', value),
});

const connection = computed(() => props.comparison?.actual_connection);
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Cable class="size-5" />
                    Connection Details
                </DialogTitle>
                <DialogDescription>
                    Physical connection between two device ports
                </DialogDescription>
            </DialogHeader>

            <div v-if="comparison && connection" class="space-y-6">
                <!-- Connection Flow -->
                <div class="flex items-center justify-between gap-4 rounded-lg border bg-muted/30 p-4">
                    <!-- Source -->
                    <div class="flex-1 text-center">
                        <div class="flex items-center justify-center gap-1 text-muted-foreground">
                            <Server class="size-4" />
                            <span class="text-xs">Source</span>
                        </div>
                        <p class="mt-1 font-medium">{{ comparison.source_device?.name }}</p>
                        <div class="mt-1 flex items-center justify-center gap-1">
                            <Plug class="size-3 text-muted-foreground" />
                            <span class="text-sm">{{ comparison.source_port?.label }}</span>
                        </div>
                    </div>

                    <!-- Arrow -->
                    <ArrowRight class="size-5 shrink-0 text-muted-foreground" />

                    <!-- Destination -->
                    <div class="flex-1 text-center">
                        <div class="flex items-center justify-center gap-1 text-muted-foreground">
                            <Server class="size-4" />
                            <span class="text-xs">Destination</span>
                        </div>
                        <p class="mt-1 font-medium">{{ comparison.dest_device?.name }}</p>
                        <div class="mt-1 flex items-center justify-center gap-1">
                            <Plug class="size-3 text-muted-foreground" />
                            <span class="text-sm">{{ comparison.dest_port?.label }}</span>
                        </div>
                    </div>
                </div>

                <!-- Cable Details -->
                <div class="space-y-3">
                    <h4 class="text-sm font-medium text-muted-foreground">Cable Properties</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-muted-foreground">Type</p>
                            <Badge variant="secondary" class="mt-1">
                                {{ connection.cable_type_label }}
                            </Badge>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Length</p>
                            <p class="mt-1 font-medium">{{ connection.cable_length }}m</p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Color</p>
                            <p class="mt-1 font-medium">{{ connection.cable_color || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Port Type</p>
                            <p class="mt-1 font-medium">{{ comparison.source_port?.type_label }}</p>
                        </div>
                    </div>
                </div>

                <!-- Device Links -->
                <div class="space-y-3">
                    <h4 class="text-sm font-medium text-muted-foreground">Quick Links</h4>
                    <div class="flex flex-wrap gap-2">
                        <Link
                            v-if="comparison.source_device?.id"
                            :href="`/devices/${comparison.source_device.id}`"
                            class="text-sm text-primary hover:underline"
                        >
                            View {{ comparison.source_device.name }}
                        </Link>
                        <span class="text-muted-foreground">|</span>
                        <Link
                            v-if="comparison.dest_device?.id"
                            :href="`/devices/${comparison.dest_device.id}`"
                            class="text-sm text-primary hover:underline"
                        >
                            View {{ comparison.dest_device.name }}
                        </Link>
                    </div>
                </div>

                <!-- Status Badge -->
                <div class="flex items-center justify-between border-t pt-4">
                    <span class="text-sm text-muted-foreground">Status</span>
                    <Badge variant="default" class="bg-green-600">
                        {{ comparison.discrepancy_type_label }}
                    </Badge>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
