<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DatacenterController from '@/actions/App/Http/Controllers/DatacenterController';
import RoomController from '@/actions/App/Http/Controllers/RoomController';
import PduController from '@/actions/App/Http/Controllers/PduController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import PduForm from '@/components/pdus/PduForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { DatacenterReference, RoomReference, RowReference, SelectOption } from '@/types/rooms';

interface Props {
    datacenter: DatacenterReference;
    room: RoomReference;
    rows: RowReference[];
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
        href: RoomController.show.url({ datacenter: props.datacenter.id, room: props.room.id }),
    },
    {
        title: 'Create PDU',
        href: PduController.create.url({ datacenter: props.datacenter.id, room: props.room.id }),
    },
];
</script>

<template>
    <Head title="Create PDU" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <HeadingSmall
                title="Create PDU"
                :description="`Add a new PDU to ${room.name}.`"
            />

            <div class="max-w-2xl">
                <PduForm
                    mode="create"
                    :datacenter="datacenter"
                    :room="room"
                    :rows="rows"
                    :phase-options="phaseOptions"
                    :status-options="statusOptions"
                />
            </div>
        </div>
    </AppLayout>
</template>
