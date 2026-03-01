<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { debounce } from '@/lib/utils';
import { computed, onMounted, ref } from 'vue';

interface UserOption {
    id: number;
    name: string;
    email: string;
}

interface Props {
    /** Selected assignee user IDs */
    modelValue: number[];
    /** Pre-loaded list of assignable users (optional - will fetch if not provided) */
    users?: UserOption[];
    /** Error message for assignee selection */
    error?: string;
    /** Whether to show the section heading */
    showHeading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    users: () => [],
    showHeading: true,
});

const emit = defineEmits<{
    'update:modelValue': [value: number[]];
}>();

// Internal state
const availableUsers = ref<UserOption[]>([]);
const isLoading = ref(false);
const searchQuery = ref('');

// Computed for v-model binding
const selectedAssigneeIds = computed({
    get: () => props.modelValue,
    set: (value: number[]) => emit('update:modelValue', value),
});

// Filtered users based on search
const filteredUsers = computed(() => {
    if (!searchQuery.value) {
        return availableUsers.value;
    }
    const query = searchQuery.value.toLowerCase();
    return availableUsers.value.filter(
        (user) =>
            user.name.toLowerCase().includes(query) ||
            user.email.toLowerCase().includes(query),
    );
});

// Check if a user is selected
const isUserSelected = (userId: number): boolean => {
    return selectedAssigneeIds.value.includes(userId);
};

// Toggle user selection
const toggleUser = (userId: number): void => {
    const currentIds = [...selectedAssigneeIds.value];
    const index = currentIds.indexOf(userId);

    if (index > -1) {
        currentIds.splice(index, 1);
    } else {
        currentIds.push(userId);
    }

    selectedAssigneeIds.value = currentIds;
};

// Select all visible users
const selectAllVisible = (): void => {
    const visibleIds = filteredUsers.value.map((user) => user.id);
    const newIds = [...new Set([...selectedAssigneeIds.value, ...visibleIds])];
    selectedAssigneeIds.value = newIds;
};

// Clear all selections
const clearSelection = (): void => {
    selectedAssigneeIds.value = [];
};

// Get selected user names for display
const selectedUserInfo = computed(() => {
    return availableUsers.value
        .filter((user) => selectedAssigneeIds.value.includes(user.id))
        .map((user) => ({ name: user.name, email: user.email }));
});

/**
 * Fetch assignable users from API
 */
async function fetchAssignableUsers(): Promise<void> {
    // If users are provided via props, use those
    if (props.users && props.users.length > 0) {
        availableUsers.value = props.users;
        return;
    }

    isLoading.value = true;
    try {
        const response = await fetch('/api/audits/assignable-users', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('Failed to fetch assignable users');
        }

        const data = await response.json();
        availableUsers.value = data.data || [];
    } catch (error) {
        console.error('Error fetching assignable users:', error);
        availableUsers.value = [];
    } finally {
        isLoading.value = false;
    }
}

// Debounced search handler
const handleSearchInput = debounce((value: string) => {
    searchQuery.value = value;
}, 200);

// Initialize - fetch users if not provided
onMounted(() => {
    if (props.users && props.users.length > 0) {
        availableUsers.value = props.users;
    } else {
        fetchAssignableUsers();
    }
});
</script>

