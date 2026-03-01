<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import DatacenterController from '@/actions/App/Http/Controllers/DatacenterController';
import RoomController from '@/actions/App/Http/Controllers/RoomController';
import PduController from '@/actions/App/Http/Controllers/PduController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import DeletePduDialog from '@/components/pdus/DeletePduDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { DatacenterReference, RoomReference, PduData } from '@/types/rooms';
import { Zap, Settings, MapPin } from 'lucide-vue-next';

interface Props {
    datacenter: DatacenterReference;
    room: RoomReference;
    pdu: PduData;
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
        href: RoomController.show.url({ datacenter: props.datacenter.id, room: props.room.id }),
    },
    {
        title: props.pdu.name,
        href: PduController.show.url({ datacenter: props.datacenter.id, room: props.room.id, pdu: props.pdu.id }),
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
    <Head :title="pdu.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <HeadingSmall
                    :title="pdu.name"
                    :description="pdu.model || 'PDU'"
                />
                <div v-if="canEdit || canDelete" class="flex gap-2">
                    <Link v-if="canEdit" :href="PduController.edit.url({ datacenter: datacenter.id, room: room.id, pdu: pdu.id })">
                        <Button variant="outline">Edit</Button>
                    </Link>
                    <DeletePduDialog
                        v-if="canDelete"
                        :datacenter-id="datacenter.id"
                        :room-id="room.id"
                        :pdu-id="pdu.id"
                        :pdu-name="pdu.name"
                    >
                        <Button variant="destructive">Delete</Button>
                    </DeletePduDialog>
                </div>
            </div>

            <!-- PDU Details Card -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <Zap class="size-5" />
                        PDU Information
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Name</dt>
                            <dd class="text-sm">{{ pdu.name }}</dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Model</dt>
                            <dd class="text-sm">{{ pdu.model || '-' }}</dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Manufacturer</dt>
                            <dd class="text-sm">{{ pdu.manufacturer || '-' }}</dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Status</dt>
                            <dd>
                                <Badge :variant="getStatusVariant(pdu.status)">
                                    {{ pdu.status_label || 'Unknown' }}
                                </Badge>
                            </dd>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Power Specifications Card -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <Settings class="size-5" />
                        Power Specifications
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Total Capacity</dt>
                            <dd class="text-sm">
                                {{ pdu.total_capacity_kw ? `${pdu.total_capacity_kw} kW` : '-' }}
                            </dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Voltage</dt>
                            <dd class="text-sm">
                                {{ pdu.voltage ? `${pdu.voltage} V` : '-' }}
                            </dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Phase</dt>
                            <dd class="text-sm">{{ pdu.phase_label || '-' }}</dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Circuit Count</dt>
                            <dd class="text-sm">{{ pdu.circuit_count }}</dd>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Assignment Card -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <MapPin class="size-5" />
                        Assignment
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Assignment Level</dt>
                            <dd>
                                <Badge variant="outline">
                                    {{ pdu.assignment_level }}
                                </Badge>
                            </dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Created</dt>
                            <dd class="text-sm text-muted-foreground">{{ formatDate(pdu.created_at) }}</dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Updated</dt>
                            <dd class="text-sm text-muted-foreground">{{ formatDate(pdu.updated_at) }}</dd>
                        </div>
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
