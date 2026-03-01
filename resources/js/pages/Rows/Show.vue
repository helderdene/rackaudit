<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import DatacenterController from '@/actions/App/Http/Controllers/DatacenterController';
import RoomController from '@/actions/App/Http/Controllers/RoomController';
import RowController from '@/actions/App/Http/Controllers/RowController';
import RackController from '@/actions/App/Http/Controllers/RackController';
import PduController from '@/actions/App/Http/Controllers/PduController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import DeleteRowDialog from '@/components/rows/DeleteRowDialog.vue';
import DeletePduDialog from '@/components/pdus/DeletePduDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { DatacenterReference, RoomReference, RowData, PduData, RackData } from '@/types/rooms';
import { LayoutGrid, Zap, Server } from 'lucide-vue-next';

interface Props {
    datacenter: DatacenterReference;
    room: RoomReference;
    row: RowData;
    pdus: PduData[];
    racks: RackData[];
    canEdit: boolean;
    canDelete: boolean;
    canCreateRack: boolean;
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
        href: RoomController.show.url({ datacenter: props.datacenter.id, room: props.room.id }),
    },
    {
        title: props.row.name,
        href: RowController.show.url({ datacenter: props.datacenter.id, room: props.room.id, row: props.row.id }),
    },
];

// Format date for display
const formatDate = (dateString: string | undefined): string => {
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
const getStatusVariant = (status: string | null): 'default' | 'secondary' | 'destructive' | 'outline' => {
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
</script>

<template>
    <Head :title="row.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <HeadingSmall
                    :title="row.name"
                    :description="row.orientation_label || 'Row'"
                />
                <div v-if="canEdit || canDelete" class="flex gap-2">
                    <Link v-if="canEdit" :href="RowController.edit.url({ datacenter: datacenter.id, room: room.id, row: row.id })">
                        <Button variant="outline">Edit</Button>
                    </Link>
                    <DeleteRowDialog
                        v-if="canDelete"
                        :datacenter-id="datacenter.id"
                        :room-id="room.id"
                        :row-id="row.id"
                        :row-name="row.name"
                        :has-pdus="pdus.length > 0"
                    >
                        <Button variant="destructive">Delete</Button>
                    </DeleteRowDialog>
                </div>
            </div>

            <!-- Row Details Card -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <LayoutGrid class="size-5" />
                        Row Details
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Name</dt>
                            <dd class="text-sm">{{ row.name }}</dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Position</dt>
                            <dd class="text-sm">{{ row.position }}</dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Orientation</dt>
                            <dd class="text-sm">{{ row.orientation_label || '-' }}</dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Status</dt>
                            <dd>
                                <Badge :variant="getStatusVariant(row.status)">
                                    {{ row.status_label || 'Unknown' }}
                                </Badge>
                            </dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Created</dt>
                            <dd class="text-sm text-muted-foreground">{{ formatDate(row.created_at) }}</dd>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Racks Section -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0">
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <Server class="size-5" />
                        Racks in this Row
                    </CardTitle>
                    <div class="flex items-center gap-2">
                        <Link :href="RackController.index.url({ datacenter: datacenter.id, room: room.id, row: row.id })">
                            <Button variant="ghost" size="sm">View All Racks</Button>
                        </Link>
                        <Link v-if="canCreateRack" :href="RackController.create.url({ datacenter: datacenter.id, room: room.id, row: row.id })">
                            <Button size="sm">Add Rack</Button>
                        </Link>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="racks.length > 0" class="overflow-hidden rounded-md border">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="border-b bg-muted/50">
                                    <tr>
                                        <th class="h-10 px-4 text-left font-medium text-muted-foreground">Position</th>
                                        <th class="h-10 px-4 text-left font-medium text-muted-foreground">Name</th>
                                        <th class="h-10 px-4 text-left font-medium text-muted-foreground">U-Height</th>
                                        <th class="h-10 px-4 text-left font-medium text-muted-foreground">Status</th>
                                        <th class="h-10 w-[100px] px-4 text-left font-medium text-muted-foreground">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="rack in racks"
                                        :key="rack.id"
                                        class="border-b transition-colors hover:bg-muted/50"
                                    >
                                        <td class="p-4 text-muted-foreground">{{ rack.position }}</td>
                                        <td class="p-4 font-medium">
                                            <Link
                                                :href="RackController.show.url({ datacenter: datacenter.id, room: room.id, row: row.id, rack: rack.id })"
                                                class="text-primary hover:underline"
                                            >
                                                {{ rack.name }}
                                            </Link>
                                        </td>
                                        <td class="p-4">{{ rack.u_height_label || '-' }}</td>
                                        <td class="p-4">
                                            <Badge :variant="getStatusVariant(rack.status)">
                                                {{ rack.status_label || 'Unknown' }}
                                            </Badge>
                                        </td>
                                        <td class="p-4">
                                            <div class="flex items-center gap-2">
                                                <Link :href="RackController.edit.url({ datacenter: datacenter.id, room: room.id, row: row.id, rack: rack.id })">
                                                    <Button variant="outline" size="sm">Edit</Button>
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div v-else class="py-8 text-center text-muted-foreground">
                        No racks in this row.
                        <Link
                            v-if="canCreateRack"
                            :href="RackController.create.url({ datacenter: datacenter.id, room: room.id, row: row.id })"
                            class="text-primary hover:underline"
                        >
                            Add the first rack.
                        </Link>
                    </div>
                </CardContent>
            </Card>

            <!-- PDUs Section -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <Zap class="size-5" />
                        PDUs in this Row
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="pdus.length > 0" class="overflow-hidden rounded-md border">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="border-b bg-muted/50">
                                    <tr>
                                        <th class="h-10 px-4 text-left font-medium text-muted-foreground">Name</th>
                                        <th class="h-10 px-4 text-left font-medium text-muted-foreground">Model</th>
                                        <th class="h-10 px-4 text-left font-medium text-muted-foreground">Capacity</th>
                                        <th class="h-10 px-4 text-left font-medium text-muted-foreground">Circuits</th>
                                        <th class="h-10 px-4 text-left font-medium text-muted-foreground">Status</th>
                                        <th class="h-10 w-[100px] px-4 text-left font-medium text-muted-foreground">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="pdu in pdus"
                                        :key="pdu.id"
                                        class="border-b transition-colors hover:bg-muted/50"
                                    >
                                        <td class="p-4 font-medium">{{ pdu.name }}</td>
                                        <td class="p-4">{{ pdu.model || '-' }}</td>
                                        <td class="p-4">
                                            {{ pdu.total_capacity_kw ? `${pdu.total_capacity_kw} kW` : '-' }}
                                        </td>
                                        <td class="p-4 text-muted-foreground">{{ pdu.circuit_count }}</td>
                                        <td class="p-4">
                                            <Badge :variant="getStatusVariant(pdu.status)">
                                                {{ pdu.status_label || 'Unknown' }}
                                            </Badge>
                                        </td>
                                        <td class="p-4">
                                            <div class="flex items-center gap-2">
                                                <Link :href="PduController.edit.url({ datacenter: datacenter.id, room: room.id, pdu: pdu.id })">
                                                    <Button variant="outline" size="sm">Edit</Button>
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div v-else class="py-8 text-center text-muted-foreground">
                        No PDUs assigned to this row.
                    </div>
                </CardContent>
            </Card>

            <!-- Back to Room -->
            <div>
                <Link :href="RoomController.show.url({ datacenter: datacenter.id, room: room.id })">
                    <Button variant="outline">Back to Room</Button>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
