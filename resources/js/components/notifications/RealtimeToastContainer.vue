<script setup lang="ts">
import type { PendingUpdate } from '@/composables/useRealtimeUpdates';
import { computed } from 'vue';
import RealtimeToast from './RealtimeToast.vue';

const props = withDefaults(
    defineProps<{
        updates: PendingUpdate[];
        editingEntityId?: number | null;
        editingEntityType?: string | null;
        maxVisible?: number;
    }>(),
    {
        editingEntityId: null,
        editingEntityType: null,
        maxVisible: 5,
    },
);

const emit = defineEmits<{
    dismiss: [id: string];
    refresh: [];
    clearAll: [];
}>();

/**
 * Separate updates into conflicts and regular updates.
 * Conflicts are updates that affect the entity currently being edited.
 */
const conflictUpdates = computed((): PendingUpdate[] => {
    if (!props.editingEntityId || !props.editingEntityType) {
        return [];
    }

    return props.updates.filter(
        (update) =>
            update.entityType === props.editingEntityType &&
            update.entityId === props.editingEntityId,
    );
});

/**
 * Regular updates (not conflicts).
 */
const regularUpdates = computed((): PendingUpdate[] => {
    if (!props.editingEntityId || !props.editingEntityType) {
        return props.updates;
    }

    return props.updates.filter(
        (update) =>
            update.entityType !== props.editingEntityType ||
            update.entityId !== props.editingEntityId,
    );
});

/**
 * Visible updates (limited by maxVisible).
 */
const visibleUpdates = computed((): PendingUpdate[] => {
    return regularUpdates.value.slice(0, props.maxVisible);
});

/**
 * Count of hidden updates.
 */
const hiddenCount = computed((): number => {
    return Math.max(0, regularUpdates.value.length - props.maxVisible);
});

/**
 * Handle dismissing a single toast.
 */
function handleDismiss(id: string): void {
    emit('dismiss', id);
}

/**
 * Handle refresh request.
 */
function handleRefresh(): void {
    emit('refresh');
}

/**
 * Clear all regular updates (not conflicts).
 */
function handleClearAll(): void {
    emit('clearAll');
}
</script>

<template>
    <Teleport to="body">
        <div
            aria-live="assertive"
            class="pointer-events-none fixed inset-0 z-50 flex flex-col items-end justify-end gap-2 p-4 sm:p-6"
        >
            <!-- Conflict toasts (at the top of the stack, more prominent) -->
            <TransitionGroup
                name="toast"
                tag="div"
                class="flex w-full flex-col items-end gap-2"
            >
                <RealtimeToast
                    v-for="update in conflictUpdates"
                    :key="update.id"
                    :update="update"
                    :is-conflict="true"
                    :auto-dismiss="false"
                    @dismiss="handleDismiss"
                    @refresh="handleRefresh"
                />
            </TransitionGroup>

            <!-- Regular toasts -->
            <TransitionGroup
                name="toast"
                tag="div"
                class="flex w-full flex-col items-end gap-2"
            >
                <RealtimeToast
                    v-for="update in visibleUpdates"
                    :key="update.id"
                    :update="update"
                    :is-conflict="false"
                    :auto-dismiss="true"
                    @dismiss="handleDismiss"
                    @refresh="handleRefresh"
                />
            </TransitionGroup>

            <!-- Hidden count indicator -->
            <Transition name="fade">
                <div
                    v-if="hiddenCount > 0"
                    class="pointer-events-auto flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm text-zinc-600 shadow-lg ring-1 ring-black/5 dark:bg-zinc-900 dark:text-zinc-400 dark:ring-zinc-800"
                >
                    <span
                        >+{{ hiddenCount }} more update{{
                            hiddenCount > 1 ? 's' : ''
                        }}</span
                    >
                    <button
                        type="button"
                        class="text-primary hover:underline"
                        @click="handleClearAll"
                    >
                        Clear all
                    </button>
                </div>
            </Transition>
        </div>
    </Teleport>
</template>

<style scoped>
/* Toast enter/leave transitions */
.toast-enter-active {
    transition: all 0.3s ease-out;
}

.toast-leave-active {
    transition: all 0.2s ease-in;
}

.toast-enter-from {
    opacity: 0;
    transform: translateX(100%);
}

.toast-leave-to {
    opacity: 0;
    transform: translateX(100%);
}

.toast-move {
    transition: transform 0.3s ease;
}

/* Fade transition for hidden count */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
