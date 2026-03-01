<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import DatacenterController from '@/actions/App/Http/Controllers/DatacenterController';
import RoomController from '@/actions/App/Http/Controllers/RoomController';
import RowController from '@/actions/App/Http/Controllers/RowController';
import RackController from '@/actions/App/Http/Controllers/RackController';
import { create as createExport } from '@/actions/App/Http/Controllers/BulkExportController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import DeleteRackDialog from '@/components/racks/DeleteRackDialog.vue';
import RealtimeToastContainer from '@/components/notifications/RealtimeToastContainer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { DatacenterReference, RoomReference, RowReference, RackData, SelectOption } from '@/types/rooms';
import { usePermissions } from '@/composables/usePermissions';
import { useRealtimeUpdates } from '@/composables/useRealtimeUpdates';
import { Download } from 'lucide-vue-next';

interface Props {
    datacenter: DatacenterReference;
    room: RoomReference;
    row: RowReference;
    racks: RackData[];
    canCreate: boolean;
    statusOptions: SelectOption[];
    uHeightOptions: SelectOption[];
}

const props = defineProps<Props>();

const { hasAnyRole } = usePermissions();
const canExport = hasAnyRole(['Administrator', 'IT Manager']);

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
];

// Real-time updates integration
const {
    pendingUpdates,
    dismissUpdate,
    clearUpdates,
    onDataChange,
} = useRealtimeUpdates(props.datacenter.id);

// Register handler for rack changes
onDataChange('rack', (data) => {
    // Toast will be automatically shown via pendingUpdates
    console.log('Rack changed:', data);
});

// Handle toast dismissal
function handleDismissUpdate(id: string): void {
    dismissUpdate(id);
}

// Handle toast refresh
function handleRefresh(): void {
    clearUpdates();
    router.reload();
}

// Handle clear all updates
function handleClearAll(): void {
    clearUpdates();
}

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

// Navigate to export create page with rack entity type pre-selected
const exportUrl = createExport.url({ query: { entity_type: 'rack' } });
</script>

<template>
    <Head title="Racks" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <HeadingSmall
                    title="Rack Management"
                    :description="`Manage racks within ${row.name}.`"
                />
                <div class="flex items-center gap-2">
                    <Link v-if="canExport" :href="exportUrl">
                        <Button variant="outline">
                            <Download class="mr-2 h-4 w-4" />
                            Export
                        </Button>
                    </Link>
                    <Link v-if="canCreate" :href="RackController.create.url({ datacenter: datacenter.id, room: room.id, row: row.id })">
                        <Button>Add Rack</Button>
                    </Link>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-md border">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/50">
                            <tr>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Position</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Name</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">U-Height</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">PDU Count</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Status</th>
                                <th class="h-12 w-[140px] px-4 text-left font-medium text-muted-foreground">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="rack in racks"
                                :key="rack.id"
                                class="border-b transition-colors hover:bg-muted/50"
                            >
                                <td class="p-4 text-muted-foreground">{{ rack.position }}</td>
                                <td class="p-4 font-medium">
                                    <Link
                                        :href="RackController.show.url({ datacenter: datacenter.id, room: room.id, row: row.id, rack: rack.id })"
                                        class="hover:underline"
                                    >
                                        {{ rack.name }}
                                    </Link>
                                </td>
                                <td class="p-4">{{ rack.u_height_label || '-' }}</td>
                                <td class="p-4 text-muted-foreground">{{ rack.pdu_count ?? 0 }}</td>
                                <td class="p-4">
                                    <Badge :variant="getStatusVariant(rack.status)">
                                        {{ rack.status_label || 'Unknown' }}
                                    </Badge>
                                </td>
                                <td class="p-4">
                                    <div class="flex items-center gap-2">
                                        <Link :href="RackController.edit.url({ datacenter: datacenter.id, room: room.id, row: row.id, rack: rack.id })">
                                            <Button variant="outline" size="sm">Edit</Button>
                                        </Link>
                                        <DeleteRackDialog
                                            :datacenter-id="datacenter.id"
                                            :room-id="room.id"
                                            :row-id="row.id"
                                            :rack-id="rack.id"
                                            :rack-name="rack.name"
                                            :has-pdus="(rack.pdu_count ?? 0) > 0"
                                        />
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="racks.length === 0">
                                <td colspan="6" class="p-8 text-center text-muted-foreground">
                                    No racks found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Back to Row -->
            <div>
                <Link :href="RowController.show.url({ datacenter: datacenter.id, room: room.id, row: row.id })">
                    <Button variant="outline">Back to Row</Button>
                </Link>
            </div>
        </div>

        <!-- Real-time Toast Container -->
        <RealtimeToastContainer
            :updates="pendingUpdates"
            @dismiss="handleDismissUpdate"
            @refresh="handleRefresh"
            @clear-all="handleClearAll"
        />
    </AppLayout>
</template>
