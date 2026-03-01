<script setup lang="ts">
import DatacenterController from '@/actions/App/Http/Controllers/DatacenterController';
import RoomController from '@/actions/App/Http/Controllers/RoomController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import RoomForm from '@/components/rooms/RoomForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { DatacenterReference, RoomTypeOption } from '@/types/rooms';
import { Head } from '@inertiajs/vue3';

interface Props {
    datacenter: DatacenterReference;
    roomTypes: RoomTypeOption[];
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
        title: 'Create',
        href: RoomController.create.url(props.datacenter.id),
    },
];
</script>

<template>
    <Head title="Create Room" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <HeadingSmall
                title="Create Room"
                :description="`Add a new room to ${datacenter.name}.`"
            />

            <div class="max-w-2xl">
                <RoomForm
                    mode="create"
                    :datacenter="datacenter"
                    :room-types="roomTypes"
                />
            </div>
        </div>
    </AppLayout>
</template>
