<script setup lang="ts">
import DatacenterController from '@/actions/App/Http/Controllers/DatacenterController';
import RoomController from '@/actions/App/Http/Controllers/RoomController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import DeleteRoomDialog from '@/components/rooms/DeleteRoomDialog.vue';
import RoomForm from '@/components/rooms/RoomForm.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type {
    DatacenterReference,
    RoomData,
    RoomTypeOption,
} from '@/types/rooms';
import { Head } from '@inertiajs/vue3';

interface Props {
    datacenter: DatacenterReference;
    room: RoomData;
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
        title: props.room.name,
        href: RoomController.show.url({
            datacenter: props.datacenter.id,
            room: props.room.id,
        }),
    },
    {
        title: 'Edit',
        href: RoomController.edit.url({
            datacenter: props.datacenter.id,
            room: props.room.id,
        }),
    },
];
</script>

<template>
    <Head :title="`Edit ${room.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <HeadingSmall
                title="Edit Room"
                :description="`Update information for ${room.name}.`"
            />

            <div class="max-w-2xl">
                <RoomForm
                    mode="edit"
                    :datacenter="datacenter"
                    :room="room"
                    :room-types="roomTypes"
                />
            </div>

            <!-- Delete Room Section -->
            <div class="max-w-2xl space-y-6">
                <HeadingSmall
                    title="Delete Room"
                    description="Permanently remove this room and all its rows and PDUs from the system."
                />
                <div
                    class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
                >
                    <div
                        class="relative space-y-0.5 text-red-600 dark:text-red-100"
                    >
                        <p class="font-medium">Warning</p>
                        <p class="text-sm">
                            Once deleted, this room and all associated rows and
                            PDUs will be permanently removed. This action cannot
                            be undone.
                        </p>
                    </div>
                    <DeleteRoomDialog
                        :datacenter-id="datacenter.id"
                        :room-id="room.id"
                        :room-name="room.name"
                    >
                        <Button variant="destructive"> Delete Room </Button>
                    </DeleteRoomDialog>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
