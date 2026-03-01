<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { type HelpTour } from '@/composables/useHelp';
import { useHelpInteractions } from '@/composables/useHelpInteractions';
import { cn } from '@/lib/utils';
import { CircleHelp } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import HelpSidebar from './HelpSidebar.vue';

interface Props {
    /** Current context key for filtering articles */
    contextKey?: string;
    /** Position of the button */
    position?: 'fixed' | 'inline';
    /** Size of the button */
    size?: 'sm' | 'default' | 'lg';
    /** Whether to show notification badge */
    showBadge?: boolean;
    /** Badge count (if showing badge) */
    badgeCount?: number;
    /** Additional class for the button */
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    contextKey: '',
    position: 'inline',
    size: 'default',
    showBadge: false,
    badgeCount: 0,
});

const emit = defineEmits<{
    (e: 'open'): void;
    (e: 'close'): void;
    (e: 'replayTour', tour: HelpTour): void;
}>();

// State
const isSidebarOpen = ref(false);

// Initialize help interactions
const { initialize } = useHelpInteractions();

// Computed: check if Mac platform for keyboard shortcut display
const isMac = computed(
    () =>
        typeof window !== 'undefined' &&
        window.navigator?.platform?.includes('Mac'),
);

// Size classes
const buttonSizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'size-8';
        case 'lg':
            return 'size-12';
        default:
            return 'size-10';
    }
});

const iconSizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'size-4';
        case 'lg':
            return 'size-6';
        default:
            return 'size-5';
    }
});

/**
 * Open the help sidebar
 */
function openSidebar() {
    isSidebarOpen.value = true;
    emit('open');
}

/**
 * Close the help sidebar
 */
function closeSidebar() {
    isSidebarOpen.value = false;
    emit('close');
}

/**
 * Toggle sidebar
 */
function toggleSidebar() {
    if (isSidebarOpen.value) {
        closeSidebar();
    } else {
        openSidebar();
    }
}

/**
 * Handle sidebar open state change
 */
function handleSidebarChange(open: boolean) {
    isSidebarOpen.value = open;
    if (open) {
        emit('open');
    } else {
        emit('close');
    }
}

/**
 * Handle replay tour
 */
function handleReplayTour(tour: HelpTour) {
    emit('replayTour', tour);
}

/**
 * Global keyboard shortcut handler (Cmd/Ctrl + ? or F1)
 */
function handleGlobalKeydown(event: KeyboardEvent) {
    // Cmd/Ctrl + ? (Shift + /)
    if (
        (event.metaKey || event.ctrlKey) &&
        event.shiftKey &&
        event.key === '/'
    ) {
        event.preventDefault();
        toggleSidebar();
        return;
    }

    // F1 key
    if (event.key === 'F1') {
        event.preventDefault();
        toggleSidebar();
    }
}

// Lifecycle
onMounted(async () => {
    document.addEventListener('keydown', handleGlobalKeydown);
    // Initialize help interactions (fetch dismissed articles, etc.)
    await initialize();
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleGlobalKeydown);
});
</script>

<template>
    <div>
        <!-- Help Button -->
        <TooltipProvider :delay-duration="300">
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button
                        variant="ghost"
                        :size="'icon'"
                        :class="
                            cn(
                                buttonSizeClasses,
                                'relative rounded-full',
                                position === 'fixed' &&
                                    'fixed right-6 bottom-6 z-50 bg-primary text-primary-foreground shadow-lg hover:bg-primary/90',
                                props.class,
                            )
                        "
                        :aria-label="'Open help center'"
                        @click="toggleSidebar"
                    >
                        <CircleHelp :class="iconSizeClasses" />

                        <!-- Notification badge -->
                        <span
                            v-if="showBadge && badgeCount > 0"
                            :class="
                                cn(
                                    'absolute -top-1 -right-1 flex items-center justify-center',
                                    'h-5 min-w-5 rounded-full px-1.5',
                                    'bg-destructive text-destructive-foreground',
                                    'text-xs font-medium',
                                )
                            "
                        >
                            {{ badgeCount > 99 ? '99+' : badgeCount }}
                        </span>
                    </Button>
                </TooltipTrigger>
                <TooltipContent side="left" :side-offset="8">
                    <div class="flex items-center gap-2">
                        <span>Help</span>
                        <kbd
                            class="hidden items-center gap-0.5 rounded bg-muted px-1.5 py-0.5 font-mono text-[10px] sm:inline-flex"
                        >
                            {{ isMac ? 'Cmd' : 'Ctrl' }}+?
                        </kbd>
                    </div>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>

        <!-- Help Sidebar -->
        <HelpSidebar
            v-model="isSidebarOpen"
            :context-key="contextKey"
            @update:model-value="handleSidebarChange"
            @replay-tour="handleReplayTour"
        />
    </div>
</template>
