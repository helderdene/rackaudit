import { ref, computed, readonly, onMounted, onUnmounted, nextTick } from 'vue';
import { useHelp, type HelpTour, type HelpTourStep } from '@/composables/useHelp';
import { useHelpInteractions } from '@/composables/useHelpInteractions';

/**
 * Position coordinates for popover
 */
export interface PopoverPosition {
    top: number;
    left: number;
}

/**
 * Spotlight rectangle for target element highlighting
 */
export interface SpotlightRect {
    top: number;
    left: number;
    width: number;
    height: number;
}

/**
 * Tour state interface
 */
export interface TourState {
    isActive: boolean;
    isLoading: boolean;
    currentStepIndex: number;
    tour: HelpTour | null;
    targetElement: HTMLElement | null;
    popoverPosition: PopoverPosition;
    spotlightRect: SpotlightRect;
}

/**
 * Options for useTour composable
 */
export interface UseTourOptions {
    /** Context key to load tour for */
    contextKey?: string;
    /** Tour slug to load directly */
    tourSlug?: string;
    /** Whether to auto-start tour on first visit (default: true) */
    autoStart?: boolean;
    /** Pre-loaded tour data (skip API fetch) */
    initialTour?: HelpTour;
    /** Padding around spotlight */
    spotlightPadding?: number;
    /** Gap between target and popover */
    popoverGap?: number;
}

/**
 * Composable for managing feature tour state and navigation
 */
export function useTour(options: UseTourOptions = {}) {
    const {
        contextKey = '',
        tourSlug = '',
        autoStart = true,
        initialTour,
        spotlightPadding = 8,
        popoverGap = 16,
    } = options;

    // Dependencies
    const { fetchTour } = useHelp();
    const { isTourCompleted, recordTourCompletion, fetchCompletedTourSlugs } =
        useHelpInteractions();

    // Tour state
    const isActive = ref(false);
    const isLoading = ref(false);
    const currentStepIndex = ref(0);
    const tourData = ref<HelpTour | null>(initialTour || null);
    const targetElement = ref<HTMLElement | null>(null);
    const popoverPosition = ref<PopoverPosition>({ top: 0, left: 0 });
    const spotlightRect = ref<SpotlightRect>({ top: 0, left: 0, width: 0, height: 0 });

    // Event handlers storage for cleanup
    const boundHandleResize = handleResize.bind(null);
    const boundHandleKeydown = handleKeydown.bind(null);

    // Computed properties
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
     * Load tour data from API or use initial data
     */
    async function loadTour(): Promise<HelpTour | null> {
        if (initialTour) {
            tourData.value = initialTour;
            return initialTour;
        }

        isLoading.value = true;

        try {
            const tour = await fetchTour(contextKey || tourSlug);
            tourData.value = tour;
            return tour;
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * Check if tour should auto-start (first visit)
     */
    async function shouldAutoStart(): Promise<boolean> {
        if (!autoStart) return false;
        if (!tourData.value) return false;

        // Fetch completed tours if not already done
        await fetchCompletedTourSlugs();

        return !isTourCompleted(tourData.value.slug);
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

        // Set up event listeners
        window.addEventListener('resize', boundHandleResize);
        window.addEventListener('scroll', boundHandleResize, true);
        document.addEventListener('keydown', boundHandleKeydown);

        await nextTick();
        updateTargetElement();
    }

    /**
     * Stop/close the tour without recording completion
     */
    function stop(): void {
        isActive.value = false;
        cleanup();
    }

    /**
     * Go to next step
     */
    function next(): void {
        if (isLastStep.value) {
            complete();
        } else {
            currentStepIndex.value++;
            updateTargetElement();
        }
    }

    /**
     * Go to previous step
     */
    function previous(): void {
        if (!isFirstStep.value) {
            currentStepIndex.value--;
            updateTargetElement();
        }
    }

    /**
     * Skip the tour (close without completion)
     */
    function skip(): void {
        isActive.value = false;
        cleanup();
    }

    /**
     * Complete the tour and record completion
     */
    async function complete(): Promise<void> {
        isActive.value = false;

        if (tourData.value) {
            await recordTourCompletion(tourData.value.id);
        }

        cleanup();
    }

    /**
     * Replay a previously completed tour
     */
    async function replay(): Promise<void> {
        if (tourData.value) {
            await start();
        } else if (contextKey || tourSlug) {
            await loadTour();
            await start();
        }
    }

    /**
     * Go to a specific step by index
     */
    function goToStep(index: number): void {
        if (index >= 0 && index < totalSteps.value) {
            currentStepIndex.value = index;
            updateTargetElement();
        }
    }

    /**
     * Update target element and positions for current step
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
     * Update spotlight and popover positions based on target element
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
     * Cleanup event listeners and state
     */
    function cleanup(): void {
        window.removeEventListener('resize', boundHandleResize);
        window.removeEventListener('scroll', boundHandleResize, true);
        document.removeEventListener('keydown', boundHandleKeydown);

        targetElement.value = null;
        currentStepIndex.value = 0;
    }

    /**
     * Initialize tour - load data and optionally auto-start
     */
    async function initialize(): Promise<void> {
        if (!tourData.value && (contextKey || tourSlug)) {
            await loadTour();
        }

        if (tourData.value && await shouldAutoStart()) {
            await start();
        }
    }

    // Cleanup on unmount
    onUnmounted(() => {
        cleanup();
    });

    return {
        // State (readonly)
        isActive: readonly(isActive),
        isLoading: readonly(isLoading),
        currentStepIndex: readonly(currentStepIndex),
        tour: readonly(tourData),
        currentStep,
        totalSteps,
        isFirstStep,
        isLastStep,
        hasCompletedTour,
        targetElement: readonly(targetElement),
        popoverPosition: readonly(popoverPosition),
        spotlightRect: readonly(spotlightRect),

        // Actions
        initialize,
        loadTour,
        start,
        stop,
        next,
        previous,
        skip,
        complete,
        replay,
        goToStep,
        updateTargetElement,
    };
}
