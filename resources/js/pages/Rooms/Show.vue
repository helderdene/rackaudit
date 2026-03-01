<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import DatacenterController from '@/actions/App/Http/Controllers/DatacenterController';
import RoomController from '@/actions/App/Http/Controllers/RoomController';
import RowController from '@/actions/App/Http/Controllers/RowController';
import PduController from '@/actions/App/Http/Controllers/PduController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import DeleteRoomDialog from '@/components/rooms/DeleteRoomDialog.vue';
import DeleteRowDialog from '@/components/rows/DeleteRowDialog.vue';
import DeletePduDialog from '@/components/pdus/DeletePduDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { DatacenterReference, RoomData, RowData, PduData } from '@/types/rooms';
import { Building2, LayoutGrid, Zap, Ruler } from 'lucide-vue-next';

interface Props {
    datacenter: DatacenterReference;
    room: RoomData;
    rows: RowData[];
    pdus: PduData[];
    canEdit: boolean;
    canDelete: boolean;
    canCreateRow: boolean;
    canCreatePdu: boolean;
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
    <Head :title="room.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <HeadingSmall
                    :title="room.name"
                    :description="room.type_label || 'Room'"
                />
                <div v-if="canEdit || canDelete" class="flex gap-2">
                    <Link v-if="canEdit" :href="RoomController.edit.url({ datacenter: datacenter.id, room: room.id })">
                        <Button variant="outline">Edit</Button>
                    </Link>
                    <DeleteRoomDialog
                        v-if="canDelete"
                        :datacenter-id="datacenter.id"
                        :room-id="room.id"
                        :room-name="room.name"
                    >
                        <Button variant="destructive">Delete</Button>
                    </DeleteRoomDialog>
                </div>
            </div>

            <!-- Room Details Card -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <Building2 class="size-5" />
                        Room Details
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Name</dt>
                            <dd class="text-sm">{{ room.name }}</dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Type</dt>
                            <dd class="text-sm">{{ room.type_label || '-' }}</dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="flex items-center gap-1 text-sm font-medium text-muted-foreground">
                                <Ruler class="size-4" />
                                Square Footage
                            </dt>
                            <dd class="text-sm">
                                {{ room.square_footage ? `${room.square_footage.toLocaleString()} sq ft` : '-' }}
                            </dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Created</dt>
                            <dd class="text-sm text-muted-foreground">{{ formatDate(room.created_at) }}</dd>
                        </div>
                        <div v-if="room.description" class="grid gap-2 sm:col-span-2 lg:col-span-4">
                            <dt class="text-sm font-medium text-muted-foreground">Description</dt>
                            <dd class="text-sm">{{ room.description }}</dd>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Rows Section -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2 text-lg">
                            <LayoutGrid class="size-5" />
                            Rows
                        </CardTitle>
                        <Link v-if="canCreateRow" :href="RowController.create.url({ datacenter: datacenter.id, room: room.id })">
                            <Button size="sm">Add Row</Button>
                        </Link>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="rows.length > 0" class="overflow-hidden rounded-md border">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="border-b bg-muted/50">
                                    <tr>
                                        <th class="h-10 px-4 text-left font-medium text-muted-foreground">Name</th>
                                        <th class="h-10 px-4 text-left font-medium text-muted-foreground">Position</th>
                                        <th class="h-10 px-4 text-left font-medium text-muted-foreground">Orientation</th>
                                        <th class="h-10 px-4 text-left font-medium text-muted-foreground">Rack Count</th>
                                        <th class="h-10 px-4 text-left font-medium text-muted-foreground">Status</th>
                                        <th class="h-10 w-[140px] px-4 text-left font-medium text-muted-foreground">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="row in rows"
                                        :key="row.id"
                                        class="border-b transition-colors hover:bg-muted/50"
                                    >
                                        <td class="p-4 font-medium">
                                            <Link
                                                :href="RowController.show.url({ datacenter: datacenter.id, room: room.id, row: row.id })"
                                                class="text-primary hover:underline"
                                            >
                                                {{ row.name }}
                                            </Link>
                                        </td>
                                        <td class="p-4">{{ row.position }}</td>
                                        <td class="p-4">{{ row.orientation_label || '-' }}</td>
                                        <td class="p-4 text-muted-foreground">-</td>
                                        <td class="p-4">
                                            <Badge :variant="getStatusVariant(row.status)">
                                                {{ row.status_label || 'Unknown' }}
                                            </Badge>
                                        </td>
                                        <td class="p-4">
                                            <div class="flex items-center gap-2">
                                                <Link :href="RowController.edit.url({ datacenter: datacenter.id, room: room.id, row: row.id })">
                                                    <Button variant="outline" size="sm">Edit</Button>
                                                </Link>
                                                <DeleteRowDialog
                                                    v-if="canDelete"
                                                    :datacenter-id="datacenter.id"
                                                    :room-id="room.id"
                                                    :row-id="row.id"
                                                    :row-name="row.name"
                                                    :has-pdus="(row.pdu_count ?? 0) > 0"
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div v-else class="py-8 text-center text-muted-foreground">
                        No rows have been added to this room yet.
                    </div>
                </CardContent>
            </Card>

            <!-- PDUs Section -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2 text-lg">
                            <Zap class="size-5" />
                            PDUs
                        </CardTitle>
                        <Link v-if="canCreatePdu" :href="PduController.create.url({ datacenter: datacenter.id, room: room.id })">
                            <Button size="sm">Add PDU</Button>
                        </Link>
                    </div>
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
                                        <th class="h-10 px-4 text-left font-medium text-muted-foreground">Circuit Usage</th>
                                        <th class="h-10 px-4 text-left font-medium text-muted-foreground">Status</th>
                                        <th class="h-10 px-4 text-left font-medium text-muted-foreground">Assignment Level</th>
                                        <th class="h-10 w-[140px] px-4 text-left font-medium text-muted-foreground">Actions</th>
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
                                        <td class="p-4 text-muted-foreground">N/A</td>
                                        <td class="p-4">
                                            <Badge :variant="getStatusVariant(pdu.status)">
                                                {{ pdu.status_label || 'Unknown' }}
                                            </Badge>
                                        </td>
                                        <td class="p-4">
                                            <Badge variant="outline">
                                                {{ pdu.assignment_level }}
                                            </Badge>
                                        </td>
                                        <td class="p-4">
                                            <div class="flex items-center gap-2">
                                                <Link :href="PduController.edit.url({ datacenter: datacenter.id, room: room.id, pdu: pdu.id })">
                                                    <Button variant="outline" size="sm">Edit</Button>
                                                </Link>
                                                <DeletePduDialog
                                                    v-if="canDelete"
                                                    :datacenter-id="datacenter.id"
                                                    :room-id="room.id"
                                                    :pdu-id="pdu.id"
                                                    :pdu-name="pdu.name"
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div v-else class="py-8 text-center text-muted-foreground">
                        No PDUs have been added to this room yet.
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
