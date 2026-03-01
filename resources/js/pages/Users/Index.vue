<script setup lang="ts">
import UserController from '@/actions/App/Http/Controllers/UserController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import BulkActionsBar from '@/components/users/BulkActionsBar.vue';
import DeleteUserDialog from '@/components/users/DeleteUserDialog.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { debounce } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface UserData {
    id: number;
    name: string;
    email: string;
    role: string;
    status: 'active' | 'inactive' | 'suspended';
    last_active_at: string | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedUsers {
    data: UserData[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Filters {
    search: string;
    status: string;
    role: string;
}

interface Props {
    users: PaginatedUsers;
    availableRoles: string[];
    filters: Filters;
}

const props = defineProps<Props>();

const page = usePage();
const currentUser = computed(() => page.props.auth.user);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Users',
        href: UserController.index.url(),
    },
];

// Local filter state
const searchQuery = ref(props.filters.search);
const statusFilter = ref(props.filters.status);
const roleFilter = ref(props.filters.role);

// Selected users for bulk actions
const selectedUserIds = ref<number[]>([]);

// Check if all users are selected (excluding current user)
const selectableUsers = computed(() =>
    props.users.data.filter((user) => user.id !== currentUser.value.id),
);

const allSelected = computed(
    () =>
        selectableUsers.value.length > 0 &&
        selectableUsers.value.every((user) =>
            selectedUserIds.value.includes(user.id),
        ),
);

const toggleSelectAll = (checked: boolean) => {
    if (checked) {
        selectedUserIds.value = selectableUsers.value.map((user) => user.id);
    } else {
        selectedUserIds.value = [];
    }
};

const toggleUserSelection = (userId: number) => {
    const index = selectedUserIds.value.indexOf(userId);
    if (index === -1) {
        selectedUserIds.value.push(userId);
    } else {
        selectedUserIds.value.splice(index, 1);
    }
};

const isUserSelected = (userId: number) => {
    return selectedUserIds.value.includes(userId);
};

const clearSelection = () => {
    selectedUserIds.value = [];
};

// Apply filters with debounced search
const applyFilters = debounce(() => {
    router.get(
        UserController.index.url(),
        {
            search: searchQuery.value || undefined,
            status: statusFilter.value || undefined,
            role: roleFilter.value || undefined,
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

const handleFilterChange = () => {
    applyFilters();
};

// Format relative time
const formatLastActive = (dateString: string | null): string => {
    if (!dateString) {
        return 'Never';
    }

    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffSeconds = Math.floor(diffMs / 1000);
    const diffMinutes = Math.floor(diffSeconds / 60);
    const diffHours = Math.floor(diffMinutes / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffSeconds < 60) {
        return 'Just now';
    } else if (diffMinutes < 60) {
        return `${diffMinutes} minute${diffMinutes > 1 ? 's' : ''} ago`;
    } else if (diffHours < 24) {
        return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
    } else if (diffDays < 30) {
        return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
    } else {
        return date.toLocaleDateString();
    }
};

// Navigate to page
const goToPage = (url: string | null) => {
    if (url) {
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Users" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <HeadingSmall
                    title="User Management"
                    description="Manage user accounts, roles, and access permissions."
                />
                <Link :href="UserController.create.url()">
                    <Button>Create User</Button>
                </Link>
            </div>

            <!-- Filters -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                <div class="flex-1">
                    <Input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Search by name or email..."
                        class="max-w-sm"
                    />
                </div>
                <div class="flex gap-2">
                    <select
                        v-model="roleFilter"
                        @change="handleFilterChange"
                        class="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus:ring-2 focus:ring-ring focus:outline-none"
                    >
                        <option value="">All Roles</option>
                        <option
                            v-for="role in availableRoles"
                            :key="role"
                            :value="role"
                        >
                            {{ role }}
                        </option>
                    </select>
                    <select
                        v-model="statusFilter"
                        @change="handleFilterChange"
                        class="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus:ring-2 focus:ring-ring focus:outline-none"
                    >
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-md border">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="h-12 w-[40px] px-4">
                                <Checkbox
                                    :checked="allSelected"
                                    @update:checked="toggleSelectAll"
                                    :disabled="selectableUsers.length === 0"
                                />
                            </th>
                            <th
                                class="h-12 px-4 text-left font-medium text-muted-foreground"
                            >
                                Name
                            </th>
                            <th
                                class="h-12 px-4 text-left font-medium text-muted-foreground"
                            >
                                Email
                            </th>
                            <th
                                class="h-12 px-4 text-left font-medium text-muted-foreground"
                            >
                                Role
                            </th>
                            <th
                                class="h-12 px-4 text-left font-medium text-muted-foreground"
                            >
                                Status
                            </th>
                            <th
                                class="h-12 px-4 text-left font-medium text-muted-foreground"
                            >
                                Last Active
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
                            v-for="user in users.data"
                            :key="user.id"
                            class="border-b transition-colors hover:bg-muted/50"
                        >
                            <td class="p-4">
                                <Checkbox
                                    :checked="isUserSelected(user.id)"
                                    @update:checked="
                                        toggleUserSelection(user.id)
                                    "
                                    :disabled="user.id === currentUser.id"
                                />
                            </td>
                            <td class="p-4 font-medium">
                                {{ user.name }}
                                <span
                                    v-if="user.id === currentUser.id"
                                    class="text-xs text-muted-foreground"
                                    >(you)</span
                                >
                            </td>
                            <td class="p-4">{{ user.email }}</td>
                            <td class="p-4">{{ user.role }}</td>
                            <td class="p-4">
                                <StatusBadge :status="user.status" />
                            </td>
                            <td class="p-4 text-muted-foreground">
                                {{ formatLastActive(user.last_active_at) }}
                            </td>
                            <td class="p-4">
                                <div class="flex items-center gap-2">
                                    <Link
                                        :href="UserController.edit.url(user.id)"
                                    >
                                        <Button variant="outline" size="sm"
                                            >Edit</Button
                                        >
                                    </Link>
                                    <DeleteUserDialog
                                        :user-id="user.id"
                                        :user-name="user.name"
                                        :disabled="user.id === currentUser.id"
                                    />
                                </div>
                            </td>
                        </tr>
                        <tr v-if="users.data.length === 0">
                            <td
                                colspan="7"
                                class="p-8 text-center text-muted-foreground"
                            >
                                No users found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="users.last_page > 1"
                class="flex items-center justify-between"
            >
                <div class="text-sm text-muted-foreground">
                    Showing
                    {{ (users.current_page - 1) * users.per_page + 1 }} to
                    {{
                        Math.min(
                            users.current_page * users.per_page,
                            users.total,
                        )
                    }}
                    of {{ users.total }} users
                </div>
                <div class="flex gap-1">
                    <Button
                        v-for="link in users.links"
                        :key="link.label"
                        variant="outline"
                        size="sm"
                        :disabled="!link.url || link.active"
                        @click="goToPage(link.url)"
                        ><span v-html="link.label"
                    /></Button>
                </div>
            </div>

            <!-- Bulk Actions Bar -->
            <BulkActionsBar
                :selected-user-ids="selectedUserIds"
                :current-user-id="currentUser.id"
                @clear-selection="clearSelection"
            />
        </div>
    </AppLayout>
</template>
