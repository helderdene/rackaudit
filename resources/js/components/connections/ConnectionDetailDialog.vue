<script setup lang="ts">
import ConnectionTimeline from '@/components/connections/ConnectionTimeline.vue';
import DeleteConnectionConfirmation from '@/components/connections/DeleteConnectionConfirmation.vue';
import EditConnectionDialog from '@/components/connections/EditConnectionDialog.vue';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { CableTypeOption, ConnectionWithPorts } from '@/types/connections';
import { Cable, History, Pencil, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Props {
    /** Connection data with source and destination port info */
    connection: ConnectionWithPorts;
    /** Whether user has edit permissions */
    canEdit?: boolean;
    /** Cable type options for edit dialog */
    cableTypeOptions?: CableTypeOption[];
    /** Whether to show history tab */
    showHistory?: boolean;
}

withDefaults(defineProps<Props>(), {
    canEdit: false,
    cableTypeOptions: () => [],
    showHistory: true,
});

const isOpen = ref(false);
const activeTab = ref('details');
const timelineRef = ref<InstanceType<typeof ConnectionTimeline> | null>(null);

// Reset tab and load timeline when dialog opens
watch(isOpen, (open) => {
    if (open) {
        activeTab.value = 'details';
    }
});

// Lazy load timeline when history tab is selected
watch(activeTab, (tab) => {
    if (tab === 'history' && timelineRef.value) {
        timelineRef.value.loadTimeline();
    }
});

// Format cable length for display
const formatCableLength = (length: number | null): string => {
    if (length === null) return '-';
    return `${length}m`;
};

// Format logical path for display
const formatLogicalPath = (
    path:
        | Array<{ id: number; label: string; device_name: string | null }>
        | undefined,
): string => {
    if (!path || path.length === 0) return '';
    return path
        .map((step) => `${step.device_name || 'Unknown'}: ${step.label}`)
        .join(' -> ');
};
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogTrigger as-child>
            <slot>
                <Button size="sm" variant="ghost"> View Details </Button>
            </slot>
        </DialogTrigger>
        <DialogContent
            class="flex max-h-[85vh] flex-col overflow-hidden sm:max-w-lg"
        >
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Cable class="size-5" />
                    Connection Details
                </DialogTitle>
                <DialogDescription>
                    View the details of this port connection including source,
                    destination, and cable properties.
                </DialogDescription>
            </DialogHeader>

            <Tabs
                v-model="activeTab"
                class="flex flex-1 flex-col overflow-hidden"
            >
                <TabsList class="grid w-full grid-cols-2">
                    <TabsTrigger value="details">Details</TabsTrigger>
                    <TabsTrigger
                        v-if="showHistory"
                        value="history"
                        class="flex items-center gap-1.5"
                    >
                        <History class="h-3.5 w-3.5" />
                        History
                    </TabsTrigger>
                </TabsList>

                <!-- Details Tab -->
                <TabsContent
                    value="details"
                    class="mt-0 flex-1 overflow-y-auto pt-4"
                >
                    <div class="space-y-6">
                        <!-- Source Port Info -->
                        <div class="space-y-2">
                            <h4
                                class="text-sm font-medium text-muted-foreground"
                            >
                                Source
                            </h4>
                            <div
                                class="rounded-lg border bg-muted/30 p-4 dark:bg-muted/20"
                            >
                                <div class="space-y-1">
                                    <p class="font-medium">
                                        {{
                                            connection.source_port.device
                                                ?.name || 'Unknown Device'
                                        }}
                                    </p>
                                    <p class="text-sm text-muted-foreground">
                                        Port: {{ connection.source_port.label }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ connection.source_port.type_label }}
                                        -
                                        {{
                                            connection.source_port
                                                .direction_label
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Destination Port Info -->
                        <div class="space-y-2">
                            <h4
                                class="text-sm font-medium text-muted-foreground"
                            >
                                Destination
                            </h4>
                            <div
                                class="rounded-lg border bg-muted/30 p-4 dark:bg-muted/20"
                            >
                                <div class="space-y-1">
                                    <p class="font-medium">
                                        {{
                                            connection.destination_port.device
                                                ?.name || 'Unknown Device'
                                        }}
                                    </p>
                                    <p class="text-sm text-muted-foreground">
                                        Port:
                                        {{ connection.destination_port.label }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{
                                            connection.destination_port
                                                .type_label
                                        }}
                                        -
                                        {{
                                            connection.destination_port
                                                .direction_label
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Cable Properties -->
                        <div class="space-y-2">
                            <h4
                                class="text-sm font-medium text-muted-foreground"
                            >
                                Cable Properties
                            </h4>
                            <div class="rounded-lg border p-4">
                                <dl class="grid gap-3 text-sm">
                                    <div class="flex justify-between">
                                        <dt class="text-muted-foreground">
                                            Type
                                        </dt>
                                        <dd class="font-medium">
                                            {{
                                                connection.cable_type_label ||
                                                '-'
                                            }}
                                        </dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-muted-foreground">
                                            Length
                                        </dt>
                                        <dd class="font-medium">
                                            {{
                                                formatCableLength(
                                                    connection.cable_length,
                                                )
                                            }}
                                        </dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-muted-foreground">
                                            Color
                                        </dt>
                                        <dd class="font-medium">
                                            {{ connection.cable_color || '-' }}
                                        </dd>
                                    </div>
                                    <div
                                        v-if="connection.path_notes"
                                        class="border-t pt-3"
                                    >
                                        <dt class="mb-1 text-muted-foreground">
                                            Path Notes
                                        </dt>
                                        <dd class="text-sm">
                                            {{ connection.path_notes }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <!-- Logical Path (for patch panel connections) -->
                        <div
                            v-if="
                                connection.logical_path &&
                                connection.logical_path.length > 2
                            "
                            class="space-y-2"
                        >
                            <h4
                                class="text-sm font-medium text-muted-foreground"
                            >
                                Logical Path
                            </h4>
                            <div
                                class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20"
                            >
                                <p
                                    class="text-sm text-blue-800 dark:text-blue-200"
                                >
                                    {{
                                        formatLogicalPath(
                                            connection.logical_path,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </TabsContent>

                <!-- History Tab -->
                <TabsContent
                    v-if="showHistory"
                    value="history"
                    class="mt-0 flex-1 overflow-y-auto pt-4"
                >
                    <ConnectionTimeline
                        ref="timelineRef"
                        :connection-id="connection.id"
                        :auto-load="false"
                    />
                </TabsContent>
            </Tabs>

            <DialogFooter class="gap-2 border-t pt-4">
                <DialogClose as-child>
                    <Button variant="secondary">Close</Button>
                </DialogClose>

                <!-- Edit Button (permission-based) -->
                <EditConnectionDialog
                    v-if="canEdit"
                    :connection="connection"
                    :cable-type-options="cableTypeOptions"
                >
                    <Button variant="outline">
                        <Pencil class="mr-2 size-4" />
                        Edit
                    </Button>
                </EditConnectionDialog>

                <!-- Delete Button (permission-based) -->
                <DeleteConnectionConfirmation
                    v-if="canEdit"
                    :connection="connection"
                    @deleted="isOpen = false"
                >
                    <Button variant="destructive">
                        <Trash2 class="mr-2 size-4" />
                        Delete
                    </Button>
                </DeleteConnectionConfirmation>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
