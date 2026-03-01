<script setup lang="ts">
import DatacenterController from '@/actions/App/Http/Controllers/DatacenterController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import DatacenterForm from '@/components/datacenters/DatacenterForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Datacenters',
        href: DatacenterController.index.url(),
    },
    {
        title: 'Create Datacenter',
        href: DatacenterController.create.url(),
    },
];
</script>

<template>
    <Head title="Create Datacenter" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <HeadingSmall
                title="Create Datacenter"
                description="Add a new datacenter with location details, contact information, and floor plan."
            />

            <div class="max-w-2xl">
                <DatacenterForm mode="create" />
            </div>
        </div>
    </AppLayout>
</template>
