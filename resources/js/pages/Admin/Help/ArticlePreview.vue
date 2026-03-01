<script setup lang="ts">
import { MarkdownRenderer } from '@/components/help';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Edit, MessageCircle } from 'lucide-vue-next';
import { ref } from 'vue';

interface Article {
    id: number;
    slug: string;
    title: string;
    content: string;
    context_key: string | null;
    article_type: string;
    article_type_label: string;
    category: string | null;
    is_active: boolean;
}

interface Props {
    article: Article;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Admin', href: '/admin/help' },
    { title: 'Help Management', href: '/admin/help' },
    { title: 'Preview', href: '#' },
];

const showTooltipDemo = ref(false);
const demoTargetRef = ref<HTMLElement | null>(null);

function getArticleTypeBadge(type: string): string {
    switch (type) {
        case 'tooltip':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
        case 'tour_step':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300';
        case 'article':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Preview: ${article.title}`" />

        <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link href="/admin/help">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight">
                            Preview: {{ article.title }}
                        </h1>
                        <div class="mt-1 flex items-center gap-2">
                            <Badge
                                :class="
                                    getArticleTypeBadge(article.article_type)
                                "
                            >
                                {{ article.article_type_label }}
                            </Badge>
                            <Badge
                                :variant="
                                    article.is_active ? 'default' : 'secondary'
                                "
                            >
                                {{ article.is_active ? 'Active' : 'Inactive' }}
                            </Badge>
                            <span
                                v-if="article.context_key"
                                class="text-sm text-muted-foreground"
                            >
                                Context:
                                <code
                                    class="rounded bg-muted px-1 py-0.5 text-xs"
                                    >{{ article.context_key }}</code
                                >
                            </span>
                        </div>
                    </div>
                </div>
                <Link :href="`/admin/help/articles/${article.id}/edit`">
                    <Button>
                        <Edit class="mr-2 h-4 w-4" />
                        Edit Article
                    </Button>
                </Link>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Article Content</CardTitle>
                        <CardDescription>
                            Full article content as it will appear to users.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div
                            class="prose prose-sm dark:prose-invert max-w-none"
                        >
                            <MarkdownRenderer :content="article.content" />
                        </div>
                    </CardContent>
                </Card>

                <div class="space-y-6">
                    <Card v-if="article.article_type === 'tooltip'">
                        <CardHeader>
                            <CardTitle>Tooltip Preview</CardTitle>
                            <CardDescription>
                                See how the tooltip will appear when attached to
                                an element.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex flex-col items-center gap-8 py-8">
                                <p
                                    class="text-center text-sm text-muted-foreground"
                                >
                                    Click the button below to see the tooltip in
                                    action.
                                </p>

                                <div class="relative">
                                    <Button
                                        ref="demoTargetRef"
                                        variant="outline"
                                        @click="
                                            showTooltipDemo = !showTooltipDemo
                                        "
                                    >
                                        <MessageCircle class="mr-2 h-4 w-4" />
                                        {{
                                            showTooltipDemo
                                                ? 'Hide Tooltip'
                                                : 'Show Tooltip'
                                        }}
                                    </Button>

                                    <div
                                        v-if="showTooltipDemo"
                                        class="absolute top-full left-1/2 z-50 mt-2 w-72 -translate-x-1/2 rounded-lg border bg-popover p-4 text-popover-foreground shadow-md"
                                    >
                                        <div
                                            class="absolute -top-2 left-1/2 -translate-x-1/2 border-8 border-transparent border-b-popover"
                                        />
                                        <h4 class="mb-2 font-semibold">
                                            {{ article.title }}
                                        </h4>
                                        <div class="text-sm">
                                            <MarkdownRenderer
                                                :content="article.content"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <p
                                    class="max-w-xs text-center text-xs text-muted-foreground"
                                >
                                    In the actual application, this tooltip
                                    would be positioned relative to the target
                                    element specified by a CSS selector.
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card v-if="article.article_type === 'tour_step'">
                        <CardHeader>
                            <CardTitle>Tour Step Preview</CardTitle>
                            <CardDescription>
                                See how this step will appear during a guided
                                tour.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex flex-col items-center gap-4 py-8">
                                <div
                                    class="w-full max-w-sm rounded-lg border bg-popover p-4 shadow-lg"
                                >
                                    <div
                                        class="mb-3 flex items-center justify-between"
                                    >
                                        <h4 class="font-semibold">
                                            {{ article.title }}
                                        </h4>
                                        <Badge variant="outline">Step</Badge>
                                    </div>
                                    <div class="mb-4 text-sm">
                                        <MarkdownRenderer
                                            :content="article.content"
                                        />
                                    </div>
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <Button variant="ghost" size="sm"
                                            >Previous</Button
                                        >
                                        <span
                                            class="text-xs text-muted-foreground"
                                            >1 of 3</span
                                        >
                                        <Button size="sm">Next</Button>
                                    </div>
                                </div>
                                <p
                                    class="max-w-xs text-center text-xs text-muted-foreground"
                                >
                                    In the tour, this step would be positioned
                                    relative to a highlighted element.
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Article Details</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl class="space-y-4">
                                <div>
                                    <dt
                                        class="text-sm font-medium text-muted-foreground"
                                    >
                                        Slug
                                    </dt>
                                    <dd class="mt-1 text-sm">
                                        <code
                                            class="rounded bg-muted px-1 py-0.5"
                                            >{{ article.slug }}</code
                                        >
                                    </dd>
                                </div>
                                <div>
                                    <dt
                                        class="text-sm font-medium text-muted-foreground"
                                    >
                                        Type
                                    </dt>
                                    <dd class="mt-1">
                                        <Badge
                                            :class="
                                                getArticleTypeBadge(
                                                    article.article_type,
                                                )
                                            "
                                        >
                                            {{ article.article_type_label }}
                                        </Badge>
                                    </dd>
                                </div>
                                <div v-if="article.category">
                                    <dt
                                        class="text-sm font-medium text-muted-foreground"
                                    >
                                        Category
                                    </dt>
                                    <dd class="mt-1 text-sm">
                                        {{ article.category }}
                                    </dd>
                                </div>
                                <div v-if="article.context_key">
                                    <dt
                                        class="text-sm font-medium text-muted-foreground"
                                    >
                                        Context Key
                                    </dt>
                                    <dd class="mt-1 text-sm">
                                        <code
                                            class="rounded bg-muted px-1 py-0.5"
                                            >{{ article.context_key }}</code
                                        >
                                    </dd>
                                </div>
                                <div>
                                    <dt
                                        class="text-sm font-medium text-muted-foreground"
                                    >
                                        Status
                                    </dt>
                                    <dd class="mt-1">
                                        <Badge
                                            :variant="
                                                article.is_active
                                                    ? 'default'
                                                    : 'secondary'
                                            "
                                        >
                                            {{
                                                article.is_active
                                                    ? 'Active'
                                                    : 'Inactive'
                                            }}
                                        </Badge>
                                    </dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
