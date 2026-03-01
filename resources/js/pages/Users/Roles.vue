<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface UserWithRole {
    id: number;
    name: string;
    email: string;
    role: string;
}

interface Props {
    users: UserWithRole[];
    availableRoles: string[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Users',
        href: '#',
    },
    {
        title: 'Role Management',
        href: '/users/roles',
    },
];

const selectedRoles = ref<Record<number, string>>({});

// Initialize selected roles with current values
props.users.forEach((user) => {
    selectedRoles.value[user.id] = user.role;
});

const updateRole = (userId: number) => {
    const role = selectedRoles.value[userId];
    router.put(`/users/${userId}/role`, { role }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Role Management" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <HeadingSmall
                title="User Role Management"
                description="Assign roles to users to control their access to system features."
            />

            <div class="overflow-hidden rounded-md border">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="h-12 px-4 text-left font-medium text-muted-foreground">Name</th>
                            <th class="h-12 px-4 text-left font-medium text-muted-foreground">Email</th>
                            <th class="h-12 px-4 text-left font-medium text-muted-foreground">Role</th>
                            <th class="h-12 w-[100px] px-4 text-left font-medium text-muted-foreground">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="user in users"
                            :key="user.id"
                            class="border-b transition-colors hover:bg-muted/50"
                        >
                            <td class="p-4 font-medium">{{ user.name }}</td>
                            <td class="p-4">{{ user.email }}</td>
                            <td class="p-4">
                                <select
                                    v-model="selectedRoles[user.id]"
                                    class="h-9 w-[180px] rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                >
                                    <option
                                        v-for="role in availableRoles"
                                        :key="role"
                                        :value="role"
                                    >
                                        {{ role }}
                                    </option>
                                </select>
                            </td>
                            <td class="p-4">
                                <Button
                                    size="sm"
                                    :disabled="selectedRoles[user.id] === user.role"
                                    @click="updateRole(user.id)"
                                >
                                    Update
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
