<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { edit } from '@/actions/App/Http/Controllers/DeviceController';
import RackController from '@/actions/App/Http/Controllers/RackController';
import { diagramPage } from '@/actions/App/Http/Controllers/ConnectionController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import QrCodeDialog from '@/components/common/QrCodeDialog.vue';
import DeleteDeviceDialog from '@/components/devices/DeleteDeviceDialog.vue';
import PortsSection from '@/components/devices/PortsSection.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { DeviceData } from '@/types/rooms';
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
import { Server, Settings, Shield, FileText, MapPin, QrCode, Cable } from 'lucide-vue-next';

interface Props {
    device: DeviceData;
    canEdit: boolean;
    canDelete: boolean;
    ports: PortData[];
    portTypeOptions: PortTypeOption[];
    portSubtypeOptions: PortSubtypeOption[];
    portStatusOptions: PortStatusOption[];
    portDirectionOptions: PortDirectionOption[];
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

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Devices',
        href: '/devices',
    },
    {
        title: props.device.name,
        href: `/devices/${props.device.id}`,
    },
];

// Format date for display
const formatDate = (dateString: string | null | undefined): string => {
    if (!dateString) {
        return '-';
    }
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

// Format datetime for display
const formatDateTime = (dateString: string | undefined): string => {
    if (!dateString) {
        return '-';
    }
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// Get lifecycle status badge variant
const getLifecycleStatusVariant = (status: string | null): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (status) {
        case 'deployed':
            return 'default';
        case 'in_stock':
        case 'received':
            return 'secondary';
        case 'maintenance':
        case 'decommissioned':
            return 'destructive';
        case 'ordered':
        case 'disposed':
            return 'outline';
        default:
            return 'outline';
    }
};

// Get warranty status badge variant
const getWarrantyStatusVariant = (status: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (status) {
        case 'active':
            return 'default';
        case 'expired':
            return 'destructive';
        case 'none':
        default:
            return 'outline';
    }
};

// Format warranty status label
const getWarrantyStatusLabel = (status: string): string => {
    switch (status) {
        case 'active':
            return 'Active';
        case 'expired':
            return 'Expired';
        case 'none':
        default:
            return 'None';
    }
};

// Check if specs has any entries
const hasSpecs = () => {
    return props.device.specs && Object.keys(props.device.specs).length > 0;
};

// Get connection diagram URL for this device
const connectionDiagramUrl = diagramPage.url({ query: { device_id: props.device.id } });
</script>

<template>
    <Head :title="device.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <HeadingSmall
                        :title="device.name"
                        :description="device.device_type?.name || 'Device'"
                    />
                    <p class="mt-1 font-mono text-sm text-muted-foreground">
                        {{ device.asset_tag }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link :href="connectionDiagramUrl">
                        <Button variant="outline">
                            <Cable class="size-4" />
                            View Connections
                        </Button>
                    </Link>
                    <QrCodeDialog
                        entity-type="device"
                        :entity-id="device.id"
                        :entity-name="device.name"
                        :secondary-label="device.asset_tag"
                    >
                        <Button variant="outline">
                            <QrCode class="size-4" />
                            QR Code
                        </Button>
                    </QrCodeDialog>
                    <Link v-if="canEdit" :href="edit.url(device.id)">
                        <Button variant="outline">Edit</Button>
                    </Link>
                    <DeleteDeviceDialog
                        v-if="canDelete"
                        :device-id="device.id"
                        :device-name="device.name"
                    >
                        <Button variant="destructive">Delete</Button>
                    </DeleteDeviceDialog>
                </div>
            </div>

            <!-- Device Details Card -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <Server class="size-5" />
                        Device Details
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Name</dt>
                            <dd class="text-sm">{{ device.name }}</dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Device Type</dt>
                            <dd class="text-sm">{{ device.device_type?.name || '-' }}</dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Lifecycle Status</dt>
                            <dd>
                                <Badge :variant="getLifecycleStatusVariant(device.lifecycle_status)">
                                    {{ device.lifecycle_status_label || 'Unknown' }}
                                </Badge>
                            </dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Asset Tag</dt>
                            <dd class="font-mono text-sm">{{ device.asset_tag }}</dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Serial Number</dt>
                            <dd class="text-sm">{{ device.serial_number || '-' }}</dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Manufacturer</dt>
                            <dd class="text-sm">{{ device.manufacturer || '-' }}</dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Model</dt>
                            <dd class="text-sm">{{ device.model || '-' }}</dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Created</dt>
                            <dd class="text-sm text-muted-foreground">{{ formatDateTime(device.created_at) }}</dd>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Physical Dimensions Card -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <Settings class="size-5" />
                        Physical Dimensions
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">U Height</dt>
                            <dd class="text-sm">{{ device.u_height }}U</dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Depth</dt>
                            <dd class="text-sm">{{ device.depth_label || '-' }}</dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Width Type</dt>
                            <dd class="text-sm">{{ device.width_type_label || '-' }}</dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Rack Face</dt>
                            <dd class="text-sm">{{ device.rack_face_label || '-' }}</dd>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Rack Placement Card -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <MapPin class="size-5" />
                        Rack Placement
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="device.rack?.id" class="grid gap-6 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Rack</dt>
                            <dd class="text-sm">
                                <Link
                                    v-if="device.rack.datacenter_id && device.rack.room_id && device.rack.row_id"
                                    :href="RackController.show.url({
                                        datacenter: device.rack.datacenter_id,
                                        room: device.rack.room_id,
                                        row: device.rack.row_id,
                                        rack: device.rack.id
                                    })"
                                    class="text-primary hover:underline"
                                >
                                    {{ device.rack.name }}
                                </Link>
                                <span v-else>{{ device.rack.name }}</span>
                            </dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Starting U Position</dt>
                            <dd class="text-sm">U{{ device.start_u || '-' }}</dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Ending U Position</dt>
                            <dd class="text-sm">
                                <template v-if="device.start_u">
                                    U{{ device.start_u + device.u_height - 1 }}
                                </template>
                                <template v-else>-</template>
                            </dd>
                        </div>
                    </div>
                    <div v-else class="py-4 text-center text-muted-foreground">
                        This device is not placed in any rack. It is currently in inventory.
                    </div>
                </CardContent>
            </Card>

            <!-- Ports Section -->
            <PortsSection
                :ports="ports"
                :device-id="device.id"
                :can-edit="canEdit"
                :type-options="portTypeOptions"
                :subtype-options="portSubtypeOptions"
                :status-options="portStatusOptions"
                :direction-options="portDirectionOptions"
                :filter-options="filterOptions"
                :cable-type-options="cableTypeOptions"
            />

            <!-- Warranty Information Card -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <Shield class="size-5" />
                        Warranty Information
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Warranty Status</dt>
                            <dd>
                                <Badge :variant="getWarrantyStatusVariant(device.warranty_status)">
                                    {{ getWarrantyStatusLabel(device.warranty_status) }}
                                </Badge>
                            </dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Purchase Date</dt>
                            <dd class="text-sm">{{ formatDate(device.purchase_date) }}</dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Warranty Start</dt>
                            <dd class="text-sm">{{ formatDate(device.warranty_start_date) }}</dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Warranty End</dt>
                            <dd class="text-sm">{{ formatDate(device.warranty_end_date) }}</dd>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Specifications Card -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <FileText class="size-5" />
                        Specifications
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="hasSpecs()" class="overflow-hidden rounded-md border">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="border-b bg-muted/50">
                                    <tr>
                                        <th class="h-10 px-4 text-left font-medium text-muted-foreground">Key</th>
                                        <th class="h-10 px-4 text-left font-medium text-muted-foreground">Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(value, key) in device.specs"
                                        :key="key"
                                        class="border-b transition-colors hover:bg-muted/50"
                                    >
                                        <td class="p-4 font-medium">{{ key }}</td>
                                        <td class="p-4">{{ value }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div v-else class="py-4 text-center text-muted-foreground">
                        No specifications recorded for this device.
                    </div>
                </CardContent>
            </Card>

            <!-- Notes Card -->
            <Card v-if="device.notes">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <FileText class="size-5" />
                        Notes
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="whitespace-pre-wrap text-sm">{{ device.notes }}</p>
                </CardContent>
            </Card>

            <!-- Back to Devices -->
            <div>
                <Link href="/devices">
                    <Button variant="outline">Back to Devices</Button>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
