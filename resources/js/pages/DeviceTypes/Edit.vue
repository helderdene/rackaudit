<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DeviceTypeController from '@/actions/App/Http/Controllers/DeviceTypeController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import DeviceTypeForm from '@/components/device-types/DeviceTypeForm.vue';
import DeleteDeviceTypeDialog from '@/components/device-types/DeleteDeviceTypeDialog.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

interface DeviceTypeData {
    id: number;
    name: string;
    description: string | null;
    default_u_size: number;
}

interface Props {
    deviceType: DeviceTypeData;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Device Types',
        href: DeviceTypeController.index.url(),
    },
    {
        title: `Edit ${props.deviceType.name}`,
        href: DeviceTypeController.edit.url(props.deviceType.id),
    },
];
</script>

<template>
    <Head :title="`Edit ${deviceType.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <HeadingSmall
                title="Edit Device Type"
                :description="`Update information for ${deviceType.name}.`"
            />

            <div class="max-w-2xl">
                <DeviceTypeForm
                    mode="edit"
                    :device-type="deviceType"
                />
            </div>

            <!-- Delete Device Type Section -->
            <div class="max-w-2xl space-y-6">
                <HeadingSmall
                    title="Delete Device Type"
                    description="Permanently remove this device type from the system."
                />
                <div
                    class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
                >
                    <div class="relative space-y-0.5 text-red-600 dark:text-red-100">
                        <p class="font-medium">Warning</p>
                        <p class="text-sm">
                            Once deleted, this device type will be permanently removed.
                            Devices using this type may need to be reassigned.
                        </p>
                    </div>
                    <DeleteDeviceTypeDialog
                        :device-type-id="deviceType.id"
                        :device-type-name="deviceType.name"
                    >
                        <Button variant="destructive">
                            Delete Device Type
                        </Button>
                    </DeleteDeviceTypeDialog>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
