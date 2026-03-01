<script setup lang="ts">
import DatacenterController from '@/actions/App/Http/Controllers/DatacenterController';
import PduController from '@/actions/App/Http/Controllers/PduController';
import RoomController from '@/actions/App/Http/Controllers/RoomController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import DeletePduDialog from '@/components/pdus/DeletePduDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type {
    DatacenterReference,
    PduData,
    RoomReference,
    RowReference,
    SelectOption,
} from '@/types/rooms';
import { Head, Link } from '@inertiajs/vue3';

interface Props {
    datacenter: DatacenterReference;
    room: RoomReference;
    pdus: PduData[];
    rows: RowReference[];
    canCreate: boolean;
    phaseOptions: SelectOption[];
    statusOptions: SelectOption[];
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
        title: 'PDUs',
        href: PduController.index.url({
            datacenter: props.datacenter.id,
            room: props.room.id,
        }),
    },
];

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
</script>

<template>
    <Head title="PDUs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <HeadingSmall
                    title="PDU Management"
                    :description="`Manage PDUs within ${room.name}.`"
                />
                <Link
                    v-if="canCreate"
                    :href="
                        PduController.create.url({
                            datacenter: datacenter.id,
                            room: room.id,
                        })
                    "
                >
                    <Button>Add PDU</Button>
                </Link>
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-md border">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/50">
                            <tr>
                                <th
                                    class="h-12 px-4 text-left font-medium text-muted-foreground"
                                >
                                    Name
                                </th>
                                <th
                                    class="h-12 px-4 text-left font-medium text-muted-foreground"
                                >
                                    Model
                                </th>
                                <th
                                    class="h-12 px-4 text-left font-medium text-muted-foreground"
                                >
                                    Capacity
                                </th>
                                <th
                                    class="h-12 px-4 text-left font-medium text-muted-foreground"
                                >
                                    Circuits
                                </th>
                                <th
                                    class="h-12 px-4 text-left font-medium text-muted-foreground"
                                >
                                    Status
                                </th>
                                <th
                                    class="h-12 px-4 text-left font-medium text-muted-foreground"
                                >
                                    Assignment
                                </th>
                                <th
                                    class="h-12 w-[140px] px-4 text-left font-medium text-muted-foreground"
                                >
                                    Actions
                                </th>
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
                                    {{
                                        pdu.total_capacity_kw
                                            ? `${pdu.total_capacity_kw} kW`
                                            : '-'
                                    }}
                                </td>
                                <td class="p-4 text-muted-foreground">
                                    {{ pdu.circuit_count }}
                                </td>
                                <td class="p-4">
                                    <Badge
                                        :variant="getStatusVariant(pdu.status)"
                                    >
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
                                        <Link
                                            :href="
                                                PduController.edit.url({
                                                    datacenter: datacenter.id,
                                                    room: room.id,
                                                    pdu: pdu.id,
                                                })
                                            "
                                        >
                                            <Button variant="outline" size="sm"
                                                >Edit</Button
                                            >
                                        </Link>
                                        <DeletePduDialog
                                            :datacenter-id="datacenter.id"
                                            :room-id="room.id"
                                            :pdu-id="pdu.id"
                                            :pdu-name="pdu.name"
                                        />
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="pdus.length === 0">
                                <td
                                    colspan="7"
                                    class="p-8 text-center text-muted-foreground"
                                >
                                    No PDUs found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Back to Room -->
            <div>
                <Link
                    :href="
                        RoomController.show.url({
                            datacenter: datacenter.id,
                            room: room.id,
                        })
                    "
                >
                    <Button variant="outline">Back to Room</Button>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
