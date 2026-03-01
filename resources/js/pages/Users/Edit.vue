<script setup lang="ts">
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import UserController from '@/actions/App/Http/Controllers/UserController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import DeleteUserDialog from '@/components/users/DeleteUserDialog.vue';
import UserForm from '@/components/users/UserForm.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

interface Datacenter {
    id: number;
    name: string;
}

interface UserData {
    id: number;
    name: string;
    email: string;
    role: string;
    status: string;
    datacenter_ids: number[];
}

interface Props {
    user: UserData;
    availableRoles: string[];
    datacenters: Datacenter[];
}

const props = defineProps<Props>();

const page = usePage();
const currentUserId = page.props.auth.user.id;

const isCurrentUser = computed(() => props.user.id === currentUserId);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Users',
        href: UserController.index.url(),
    },
    {
        title: `Edit ${props.user.name}`,
        href: UserController.edit.url(props.user.id),
    },
];
</script>

<template>
    <Head :title="`Edit ${user.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <HeadingSmall
                title="Edit User"
                :description="`Update information and access permissions for ${user.name}.`"
            />

            <div class="max-w-xl">
                <UserForm
                    mode="edit"
                    :user="user"
                    :available-roles="availableRoles"
                    :datacenters="datacenters"
                    :current-user-id="currentUserId"
                />
            </div>

            <!-- Delete User Section -->
            <div v-if="!isCurrentUser" class="max-w-xl space-y-6">
                <HeadingSmall
                    title="Delete User"
                    description="Permanently remove this user from the system."
                />
                <div
                    class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
                >
                    <div class="relative space-y-0.5 text-red-600 dark:text-red-100">
                        <p class="font-medium">Warning</p>
                        <p class="text-sm">
                            Once deleted, this user will no longer be able to access the system.
                            This action cannot be undone.
                        </p>
                    </div>
                    <DeleteUserDialog
                        :user-id="user.id"
                        :user-name="user.name"
                    >
                        <Button variant="destructive">
                            Delete User
                        </Button>
                    </DeleteUserDialog>
                </div>
            </div>

            <div v-else class="max-w-xl">
                <div
                    class="rounded-lg border border-blue-100 bg-blue-50 p-4 dark:border-blue-200/10 dark:bg-blue-700/10"
                >
                    <div class="relative space-y-0.5 text-blue-700 dark:text-blue-100">
                        <p class="font-medium">Note</p>
                        <p class="text-sm">
                            You are editing your own account. Some restrictions apply:
                        </p>
                        <ul class="mt-2 list-inside list-disc text-sm">
                            <li>You cannot change your own role</li>
                            <li>You cannot change your own status</li>
                            <li>You cannot delete your own account</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
