<script setup lang="ts">
import { MarkdownRenderer } from '@/components/help';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Eye, Save } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface ArticleType {
    value: string;
    label: string;
}

interface Article {
    id: number;
    slug: string;
    title: string;
    content: string;
    context_key: string | null;
    article_type: string;
    category: string | null;
    sort_order: number;
    is_active: boolean;
}

interface Props {
    mode: 'create' | 'edit';
    article?: Article;
    articleTypes: ArticleType[];
    categories: string[];
    contextKeys: Record<string, string>;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Admin', href: '/admin/help' },
    { title: 'Help Management', href: '/admin/help' },
    {
        title: props.mode === 'create' ? 'New Article' : 'Edit Article',
        href: '#',
    },
];

const isPreviewMode = ref(false);

const form = ref({
    slug: props.article?.slug ?? '',
    title: props.article?.title ?? '',
    content: props.article?.content ?? '',
    context_key: props.article?.context_key ?? '',
    article_type: props.article?.article_type ?? 'article',
    category: props.article?.category ?? '',
    sort_order: props.article?.sort_order ?? 0,
    is_active: props.article?.is_active ?? true,
});

const errors = ref<Record<string, string>>({});
const processing = ref(false);

const customCategory = ref('');
const showCustomCategory = ref(false);
const customContextKey = ref('');
const showCustomContextKey = ref(false);

const contextKeysList = computed(() => {
    return Object.entries(props.contextKeys).map(([key, description]) => ({
        key,
        description,
    }));
});

function generateSlug(title: string): string {
    return title
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
}

watch(
    () => form.value.title,
    (newTitle) => {
        if (props.mode === 'create' && !form.value.slug) {
            form.value.slug = generateSlug(newTitle);
        }
    },
);

function handleCategoryChange(event: Event) {
    const target = event.target as HTMLSelectElement;
    if (target.value === '__custom__') {
        showCustomCategory.value = true;
        form.value.category = '';
    } else {
        showCustomCategory.value = false;
        form.value.category = target.value;
    }
}

function handleContextKeyChange(event: Event) {
    const target = event.target as HTMLSelectElement;
    if (target.value === '__custom__') {
        showCustomContextKey.value = true;
        form.value.context_key = '';
    } else {
        showCustomContextKey.value = false;
        form.value.context_key = target.value;
    }
}

