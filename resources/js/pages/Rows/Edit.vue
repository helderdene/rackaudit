<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DatacenterController from '@/actions/App/Http/Controllers/DatacenterController';
import RoomController from '@/actions/App/Http/Controllers/RoomController';
import RowController from '@/actions/App/Http/Controllers/RowController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import RowForm from '@/components/rows/RowForm.vue';
import DeleteRowDialog from '@/components/rows/DeleteRowDialog.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { DatacenterReference, RoomReference, RowData, SelectOption } from '@/types/rooms';

interface Props {
    datacenter: DatacenterReference;
    room: RoomReference;
    row: RowData;
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
        href: RoomController.show.url({ datacenter: props.datacenter.id, room: props.room.id }),
    },
    {
        title: `Edit ${props.row.name}`,
        href: RowController.edit.url({ datacenter: props.datacenter.id, room: props.room.id, row: props.row.id }),
    },
];
</script>

<template>
    <Head :title="`Edit ${row.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <HeadingSmall
                title="Edit Row"
                :description="`Update information for ${row.name}.`"
            />

            <div class="max-w-2xl">
                <RowForm
                    mode="edit"
                    :datacenter="datacenter"
                    :room="room"
                    :row="row"
                    :orientation-options="orientationOptions"
                    :status-options="statusOptions"
                />
            </div>

            <!-- Delete Row Section -->
            <div class="max-w-2xl space-y-6">
                <HeadingSmall
                    title="Delete Row"
                    description="Permanently remove this row from the room."
                />
                <div
                    class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
                >
                    <div class="relative space-y-0.5 text-red-600 dark:text-red-100">
                        <p class="font-medium">Warning</p>
                        <p class="text-sm">
                            Once deleted, this row will be permanently removed.
                            Any PDUs assigned to this row will be reassigned to the room level.
                        </p>
                    </div>
                    <DeleteRowDialog
                        :datacenter-id="datacenter.id"
                        :room-id="room.id"
                        :row-id="row.id"
                        :row-name="row.name"
                        :has-pdus="(row.pdu_count ?? 0) > 0"
                    >
                        <Button variant="destructive">
                            Delete Row
                        </Button>
                    </DeleteRowDialog>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
