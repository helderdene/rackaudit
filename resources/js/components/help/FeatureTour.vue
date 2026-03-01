<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { Check } from 'lucide-vue-next';
import TourSpotlight from './TourSpotlight.vue';
import TourStepPopover from './TourStepPopover.vue';
import { useHelp, type HelpTour, type HelpTourStep } from '@/composables/useHelp';
import { useHelpInteractions } from '@/composables/useHelpInteractions';

interface Props {
    /** Tour slug to load */
    tourSlug?: string;
    /** Context key to load tour for */
    contextKey?: string;
    /** Whether to auto-start the tour on first visit */
    autoStart?: boolean;
    /** Manually provided tour data */
    tour?: HelpTour;
}

const props = withDefaults(defineProps<Props>(), {
    tourSlug: '',
    contextKey: '',
    autoStart: true,
    tour: undefined,
});

const emit = defineEmits<{
    (e: 'started'): void;
    (e: 'completed'): void;
    (e: 'skipped'): void;
    (e: 'step-changed', stepIndex: number): void;
}>();

// Composables
const { fetchTour } = useHelp();
const { isTourCompleted, recordTourCompletion, fetchCompletedTourSlugs } =
    useHelpInteractions();

// Tour state
const isActive = ref(false);
const currentStepIndex = ref(0);
const tourData = ref<HelpTour | null>(null);
const isLoading = ref(false);
const targetElement = ref<HTMLElement | null>(null);
const popoverPosition = ref({ top: 0, left: 0 });
const spotlightRect = ref({ top: 0, left: 0, width: 0, height: 0 });

// Configuration
const spotlightPadding = 8;
const popoverGap = 16;

// Computed
const currentStep = computed<HelpTourStep | null>(() => {
    if (!tourData.value?.steps || !tourData.value.steps[currentStepIndex.value]) {
        return null;
    }
    return tourData.value.steps[currentStepIndex.value];
});

const totalSteps = computed(() => tourData.value?.steps?.length || 0);
const isFirstStep = computed(() => currentStepIndex.value === 0);
const isLastStep = computed(() => currentStepIndex.value === totalSteps.value - 1);

const hasCompletedTour = computed(() => {
    if (!tourData.value) return false;
    return isTourCompleted(tourData.value.slug);
});

/**
 * Load tour data
 */
async function loadTour(): Promise<void> {
    if (props.tour) {
        tourData.value = props.tour;
        return;
    }

    isLoading.value = true;

    try {
        if (props.contextKey) {
            tourData.value = await fetchTour(props.contextKey);
        } else if (props.tourSlug) {
            // Fetch by slug - try context_key approach
            tourData.value = await fetchTour(props.tourSlug);
        }
    } finally {
        isLoading.value = false;
    }
}

/**
 * Start the tour
 */
async function start(): Promise<void> {
    if (!tourData.value || tourData.value.steps.length === 0) {
        return;
    }

    isActive.value = true;
    currentStepIndex.value = 0;
    emit('started');

    await nextTick();
    updateTargetElement();
}

/**
 * Replay a previously completed tour
 */
async function replay(): Promise<void> {
    if (tourData.value) {
        await start();
    } else if (props.contextKey || props.tourSlug) {
        await loadTour();
        await start();
    }
}

/**
 * Go to next step
 */
function next(): void {
    if (isLastStep.value) {
        complete();
    } else {
        currentStepIndex.value++;
        emit('step-changed', currentStepIndex.value);
        updateTargetElement();
    }
}

/**
 * Go to previous step
 */
function previous(): void {
    if (!isFirstStep.value) {
        currentStepIndex.value--;
        emit('step-changed', currentStepIndex.value);
        updateTargetElement();
    }
}

/**
 * Skip/close the tour
 */
function skip(): void {
    isActive.value = false;
    emit('skipped');
    cleanup();
}

/**
 * Complete the tour
 */
async function complete(): Promise<void> {
    isActive.value = false;

    if (tourData.value) {
        await recordTourCompletion(tourData.value.id);
    }

    emit('completed');
    cleanup();
}

/**
 * Go to a specific step by index
 */
function goToStep(index: number): void {
    if (index >= 0 && index < totalSteps.value) {
        currentStepIndex.value = index;
        emit('step-changed', currentStepIndex.value);
        updateTargetElement();
    }
}

