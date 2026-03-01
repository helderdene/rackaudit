<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Eye, FileText, Sparkles } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Type definition for help article
 */
interface HelpArticle {
    id: number;
    slug: string;
    title: string;
    content?: string;
    excerpt: string;
    category: string | null;
    context_key?: string | null;
    sort_order?: number;
    is_new?: boolean;
    view_count?: number;
    created_at?: string;
    updated_at?: string;
}

interface Props {
    article: HelpArticle;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    click: [article: HelpArticle];
}>();

// Truncate excerpt to 150 characters
const truncatedExcerpt = computed(() => {
    if (!props.article.excerpt) {
        return '';
    }
    if (props.article.excerpt.length <= 150) {
        return props.article.excerpt;
    }
    return props.article.excerpt.slice(0, 150).trim() + '...';
});

// Handle click
const handleClick = () => {
    emit('click', props.article);
};
</script>

<template>
    <Card
        class="cursor-pointer transition-all hover:border-primary/50 hover:shadow-md"
        role="button"
        tabindex="0"
        @click="handleClick"
        @keydown.enter="handleClick"
        @keydown.space.prevent="handleClick"
    >
        <CardHeader class="pb-2">
            <div class="flex items-start justify-between gap-2">
                <div class="flex flex-wrap items-center gap-2">
                    <Badge
                        v-if="article.category"
                        variant="outline"
                        class="text-xs"
                    >
                        {{ article.category }}
                    </Badge>
                    <Badge
                        v-if="article.is_new"
                        variant="default"
                        class="gap-1 bg-green-600 text-xs hover:bg-green-700"
                    >
                        <Sparkles class="h-3 w-3" />
                        New
                    </Badge>
                </div>
                <div
                    v-if="
                        article.view_count !== undefined &&
                        article.view_count > 0
                    "
                    class="flex items-center gap-1 text-xs text-muted-foreground"
                >
                    <Eye class="h-3 w-3" />
                    {{ article.view_count }}
                </div>
            </div>
            <CardTitle class="mt-2 text-base leading-tight font-semibold">
                <span class="flex items-start gap-2">
                    <FileText
                        class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground"
                    />
                    <span class="line-clamp-2">{{ article.title }}</span>
                </span>
            </CardTitle>
        </CardHeader>
        <CardContent>
            <CardDescription class="line-clamp-2 text-sm">
                {{ truncatedExcerpt }}
            </CardDescription>
        </CardContent>
    </Card>
</template>
