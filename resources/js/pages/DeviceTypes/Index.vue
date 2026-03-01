<script setup lang="ts">
import DeviceTypeController from '@/actions/App/Http/Controllers/DeviceTypeController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import DeleteDeviceTypeDialog from '@/components/device-types/DeleteDeviceTypeDialog.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

interface DeviceTypeData {
    id: number;
    name: string;
    description: string | null;
    default_u_size: number;
    created_at: string;
    updated_at: string;
}

interface Props {
    deviceTypes: DeviceTypeData[];
    canCreate: boolean;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Device Types',
        href: DeviceTypeController.index.url(),
    },
];
</script>

<template>
    <Head title="Device Types" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <HeadingSmall
                    title="Device Type Management"
                    description="Manage device types for categorizing datacenter equipment."
                />
                <Link
                    v-if="canCreate"
                    :href="DeviceTypeController.create.url()"
                >
                    <Button>Add Device Type</Button>
                </Link>
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
                                    Description
                                </th>
                                <th
                                    class="h-12 px-4 text-left font-medium text-muted-foreground"
                                >
                                    Default U Size
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
                                v-for="deviceType in deviceTypes"
                                :key="deviceType.id"
                                class="border-b transition-colors hover:bg-muted/50"
                            >
                                <td class="p-4 font-medium">
                                    {{ deviceType.name }}
                                </td>
                                <td class="p-4 text-muted-foreground">
                                    {{ deviceType.description || '-' }}
                                </td>
                                <td class="p-4">
                                    {{ deviceType.default_u_size }}U
                                </td>
                                <td class="p-4">
                                    <div class="flex items-center gap-2">
                                        <Link
                                            :href="
                                                DeviceTypeController.edit.url(
                                                    deviceType.id,
                                                )
                                            "
                                        >
                                            <Button variant="outline" size="sm"
                                                >Edit</Button
                                            >
                                        </Link>
                                        <DeleteDeviceTypeDialog
                                            :device-type-id="deviceType.id"
                                            :device-type-name="deviceType.name"
                                        />
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="deviceTypes.length === 0">
                                <td
                                    colspan="4"
                                    class="p-8 text-center text-muted-foreground"
                                >
                                    No device types found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
