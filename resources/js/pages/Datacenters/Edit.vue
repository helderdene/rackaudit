<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DatacenterController from '@/actions/App/Http/Controllers/DatacenterController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import DatacenterForm from '@/components/datacenters/DatacenterForm.vue';
import DeleteDatacenterDialog from '@/components/datacenters/DeleteDatacenterDialog.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

interface DatacenterData {
    id: number;
    name: string;
    address_line_1: string;
    address_line_2: string | null;
    city: string;
    state_province: string;
    postal_code: string;
    country: string;
    company_name: string | null;
    primary_contact_name: string;
    primary_contact_email: string;
    primary_contact_phone: string;
    secondary_contact_name: string | null;
    secondary_contact_email: string | null;
    secondary_contact_phone: string | null;
    floor_plan_path: string | null;
    floor_plan_url: string | null;
}

interface Props {
    datacenter: DatacenterData;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Datacenters',
        href: DatacenterController.index.url(),
    },
    {
        title: `Edit ${props.datacenter.name}`,
        href: DatacenterController.edit.url(props.datacenter.id),
    },
];
</script>

<template>
    <Head :title="`Edit ${datacenter.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <HeadingSmall
                title="Edit Datacenter"
                :description="`Update information for ${datacenter.name}.`"
            />

            <div class="max-w-2xl">
                <DatacenterForm
                    mode="edit"
                    :datacenter="datacenter"
                />
            </div>

            <!-- Delete Datacenter Section -->
            <div class="max-w-2xl space-y-6">
                <HeadingSmall
                    title="Delete Datacenter"
                    description="Permanently remove this datacenter from the system."
                />
                <div
                    class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
                >
                    <div class="relative space-y-0.5 text-red-600 dark:text-red-100">
                        <p class="font-medium">Warning</p>
                        <p class="text-sm">
                            Once deleted, this datacenter and all associated data will be permanently removed.
                            This action cannot be undone.
                        </p>
                    </div>
                    <DeleteDatacenterDialog
                        :datacenter-id="datacenter.id"
                        :datacenter-name="datacenter.name"
                    >
                        <Button variant="destructive">
                            Delete Datacenter
                        </Button>
                    </DeleteDatacenterDialog>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