/**
 * Cleanup on close
 */
function cleanup(): void {
    targetElement.value = null;
    currentStepIndex.value = 0;
}

/**
 * Update target element and positions
 */
function updateTargetElement(): void {
    if (!currentStep.value) {
        targetElement.value = null;
        return;
    }

    const selector = currentStep.value.target_selector;
    const element = document.querySelector(selector) as HTMLElement | null;

    targetElement.value = element;

    if (element) {
        updatePositions(element);
        // Scroll element into view
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

/**
 * Update spotlight and popover positions
 */
function updatePositions(element: HTMLElement): void {
    const rect = element.getBoundingClientRect();

    // Spotlight position (with padding)
    spotlightRect.value = {
        top: rect.top - spotlightPadding + window.scrollY,
        left: rect.left - spotlightPadding + window.scrollX,
        width: rect.width + spotlightPadding * 2,
        height: rect.height + spotlightPadding * 2,
    };

    // Popover position based on step.position
    const position = currentStep.value?.position || 'bottom';

    switch (position) {
        case 'top':
            popoverPosition.value = {
                top: rect.top - popoverGap + window.scrollY,
                left: rect.left + rect.width / 2 + window.scrollX,
            };
            break;
        case 'bottom':
            popoverPosition.value = {
                top: rect.bottom + popoverGap + window.scrollY,
                left: rect.left + rect.width / 2 + window.scrollX,
            };
            break;
        case 'left':
            popoverPosition.value = {
                top: rect.top + rect.height / 2 + window.scrollY,
                left: rect.left - popoverGap + window.scrollX,
            };
            break;
        case 'right':
            popoverPosition.value = {
                top: rect.top + rect.height / 2 + window.scrollY,
                left: rect.right + popoverGap + window.scrollX,
            };
            break;
    }
}

/**
 * Handle window resize and scroll
 */
function handleResize(): void {
    if (isActive.value && targetElement.value) {
        updatePositions(targetElement.value);
    }
}

/**
 * Handle keyboard navigation
 */
function handleKeydown(event: KeyboardEvent): void {
    if (!isActive.value) return;

    switch (event.key) {
        case 'Escape':
            skip();
            break;
        case 'ArrowRight':
        case 'Enter':
            event.preventDefault();
            next();
            break;
        case 'ArrowLeft':
            event.preventDefault();
            previous();
            break;
    }
}

/**
 * Handle spotlight overlay click (skip tour)
 */
function handleSpotlightClick(): void {
    skip();
}

// Watch for tour data and auto-start
watch(
    () => tourData.value,
    async (tour) => {
        if (tour && props.autoStart) {
            // Check if user has already completed this tour
            await fetchCompletedTourSlugs();
            if (!isTourCompleted(tour.slug)) {
                await start();
            }
        }
    },
);

// Lifecycle
onMounted(async () => {
    await loadTour();
    window.addEventListener('resize', handleResize);
    window.addEventListener('scroll', handleResize, true);
    document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('resize', handleResize);
    window.removeEventListener('scroll', handleResize, true);
    document.removeEventListener('keydown', handleKeydown);
});

// Expose methods for parent control
defineExpose({
    start,
    replay,
    next,
    previous,
    skip,
    complete,
    goToStep,
    isActive,
    currentStepIndex,
    hasCompletedTour,
    tourData,
});
</script>

<template>
    <Teleport to="body">
        <div v-if="isActive && currentStep" class="feature-tour">
            <!-- Spotlight overlay with cutout -->
            <TourSpotlight
                :target-element="targetElement"
                :padding="spotlightPadding"
                :animate="true"
                :is-active="isActive"
                @overlay-click="handleSpotlightClick"
            />

            <!-- Tour step popover -->
            <TourStepPopover
                v-if="currentStep"
                :step="currentStep"
                :current-index="currentStepIndex"
                :total-steps="totalSteps"
                :position="popoverPosition"
                :animate="true"
                @next="next"
                @previous="previous"
                @skip="skip"
                @finish="complete"
            />
        </div>
    </Teleport>
</template>

<style scoped>
.feature-tour {
    /* Ensure tour elements are above everything else */
}
</style>
