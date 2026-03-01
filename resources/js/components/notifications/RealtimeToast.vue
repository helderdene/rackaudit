<script setup lang="ts">
import { Button } from '@/components/ui/button';
import type {
    EntityType,
    PendingUpdate,
} from '@/composables/useRealtimeUpdates';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    ClipboardList,
    Database,
    FileCheck,
    Network,
    RefreshCw,
    Server,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        update: PendingUpdate;
        isConflict?: boolean;
        autoDismiss?: boolean;
        autoDismissDelay?: number;
    }>(),
    {
        isConflict: false,
        autoDismiss: true,
        autoDismissDelay: 10000,
    },
);

const emit = defineEmits<{
    dismiss: [id: string];
    refresh: [];
}>();

const isHovered = ref(false);
const dismissTimer = ref<ReturnType<typeof setTimeout> | null>(null);

/**
 * Get the display name for an entity type.
 */
const entityDisplayName = computed((): string => {
    const names: Record<EntityType, string> = {
        connection: 'Connection',
        device: 'Device',
        rack: 'Rack',
        implementation_file: 'Implementation File',
        finding: 'Finding',
        audit: 'Audit',
    };
    return names[props.update.entityType] ?? 'Item';
});

/**
 * Get the icon for the entity type.
 */
const entityIcon = computed(() => {
    const icons: Record<EntityType, typeof Database> = {
        connection: Network,
        device: Server,
        rack: Database,
        implementation_file: FileCheck,
        finding: ClipboardList,
        audit: Activity,
    };
    return icons[props.update.entityType] ?? Database;
});

/**
 * Get the action verb in past tense.
 */
const actionVerb = computed((): string => {
    const verbs: Record<string, string> = {
        created: 'created',
        changed: 'modified',
        updated: 'updated',
        deleted: 'deleted',
        placed: 'placed',
        moved: 'moved',
        removed: 'removed',
        approved: 'approved',
        rejected: 'rejected',
        resolved: 'resolved',
        assigned: 'assigned',
        status_changed: 'status changed',
    };
    return verbs[props.update.action] ?? props.update.action;
});

/**
 * Format the timestamp for display.
 */
const formattedTime = computed((): string => {
    try {
        const date = new Date(props.update.timestamp);
        return date.toLocaleTimeString(undefined, {
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return '';
    }
});

/**
 * Get the message to display.
 */
const displayMessage = computed((): string => {
    if (props.update.message) {
        return props.update.message;
    }

    if (props.isConflict) {
        return `This ${entityDisplayName.value.toLowerCase()} was modified by ${props.update.user.name}. Save your changes or refresh.`;
    }

    return `${entityDisplayName.value} ${actionVerb.value} by ${props.update.user.name}`;
});

/**
 * Start the auto-dismiss timer.
 */
function startDismissTimer(): void {
    if (!props.autoDismiss || props.isConflict) {
        return;
    }

    dismissTimer.value = setTimeout(() => {
        emit('dismiss', props.update.id);
    }, props.autoDismissDelay);
}

/**
 * Stop the auto-dismiss timer.
 */
function stopDismissTimer(): void {
    if (dismissTimer.value) {
        clearTimeout(dismissTimer.value);
        dismissTimer.value = null;
    }
}

/**
 * Handle mouse enter to pause auto-dismiss.
 */
function handleMouseEnter(): void {
    isHovered.value = true;
    stopDismissTimer();
}

/**
 * Handle mouse leave to resume auto-dismiss.
 */
function handleMouseLeave(): void {
    isHovered.value = false;
    startDismissTimer();
}

/**
 * Handle the dismiss button click.
 */
function handleDismiss(): void {
    stopDismissTimer();
    emit('dismiss', props.update.id);
}

/**
 * Handle the refresh button click.
 */
function handleRefresh(): void {
    stopDismissTimer();
    emit('refresh');
    router.reload();
}

onMounted(() => {
    startDismissTimer();
});

onUnmounted(() => {
    stopDismissTimer();
});
</script>

<template>
    <div
        :class="
            cn(
                'pointer-events-auto w-full max-w-sm overflow-hidden rounded-lg shadow-lg ring-1 ring-black/5',
                isConflict
                    ? 'bg-amber-50 ring-amber-500/30 dark:bg-amber-950/50'
                    : 'bg-white ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800',
            )
        "
        @mouseenter="handleMouseEnter"
        @mouseleave="handleMouseLeave"
    >
        <div class="p-4">
            <div class="flex items-start gap-3">
                <!-- Icon -->
                <div
                    :class="
                        cn(
                            'flex h-8 w-8 shrink-0 items-center justify-center rounded-full',
                            isConflict
                                ? 'bg-amber-100 text-amber-600 dark:bg-amber-900/50 dark:text-amber-400'
                                : 'bg-blue-100 text-blue-600 dark:bg-blue-900/50 dark:text-blue-400',
                        )
                    "
                >
                    <AlertTriangle v-if="isConflict" class="size-4" />
                    <component v-else :is="entityIcon" class="size-4" />
                </div>

                <!-- Content -->
                <div class="min-w-0 flex-1">
                    <!-- Header -->
                    <div class="flex items-center justify-between gap-2">
                        <p
                            :class="
                                cn(
                                    'text-sm font-medium',
                                    isConflict
                                        ? 'text-amber-900 dark:text-amber-200'
                                        : 'text-zinc-900 dark:text-zinc-100',
                                )
                            "
                        >
                            {{
                                isConflict
                                    ? 'Data Conflict Warning'
                                    : 'Data Updated'
                            }}
                        </p>
                        <p
                            v-if="formattedTime"
                            :class="
                                cn(
                                    'text-xs',
                                    isConflict
                                        ? 'text-amber-600 dark:text-amber-400'
                                        : 'text-zinc-500 dark:text-zinc-400',
                                )
                            "
                        >
                            {{ formattedTime }}
                        </p>
                    </div>

                    <!-- Message -->
                    <p
                        :class="
                            cn(
                                'mt-1 text-sm',
                                isConflict
                                    ? 'text-amber-800 dark:text-amber-300'
                                    : 'text-zinc-600 dark:text-zinc-400',
                            )
                        "
                    >
                        {{ displayMessage }}
                    </p>

                    <!-- Actions -->
                    <div class="mt-3 flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-7 gap-1.5 px-2 text-xs"
                            @click="handleRefresh"
                        >
                            <RefreshCw class="size-3" />
                            Refresh
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-7 gap-1.5 px-2 text-xs"
                            @click="handleDismiss"
                        >
                            <X class="size-3" />
                            Dismiss
                        </Button>
                    </div>
                </div>

                <!-- Close button -->
                <button
                    type="button"
                    :class="
                        cn(
                            'shrink-0 rounded-md p-1 transition-colors',
                            isConflict
                                ? 'text-amber-600 hover:bg-amber-100 dark:text-amber-400 dark:hover:bg-amber-900/50'
                                : 'text-zinc-400 hover:bg-zinc-100 dark:text-zinc-500 dark:hover:bg-zinc-800',
                        )
                    "
                    @click="handleDismiss"
                >
                    <span class="sr-only">Dismiss</span>
                    <X class="size-4" />
                </button>
            </div>
        </div>
    </div>
</template>
