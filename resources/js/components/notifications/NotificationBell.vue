<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Skeleton } from '@/components/ui/skeleton';
import { useRealtimeUpdates } from '@/composables/useRealtimeUpdates';
import type { BroadcastNotification } from '@/composables/useUserNotifications';
import { useUserNotifications } from '@/composables/useUserNotifications';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertCircle,
    Bell,
    CheckCheck,
    ClipboardCheck,
    ClipboardX,
    Clock,
    ExternalLink,
    FileCheck,
    FileClock,
    Radio,
    UserCheck,
    UserMinus,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

interface Props {
    datacenterId?: number | null;
}

const props = withDefaults(defineProps<Props>(), {
    datacenterId: null,
});

/**
 * Extended notification type to include audit-related notifications.
 */
type NotificationType =
    | 'file_awaiting_approval'
    | 'file_approved'
    | 'audit_assigned'
    | 'audit_reassigned'
    | 'finding_assigned'
    | 'finding_reassigned'
    | 'finding_status_changed'
    | 'finding_due_date_approaching'
    | 'finding_overdue'
    | 'general';

interface Notification {
    id: string;
    type: NotificationType;
    message: string;
    link: string | null;
    file_name: string | null;
    datacenter_name: string | null;
    audit_id?: number;
    audit_name?: string;
    finding_id?: number;
    read: boolean;
    read_at: string | null;
    created_at: string;
    relative_time: string;
}

// Get the current user ID from Inertia page props
const page = usePage();
const userId = computed(
    () => (page.props.auth as { user: { id: number } })?.user?.id ?? null,
);

const notifications = ref<Notification[]>([]);
const unreadCount = ref(0);
const isLoading = ref(false);
const isOpen = ref(false);
const pollingInterval = ref<ReturnType<typeof setInterval> | null>(null);

// Real-time updates integration (for datacenter-scoped updates)
const realtimeUpdatesCount = ref(0);
let realtimeComposable: ReturnType<typeof useRealtimeUpdates> | null = null;

// User-specific notification subscription
let userNotificationComposable: ReturnType<typeof useUserNotifications> | null =
    null;

/**
 * Initialize real-time updates subscription if datacenter ID is provided.
 */
function initializeRealtimeUpdates(): void {
    if (!props.datacenterId) {
        return;
    }

    realtimeComposable = useRealtimeUpdates(props.datacenterId);

    // Watch for changes in pending updates
    watch(
        () => realtimeComposable?.pendingUpdates.value,
        (newUpdates) => {
            if (newUpdates) {
                realtimeUpdatesCount.value = newUpdates.length;
            }
        },
        { immediate: true, deep: true },
    );
}

/**
 * Initialize user-specific notification subscription.
 */
function initializeUserNotifications(): void {
    if (!userId.value) {
        return;
    }

    userNotificationComposable = useUserNotifications(userId.value);

    // Handle incoming real-time notifications
    userNotificationComposable.onNotification(
        (notification: BroadcastNotification) => {
            // Increment unread badge count
            unreadCount.value += 1;

            // If dropdown is open, prepend the new notification to the list
            if (isOpen.value) {
                const newNotification: Notification = {
                    id: notification.id,
                    type: (notification.type as NotificationType) || 'general',
                    message: notification.message,
                    link: null,
                    file_name: null,
                    datacenter_name: notification.datacenter_name ?? null,
                    audit_id: notification.audit_id,
                    audit_name: notification.audit_name,
                    read: false,
                    read_at: null,
                    created_at: notification.created_at,
                    relative_time: notification.relative_time || 'just now',
                };

                // Prepend to the list (most recent first)
                notifications.value = [newNotification, ...notifications.value];
            }
        },
    );
}

/**
 * Clear real-time update indicator.
 */
function clearRealtimeIndicator(): void {
    realtimeUpdatesCount.value = 0;
    if (realtimeComposable) {
        realtimeComposable.clearUpdates();
    }
}

/**
 * Total badge count (database notifications + real-time indicators).
 */
const totalBadgeCount = computed((): number => {
    return unreadCount.value + realtimeUpdatesCount.value;
});

/**
 * Whether to show the real-time indicator dot.
 */
const hasRealtimeUpdates = computed((): boolean => {
    return realtimeUpdatesCount.value > 0;
});

/**
 * Fetch notifications from the API
 */
const fetchNotifications = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get<{
            data: Notification[];
            unread_count: number;
        }>('/notifications');
        notifications.value = response.data.data;
        unreadCount.value = response.data.unread_count;
    } catch (error) {
        console.error('Failed to fetch notifications:', error);
    } finally {
        isLoading.value = false;
    }
};

