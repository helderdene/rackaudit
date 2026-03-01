<script setup lang="ts">
import { create as createExport } from '@/actions/App/Http/Controllers/BulkExportController';
import DatacenterController from '@/actions/App/Http/Controllers/DatacenterController';
import RoomController from '@/actions/App/Http/Controllers/RoomController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import DeleteRoomDialog from '@/components/rooms/DeleteRoomDialog.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { debounce } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import type {
    DatacenterReference,
    PaginatedRooms,
    RoomFilters,
} from '@/types/rooms';
import { Head, Link, router } from '@inertiajs/vue3';
import { Download } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Props {
    datacenter: DatacenterReference;
    rooms: PaginatedRooms;
    filters: RoomFilters;
    canCreate: boolean;
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
];

// Local filter state
const searchQuery = ref(props.filters.search);

// Apply filters with debounced search
const applyFilters = debounce(() => {
    router.get(
        RoomController.index.url(props.datacenter.id),
        {
            search: searchQuery.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}, 300);

watch([searchQuery], () => {
    applyFilters();
});

// Navigate to page
const goToPage = (url: string | null) => {
    if (url) {
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }
};

// Navigate to export create page with room entity type pre-selected
const exportUrl = createExport.url({ query: { entity_type: 'room' } });
</script>

<template>
    <Head title="Rooms" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <HeadingSmall
                    title="Room Management"
                    :description="`Manage rooms within ${datacenter.name}.`"
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
                        :href="RoomController.create.url(datacenter.id)"
                    >
                        <Button>Create Room</Button>
                    </Link>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                <div class="flex-1">
                    <Input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Search by room name..."
                        class="max-w-sm"
                    />
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
                                    Name
                                </th>
                                <th
                                    class="h-12 px-4 text-left font-medium text-muted-foreground"
                                >
                                    Type
                                </th>
                                <th
                                    class="h-12 px-4 text-left font-medium text-muted-foreground"
                                >
                                    Row Count
                                </th>
                                <th
                                    class="h-12 px-4 text-left font-medium text-muted-foreground"
                                >
                                    Rack Count
                                </th>
                                <th
                                    class="h-12 w-[180px] px-4 text-left font-medium text-muted-foreground"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="room in rooms.data"
                                :key="room.id"
                                class="border-b transition-colors hover:bg-muted/50"
                            >
                                <td class="p-4 font-medium">
                                    {{ room.name }}
                                </td>
                                <td class="p-4">
                                    {{ room.type_label || '-' }}
                                </td>
                                <td class="p-4 text-muted-foreground">
                                    {{ room.row_count ?? '-' }}
                                </td>
                                <td class="p-4 text-muted-foreground">-</td>
                                <td class="p-4">
                                    <div class="flex items-center gap-2">
                                        <Link
                                            :href="
                                                RoomController.show.url({
                                                    datacenter: datacenter.id,
                                                    room: room.id,
                                                })
                                            "
                                        >
                                            <Button variant="outline" size="sm"
                                                >View</Button
                                            >
                                        </Link>
                                        <Link
                                            :href="
                                                RoomController.edit.url({
                                                    datacenter: datacenter.id,
                                                    room: room.id,
                                                })
                                            "
                                        >
                                            <Button variant="outline" size="sm"
                                                >Edit</Button
                                            >
                                        </Link>
                                        <DeleteRoomDialog
                                            :datacenter-id="datacenter.id"
                                            :room-id="room.id"
                                            :room-name="room.name"
                                        />
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="rooms.data.length === 0">
                                <td
                                    colspan="5"
                                    class="p-8 text-center text-muted-foreground"
                                >
                                    No rooms found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div
                v-if="rooms.last_page > 1"
                class="flex flex-col items-center justify-between gap-4 sm:flex-row"
            >
                <div class="text-sm text-muted-foreground">
                    Showing
                    {{ (rooms.current_page - 1) * rooms.per_page + 1 }} to
                    {{
                        Math.min(
                            rooms.current_page * rooms.per_page,
                            rooms.total,
                        )
                    }}
                    of {{ rooms.total }} rooms
                </div>
                <div class="flex flex-wrap gap-1">
                    <Button
                        v-for="link in rooms.links"
                        :key="link.label"
                        variant="outline"
                        size="sm"
                        :disabled="!link.url || link.active"
                        @click="goToPage(link.url)"
                        ><span v-html="link.label"
                    /></Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
