<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import UserController from '@/actions/App/Http/Controllers/UserController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import UserForm from '@/components/users/UserForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

interface Datacenter {
    id: number;
    name: string;
}

interface Props {
    availableRoles: string[];
    datacenters: Datacenter[];
}

const props = defineProps<Props>();

const page = usePage();
const currentUserId = page.props.auth.user.id;

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Users',
        href: UserController.index.url(),
    },
    {
        title: 'Create User',
        href: UserController.create.url(),
    },
];
</script>

<template>
    <Head title="Create User" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <HeadingSmall
                title="Create User"
                description="Add a new user to the system with their credentials and access permissions."
            />

            <div class="max-w-xl">
                <UserForm
                    mode="create"
                    :available-roles="availableRoles"
                    :datacenters="datacenters"
                    :current-user-id="currentUserId"
                />
            </div>
        </div>
    </AppLayout>
</template>