/**
 * Fetch only the unread count (for polling)
 */
const fetchUnreadCount = async () => {
    try {
        const response = await axios.get<{ unread_count: number }>(
            '/notifications/unread-count',
        );
        unreadCount.value = response.data.unread_count;
    } catch (error) {
        console.error('Failed to fetch unread count:', error);
    }
};

/**
 * Mark a notification as read and navigate to its link
 */
const handleNotificationClick = async (notification: Notification) => {
    if (!notification.read) {
        try {
            await axios.post(`/notifications/${notification.id}/read`);
            notification.read = true;
            unreadCount.value = Math.max(0, unreadCount.value - 1);
        } catch (error) {
            console.error('Failed to mark notification as read:', error);
        }
    }

    // Navigate to the link if available
    if (notification.link) {
        isOpen.value = false;
        // Clear real-time indicator on navigation
        clearRealtimeIndicator();
        router.visit(notification.link);
    }
};

/**
 * Mark all notifications as read
 */
const markAllAsRead = async () => {
    try {
        await axios.post('/notifications/mark-all-read');
        notifications.value.forEach((n) => (n.read = true));
        unreadCount.value = 0;
    } catch (error) {
        console.error('Failed to mark all notifications as read:', error);
    }
};

/**
 * Handle dropdown open state change
 */
const handleOpenChange = (open: boolean) => {
    isOpen.value = open;
    if (open) {
        fetchNotifications();
    }
};

/**
 * Get icon component for notification type.
 * Returns appropriate icon based on notification category.
 */
const getNotificationIcon = (type: NotificationType) => {
    switch (type) {
        // Implementation file notifications
        case 'file_awaiting_approval':
            return FileClock;
        case 'file_approved':
            return FileCheck;
        // Audit notifications
        case 'audit_assigned':
            return ClipboardCheck;
        case 'audit_reassigned':
            return ClipboardX;
        // Finding notifications
        case 'finding_assigned':
            return UserCheck;
        case 'finding_reassigned':
            return UserMinus;
        case 'finding_status_changed':
            return AlertCircle;
        case 'finding_due_date_approaching':
            return Clock;
        case 'finding_overdue':
            return AlertCircle;
        default:
            return Bell;
    }
};

/**
 * Get icon background/text color classes for notification type.
 */
const getNotificationIconClasses = (type: NotificationType): string => {
    switch (type) {
        // Implementation file notifications - yellow/amber
        case 'file_awaiting_approval':
            return 'bg-yellow-100 text-yellow-600 dark:bg-yellow-900/30 dark:text-yellow-400';
        case 'file_approved':
            return 'bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400';
        // Audit notifications - blue
        case 'audit_assigned':
            return 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400';
        case 'audit_reassigned':
            return 'bg-blue-100 text-blue-500 dark:bg-blue-900/30 dark:text-blue-300';
        // Finding notifications - purple/violet
        case 'finding_assigned':
            return 'bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400';
        case 'finding_reassigned':
            return 'bg-purple-100 text-purple-500 dark:bg-purple-900/30 dark:text-purple-300';
        case 'finding_status_changed':
            return 'bg-indigo-100 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400';
        // Finding urgency notifications - orange/red
        case 'finding_due_date_approaching':
            return 'bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400';
        case 'finding_overdue':
            return 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400';
        // Default
        default:
            return 'bg-muted text-muted-foreground';
    }
};

/**
 * Handle refresh button click - clears real-time indicator.
 */
function handleRefreshClick(): void {
    clearRealtimeIndicator();
    router.reload();
}

/**
 * Start polling for unread count
 */
const startPolling = () => {
    // Poll every 30 seconds for new notifications
    pollingInterval.value = setInterval(fetchUnreadCount, 30000);
};

/**
 * Stop polling
 */
const stopPolling = () => {
    if (pollingInterval.value) {
        clearInterval(pollingInterval.value);
        pollingInterval.value = null;
    }
};

// Clear real-time indicator on navigation
router.on('navigate', () => {
    clearRealtimeIndicator();
});

// Initial fetch and start polling
onMounted(() => {
    fetchUnreadCount();
    startPolling();
    initializeRealtimeUpdates();
    initializeUserNotifications();
});

onUnmounted(() => {
    stopPolling();
    if (realtimeComposable) {
        realtimeComposable.cleanup();
    }
    if (userNotificationComposable) {
        userNotificationComposable.cleanup();
    }
});
</script>

