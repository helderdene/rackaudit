<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';
import { computed } from 'vue';

interface Props {
    user: User;
    showEmail?: boolean;
    showRole?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
    showRole: true,
});

const { getInitials } = useInitials();

// Compute whether we should show the avatar image
const showAvatar = computed(
    () => props.user.avatar && props.user.avatar !== '',
);

// Role badge color mappings
const roleBadgeClasses = computed(() => {
    const role = props.user.role;
    switch (role) {
        case 'Administrator':
            return 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400';
        case 'IT Manager':
            return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
        case 'Operator':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';
        case 'Auditor':
            return 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400';
        case 'Viewer':
            return 'bg-gray-100 text-gray-700 dark:bg-gray-700/30 dark:text-gray-400';
        default:
            return 'bg-gray-100 text-gray-700 dark:bg-gray-700/30 dark:text-gray-400';
    }
});
</script>

<template>
    <Avatar class="h-8 w-8 overflow-hidden rounded-lg">
        <AvatarImage v-if="showAvatar" :src="user.avatar!" :alt="user.name" />
        <AvatarFallback class="rounded-lg text-black dark:text-white">
            {{ getInitials(user.name) }}
        </AvatarFallback>
    </Avatar>

    <div class="grid flex-1 text-left text-sm leading-tight">
        <span class="truncate font-medium">{{ user.name }}</span>
        <span v-if="showEmail" class="truncate text-xs text-muted-foreground">{{
            user.email
        }}</span>
        <span
            v-if="showRole && user.role"
            class="inline-flex w-fit items-center rounded px-1.5 py-0.5 text-[10px] font-medium leading-none"
            :class="roleBadgeClasses"
        >
            {{ user.role }}
        </span>
    </div>
</template>
