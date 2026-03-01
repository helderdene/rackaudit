<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import DatacenterController from '@/actions/App/Http/Controllers/DatacenterController';
import RoomController from '@/actions/App/Http/Controllers/RoomController';
import RowController from '@/actions/App/Http/Controllers/RowController';
import RackController from '@/actions/App/Http/Controllers/RackController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import RackForm from '@/components/racks/RackForm.vue';
import DeleteRackDialog from '@/components/racks/DeleteRackDialog.vue';
import RealtimeToastContainer from '@/components/notifications/RealtimeToastContainer.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { useRealtimeUpdates } from '@/composables/useRealtimeUpdates';
import { type BreadcrumbItem } from '@/types';
import type { RealtimeUpdate } from '@/types/realtime';
import type { DatacenterReference, RoomReference, RowReference, RackData, SelectOption, PduOption } from '@/types/rooms';

interface Props {
    datacenter: DatacenterReference;
    room: RoomReference;
    row: RowReference;
    rack: RackData;
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
        href: RoomController.show.url({ datacenter: props.datacenter.id, room: props.room.id }),
    },
    {
        title: 'Rows',
        href: RowController.index.url({ datacenter: props.datacenter.id, room: props.room.id }),
    },
    {
        title: props.row.name,
        href: RowController.show.url({ datacenter: props.datacenter.id, room: props.room.id, row: props.row.id }),
    },
    {
        title: 'Racks',
        href: RackController.index.url({ datacenter: props.datacenter.id, room: props.room.id, row: props.row.id }),
    },
    {
        title: `Edit ${props.rack.name}`,
        href: RackController.edit.url({ datacenter: props.datacenter.id, room: props.room.id, row: props.row.id, rack: props.rack.id }),
    },
];

// Track whether there's a conflict (another user modified this rack)
const hasConflict = ref(false);

// Real-time updates integration
const {
    pendingUpdates,
    dismissUpdate,
    clearUpdates,
    onDataChange,
} = useRealtimeUpdates(props.datacenter.id);

// Register handler for rack changes - detect if this specific rack was modified
onDataChange('rack', (data) => {
    // Check if this rack was modified by another user
    if (data.entityId === props.rack.id) {
        hasConflict.value = true;
    }
    console.log('Rack changed:', data);
});

// Transform pending updates to mark conflicts
const updatesWithConflicts = computed<RealtimeUpdate[]>(() => {
    return pendingUpdates.value.map((update) => ({
        ...update,
        isConflict: update.entityType === 'rack' && update.entityId === props.rack.id,
    }));
});

// Handle toast dismissal
function handleDismissUpdate(id: string): void {
    dismissUpdate(id);
    // If dismissing a conflict update, reset conflict state
    const update = pendingUpdates.value.find((u) => u.id === id);
    if (update && update.entityType === 'rack' && update.entityId === props.rack.id) {
        hasConflict.value = false;
    }
}

// Handle toast refresh
function handleRefresh(): void {
    clearUpdates();
    hasConflict.value = false;
    router.reload();
}

// Handle clear all updates
function handleClearAll(): void {
    clearUpdates();
    hasConflict.value = false;
}
</script>

<template>
    <Head :title="`Edit ${rack.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <HeadingSmall
                title="Edit Rack"
                :description="`Update information for ${rack.name}.`"
            />

            <div class="max-w-2xl">
                <RackForm
                    mode="edit"
                    :datacenter="datacenter"
                    :room="room"
                    :row="row"
                    :rack="rack"
                    :status-options="statusOptions"
                    :u-height-options="uHeightOptions"
                    :pdu-options="pduOptions"
                    :selected-pdu-ids="rack.pdu_ids"
                />
            </div>

            <!-- Delete Rack Section -->
            <div class="max-w-2xl space-y-6">
                <HeadingSmall
                    title="Delete Rack"
                    description="Permanently remove this rack from the row."
                />
                <div
                    class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
                >
                    <div class="relative space-y-0.5 text-red-600 dark:text-red-100">
                        <p class="font-medium">Warning</p>
                        <p class="text-sm">
                            Once deleted, this rack will be permanently removed.
                            Any PDUs assigned to this rack will be detached but not deleted.
                        </p>
                    </div>
                    <DeleteRackDialog
                        :datacenter-id="datacenter.id"
                        :room-id="room.id"
                        :row-id="row.id"
                        :rack-id="rack.id"
                        :rack-name="rack.name"
                        :has-pdus="(rack.pdu_ids?.length ?? 0) > 0"
                    >
                        <Button variant="destructive">
                            Delete Rack
                        </Button>
                    </DeleteRackDialog>
                </div>
            </div>
        </div>

        <!-- Real-time Toast Container with conflict detection -->
        <RealtimeToastContainer
            :updates="updatesWithConflicts"
            @dismiss="handleDismissUpdate"
            @refresh="handleRefresh"
            @clear-all="handleClearAll"
        />
    </AppLayout>
</template>
