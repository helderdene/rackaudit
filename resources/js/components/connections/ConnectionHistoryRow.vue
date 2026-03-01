<script setup lang="ts">
import { computed } from 'vue';
import { ChevronDown, ChevronUp, Clock, User as UserIcon, Globe } from 'lucide-vue-next';
import ActionBadge from '@/components/ActionBadge.vue';
import ActivityDetailPanel from '@/components/activity/ActivityDetailPanel.vue';

interface Props {
    /** Activity log ID */
    id: number;
    /** Subject type (e.g., Connection class) */
    subjectType: string;
    /** Subject ID (connection ID) */
    subjectId: number;
    /** Action type */
    action: 'created' | 'updated' | 'deleted' | 'restored';
    /** User who made the change */
    causerName: string;
    /** User's role */
    causerRole: string | null;
    /** IP address of the change */
    ipAddress: string;
    /** Old values before change */
    oldValues: Record<string, unknown> | null;
    /** New values after change */
    newValues: Record<string, unknown> | null;
    /** Timestamp of the change */
    createdAt: string;
    /** Whether the row is expanded */
    isExpanded: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'toggle'): void;
}>();

// Format timestamp as relative time
const formatRelativeTime = (dateString: string): string => {
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
    } else if (diffDays < 7) {
        return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
    } else {
        return date.toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    }
};

const formatExactDateTime = (dateString: string): string => {
    const date = new Date(dateString);
    return date.toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
};

// Format user display with role
const userDisplay = computed(() => {
    if (!props.causerName || props.causerName === 'System') {
        return 'System';
    }
    if (props.causerRole) {
        return `${props.causerName} (${props.causerRole})`;
    }
    return props.causerName;
});

// Get summary of changes
const changesSummary = computed(() => {
    if (props.action === 'created' && props.newValues) {
        const keys = Object.keys(props.newValues).slice(0, 3);
        if (keys.length === 0) return 'New connection created';
        return `Created with ${keys.join(', ')}${Object.keys(props.newValues).length > 3 ? '...' : ''}`;
    } else if (props.action === 'deleted' && props.oldValues) {
        const keys = Object.keys(props.oldValues).slice(0, 3);
        if (keys.length === 0) return 'Connection deleted';
        return `Deleted: ${keys.join(', ')}${Object.keys(props.oldValues).length > 3 ? '...' : ''}`;
    } else if ((props.action === 'updated' || props.action === 'restored') && props.oldValues && props.newValues) {
        const changedKeys = Object.keys(props.newValues).filter(
            (key) => JSON.stringify(props.oldValues?.[key]) !== JSON.stringify(props.newValues?.[key])
        );
        if (changedKeys.length === 0) return 'No visible changes';
        return `Changed: ${changedKeys.slice(0, 3).join(', ')}${changedKeys.length > 3 ? '...' : ''}`;
    } else if (props.action === 'restored' && props.newValues) {
        return 'Connection restored';
    }
    return 'No details available';
});

// Connection identifier
const connectionIdentifier = computed(() => `#${props.subjectId}`);
</script>

<template>
    <tr
        class="cursor-pointer border-b transition-colors hover:bg-muted/50"
        :class="{ 'bg-muted/30': isExpanded }"
        @click="emit('toggle')"
    >
        <!-- Timestamp -->
        <td class="p-4 text-muted-foreground">
            <span :title="formatExactDateTime(createdAt)" class="flex items-center gap-1.5">
                <Clock class="h-3.5 w-3.5 hidden sm:inline-block" />
                {{ formatRelativeTime(createdAt) }}
            </span>
        </td>

        <!-- User with role -->
        <td class="p-4">
            <span class="flex items-center gap-1.5 font-medium">
                <UserIcon class="h-3.5 w-3.5 text-muted-foreground hidden sm:inline-block" />
                {{ userDisplay }}
            </span>
        </td>

        <!-- Action badge -->
        <td class="p-4">
            <ActionBadge :action="action" />
        </td>

        <!-- Connection ID -->
        <td class="p-4 font-mono text-xs">
            {{ connectionIdentifier }}
        </td>

        <!-- Summary -->
        <td class="max-w-[200px] truncate p-4 text-muted-foreground">
            {{ changesSummary }}
        </td>

        <!-- IP Address (hidden on mobile) -->
        <td class="hidden p-4 text-muted-foreground lg:table-cell">
            <span class="flex items-center gap-1.5">
                <Globe class="h-3.5 w-3.5" />
                {{ ipAddress || '-' }}
            </span>
        </td>

        <!-- Expand indicator -->
        <td class="p-4 text-right">
            <component
                :is="isExpanded ? ChevronUp : ChevronDown"
                class="h-4 w-4 text-muted-foreground inline-block"
            />
        </td>
    </tr>

    <!-- Expanded detail panel row -->
    <tr v-if="isExpanded">
        <td colspan="7" class="bg-muted/10 px-4 pb-4">
            <ActivityDetailPanel
                :old-values="oldValues"
                :new-values="newValues"
            />
        </td>
    </tr>
</template>