function submit() {
    processing.value = true;
    errors.value = {};

    const url =
        props.mode === 'create'
            ? '/admin/help/articles'
            : `/admin/help/articles/${props.article?.id}`;

    const method = props.mode === 'create' ? 'post' : 'put';

    const data = {
        ...form.value,
        category: showCustomCategory.value
            ? customCategory.value
            : form.value.category,
        context_key: showCustomContextKey.value
            ? customContextKey.value
            : form.value.context_key,
    };

    router[method](url, data, {
        onError: (validationErrors) => {
            errors.value = validationErrors as Record<string, string>;
            processing.value = false;
        },
        onSuccess: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head
            :title="
                mode === 'create' ? 'Create Help Article' : 'Edit Help Article'
            "
        />

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
                            {{
                                mode === 'create'
                                    ? 'Create Help Article'
                                    : 'Edit Help Article'
                            }}
                        </h1>
                        <p class="text-muted-foreground">
                            {{
                                mode === 'create'
                                    ? 'Add a new help article, tooltip, or tour step.'
                                    : 'Update the help article details.'
                            }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isPreviewMode = !isPreviewMode"
                    >
                        <Eye class="mr-2 h-4 w-4" />
                        {{ isPreviewMode ? 'Edit' : 'Preview' }}
                    </Button>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Article Details</CardTitle>
                        <CardDescription>
                            Configure the article settings and content.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submit" class="space-y-6">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="title">Title</Label>
                                    <Input
                                        id="title"
                                        v-model="form.title"
                                        placeholder="Enter article title"
                                        :class="{
                                            'border-destructive': errors.title,
                                        }"
                                    />
                                    <p
                                        v-if="errors.title"
                                        class="text-sm text-destructive"
                                    >
                                        {{ errors.title }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="slug">Slug</Label>
                                    <Input
                                        id="slug"
                                        v-model="form.slug"
                                        placeholder="article-slug"
                                        :class="{
                                            'border-destructive': errors.slug,
                                        }"
                                    />
                                    <p
                                        v-if="errors.slug"
                                        class="text-sm text-destructive"
                                    >
                                        {{ errors.slug }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="article_type"
                                        >Article Type</Label
                                    >
                                    <select
                                        id="article_type"
                                        v-model="form.article_type"
                                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none"
                                        :class="{
                                            'border-destructive':
                                                errors.article_type,
                                        }"
                                    >
                                        <option
                                            v-for="type in articleTypes"
                                            :key="type.value"
                                            :value="type.value"
                                        >
                                            {{ type.label }}
                                        </option>
                                    </select>
                                    <p
                                        v-if="errors.article_type"
                                        class="text-sm text-destructive"
                                    >
                                        {{ errors.article_type }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="category">Category</Label>
                                    <select
                                        v-if="!showCustomCategory"
                                        id="category"
                                        :value="form.category"
                                        @change="handleCategoryChange"
                                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none"
                                    >
                                        <option value="">No Category</option>
                                        <option
                                            v-for="cat in categories"
                                            :key="cat"
                                            :value="cat"
                                        >
                                            {{ cat }}
                                        </option>
                                        <option value="__custom__">
                                            + Add Custom Category
                                        </option>
                                    </select>
                                    <div v-else class="flex gap-2">
                                        <Input
                                            v-model="customCategory"
                                            placeholder="Enter custom category"
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            @click="showCustomCategory = false"
                                        >
                                            X
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="context_key">Context Key</Label>
                                <p class="text-xs text-muted-foreground">
                                    The context key determines which pages will
                                    show this content.
                                </p>
                                <select
                                    v-if="!showCustomContextKey"
                                    id="context_key"
                                    :value="form.context_key"
                                    @change="handleContextKeyChange"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none"
                                >
                                    <option value="">
                                        No Context (Global)
                                    </option>
                                    <option
                                        v-for="ctx in contextKeysList"
                                        :key="ctx.key"
                                        :value="ctx.key"
                                    >
                                        {{ ctx.key }} - {{ ctx.description }}
                                    </option>
                                    <option value="__custom__">
                                        + Add Custom Context Key
                                    </option>
                                </select>
                                <div v-else class="flex gap-2">
                                    <Input
                                        v-model="customContextKey"
                                        placeholder="e.g., page.feature.action"
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        @click="showCustomContextKey = false"
                                    >
                                        X
                                    </Button>
                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="sort_order">Sort Order</Label>
                                    <Input
                                        id="sort_order"
                                        v-model.number="form.sort_order"
                                        type="number"
                                        min="0"
                                        max="9999"
                                    />
                                    <p
                                        v-if="errors.sort_order"
                                        class="text-sm text-destructive"
                                    >
                                        {{ errors.sort_order }}
                                    </p>
                                </div>

                                <div class="flex items-center space-x-2 pt-6">
                                    <Switch
                                        id="is_active"
                                        :checked="form.is_active"
                                        @update:checked="
                                            form.is_active = $event
                                        "
                                    />
                                    <Label for="is_active">Active</Label>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="content">Content (Markdown)</Label>
                                <Textarea
                                    id="content"
                                    v-model="form.content"
                                    placeholder="Write your article content using Markdown..."
                                    class="min-h-[300px] font-mono text-sm"
                                    :class="{
                                        'border-destructive': errors.content,
                                    }"
                                />
                                <p
                                    v-if="errors.content"
                                    class="text-sm text-destructive"
                                >
                                    {{ errors.content }}
                                </p>
                            </div>

                            <div class="flex justify-end gap-4">
                                <Link href="/admin/help">
                                    <Button type="button" variant="outline"
                                        >Cancel</Button
                                    >
                                </Link>
                                <Button type="submit" :disabled="processing">
                                    <Save class="mr-2 h-4 w-4" />
                                    {{
                                        processing
                                            ? 'Saving...'
                                            : 'Save Article'
                                    }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Preview</CardTitle>
                        <CardDescription>
                            See how the content will appear to users.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="rounded-lg border bg-card p-4">
                            <h3
                                v-if="form.title"
                                class="mb-4 text-lg font-semibold"
                            >
                                {{ form.title }}
                            </h3>
                            <div
                                v-if="form.content"
                                class="prose prose-sm dark:prose-invert max-w-none"
                            >
                                <MarkdownRenderer :content="form.content" />
                            </div>
                            <p v-else class="text-muted-foreground italic">
                                Start typing to see the preview...
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
