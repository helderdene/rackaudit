<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

interface SpotlightRect {
    top: number;
    left: number;
    width: number;
    height: number;
}

interface Props {
    /** The target element to spotlight */
    targetElement: HTMLElement | null;
    /** Padding around the spotlight cutout */
    padding?: number;
    /** Whether to animate transitions between targets */
    animate?: boolean;
    /** Whether the spotlight is active */
    isActive?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    padding: 8,
    animate: true,
    isActive: true,
});

const emit = defineEmits<{
    (e: 'overlay-click'): void;
}>();

// Spotlight rectangle state
const spotlightRect = ref<SpotlightRect>({
    top: 0,
    left: 0,
    width: 0,
    height: 0,
});

/**
 * Calculate spotlight position from target element
 */
function updateSpotlightPosition(): void {
    if (!props.targetElement) {
        return;
    }

    const rect = props.targetElement.getBoundingClientRect();

    spotlightRect.value = {
        top: rect.top - props.padding + window.scrollY,
        left: rect.left - props.padding + window.scrollX,
        width: rect.width + props.padding * 2,
        height: rect.height + props.padding * 2,
    };
}

/**
 * Handle window resize and scroll events
 */
function handleResize(): void {
    if (props.isActive && props.targetElement) {
        updateSpotlightPosition();
    }
}

/**
 * Handle overlay click (outside the spotlight)
 */
function handleOverlayClick(): void {
    emit('overlay-click');
}

// Computed clip-path for the spotlight cutout
const clipPath = computed(() => {
    const { top, left, width, height } = spotlightRect.value;

    // Create a polygon that covers the entire viewport with a hole for the spotlight
    return `polygon(
        0% 0%,
        0% 100%,
        ${left}px 100%,
        ${left}px ${top}px,
        ${left + width}px ${top}px,
        ${left + width}px ${top + height}px,
        ${left}px ${top + height}px,
        ${left}px 100%,
        100% 100%,
        100% 0%
    )`;
});

// Watch for target element changes
watch(
    () => props.targetElement,
    (element) => {
        if (element) {
            updateSpotlightPosition();
        }
    },
    { immediate: true },
);

// Lifecycle
onMounted(() => {
    window.addEventListener('resize', handleResize);
    window.addEventListener('scroll', handleResize, true);

    if (props.targetElement) {
        updateSpotlightPosition();
    }
});

onUnmounted(() => {
    window.removeEventListener('resize', handleResize);
    window.removeEventListener('scroll', handleResize, true);
});

// Expose update method for parent component to call
defineExpose({
    updateSpotlightPosition,
});
</script>

<template>
    <div v-if="isActive && targetElement" class="tour-spotlight">
        <!-- Dark overlay with spotlight cutout -->
        <div
            class="fixed inset-0 z-[9998] bg-black/50 transition-opacity"
            @click="handleOverlayClick"
        />

        <!-- Spotlight cutout layer using clip-path -->
        <div
            class="fixed inset-0 z-[9999] pointer-events-none"
            :class="{ 'transition-all duration-300': animate }"
            :style="{ clipPath }"
        >
            <div class="w-full h-full bg-black/50" />
        </div>

        <!-- Spotlight border/highlight around target -->
        <div
            class="fixed z-[10000] pointer-events-none border-2 border-primary rounded-lg shadow-[0_0_0_4px_rgba(var(--primary)/0.2)]"
            :class="{ 'transition-all duration-300': animate }"
            :style="{
                top: `${spotlightRect.top}px`,
                left: `${spotlightRect.left}px`,
                width: `${spotlightRect.width}px`,
                height: `${spotlightRect.height}px`,
            }"
        >
            <!-- Pulsing animation ring -->
            <div
                class="absolute inset-0 rounded-lg border-2 border-primary/50 animate-pulse"
            />
        </div>
    </div>
</template>

<style scoped>
.tour-spotlight {
    /* Container for spotlight elements */
}

@keyframes spotlight-pulse {
    0%,
    100% {
        box-shadow: 0 0 0 0 rgba(var(--primary), 0.4);
    }
    50% {
        box-shadow: 0 0 0 8px rgba(var(--primary), 0);
    }
}
</style>
