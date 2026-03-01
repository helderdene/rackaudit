<script setup lang="ts">
import DatacenterController from '@/actions/App/Http/Controllers/DatacenterController';
import RackController from '@/actions/App/Http/Controllers/RackController';
import RoomController from '@/actions/App/Http/Controllers/RoomController';
import RowController from '@/actions/App/Http/Controllers/RowController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import RackForm from '@/components/racks/RackForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type {
    DatacenterReference,
    PduOption,
    RoomReference,
    RowReference,
    SelectOption,
} from '@/types/rooms';
import { Head } from '@inertiajs/vue3';

interface Props {
    datacenter: DatacenterReference;
    room: RoomReference;
    row: RowReference;
    nextPosition: number;
    statusOptions: SelectOption[];
    uHeightOptions: SelectOption[];
    pduOptions: PduOption[];
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
        title: 'Create Rack',
        href: RackController.create.url({
            datacenter: props.datacenter.id,
            room: props.room.id,
            row: props.row.id,
        }),
    },
];
</script>

<template>
    <Head title="Create Rack" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <HeadingSmall
                title="Create Rack"
                :description="`Add a new rack to ${row.name}.`"
            />

            <div class="max-w-2xl">
                <RackForm
                    mode="create"
                    :datacenter="datacenter"
                    :room="room"
                    :row="row"
                    :next-position="nextPosition"
                    :status-options="statusOptions"
                    :u-height-options="uHeightOptions"
                    :pdu-options="pduOptions"
                />
            </div>
        </div>
    </AppLayout>
</template>
