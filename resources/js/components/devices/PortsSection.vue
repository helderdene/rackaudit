<script setup lang="ts">
import { computed } from 'vue';
import { Plug } from 'lucide-vue-next';
import AddPortDialog from '@/components/ports/AddPortDialog.vue';
import BulkAddPortDialog from '@/components/ports/BulkAddPortDialog.vue';
import DeletePortDialog from '@/components/ports/DeletePortDialog.vue';
import EditPortDialog from '@/components/ports/EditPortDialog.vue';
import PortStatusBadge from '@/components/ports/PortStatusBadge.vue';
import CreateConnectionDialog from '@/components/connections/CreateConnectionDialog.vue';
import ConnectionDetailDialog from '@/components/connections/ConnectionDetailDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type {
    PortData,
    PortTypeOption,
    PortSubtypeOption,
    PortStatusOption,
    PortDirectionOption,
} from '@/types/ports';
import type {
    CableTypeOption,
    HierarchicalFilterOptions,
} from '@/types/connections';

interface Props {
    ports: PortData[];
    deviceId: number;
    canEdit: boolean;
    typeOptions: PortTypeOption[];
    subtypeOptions: PortSubtypeOption[];
    statusOptions: PortStatusOption[];
    directionOptions: PortDirectionOption[];
    filterOptions?: HierarchicalFilterOptions;
    cableTypeOptions?: CableTypeOption[];
}

const props = withDefaults(defineProps<Props>(), {
    filterOptions: () => ({
        datacenters: [],
        rooms: [],
        rows: [],
        racks: [],
    }),
    cableTypeOptions: () => [],
});

// Check if any port has a connection (to show/hide the column)
const hasAnyConnections = computed(() => {
    return props.ports.some(port => port.connection !== null && port.connection !== undefined);
});

// Format connection info for display
const getConnectionDisplay = (port: PortData): string => {
    if (port.remote_device_name && port.remote_port_label) {
        return `${port.remote_device_name} : ${port.remote_port_label}`;
    }
    return '-';
};

// Check if a port can be connected (available and canEdit)
const canConnect = (port: PortData): boolean => {
    return props.canEdit && port.status === 'available';
};
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center justify-between">
                <CardTitle class="flex items-center gap-2 text-lg">
                    <Plug class="size-5" />
                    Ports ({{ ports.length }})
                </CardTitle>
                <div v-if="canEdit" class="flex gap-2">
                    <AddPortDialog
                        :device-id="deviceId"
                        :type-options="typeOptions"
                        :subtype-options="subtypeOptions"
                        :status-options="statusOptions"
                        :direction-options="directionOptions"
                    >
                        <Button size="sm">Add Port</Button>
                    </AddPortDialog>
                    <BulkAddPortDialog
                        :device-id="deviceId"
                        :type-options="typeOptions"
                        :subtype-options="subtypeOptions"
                        :direction-options="directionOptions"
                    >
                        <Button size="sm" variant="outline">Bulk Add</Button>
                    </BulkAddPortDialog>
                </div>
            </div>
        </CardHeader>
        <CardContent>
            <!-- Ports Table -->
            <div v-if="ports.length > 0" class="overflow-hidden rounded-md border">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/50">
                            <tr>
                                <th class="h-10 px-4 text-left font-medium text-muted-foreground">Label</th>
                                <th class="h-10 px-4 text-left font-medium text-muted-foreground">Type</th>
                                <th class="h-10 px-4 text-left font-medium text-muted-foreground">Subtype</th>
                                <th class="h-10 px-4 text-left font-medium text-muted-foreground">Direction</th>
                                <th class="h-10 px-4 text-left font-medium text-muted-foreground">Status</th>
                                <th v-if="hasAnyConnections" class="h-10 px-4 text-left font-medium text-muted-foreground">Connected To</th>
                                <th v-if="canEdit" class="h-10 px-4 text-right font-medium text-muted-foreground">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="port in ports"
                                :key="port.id"
                                class="border-b transition-colors hover:bg-muted/50 last:border-b-0"
                            >
                                <td class="p-4 font-medium">{{ port.label }}</td>
                                <td class="p-4">{{ port.type_label }}</td>
                                <td class="p-4">{{ port.subtype_label }}</td>
                                <td class="p-4">{{ port.direction_label }}</td>
                                <td class="p-4">
                                    <PortStatusBadge
                                        :status="port.status"
                                        :label="port.status_label"
                                    />
                                </td>
                                <!-- Connected To Column -->
                                <td v-if="hasAnyConnections" class="p-4">
                                    <template v-if="port.connection">
                                        <ConnectionDetailDialog
                                            :connection="port.connection"
                                            :can-edit="canEdit"
                                            :cable-type-options="cableTypeOptions"
                                        >
                                            <button
                                                type="button"
                                                class="text-left text-sm font-medium text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 rounded"
                                            >
                                                {{ getConnectionDisplay(port) }}
                                            </button>
                                        </ConnectionDetailDialog>
                                    </template>
                                    <template v-else>
                                        <span class="text-muted-foreground">-</span>
                                    </template>
                                </td>
                                <!-- Actions Column -->
                                <td v-if="canEdit" class="p-4 text-right">
                                    <div class="flex justify-end gap-1">
                                        <!-- Connect button for available ports -->
                                        <CreateConnectionDialog
                                            v-if="canConnect(port)"
                                            :source-port="port"
                                            :device-id="deviceId"
                                            :filter-options="filterOptions"
                                            :cable-type-options="cableTypeOptions"
                                            :can-edit="canEdit"
                                        >
                                            <Button size="sm" variant="outline">Connect</Button>
                                        </CreateConnectionDialog>
                                        <EditPortDialog
                                            :device-id="deviceId"
                                            :port="port"
                                            :type-options="typeOptions"
                                            :subtype-options="subtypeOptions"
                                            :status-options="statusOptions"
                                            :direction-options="directionOptions"
                                        >
                                            <Button size="sm" variant="ghost">Edit</Button>
                                        </EditPortDialog>
                                        <DeletePortDialog
                                            :device-id="deviceId"
                                            :port-id="port.id"
                                            :port-label="port.label"
                                        />
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="py-8 text-center">
                <Plug class="mx-auto size-12 text-muted-foreground/50" />
                <h3 class="mt-4 text-sm font-semibold">No ports</h3>
                <p class="mt-1 text-sm text-muted-foreground">
                    This device doesn't have any ports configured yet.
                </p>
                <div v-if="canEdit" class="mt-4 flex justify-center gap-2">
                    <AddPortDialog
                        :device-id="deviceId"
                        :type-options="typeOptions"
                        :subtype-options="subtypeOptions"
                        :status-options="statusOptions"
                        :direction-options="directionOptions"
                    >
                        <Button size="sm">Add Port</Button>
                    </AddPortDialog>
                    <BulkAddPortDialog
                        :device-id="deviceId"
                        :type-options="typeOptions"
                        :subtype-options="subtypeOptions"
                        :direction-options="directionOptions"
                    >
                        <Button size="sm" variant="outline">Bulk Add</Button>
                    </BulkAddPortDialog>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