<template>
    <div class="space-y-4">
        <HeadingSmall
            v-if="showHeading"
            title="Assignees"
            description="Select team members who will execute this audit."
        />

        <div class="space-y-3">
            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <Label>
                    Team Members <span class="text-red-500">*</span>
                </Label>
                <div class="flex items-center gap-2">
                    <button
                        v-if="filteredUsers.length > 0 && !isLoading"
                        type="button"
                        class="text-xs text-primary hover:underline"
                        @click="selectAllVisible"
                    >
                        Select all
                    </button>
                    <span
                        v-if="selectedAssigneeIds.length > 0 && !isLoading"
                        class="text-xs text-muted-foreground"
                        >|</span
                    >
                    <button
                        v-if="selectedAssigneeIds.length > 0 && !isLoading"
                        type="button"
                        class="text-xs text-muted-foreground hover:text-foreground hover:underline"
                        @click="clearSelection"
                    >
                        Clear selection
                    </button>
                </div>
            </div>

            <!-- Search input -->
            <div class="relative">
                <Input
                    type="text"
                    placeholder="Search by name or email..."
                    :model-value="searchQuery"
                    :disabled="isLoading"
                    @update:model-value="handleSearchInput"
                    class="pr-8"
                />
                <svg
                    class="absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                    />
                </svg>
            </div>

            <!-- Loading state with skeleton -->
            <div
                v-if="isLoading"
                class="space-y-2 rounded-md border border-input p-3"
            >
                <div class="flex items-center gap-2">
                    <Spinner class="h-4 w-4" />
                    <span class="text-sm text-muted-foreground"
                        >Loading available assignees...</span
                    >
                </div>
                <div class="space-y-2">
                    <Skeleton class="h-12 w-full" />
                    <Skeleton class="h-12 w-full" />
                    <Skeleton class="h-12 w-3/4" />
                </div>
            </div>

            <!-- Empty state -->
            <div
                v-else-if="availableUsers.length === 0"
                class="rounded-md border border-dashed border-input py-8 text-center text-sm text-muted-foreground"
            >
                <svg
                    class="mx-auto mb-2 h-8 w-8 text-muted-foreground/50"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.5"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"
                    />
                </svg>
                No assignable users available
            </div>

            <div
                v-else-if="filteredUsers.length === 0"
                class="rounded-md border border-dashed border-input py-8 text-center text-sm text-muted-foreground"
            >
                No users match your search
            </div>

            <!-- User list - responsive design with touch-friendly targets -->
            <div
                v-else
                class="max-h-64 space-y-1 overflow-y-auto rounded-md border border-input p-2 sm:max-h-72"
            >
                <label
                    v-for="user in filteredUsers"
                    :key="user.id"
                    :for="`assignee-${user.id}`"
                    class="flex cursor-pointer touch-manipulation items-center gap-3 rounded-md px-2 py-2.5 hover:bg-muted/50 active:bg-muted/70 sm:py-2"
                >
                    <Checkbox
                        :id="`assignee-${user.id}`"
                        :checked="isUserSelected(user.id)"
                        class="h-5 w-5 sm:h-4 sm:w-4"
                        @update:checked="toggleUser(user.id)"
                    />
                    <div
                        class="flex flex-1 flex-col gap-0.5 sm:flex-row sm:items-center sm:gap-2"
                    >
                        <span class="text-sm font-medium">{{ user.name }}</span>
                        <span class="text-xs text-muted-foreground">{{
                            user.email
                        }}</span>
                    </div>
                    <!-- Selection indicator -->
                    <svg
                        v-if="isUserSelected(user.id)"
                        class="h-5 w-5 shrink-0 text-primary sm:h-4 sm:w-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                </label>
            </div>

            <!-- Selection summary - responsive wrapping -->
            <div v-if="selectedAssigneeIds.length > 0" class="space-y-2">
                <div class="flex items-center gap-2">
                    <Badge variant="secondary" class="font-normal">
                        {{ selectedAssigneeIds.length }}
                        {{
                            selectedAssigneeIds.length === 1
                                ? 'assignee'
                                : 'assignees'
                        }}
                        selected
                    </Badge>
                </div>
                <div
                    v-if="selectedUserInfo.length <= 5"
                    class="flex flex-wrap gap-1.5"
                >
                    <Badge
                        v-for="user in selectedUserInfo"
                        :key="user.email"
                        variant="outline"
                        class="gap-1 font-normal"
                    >
                        <svg
                            class="h-3 w-3"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                            />
                        </svg>
                        {{ user.name }}
                    </Badge>
                </div>
            </div>

            <!-- Error message -->
            <p v-if="error" class="text-sm text-destructive">
                {{ error }}
            </p>
        </div>
    </div>
</template>
