<script setup lang="ts">
import { computed } from 'vue';
import { Form } from '@inertiajs/vue3';
import UserController from '@/actions/App/Http/Controllers/UserController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Datacenter {
    id: number;
    name: string;
}

interface UserData {
    id?: number;
    name: string;
    email: string;
    role: string;
    status: string;
    datacenter_ids: number[];
}

interface Props {
    mode: 'create' | 'edit';
    user?: UserData;
    availableRoles: string[];
    datacenters: Datacenter[];
    currentUserId?: number;
}

const props = withDefaults(defineProps<Props>(), {
    user: () => ({
        name: '',
        email: '',
        role: 'Viewer',
        status: 'active',
        datacenter_ids: [],
    }),
});

const emit = defineEmits<{
    (e: 'success'): void;
}>();

// Determine form action based on mode
const formAction = computed(() => {
    if (props.mode === 'create') {
        return { action: UserController.store.url(), method: 'post' as const };
    }
    return {
        action: UserController.update.url(props.user.id!),
        method: 'post' as const,
    };
});

// Check if this is the current user editing themselves
const isCurrentUser = computed(() => {
    return props.mode === 'edit' && props.user?.id === props.currentUserId;
});

// Determine which roles the user can select (prevent self-demotion)
const canChangeRole = computed(() => {
    return !isCurrentUser.value;
});

// Determine which statuses the user can select (prevent self-deactivation)
const canChangeStatus = computed(() => {
    return !isCurrentUser.value;
});

// Check if datacenter is in user's current access list
const isDatacenterSelected = (id: number) => {
    return props.user.datacenter_ids.includes(id);
};
</script>

<template>
    <Form
        :action="formAction.action"
        :method="formAction.method"
        class="space-y-6"
        v-slot="{ errors, processing, recentlySuccessful }"
    >
        <!-- Hidden method field for PUT request in edit mode -->
        <input v-if="mode === 'edit'" type="hidden" name="_method" value="PUT" />

        <div class="grid gap-2">
            <Label for="name">Name</Label>
            <Input
                id="name"
                name="name"
                type="text"
                :default-value="user.name"
                required
                autocomplete="name"
                placeholder="Full name"
            />
            <InputError :message="errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="email">Email address</Label>
            <Input
                id="email"
                name="email"
                type="email"
                :default-value="user.email"
                required
                autocomplete="email"
                placeholder="Email address"
            />
            <InputError :message="errors.email" />
        </div>

        <div class="grid gap-2">
            <Label for="password">
                Password
                <span v-if="mode === 'edit'" class="text-muted-foreground font-normal">
                    (leave blank to keep current)
                </span>
            </Label>
            <Input
                id="password"
                name="password"
                type="password"
                :required="mode === 'create'"
                autocomplete="new-password"
                :placeholder="mode === 'edit' ? 'Leave blank to keep current password' : 'Password'"
            />
            <InputError :message="errors.password" />
        </div>

        <div class="grid gap-2">
            <Label for="password_confirmation">Confirm Password</Label>
            <Input
                id="password_confirmation"
                name="password_confirmation"
                type="password"
                :required="mode === 'create'"
                autocomplete="new-password"
                placeholder="Confirm password"
            />
            <InputError :message="errors.password_confirmation" />
        </div>

        <div class="grid gap-2">
            <Label for="role">Role</Label>
            <div class="relative">
                <select
                    id="role"
                    name="role"
                    :disabled="!canChangeRole"
                    class="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <option
                        v-for="role in availableRoles"
                        :key="role"
                        :value="role"
                        :selected="user.role === role"
                    >
                        {{ role }}
                    </option>
                </select>
            </div>
            <p v-if="isCurrentUser" class="text-sm text-muted-foreground">
                You cannot change your own role.
            </p>
            <InputError :message="errors.role" />
        </div>

        <div class="grid gap-2">
            <Label for="status">Status</Label>
            <div class="relative">
                <select
                    id="status"
                    name="status"
                    :disabled="!canChangeStatus"
                    class="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <option value="active" :selected="user.status === 'active'">Active</option>
                    <option value="inactive" :selected="user.status === 'inactive'">Inactive</option>
                    <option value="suspended" :selected="user.status === 'suspended'">Suspended</option>
                </select>
            </div>
            <p v-if="isCurrentUser" class="text-sm text-muted-foreground">
                You cannot change your own status.
            </p>
            <InputError :message="errors.status" />
        </div>

        <div v-if="datacenters.length > 0" class="grid gap-2">
            <Label>Datacenter Access</Label>
            <div class="rounded-md border border-input p-4">
                <div class="grid gap-3 sm:grid-cols-2">
                    <label
                        v-for="datacenter in datacenters"
                        :key="datacenter.id"
                        :for="`datacenter-${datacenter.id}`"
                        class="flex cursor-pointer items-center gap-2"
                    >
                        <input
                            :id="`datacenter-${datacenter.id}`"
                            type="checkbox"
                            name="datacenter_ids[]"
                            :value="datacenter.id"
                            :checked="isDatacenterSelected(datacenter.id)"
                            class="border-input text-primary focus:ring-primary size-4 rounded"
                        />
                        <span class="text-sm">{{ datacenter.name }}</span>
                    </label>
                </div>
            </div>
            <InputError :message="errors.datacenter_ids" />
        </div>

        <div class="flex items-center gap-4">
            <Button :disabled="processing" type="submit">
                {{ processing ? 'Saving...' : (mode === 'create' ? 'Create User' : 'Save Changes') }}
            </Button>

            <Transition
                enter-active-class="transition ease-in-out"
                enter-from-class="opacity-0"
                leave-active-class="transition ease-in-out"
                leave-to-class="opacity-0"
            >
                <p v-show="recentlySuccessful" class="text-sm text-neutral-600 dark:text-neutral-400">
                    Saved.
                </p>
            </Transition>
        </div>
    </Form>
</template>
