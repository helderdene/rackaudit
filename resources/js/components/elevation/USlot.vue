<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { DeviceWidth } from '@/types/rooms';
import type { HTMLAttributes } from 'vue';

interface Props {
    /** The U number for this slot (1-based) */
    uNumber: number;
    /** Whether this slot is occupied by a device */
    isOccupied?: boolean;
    /** Whether this slot is a valid drop target during drag operations */
    isDropTarget?: boolean;
    /** Whether this is a valid position for dropping the current device */
    isValidDrop?: boolean;
    /** Whether the left half is a valid drop target */
    isLeftHalfValid?: boolean;
    /** Whether the right half is a valid drop target */
    isRightHalfValid?: boolean;
    /** Whether drag preview should be shown */
    showDragPreview?: boolean;
    /** Height of the device being dragged (in U) */
    dragPreviewHeight?: number;
    /** Additional CSS classes */
    class?: HTMLAttributes['class'];
}

const props = withDefaults(defineProps<Props>(), {
    isOccupied: false,
    isDropTarget: false,
    isValidDrop: true,
    isLeftHalfValid: true,
    isRightHalfValid: true,
    showDragPreview: false,
    dragPreviewHeight: 1,
});

const emit = defineEmits<{
    (e: 'slotClick', uNumber: number): void;
    (e: 'drop', uNumber: number, width: DeviceWidth): void;
    (e: 'dragEnter', uNumber: number): void;
    (e: 'dragLeave', uNumber: number): void;
}>();

/**
 * Handle drop event on full slot (for full-width devices)
 */
function handleDrop(event: DragEvent) {
    event.preventDefault();
    event.stopPropagation();
    // Emit drop event for full-width placement
    emit('drop', props.uNumber, 'full');
}

/**
 * Handle drag over to allow drop and maintain hover state
 */
function handleDragOver(event: DragEvent) {
    event.preventDefault();
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = props.isValidDrop ? 'move' : 'none';
    }
    // Emit dragEnter on dragover to maintain stable hover state
    // This prevents flickering when moving between child elements
    emit('dragEnter', props.uNumber);
}

/**
 * Handle drag leave - only clear if actually leaving this element
 */
function handleDragLeave(event: DragEvent) {
    const target = event.currentTarget as HTMLElement;
    const relatedTarget = event.relatedTarget as Node | null;

    // Only emit dragLeave if we're actually leaving this slot
    // (not just moving to a child element)
    if (!relatedTarget || !target.contains(relatedTarget)) {
        emit('dragLeave', props.uNumber);
    }
}
</script>

<template>
    <div
        :class="
            cn(
                'relative flex h-7 items-center rounded transition-colors',
                // Base border styles
                isOccupied
                    ? 'border border-solid border-border bg-muted/30'
                    : 'border border-dashed border-muted-foreground/30 bg-muted/20',
                // Drop target feedback for full slot
                isDropTarget &&
                    isValidDrop &&
                    'border-2 border-green-500 bg-green-50 dark:bg-green-950/30',
                isDropTarget &&
                    !isValidDrop &&
                    'border-2 border-red-500 bg-red-50 dark:bg-red-950/30',
                // Hover state for empty slots
                !isOccupied && !isDropTarget && 'hover:bg-muted/40',
                // Drag preview indicator
                showDragPreview && 'ring-2 ring-primary ring-offset-1',
                props.class,
            )
        "
        :data-u-number="uNumber"
        @click="$emit('slotClick', uNumber)"
        @drop="handleDrop"
        @dragover="handleDragOver"
        @dragenter="$emit('dragEnter', uNumber)"
        @dragleave="handleDragLeave"
    >
        <!-- U number label -->
        <span
            class="absolute left-2 w-10 shrink-0 text-xs font-medium text-muted-foreground"
        >
            U{{ uNumber }}
        </span>

        <!-- Slot content area with half-width divisions -->
        <div class="ml-12 flex flex-1 items-center">
            <!-- Left half section -->
            <div
                :class="
                    cn(
                        'flex h-full flex-1 items-center border-r border-dashed border-muted-foreground/20 px-1',
                        // Left half drop target feedback
                        isDropTarget &&
                            isLeftHalfValid &&
                            'bg-green-100/50 dark:bg-green-900/20',
                        isDropTarget &&
                            !isLeftHalfValid &&
                            'bg-red-100/50 dark:bg-red-900/20',
                    )
                "
                @drop.stop="$emit('drop', uNumber, 'half-left')"
            >
                <slot name="left" />
            </div>
            <!-- Right half section -->
            <div
                :class="
                    cn(
                        'flex h-full flex-1 items-center px-1',
                        // Right half drop target feedback
                        isDropTarget &&
                            isRightHalfValid &&
                            'bg-green-100/50 dark:bg-green-900/20',
                        isDropTarget &&
                            !isRightHalfValid &&
                            'bg-red-100/50 dark:bg-red-900/20',
                    )
                "
                @drop.stop="$emit('drop', uNumber, 'half-right')"
            >
                <slot name="right" />
            </div>
        </div>

        <!-- Default slot for full-width content -->
        <slot />

        <!-- Drag preview overlay for multi-U devices -->
        <!-- Extends upward (toward higher U numbers) since devices occupy start_u to start_u + u_size - 1 -->
        <div
            v-if="showDragPreview && dragPreviewHeight > 1"
            :class="
                cn(
                    'absolute inset-x-0 bottom-0 z-10 rounded border-2 border-dashed',
                    isValidDrop
                        ? 'border-green-500 bg-green-500/10'
                        : 'border-red-500 bg-red-500/10',
                )
            "
            :style="{
                height: `${dragPreviewHeight * 28 + (dragPreviewHeight - 1) * 4}px`,
            }"
        />
    </div>
</template>
