<script setup lang="ts">
import DeviceTypeController from '@/actions/App/Http/Controllers/DeviceTypeController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import DeviceTypeForm from '@/components/device-types/DeviceTypeForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Device Types',
        href: DeviceTypeController.index.url(),
    },
    {
        title: 'Create Device Type',
        href: DeviceTypeController.create.url(),
    },
];
</script>

<template>
    <Head title="Create Device Type" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <HeadingSmall
                title="Create Device Type"
                description="Add a new device type for categorizing equipment."
            />

            <div class="max-w-2xl">
                <DeviceTypeForm mode="create" />
            </div>
        </div>
    </AppLayout>
</template>
