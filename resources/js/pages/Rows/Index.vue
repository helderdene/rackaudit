<script setup lang="ts">
import { create as createExport } from '@/actions/App/Http/Controllers/BulkExportController';
import DatacenterController from '@/actions/App/Http/Controllers/DatacenterController';
import RoomController from '@/actions/App/Http/Controllers/RoomController';
import RowController from '@/actions/App/Http/Controllers/RowController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import DeleteRowDialog from '@/components/rows/DeleteRowDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type {
    DatacenterReference,
    RoomReference,
    RowData,
    SelectOption,
} from '@/types/rooms';
import { Head, Link } from '@inertiajs/vue3';
import { Download } from 'lucide-vue-next';

interface Props {
    datacenter: DatacenterReference;
    room: RoomReference;
    rows: RowData[];
    canCreate: boolean;
    orientationOptions: SelectOption[];
    statusOptions: SelectOption[];
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
        default:
            return 'outline';
    }
};

// Navigate to export create page with row entity type pre-selected
const exportUrl = createExport.url({ query: { entity_type: 'row' } });
</script>

<template>
    <Head title="Rows" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <HeadingSmall
                    title="Row Management"
                    :description="`Manage rows within ${room.name}.`"
                />
                <div class="flex items-center gap-2">
                    <Link v-if="canExport" :href="exportUrl">
                        <Button variant="outline">
                            <Download class="mr-2 h-4 w-4" />
                            Export
                        </Button>
                    </Link>
                    <Link
                        v-if="canCreate"
                        :href="
                            RowController.create.url({
                                datacenter: datacenter.id,
                                room: room.id,
                            })
                        "
                    >
                        <Button>Add Row</Button>
                    </Link>
                </div>
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
                                    Position
                                </th>
                                <th
                                    class="h-12 px-4 text-left font-medium text-muted-foreground"
                                >
                                    Name
                                </th>
                                <th
                                    class="h-12 px-4 text-left font-medium text-muted-foreground"
                                >
                                    Orientation
                                </th>
                                <th
                                    class="h-12 px-4 text-left font-medium text-muted-foreground"
                                >
                                    PDU Count
                                </th>
                                <th
                                    class="h-12 px-4 text-left font-medium text-muted-foreground"
                                >
                                    Status
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
                                v-for="row in rows"
                                :key="row.id"
                                class="border-b transition-colors hover:bg-muted/50"
                            >
                                <td class="p-4 text-muted-foreground">
                                    {{ row.position }}
                                </td>
                                <td class="p-4 font-medium">{{ row.name }}</td>
                                <td class="p-4">
                                    {{ row.orientation_label || '-' }}
                                </td>
                                <td class="p-4 text-muted-foreground">
                                    {{ row.pdu_count ?? '-' }}
                                </td>
                                <td class="p-4">
                                    <Badge
                                        :variant="getStatusVariant(row.status)"
                                    >
                                        {{ row.status_label || 'Unknown' }}
                                    </Badge>
                                </td>
                                <td class="p-4">
                                    <div class="flex items-center gap-2">
                                        <Link
                                            :href="
                                                RowController.edit.url({
                                                    datacenter: datacenter.id,
                                                    room: room.id,
                                                    row: row.id,
                                                })
                                            "
                                        >
                                            <Button variant="outline" size="sm"
                                                >Edit</Button
                                            >
                                        </Link>
                                        <DeleteRowDialog
                                            :datacenter-id="datacenter.id"
                                            :room-id="room.id"
                                            :row-id="row.id"
                                            :row-name="row.name"
                                            :has-pdus="(row.pdu_count ?? 0) > 0"
                                        />
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td
                                    colspan="6"
                                    class="p-8 text-center text-muted-foreground"
                                >
                                    No rows found.
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
