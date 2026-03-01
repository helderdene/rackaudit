<script setup lang="ts">
import { computed } from 'vue';
import { X, ChevronLeft, ChevronRight, Check } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import MarkdownRenderer from './MarkdownRenderer.vue';
import type { HelpTourStep } from '@/composables/useHelp';

interface PopoverPosition {
    top: number;
    left: number;
}

interface Props {
    /** Current tour step data */
    step: HelpTourStep;
    /** Current step index (0-based) */
    currentIndex: number;
    /** Total number of steps */
    totalSteps: number;
    /** Position of the popover relative to viewport */
    position: PopoverPosition;
    /** Whether to animate the popover */
    animate?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    animate: true,
});

const emit = defineEmits<{
    (e: 'next'): void;
    (e: 'previous'): void;
    (e: 'skip'): void;
    (e: 'finish'): void;
}>();

// Step counter text
const stepCountText = computed(
    () => `Step ${props.currentIndex + 1} of ${props.totalSteps}`,
);

// Navigation state
const isFirstStep = computed(() => props.currentIndex === 0);
const isLastStep = computed(() => props.currentIndex === props.totalSteps - 1);

// Get position class for transform based on step position
const positionClass = computed(() => {
    const position = props.step.position || 'bottom';

    switch (position) {
        case 'top':
            // Position above target, centered horizontally
            return 'translate-x-[-50%] translate-y-[-100%] -mt-4';
        case 'bottom':
            // Position below target, centered horizontally
            return 'translate-x-[-50%] mt-4';
        case 'left':
            // Position to the left of target, centered vertically
            return 'translate-x-[-100%] translate-y-[-50%] -ml-4';
        case 'right':
            // Position to the right of target, centered vertically
            return 'translate-y-[-50%] ml-4';
        default:
            return 'translate-x-[-50%] mt-4';
    }
});

// Arrow class based on position
const arrowClass = computed(() => {
    const position = props.step.position || 'bottom';

    switch (position) {
        case 'top':
            // Arrow pointing down
            return 'bottom-0 left-1/2 translate-x-[-50%] translate-y-full border-l-8 border-r-8 border-t-8 border-l-transparent border-r-transparent border-t-popover';
        case 'bottom':
            // Arrow pointing up
            return 'top-0 left-1/2 translate-x-[-50%] translate-y-[-100%] border-l-8 border-r-8 border-b-8 border-l-transparent border-r-transparent border-b-popover';
        case 'left':
            // Arrow pointing right
            return 'right-0 top-1/2 translate-x-full translate-y-[-50%] border-t-8 border-b-8 border-l-8 border-t-transparent border-b-transparent border-l-popover';
        case 'right':
            // Arrow pointing left
            return 'left-0 top-1/2 translate-x-[-100%] translate-y-[-50%] border-t-8 border-b-8 border-r-8 border-t-transparent border-b-transparent border-r-popover';
        default:
            return 'top-0 left-1/2 translate-x-[-50%] translate-y-[-100%] border-l-8 border-r-8 border-b-8 border-l-transparent border-r-transparent border-b-popover';
    }
});

function handlePrevious(): void {
    emit('previous');
}

function handleNext(): void {
    if (isLastStep.value) {
        emit('finish');
    } else {
        emit('next');
    }
}

function handleSkip(): void {
    emit('skip');
}
</script>

<template>
    <div
        class="fixed z-[10001] w-80 max-w-[90vw]"
        :class="[positionClass, { 'transition-all duration-300': animate }]"
        :style="{
            top: `${position.top}px`,
            left: `${position.left}px`,
        }"
        role="dialog"
        aria-label="Tour step"
    >
        <div class="relative bg-popover text-popover-foreground rounded-lg shadow-xl border">
            <!-- Arrow pointer -->
            <div class="absolute w-0 h-0" :class="arrowClass" />

            <!-- Header -->
            <div class="flex items-center justify-between gap-2 px-4 py-3 border-b bg-muted/30">
                <div class="flex items-center gap-2">
                    <!-- Progress dots -->
                    <div class="flex items-center gap-1">
                        <template v-for="i in totalSteps" :key="i">
                            <div
                                class="size-2 rounded-full transition-colors"
                                :class="
                                    i <= currentIndex + 1
                                        ? 'bg-primary'
                                        : 'bg-muted-foreground/30'
                                "
                            />
                        </template>
                    </div>
                    <span class="text-xs text-muted-foreground ml-2">
                        {{ stepCountText }}
                    </span>
                </div>
                <button
                    type="button"
                    class="text-muted-foreground hover:text-foreground transition-colors rounded-full p-1 hover:bg-muted"
                    @click="handleSkip"
                >
                    <X class="size-4" />
                    <span class="sr-only">Close tour</span>
                </button>
            </div>

            <!-- Content -->
            <div class="px-4 py-4">
                <h4 class="font-semibold text-sm mb-2">
                    {{ step.article.title }}
                </h4>
                <MarkdownRenderer
                    :content="step.article.content"
                    class="text-sm text-muted-foreground prose-sm"
                />
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-between gap-2 px-4 py-3 border-t bg-muted/30">
                <Button
                    v-if="!isFirstStep"
                    variant="ghost"
                    size="sm"
                    class="gap-1"
                    @click="handlePrevious"
                >
                    <ChevronLeft class="size-4" />
                    Previous
                </Button>
                <div v-else />

                <div class="flex items-center gap-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        class="text-muted-foreground"
                        @click="handleSkip"
                    >
                        Skip tour
                    </Button>
                    <Button
                        size="sm"
                        class="gap-1"
                        @click="handleNext"
                    >
                        <template v-if="isLastStep">
                            <Check class="size-4" />
                            Finish
                        </template>
                        <template v-else>
                            Next
                            <ChevronRight class="size-4" />
                        </template>
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
