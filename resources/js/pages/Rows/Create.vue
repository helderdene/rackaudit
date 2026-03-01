<script setup lang="ts">
import DatacenterController from '@/actions/App/Http/Controllers/DatacenterController';
import RoomController from '@/actions/App/Http/Controllers/RoomController';
import RowController from '@/actions/App/Http/Controllers/RowController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import RowForm from '@/components/rows/RowForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type {
    DatacenterReference,
    RoomReference,
    SelectOption,
} from '@/types/rooms';
import { Head } from '@inertiajs/vue3';

interface Props {
    datacenter: DatacenterReference;
    room: RoomReference;
    nextPosition: number;
    orientationOptions: SelectOption[];
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
        title: 'Create Row',
        href: RowController.create.url({
            datacenter: props.datacenter.id,
            room: props.room.id,
        }),
    },
];
</script>

<template>
    <Head title="Create Row" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <HeadingSmall
                title="Create Row"
                :description="`Add a new row to ${room.name}.`"
            />

            <div class="max-w-2xl">
                <RowForm
                    mode="create"
                    :datacenter="datacenter"
                    :room="room"
                    :next-position="nextPosition"
                    :orientation-options="orientationOptions"
                    :status-options="statusOptions"
                />
            </div>
        </div>
    </AppLayout>
</template>
