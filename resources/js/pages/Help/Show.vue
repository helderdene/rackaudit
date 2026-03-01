<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { index as helpIndex, show as helpShow } from '@/actions/App/Http/Controllers/HelpCenterController';
import { store as storeInteraction } from '@/actions/App/Http/Controllers/Api/Help/UserHelpInteractionController';
import AppLayout from '@/layouts/AppLayout.vue';
import MarkdownRenderer from '@/components/help/MarkdownRenderer.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import {
    ArrowLeft,
    BookOpen,
    Eye,
    ThumbsUp,
    ThumbsDown,
    Clock,
    Sparkles,
    ChevronRight,
    FileText,
    Hash,
} from 'lucide-vue-next';
import { type BreadcrumbItem } from '@/types';

/**
 * Type definitions for Help article detail props
 */
interface HelpArticle {
    id: number;
    slug: string;
    title: string;
    content: string;
    category: string | null;
    context_key: string | null;
    view_count: number;
    is_new: boolean;
    created_at: string;
    updated_at: string;
}

interface RelatedArticle {
    id: number;
    slug: string;
    title: string;
    excerpt: string;
    category: string | null;
}

interface Props {
    article: HelpArticle;
    relatedArticles: RelatedArticle[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Help Center',
        href: helpIndex.url(),
    },
    {
        title: props.article.title,
        href: helpShow.url(props.article.slug),
    },
];

// Feedback state
const feedbackGiven = ref<'helpful' | 'not-helpful' | null>(null);

// Extract table of contents from markdown headings
const tableOfContents = computed(() => {
    const headingRegex = /^#{2,3}\s+(.+)$/gm;
    const headings: { text: string; slug: string; level: number }[] = [];
    let match;

    while ((match = headingRegex.exec(props.article.content)) !== null) {
        const level = match[0].indexOf(' ');
        const text = match[1];
        const slug = text.toLowerCase().replace(/[^\w]+/g, '-');
        headings.push({ text, slug, level });
    }

    return headings;
});

// Record view on mount
onMounted(async () => {
    try {
        await fetch(storeInteraction.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                interaction_type: 'viewed',
                help_article_id: props.article.id,
            }),
        });
    } catch {
        // Ignore errors for view tracking
    }
});

// Navigate to related article
const navigateToArticle = (article: RelatedArticle) => {
    router.visit(helpShow.url(article.slug));
};

// Go back to help center
const goBack = () => {
    router.visit(helpIndex.url());
};

// Handle feedback
const giveFeedback = async (isHelpful: boolean) => {
    feedbackGiven.value = isHelpful ? 'helpful' : 'not-helpful';

    try {
        await fetch(storeInteraction.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                interaction_type: isHelpful ? 'helpful' : 'not_helpful',
                help_article_id: props.article.id,
            }),
        });
    } catch {
        // Ignore errors for feedback tracking
    }
};

// Format date
const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

// Scroll to section
const scrollToSection = (slug: string) => {
    const element = document.getElementById(slug);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
};
</script>

