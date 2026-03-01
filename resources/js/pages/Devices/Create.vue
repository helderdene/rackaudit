<script setup lang="ts">
import { create } from '@/actions/App/Http/Controllers/DeviceController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import DeviceForm from '@/components/devices/DeviceForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { DeviceTypeOption, SelectOption } from '@/types/rooms';
import { Head } from '@inertiajs/vue3';

interface Props {
    deviceTypeOptions: DeviceTypeOption[];
    lifecycleStatusOptions: SelectOption[];
    depthOptions: SelectOption[];
    widthTypeOptions: SelectOption[];
    rackFaceOptions: SelectOption[];
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Devices',
        href: '/devices',
    },
    {
        title: 'Create Device',
        href: create.url(),
    },
];
</script>

<template>
    <Head title="Create Device" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <HeadingSmall
                title="Create Device"
                description="Add a new device to the inventory."
            />

            <div class="max-w-3xl">
                <DeviceForm
                    mode="create"
                    :device-type-options="deviceTypeOptions"
                    :lifecycle-status-options="lifecycleStatusOptions"
                    :depth-options="depthOptions"
                    :width-type-options="widthTypeOptions"
                    :rack-face-options="rackFaceOptions"
                />
            </div>
        </div>
    </AppLayout>
</template>
