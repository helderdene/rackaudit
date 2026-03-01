<script setup lang="ts">
import { diagramPage } from '@/actions/App/Http/Controllers/ConnectionController';
import DatacenterController from '@/actions/App/Http/Controllers/DatacenterController';
import DeviceController from '@/actions/App/Http/Controllers/DeviceController';
import RackController from '@/actions/App/Http/Controllers/RackController';
import RoomController from '@/actions/App/Http/Controllers/RoomController';
import RowController from '@/actions/App/Http/Controllers/RowController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import QrCodeDialog from '@/components/common/QrCodeDialog.vue';
import MiniElevationPreview from '@/components/elevation/MiniElevationPreview.vue';
import DeleteRackDialog from '@/components/racks/DeleteRackDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type {
    DatacenterReference,
    PduData,
    PlaceholderDevice,
    PowerMetrics,
    RackData,
    RackDevice,
    RoomReference,
    RowReference,
    UtilizationStats,
} from '@/types/rooms';
import { Head, Link } from '@inertiajs/vue3';
import {
    Cable,
    FileText,
    LayoutGrid,
    QrCode,
    Server,
    Settings,
    Zap,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    datacenter: DatacenterReference;
    room: RoomReference;
    row: RowReference;
    rack: RackData;
    pdus: PduData[];
    devices: RackDevice[];
    utilization: UtilizationStats;
    powerMetrics: PowerMetrics;
    canEdit: boolean;
    canDelete: boolean;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Datacenters',
        href: DatacenterController.index.url(),
    },
    {
        title: props.datacenter.name,
        href: DatacenterController.show.url(props.datacenter.id),
    },
    {
        title: 'Rooms',
        href: RoomController.index.url(props.datacenter.id),
    },
    {
        title: props.room.name,
        href: RoomController.show.url({
            datacenter: props.datacenter.id,
            room: props.room.id,
        }),
    },
    {
        title: 'Rows',
        href: RowController.index.url({
            datacenter: props.datacenter.id,
            room: props.room.id,
        }),
    },
    {
        title: props.row.name,
        href: RowController.show.url({
            datacenter: props.datacenter.id,
            room: props.room.id,
            row: props.row.id,
        }),
    },
    {
        title: 'Racks',
        href: RackController.index.url({
            datacenter: props.datacenter.id,
            room: props.room.id,
            row: props.row.id,
        }),
    },
    {
        title: props.rack.name,
        href: RackController.show.url({
            datacenter: props.datacenter.id,
            room: props.room.id,
            row: props.row.id,
            rack: props.rack.id,
        }),
    },
];

// Compute the rack's URL path for QR code generation
const rackPath = computed(() => {
    return RackController.show.url({
        datacenter: props.datacenter.id,
        room: props.room.id,
        row: props.row.id,
        rack: props.rack.id,
    });
});

// Get connection diagram URL for this rack
const connectionDiagramUrl = diagramPage.url({
    query: { rack_id: props.rack.id },
});

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

// Get status badge variant
const getStatusVariant = (
    status: string | null,
): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (status) {
        case 'active':
            return 'default';
        case 'inactive':
            return 'secondary';
        case 'maintenance':
            return 'destructive';
        default:
            return 'outline';
    }
};