<template>
    <Head :title="`${article.title} - Help Center`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col rounded-xl">
            <!-- Hero Header -->
            <div class="relative overflow-hidden border-b border-border/50 bg-gradient-to-br from-muted/30 via-background to-muted/20">
                <!-- Decorative background pattern -->
                <div class="absolute inset-0 opacity-[0.015] dark:opacity-[0.03]">
                    <svg class="h-full w-full" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <pattern id="grid" width="32" height="32" patternUnits="userSpaceOnUse">
                                <path d="M0 32V0h32" fill="none" stroke="currentColor" stroke-width="1"/>
                            </pattern>
                        </defs>
                        <rect width="100%" height="100%" fill="url(#grid)" />
                    </svg>
                </div>

                <div class="relative px-6 py-8 md:px-8 md:py-10">
                    <!-- Back Button -->
                    <Button
                        variant="ghost"
                        size="sm"
                        class="mb-6 -ml-2 gap-2 text-muted-foreground hover:text-foreground"
                        @click="goBack"
                    >
                        <ArrowLeft class="size-4" />
                        Back to Help Center
                    </Button>

                    <!-- Article Meta -->
                    <div class="flex flex-wrap items-center gap-2 mb-4">
                        <Badge
                            v-if="article.category"
                            variant="secondary"
                            class="font-medium"
                        >
                            {{ article.category }}
                        </Badge>
                        <Badge
                            v-if="article.is_new"
                            class="gap-1 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20"
                        >
                            <Sparkles class="size-3" />
                            New
                        </Badge>
                    </div>

                    <!-- Title -->
                    <h1 class="text-3xl font-bold tracking-tight text-foreground md:text-4xl">
                        {{ article.title }}
                    </h1>

                    <!-- Stats -->
                    <div class="mt-4 flex flex-wrap items-center gap-5 text-sm text-muted-foreground">
                        <span class="flex items-center gap-1.5">
                            <Clock class="size-4 opacity-70" />
                            Updated {{ formatDate(article.updated_at) }}
                        </span>
                        <span class="flex items-center gap-1.5">
                            <Eye class="size-4 opacity-70" />
                            {{ article.view_count }} {{ article.view_count === 1 ? 'view' : 'views' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="flex-1 px-6 py-8 md:px-8">
                <div class="mx-auto max-w-6xl">
                    <div class="grid grid-cols-1 gap-8 lg:grid-cols-4">
                        <!-- Article Content (3/4 width on desktop) -->
                        <div class="lg:col-span-3">
                            <div class="rounded-xl border border-border/50 bg-card shadow-sm">
                                <div class="p-6 md:p-8">
                                    <MarkdownRenderer :content="article.content" />
                                </div>

                                <Separator />

                                <!-- Feedback Section -->
                                <div class="p-6 md:p-8">
                                    <div class="flex flex-col items-center justify-center rounded-lg bg-muted/30 p-6 text-center">
                                        <p class="mb-4 text-sm font-medium text-foreground">
                                            Was this article helpful?
                                        </p>
                                        <div v-if="!feedbackGiven" class="flex gap-3">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                class="gap-2 hover:border-emerald-500/50 hover:bg-emerald-500/10 hover:text-emerald-600"
                                                @click="giveFeedback(true)"
                                            >
                                                <ThumbsUp class="size-4" />
                                                Yes
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                class="gap-2 hover:border-rose-500/50 hover:bg-rose-500/10 hover:text-rose-600"
                                                @click="giveFeedback(false)"
                                            >
                                                <ThumbsDown class="size-4" />
                                                No
                                            </Button>
                                        </div>
                                        <div v-else class="flex items-center gap-2 text-sm">
                                            <span
                                                v-if="feedbackGiven === 'helpful'"
                                                class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400"
                                            >
                                                <ThumbsUp class="size-4" />
                                                Thanks! Glad this was helpful.
                                            </span>
                                            <span
                                                v-else
                                                class="text-muted-foreground"
                                            >
                                                Thanks for your feedback. We'll work on improving this.
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sidebar (1/4 width on desktop) -->
                        <div class="space-y-6 lg:sticky lg:top-6 lg:self-start">
                            <!-- Table of Contents -->
                            <div
                                v-if="tableOfContents.length > 0"
                                class="rounded-xl border border-border/50 bg-card p-5 shadow-sm"
                            >
                                <h3 class="mb-4 flex items-center gap-2 text-sm font-semibold text-foreground">
                                    <Hash class="size-4 text-primary/70" />
                                    On this page
                                </h3>
                                <nav class="space-y-1">
                                    <button
                                        v-for="heading in tableOfContents"
                                        :key="heading.slug"
                                        class="block w-full rounded-md px-3 py-1.5 text-left text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                        :class="{ 'pl-6': heading.level === 3 }"
                                        @click="scrollToSection(heading.slug)"
                                    >
                                        {{ heading.text }}
                                    </button>
                                </nav>
                            </div>

                            <!-- Related Articles -->
                            <div
                                v-if="relatedArticles.length > 0"
                                class="rounded-xl border border-border/50 bg-card p-5 shadow-sm"
                            >
                                <h3 class="mb-4 flex items-center gap-2 text-sm font-semibold text-foreground">
                                    <BookOpen class="size-4 text-primary/70" />
                                    Related Articles
                                </h3>
                                <div class="space-y-1">
                                    <button
                                        v-for="related in relatedArticles"
                                        :key="related.id"
                                        class="group flex w-full items-start gap-3 rounded-lg p-3 text-left transition-all hover:bg-muted"
                                        @click="navigateToArticle(related)"
                                    >
                                        <FileText class="mt-0.5 size-4 shrink-0 text-muted-foreground/70 transition-colors group-hover:text-primary" />
                                        <div class="min-w-0 flex-1">
                                            <span class="text-sm font-medium text-foreground line-clamp-2">
                                                {{ related.title }}
                                            </span>
                                            <p
                                                v-if="related.excerpt"
                                                class="mt-1 text-xs text-muted-foreground line-clamp-2"
                                            >
                                                {{ related.excerpt }}
                                            </p>
                                        </div>
                                        <ChevronRight class="mt-0.5 size-4 shrink-0 text-muted-foreground/50 transition-transform group-hover:translate-x-0.5 group-hover:text-muted-foreground" />
                                    </button>
                                </div>
                            </div>

                            <!-- Browse All Card -->
                            <div class="rounded-xl border border-border/50 bg-gradient-to-br from-muted/50 to-muted/20 p-5 shadow-sm">
                                <div class="text-center">
                                    <div class="mx-auto mb-3 flex size-10 items-center justify-center rounded-full bg-primary/10">
                                        <BookOpen class="size-5 text-primary" />
                                    </div>
                                    <p class="mb-4 text-sm text-muted-foreground">
                                        Looking for something else?
                                    </p>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="w-full gap-2"
                                        @click="goBack"
                                    >
                                        Browse All Articles
                                        <ChevronRight class="size-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
