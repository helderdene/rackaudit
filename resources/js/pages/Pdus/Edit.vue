<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DatacenterController from '@/actions/App/Http/Controllers/DatacenterController';
import RoomController from '@/actions/App/Http/Controllers/RoomController';
import PduController from '@/actions/App/Http/Controllers/PduController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import PduForm from '@/components/pdus/PduForm.vue';
import DeletePduDialog from '@/components/pdus/DeletePduDialog.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { DatacenterReference, RoomReference, PduData, RowReference, SelectOption } from '@/types/rooms';

interface Props {
    datacenter: DatacenterReference;
    room: RoomReference;
    pdu: PduData;
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
        title: `Edit ${props.pdu.name}`,
        href: PduController.edit.url({ datacenter: props.datacenter.id, room: props.room.id, pdu: props.pdu.id }),
    },
];
</script>

<template>
    <Head :title="`Edit ${pdu.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <HeadingSmall
                title="Edit PDU"
                :description="`Update information for ${pdu.name}.`"
            />

            <div class="max-w-2xl">
                <PduForm
                    mode="edit"
                    :datacenter="datacenter"
                    :room="room"
                    :pdu="pdu"
                    :rows="rows"
                    :phase-options="phaseOptions"
                    :status-options="statusOptions"
                />
            </div>

            <!-- Delete PDU Section -->
            <div class="max-w-2xl space-y-6">
                <HeadingSmall
                    title="Delete PDU"
                    description="Permanently remove this PDU from the system."
                />
                <div
                    class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
                >
                    <div class="relative space-y-0.5 text-red-600 dark:text-red-100">
                        <p class="font-medium">Warning</p>
                        <p class="text-sm">
                            Once deleted, this PDU will be permanently removed.
                            This action cannot be undone.
                        </p>
                    </div>
                    <DeletePduDialog
                        :datacenter-id="datacenter.id"
                        :room-id="room.id"
                        :pdu-id="pdu.id"
                        :pdu-name="pdu.name"
                    >
                        <Button variant="destructive">
                            Delete PDU
                        </Button>
                    </DeletePduDialog>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