// Get lifecycle status badge variant (for devices)
const getLifecycleStatusVariant = (
    status: string | null,
): 'default' | 'secondary' | 'destructive' | 'outline' => {
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

// Get utilization color class based on percentage
const getUtilizationColorClass = (percent: number): string => {
    if (percent < 70) {
        return 'text-green-600 dark:text-green-400';
    }
    if (percent < 90) {
        return 'text-yellow-600 dark:text-yellow-400';
    }
    return 'text-red-600 dark:text-red-400';
};

// Get utilization progress bar color class
const getUtilizationBarClass = (percent: number): string => {
    if (percent < 70) {
        return 'bg-green-500';
    }
    if (percent < 90) {
        return 'bg-yellow-500';
    }
    return 'bg-red-500';
};

// Get power utilization color class
const getPowerColorClass = (percent: number): string => {
    if (percent < 80) {
        return 'text-green-600 dark:text-green-400';
    }
    return 'text-amber-600 dark:text-amber-400';
};

// Check if specs has any entries
const hasSpecs = computed(() => {
    return props.rack.specs && Object.keys(props.rack.specs).length > 0;
});

// Convert RackDevice to PlaceholderDevice format for mini elevation preview
const elevationDevices = computed<PlaceholderDevice[]>(() => {
    return props.devices.map((device) => ({
        id: String(device.id),
        name: device.name,
        type: device.type.toLowerCase(),
        u_size: device.u_height,
        width: 'full' as const,
        start_u: device.start_u,
        face: 'front' as const,
    }));
});
</script>

<template>
    <Head :title="rack.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <HeadingSmall
                    :title="rack.name"
                    :description="rack.u_height_label || 'Rack'"
                />
                <div class="flex gap-2">
                    <QrCodeDialog
                        entity-type="rack"
                        :entity-id="rack.id"
                        :entity-name="rack.name"
                        :secondary-label="rack.serial_number"
                        :entity-path="rackPath"
                    >
                        <Button variant="outline">
                            <QrCode class="size-4" />
                            QR Code
                        </Button>
                    </QrCodeDialog>
                    <Link :href="connectionDiagramUrl">
                        <Button variant="outline">
                            <Cable class="size-4" />
                            Connection Diagram
                        </Button>
                    </Link>
                    <Link
                        :href="
                            RackController.elevation.url({
                                datacenter: datacenter.id,
                                room: room.id,
                                row: row.id,
                                rack: rack.id,
                            })
                        "
                    >
                        <Button variant="secondary">View Elevation</Button>
                    </Link>
                    <Link
                        v-if="canEdit"
                        :href="
                            RackController.edit.url({
                                datacenter: datacenter.id,
                                room: room.id,
                                row: row.id,
                                rack: rack.id,
                            })
                        "
                    >
                        <Button variant="outline">Edit</Button>
                    </Link>
                    <DeleteRackDialog
                        v-if="canDelete"
                        :datacenter-id="datacenter.id"
                        :room-id="room.id"
                        :row-id="row.id"
                        :rack-id="rack.id"
                        :rack-name="rack.name"
                        :has-pdus="pdus.length > 0"
                    >
                        <Button variant="destructive">Delete</Button>
                    </DeleteRackDialog>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Left Column: Details and Metrics -->
                <div class="flex flex-col gap-6 lg:col-span-2">
                    <!-- Rack Details Card -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-lg">
                                <Server class="size-5" />
                                Rack Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div
                                class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4"
                            >
                                <div class="grid gap-2">
                                    <dt
                                        class="text-sm font-medium text-muted-foreground"
                                    >
                                        Name
                                    </dt>
                                    <dd class="text-sm">{{ rack.name }}</dd>
                                </div>
                                <div class="grid gap-2">
                                    <dt
                                        class="text-sm font-medium text-muted-foreground"
                                    >
                                        Position
                                    </dt>
                                    <dd class="text-sm">{{ rack.position }}</dd>
                                </div>
                                <div class="grid gap-2">
                                    <dt
                                        class="text-sm font-medium text-muted-foreground"
                                    >
                                        U-Height
                                    </dt>
                                    <dd class="text-sm">
                                        {{ rack.u_height_label || '-' }}
                                    </dd>
                                </div>
                                <div class="grid gap-2">
                                    <dt
                                        class="text-sm font-medium text-muted-foreground"
                                    >
                                        Status
                                    </dt>
                                    <dd>
                                        <Badge
                                            :variant="
                                                getStatusVariant(rack.status)
                                            "
                                        >
                                            {{ rack.status_label || 'Unknown' }}
                                        </Badge>
                                    </dd>
                                </div>
                                <div class="grid gap-2">
                                    <dt
                                        class="text-sm font-medium text-muted-foreground"
                                    >
                                        Manufacturer
                                    </dt>
                                    <dd class="text-sm">
                                        {{ rack.manufacturer || '-' }}
                                    </dd>
                                </div>
                                <div class="grid gap-2">
                                    <dt
                                        class="text-sm font-medium text-muted-foreground"
                                    >
                                        Model
                                    </dt>
                                    <dd class="text-sm">
                                        {{ rack.model || '-' }}
                                    </dd>
                                </div>
                                <div class="grid gap-2">
                                    <dt
                                        class="text-sm font-medium text-muted-foreground"
                                    >
                                        Depth
                                    </dt>
                                    <dd class="text-sm">
                                        {{ rack.depth || '-' }}
                                    </dd>
                                </div>
                                <div class="grid gap-2">
                                    <dt
                                        class="text-sm font-medium text-muted-foreground"
                                    >
                                        Serial Number
                                    </dt>
                                    <dd class="text-sm">
                                        {{ rack.serial_number || '-' }}
                                    </dd>
                                </div>
                                <div class="grid gap-2">
                                    <dt
                                        class="text-sm font-medium text-muted-foreground"
                                    >
                                        Installation Date
                                    </dt>
                                    <dd class="text-sm">
                                        {{ formatDate(rack.installation_date) }}
                                    </dd>
                                </div>
                                <div class="grid gap-2">
                                    <dt
                                        class="text-sm font-medium text-muted-foreground"
                                    >
                                        Created
                                    </dt>
                                    <dd class="text-sm text-muted-foreground">
                                        {{ formatDateTime(rack.created_at) }}
                                    </dd>
                                </div>
                            </div>

                            <!-- Location Notes (full width when present) -->
                            <div
                                v-if="rack.location_notes"
                                class="mt-6 border-t pt-4"
                            >
                                <dt
                                    class="text-sm font-medium text-muted-foreground"
                                >
                                    Location Notes
                                </dt>
                                <dd class="mt-1 text-sm whitespace-pre-wrap">
                                    {{ rack.location_notes }}
                                </dd>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Utilization & Power Metrics Card -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-lg">
                                <Settings class="size-5" />
                                Capacity Metrics
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="grid gap-6 sm:grid-cols-2">
                                <!-- U-Space Utilization -->
                                <div class="space-y-3">
                                    <div
                                        class="flex items-baseline justify-between"
                                    >
                                        <span
                                            class="text-sm font-medium text-muted-foreground"
                                            >U-Space Utilization</span
                                        >
                                        <span
                                            :class="[
                                                'text-lg font-semibold',
                                                getUtilizationColorClass(
                                                    utilization.utilizationPercent,
                                                ),
                                            ]"
                                        >
                                            {{
                                                utilization.utilizationPercent
                                            }}%
                                        </span>
                                    </div>
                                    <div
                                        class="h-2 w-full overflow-hidden rounded-full bg-muted"
                                    >
                                        <div
                                            :class="[
                                                'h-full transition-all duration-300',
                                                getUtilizationBarClass(
                                                    utilization.utilizationPercent,
                                                ),
                                            ]"
                                            :style="{
                                                width: `${Math.min(utilization.utilizationPercent, 100)}%`,
                                            }"
                                        />
                                    </div>
                                    <p class="text-sm text-muted-foreground">
                                        <span class="font-medium">{{
                                            utilization.usedU
                                        }}</span>
                                        of
                                        <span class="font-medium">{{
                                            utilization.totalU
                                        }}</span>
                                        U-spaces occupied
                                        <span class="text-xs"
                                            >({{
                                                utilization.availableU
                                            }}
                                            available)</span
                                        >
                                    </p>
                                </div>

                                <!-- Power Utilization -->
                                <div class="space-y-3">
                                    <div
                                        class="flex items-baseline justify-between"
                                    >
                                        <span
                                            class="text-sm font-medium text-muted-foreground"
                                            >Power Utilization</span
                                        >
                                        <span
                                            v-if="powerMetrics.pduCapacity > 0"
                                            :class="[
                                                'text-lg font-semibold',
                                                getPowerColorClass(
                                                    powerMetrics.powerUtilizationPercent,
                                                ),
                                            ]"
                                        >
                                            {{
                                                powerMetrics.powerUtilizationPercent
                                            }}%
                                        </span>
                                        <span
                                            v-else
                                            class="text-sm text-muted-foreground"
                                            >N/A</span
                                        >
                                    </div>
                                    <div
                                        v-if="powerMetrics.pduCapacity > 0"
                                        class="h-2 w-full overflow-hidden rounded-full bg-muted"
                                    >
                                        <div
                                            :class="[
                                                'h-full transition-all duration-300',
                                                powerMetrics.powerUtilizationPercent >=
                                                80
                                                    ? 'bg-amber-500'
                                                    : 'bg-green-500',
                                            ]"
                                            :style="{
                                                width: `${Math.min(powerMetrics.powerUtilizationPercent, 100)}%`,
                                            }"
                                        />
                                    </div>
                                    <p
                                        v-if="powerMetrics.pduCapacity > 0"
                                        class="flex items-center gap-1 text-sm text-muted-foreground"
                                    >
                                        <Zap class="size-3.5" />
                                        <span class="font-medium"
                                            >{{
                                                powerMetrics.totalPowerDraw.toLocaleString()
                                            }}
                                            W</span
                                        >
                                        of
                                        <span class="font-medium"
                                            >{{
                                                powerMetrics.pduCapacity.toLocaleString()
                                            }}
                                            W</span
                                        >
                                        capacity
                                    </p>
                                    <p
                                        v-else
                                        class="text-sm text-muted-foreground"
                                    >
                                        No PDUs assigned to this rack
                                    </p>
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
                            <div
                                v-if="hasSpecs"
                                class="overflow-hidden rounded-md border"
                            >
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead class="border-b bg-muted/50">
                                            <tr>
                                                <th
                                                    class="h-10 px-4 text-left font-medium text-muted-foreground"
                                                >
                                                    Key
                                                </th>
                                                <th
                                                    class="h-10 px-4 text-left font-medium text-muted-foreground"
                                                >
                                                    Value
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="(
                                                    value, key
                                                ) in rack.specs"
                                                :key="key"
                                                class="border-b transition-colors hover:bg-muted/50"
                                            >
                                                <td class="p-4 font-medium">
                                                    {{ key }}
                                                </td>
                                                <td class="p-4">{{ value }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div
                                v-else
                                class="py-4 text-center text-muted-foreground"
                            >
                                No specifications recorded for this rack.
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Installed Devices Card -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-lg">
                                <Server class="size-5" />
                                Installed Devices
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div
                                v-if="devices.length > 0"
                                class="overflow-hidden rounded-md border"
                            >
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead class="border-b bg-muted/50">
                                            <tr>
                                                <th
                                                    class="h-10 px-4 text-left font-medium text-muted-foreground"
                                                >
                                                    Name
                                                </th>
                                                <th
                                                    class="h-10 px-4 text-left font-medium text-muted-foreground"
                                                >
                                                    Type
                                                </th>
                                                <th
                                                    class="h-10 px-4 text-left font-medium text-muted-foreground"
                                                >
                                                    U Position
                                                </th>
                                                <th
                                                    class="h-10 px-4 text-left font-medium text-muted-foreground"
                                                >
                                                    Status
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="device in devices"
                                                :key="device.id"
                                                class="border-b transition-colors hover:bg-muted/50"
                                            >
                                                <td class="p-4 font-medium">
                                                    <Link
                                                        :href="
                                                            DeviceController.show.url(
                                                                device.id,
                                                            )
                                                        "
                                                        class="text-primary hover:underline"
                                                    >
                                                        {{ device.name }}
                                                    </Link>
                                                </td>
                                                <td class="p-4">
                                                    {{ device.type }}
                                                </td>
                                                <td class="p-4 font-mono">
                                                    U{{ device.start_u }}
                                                </td>
                                                <td class="p-4">
                                                    <Badge
                                                        :variant="
                                                            getLifecycleStatusVariant(
                                                                device.lifecycle_status,
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            device.lifecycle_status_label ||
                                                            'Unknown'
                                                        }}
                                                    </Badge>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div
                                v-else
                                class="py-8 text-center text-muted-foreground"
                            >
                                No devices installed in this rack.
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Assigned PDUs Section -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-lg">
                                <Zap class="size-5" />
                                Assigned PDUs
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div
                                v-if="pdus.length > 0"
                                class="overflow-hidden rounded-md border"
                            >
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead class="border-b bg-muted/50">
                                            <tr>
                                                <th
                                                    class="h-10 px-4 text-left font-medium text-muted-foreground"
                                                >
                                                    Name
                                                </th>
                                                <th
                                                    class="h-10 px-4 text-left font-medium text-muted-foreground"
                                                >
                                                    Model
                                                </th>
                                                <th
                                                    class="h-10 px-4 text-left font-medium text-muted-foreground"
                                                >
                                                    Capacity
                                                </th>
                                                <th
                                                    class="h-10 px-4 text-left font-medium text-muted-foreground"
                                                >
                                                    Status
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="pdu in pdus"
                                                :key="pdu.id"
                                                class="border-b transition-colors hover:bg-muted/50"
                                            >
                                                <td class="p-4 font-medium">
                                                    {{ pdu.name }}
                                                </td>
                                                <td class="p-4">
                                                    {{ pdu.model || '-' }}
                                                </td>
                                                <td class="p-4">
                                                    {{
                                                        pdu.total_capacity_kw
                                                            ? `${pdu.total_capacity_kw} kW`
                                                            : '-'
                                                    }}
                                                </td>
                                                <td class="p-4">
                                                    <Badge
                                                        :variant="
                                                            getStatusVariant(
                                                                pdu.status,
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            pdu.status_label ||
                                                            'Unknown'
                                                        }}
                                                    </Badge>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div
                                v-else
                                class="py-8 text-center text-muted-foreground"
                            >
                                No PDUs assigned to this rack.
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Right Column: Mini Elevation Preview -->
                <div class="lg:col-span-1">
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-lg">
                                <LayoutGrid class="size-5" />
                                Elevation Preview
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="flex justify-center">
                            <MiniElevationPreview
                                :rack="rack"
                                :devices="elevationDevices"
                                :datacenter-id="datacenter.id"
                                :room-id="room.id"
                                :row-id="row.id"
                            />
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Back to Row -->
            <div>
                <Link
                    :href="
                        RowController.show.url({
                            datacenter: datacenter.id,
                            room: room.id,
                            row: row.id,
                        })
                    "
                >
                    <Button variant="outline">Back to Row</Button>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