<template>
    <DropdownMenu @update:open="handleOpenChange">
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="relative h-9 w-9">
                <Bell class="size-5" />
                <!-- Combined badge for unread + real-time updates -->
                <Badge
                    v-if="totalBadgeCount > 0"
                    variant="destructive"
                    class="absolute -top-1 -right-1 flex h-5 min-w-5 items-center justify-center px-1 text-[10px]"
                >
                    {{ totalBadgeCount > 99 ? '99+' : totalBadgeCount }}
                </Badge>
                <!-- Real-time indicator dot (pulsing) -->
                <span
                    v-if="hasRealtimeUpdates"
                    class="absolute -top-0.5 -right-0.5 flex h-3 w-3"
                >
                    <span
                        class="absolute inline-flex h-full w-full animate-ping rounded-full bg-blue-400 opacity-75"
                    />
                    <span
                        class="relative inline-flex h-3 w-3 rounded-full bg-blue-500"
                    />
                </span>
                <span class="sr-only">Notifications</span>
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-80">
            <DropdownMenuLabel class="flex items-center justify-between">
                <span>Notifications</span>
                <div class="flex items-center gap-1">
                    <!-- Real-time refresh indicator -->
                    <Button
                        v-if="hasRealtimeUpdates"
                        variant="ghost"
                        size="sm"
                        class="h-auto gap-1 px-2 py-1 text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                        @click.stop="handleRefreshClick"
                    >
                        <Radio class="size-3" />
                        {{ realtimeUpdatesCount }} update{{
                            realtimeUpdatesCount > 1 ? 's' : ''
                        }}
                    </Button>
                    <Button
                        v-if="unreadCount > 0"
                        variant="ghost"
                        size="sm"
                        class="h-auto gap-1 px-2 py-1 text-xs"
                        @click.stop="markAllAsRead"
                    >
                        <CheckCheck class="size-3" />
                        Mark all read
                    </Button>
                </div>
            </DropdownMenuLabel>

            <DropdownMenuSeparator />

            <!-- Real-time updates banner -->
            <div
                v-if="hasRealtimeUpdates"
                class="flex items-center justify-between gap-2 bg-blue-50 px-3 py-2 dark:bg-blue-950/30"
            >
                <div class="flex items-center gap-2">
                    <Radio class="size-4 text-blue-600 dark:text-blue-400" />
                    <span class="text-sm text-blue-700 dark:text-blue-300">
                        Data has changed
                    </span>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    class="h-6 px-2 text-xs"
                    @click.stop="handleRefreshClick"
                >
                    Refresh
                </Button>
            </div>

            <!-- Loading state -->
            <div
                v-if="isLoading && notifications.length === 0"
                class="space-y-2 p-2"
            >
                <div v-for="i in 3" :key="i" class="flex items-start gap-3">
                    <Skeleton class="h-8 w-8 shrink-0 rounded-full" />
                    <div class="flex-1 space-y-1.5">
                        <Skeleton class="h-3 w-full" />
                        <Skeleton class="h-3 w-3/4" />
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div
                v-else-if="notifications.length === 0 && !hasRealtimeUpdates"
                class="flex flex-col items-center justify-center py-8 text-center"
            >
                <Bell class="mb-2 size-8 text-muted-foreground/50" />
                <p class="text-sm text-muted-foreground">
                    No notifications yet
                </p>
            </div>

            <!-- Notification list -->
            <div v-else class="max-h-[320px] overflow-y-auto">
                <DropdownMenuItem
                    v-for="notification in notifications"
                    :key="notification.id"
                    class="cursor-pointer p-0"
                    @click="handleNotificationClick(notification)"
                >
                    <div
                        class="flex w-full items-start gap-3 p-3"
                        :class="{ 'bg-muted/50': !notification.read }"
                    >
                        <!-- Icon -->
                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                            :class="
                                getNotificationIconClasses(notification.type)
                            "
                        >
                            <component
                                :is="getNotificationIcon(notification.type)"
                                class="size-4"
                            />
                        </div>

                        <!-- Content -->
                        <div class="min-w-0 flex-1">
                            <p
                                class="text-sm"
                                :class="{ 'font-medium': !notification.read }"
                            >
                                {{ notification.message }}
                            </p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{ notification.relative_time }}
                            </p>
                        </div>

                        <!-- Unread indicator -->
                        <div
                            v-if="!notification.read"
                            class="h-2 w-2 shrink-0 rounded-full bg-primary"
                        />

                        <!-- Link indicator -->
                        <ExternalLink
                            v-if="notification.link"
                            class="size-3.5 shrink-0 text-muted-foreground"
                        />
                    </div>
                </DropdownMenuItem>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
