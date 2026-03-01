<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { CircleHelp, X } from 'lucide-vue-next';
import { cn } from '@/lib/utils';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { Checkbox } from '@/components/ui/checkbox';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import MarkdownRenderer from './MarkdownRenderer.vue';
import { useHelpInteractions } from '@/composables/useHelpInteractions';

interface Props {
    /** Base context key (e.g., 'audits.execute') */
    contextKey: string;
    /** Element identifier to build full context_key (e.g., 'start-button') */
    elementId?: string;
    /** Size of the trigger icon */
    size?: 'sm' | 'md' | 'lg';
    /** Additional class for the trigger button */
    class?: string;
}

interface HelpArticle {
    id: number;
    slug: string;
    title: string;
    content: string;
    context_key: string;
    article_type: string;
    category: string | null;
}

const props = withDefaults(defineProps<Props>(), {
    elementId: '',
    size: 'sm',
});

const emit = defineEmits<{
    (e: 'dismiss', articleId: number): void;
    (e: 'view', articleId: number): void;
}>();

// Build full context key
const fullContextKey = computed(() => {
    if (props.elementId) {
        return `${props.contextKey}.${props.elementId}`;
    }
    return props.contextKey;
});

// Help interactions composable
const { isDismissed, recordDismissal, recordView } = useHelpInteractions();

// Component state
const isOpen = ref(false);
const isLoading = ref(false);
const article = ref<HelpArticle | null>(null);
const hasFetched = ref(false);
const dontShowAgain = ref(false);
const error = ref<string | null>(null);

// Check if already dismissed
const shouldShow = computed(() => {
    if (!article.value) return true;
    return !isDismissed(article.value.id);
});

// Size classes for the trigger icon
const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'size-4';
        case 'md':
            return 'size-5';
        case 'lg':
            return 'size-6';
        default:
            return 'size-4';
    }
});

/**
 * Fetch tooltip content from API
 */
async function fetchContent() {
    if (hasFetched.value || isLoading.value) return;

    isLoading.value = true;
    error.value = null;

    try {
        const response = await fetch(
            `/api/help/articles?context_key=${encodeURIComponent(fullContextKey.value)}&type=tooltip`,
            {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            },
        );

        if (response.ok) {
            const data = await response.json();
            if (data.data && data.data.length > 0) {
                article.value = data.data[0];
                // Record view interaction
                if (article.value) {
                    await recordView(article.value.id);
                    emit('view', article.value.id);
                }
            }
        } else {
            error.value = 'Failed to load help content';
        }
    } catch (err) {
        console.error('Failed to fetch help content:', err);
        error.value = 'Failed to load help content';
    } finally {
        isLoading.value = false;
        hasFetched.value = true;
    }
}

/**
 * Handle tooltip open
 */
function handleOpen(open: boolean) {
    isOpen.value = open;
    if (open && !hasFetched.value) {
        fetchContent();
    }
}

/**
 * Handle dismiss with "Don't show again"
 */
async function handleDismiss() {
    if (article.value && dontShowAgain.value) {
        await recordDismissal(article.value.id);
        emit('dismiss', article.value.id);
    }
    isOpen.value = false;
}

/**
 * Handle close without dismissing
 */
function handleClose() {
    isOpen.value = false;
}
</script>

<template>
    <TooltipProvider :delay-duration="300">
        <Tooltip :open="isOpen" @update:open="handleOpen">
            <TooltipTrigger as-child>
                <button
                    type="button"
                    :class="cn(
                        'inline-flex items-center justify-center rounded-full text-muted-foreground hover:text-foreground transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                        props.class,
                    )"
                    :aria-label="`Help for ${fullContextKey}`"
                >
                    <CircleHelp :class="sizeClasses" />
                </button>
            </TooltipTrigger>

            <TooltipContent
                :side="'bottom'"
                :side-offset="8"
                :class="cn(
                    'w-72 max-h-80 overflow-y-auto p-0',
                    'bg-popover text-popover-foreground',
                )"
                @pointer-down-outside.prevent
            >
                <!-- Loading state -->
                <div v-if="isLoading" class="p-4 space-y-2">
                    <Skeleton class="h-4 w-3/4" />
                    <Skeleton class="h-3 w-full" />
                    <Skeleton class="h-3 w-5/6" />
                </div>

                <!-- Error state -->
                <div v-else-if="error" class="p-4 text-sm text-destructive">
                    {{ error }}
                </div>

                <!-- No content state -->
                <div
                    v-else-if="hasFetched && !article"
                    class="p-4 text-sm text-muted-foreground"
                >
                    No help content available.
                </div>

                <!-- Content -->
                <div v-else-if="article" class="flex flex-col">
                    <!-- Header -->
                    <div
                        class="flex items-start justify-between gap-2 border-b px-4 py-3"
                    >
                        <h4 class="text-sm font-medium text-foreground">
                            {{ article.title }}
                        </h4>
                        <button
                            type="button"
                            class="shrink-0 text-muted-foreground hover:text-foreground transition-colors"
                            @click="handleClose"
                        >
                            <X class="size-4" />
                            <span class="sr-only">Close</span>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="px-4 py-3">
                        <MarkdownRenderer
                            :content="article.content"
                            class="text-xs"
                        />
                    </div>

                    <!-- Footer with "Don't show again" -->
                    <div
                        class="flex items-center justify-between gap-4 border-t px-4 py-3 bg-muted/30"
                    >
                        <label
                            class="flex items-center gap-2 text-xs text-muted-foreground cursor-pointer"
                        >
                            <Checkbox
                                v-model:checked="dontShowAgain"
                                class="size-3.5"
                            />
                            <span>Don't show again</span>
                        </label>
                        <Button
                            size="sm"
                            variant="outline"
                            class="h-7 text-xs"
                            @click="handleDismiss"
                        >
                            Got it
                        </Button>
                    </div>
                </div>
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
