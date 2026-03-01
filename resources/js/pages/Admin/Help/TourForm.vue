<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ArrowLeft, Save, Plus, Trash2, GripVertical, ChevronUp, ChevronDown } from 'lucide-vue-next';
import type { BreadcrumbItemType } from '@/types';

interface StepPosition {
    value: string;
    label: string;
}

interface AvailableArticle {
    id: number;
    slug: string;
    title: string;
    article_type: string;
}

interface TourStep {
    id?: number;
    help_article_id: number | null;
    target_selector: string;
    position: string;
    step_order: number;
    article?: {
        id: number;
        title: string;
    } | null;
}

interface Tour {
    id: number;
    slug: string;
    name: string;
    context_key: string | null;
    description: string | null;
    is_active: boolean;
    steps: TourStep[];
}

interface Props {
    mode: 'create' | 'edit';
    tour?: Tour;
    availableArticles: AvailableArticle[];
    stepPositions: StepPosition[];
    contextKeys: Record<string, string>;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Admin', href: '/admin/help' },
    { title: 'Help Management', href: '/admin/help?tab=tours' },
    { title: props.mode === 'create' ? 'New Tour' : 'Edit Tour', href: '#' },
];

const form = ref({
    slug: props.tour?.slug ?? '',
    name: props.tour?.name ?? '',
    context_key: props.tour?.context_key ?? '',
    description: props.tour?.description ?? '',
    is_active: props.tour?.is_active ?? true,
});

const steps = ref<TourStep[]>(
    props.tour?.steps?.map((step, index) => ({
        ...step,
        step_order: index,
    })) ?? []
);

const errors = ref<Record<string, string>>({});
const processing = ref(false);

const customContextKey = ref('');
const showCustomContextKey = ref(false);

const contextKeysList = computed(() => {
    return Object.entries(props.contextKeys).map(([key, description]) => ({
        key,
        description,
    }));
});

const tourStepArticles = computed(() => {
    return props.availableArticles.filter(a => a.article_type === 'tour_step');
});

function generateSlug(name: string): string {
    return name
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
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

function addStep() {
    steps.value.push({
        help_article_id: null,
        target_selector: '',
        position: 'bottom',
        step_order: steps.value.length,
    });
}

function removeStep(index: number) {
    steps.value.splice(index, 1);
    reorderSteps();
}

function moveStepUp(index: number) {
    if (index > 0) {
        const temp = steps.value[index];
        steps.value[index] = steps.value[index - 1];
        steps.value[index - 1] = temp;
        reorderSteps();
    }
}

function moveStepDown(index: number) {
    if (index < steps.value.length - 1) {
        const temp = steps.value[index];
        steps.value[index] = steps.value[index + 1];
        steps.value[index + 1] = temp;
        reorderSteps();
    }
}

function reorderSteps() {
    steps.value.forEach((step, index) => {
        step.step_order = index;
    });
}

function getArticleTitle(articleId: number | null): string {
    if (!articleId) return 'Select an article';
    const article = props.availableArticles.find(a => a.id === articleId);
    return article?.title ?? 'Unknown article';
}

function submit() {
    processing.value = true;
    errors.value = {};

    const url = props.mode === 'create'
        ? '/admin/help/tours'
        : `/admin/help/tours/${props.tour?.id}`;

    const method = props.mode === 'create' ? 'post' : 'put';

    const data = {
        ...form.value,
        slug: form.value.slug || generateSlug(form.value.name),
        context_key: showCustomContextKey.value ? customContextKey.value : form.value.context_key,
        steps: steps.value.map((step, index) => ({
            help_article_id: step.help_article_id,
            target_selector: step.target_selector,
            position: step.position,
            step_order: index,
        })),
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
        <Head :title="mode === 'create' ? 'Create Help Tour' : 'Edit Help Tour'" />

        <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link href="/admin/help?tab=tours">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight">
                            {{ mode === 'create' ? 'Create Guided Tour' : 'Edit Guided Tour' }}
                        </h1>
                        <p class="text-muted-foreground">
                            {{ mode === 'create' ? 'Create a new step-by-step tour for users.' : 'Update the tour details and steps.' }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Tour Details</CardTitle>
                        <CardDescription>
                            Configure the tour name and settings.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submit" class="space-y-6">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="name">Tour Name</Label>
                                    <Input
                                        id="name"
                                        v-model="form.name"
                                        placeholder="Enter tour name"
                                        :class="{ 'border-destructive': errors.name }"
                                    />
                                    <p v-if="errors.name" class="text-sm text-destructive">{{ errors.name }}</p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="slug">Slug</Label>
                                    <Input
                                        id="slug"
                                        v-model="form.slug"
                                        placeholder="tour-slug (auto-generated)"
                                        :class="{ 'border-destructive': errors.slug }"
                                    />
                                    <p v-if="errors.slug" class="text-sm text-destructive">{{ errors.slug }}</p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="description">Description</Label>
                                <Textarea
                                    id="description"
                                    v-model="form.description"
                                    placeholder="Brief description of what this tour covers..."
                                    class="min-h-[80px]"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="context_key">Context Key</Label>
                                <p class="text-xs text-muted-foreground">
                                    The context key determines which pages will show this tour.
                                </p>
                                <select
                                    v-if="!showCustomContextKey"
                                    id="context_key"
                                    :value="form.context_key"
                                    @change="handleContextKeyChange"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                >
                                    <option value="">No Context (Global)</option>
                                    <option v-for="ctx in contextKeysList" :key="ctx.key" :value="ctx.key">
                                        {{ ctx.key }} - {{ ctx.description }}
                                    </option>
                                    <option value="__custom__">+ Add Custom Context Key</option>
                                </select>
                                <div v-else class="flex gap-2">
                                    <Input
                                        v-model="customContextKey"
                                        placeholder="e.g., page.feature.action"
                                    />
                                    <Button type="button" variant="outline" size="icon" @click="showCustomContextKey = false">
                                        X
                                    </Button>
                                </div>
                            </div>

                            <div class="flex items-center space-x-2">
                                <Switch
                                    id="is_active"
                                    :checked="form.is_active"
                                    @update:checked="form.is_active = $event"
                                />
                                <Label for="is_active">Active</Label>
                            </div>

                            <div class="flex justify-end gap-4">
                                <Link href="/admin/help?tab=tours">
                                    <Button type="button" variant="outline">Cancel</Button>
                                </Link>
                                <Button type="submit" :disabled="processing">
                                    <Save class="mr-2 h-4 w-4" />
                                    {{ processing ? 'Saving...' : 'Save Tour' }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle>Tour Steps</CardTitle>
                                <CardDescription>
                                    Add and reorder steps for the guided tour.
                                </CardDescription>
                            </div>
                            <Button type="button" size="sm" @click="addStep">
                                <Plus class="mr-2 h-4 w-4" />
                                Add Step
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="steps.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                            <p class="text-muted-foreground">
                                No steps added yet. Click "Add Step" to create tour steps.
                            </p>
                        </div>

                        <div v-else class="space-y-4">
                            <div
                                v-for="(step, index) in steps"
                                :key="index"
                                class="rounded-lg border bg-card p-4"
                            >
                                <div class="mb-3 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <GripVertical class="h-4 w-4 text-muted-foreground cursor-move" />
                                        <Badge variant="outline">Step {{ index + 1 }}</Badge>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            :disabled="index === 0"
                                            @click="moveStepUp(index)"
                                        >
                                            <ChevronUp class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            :disabled="index === steps.length - 1"
                                            @click="moveStepDown(index)"
                                        >
                                            <ChevronDown class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="text-destructive hover:text-destructive"
                                            @click="removeStep(index)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>

                                <div class="grid gap-4">
                                    <div class="space-y-2">
                                        <Label :for="`step-${index}-article`">Article</Label>
                                        <select
                                            :id="`step-${index}-article`"
                                            v-model="step.help_article_id"
                                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                        >
                                            <option :value="null">Select an article</option>
                                            <option v-for="article in tourStepArticles" :key="article.id" :value="article.id">
                                                {{ article.title }}
                                            </option>
                                        </select>
                                    </div>

                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div class="space-y-2">
                                            <Label :for="`step-${index}-selector`">Target Selector</Label>
                                            <Input
                                                :id="`step-${index}-selector`"
                                                v-model="step.target_selector"
                                                placeholder="#element-id or .class-name"
                                            />
                                            <p class="text-xs text-muted-foreground">
                                                CSS selector for the element to highlight
                                            </p>
                                        </div>

                                        <div class="space-y-2">
                                            <Label :for="`step-${index}-position`">Position</Label>
                                            <select
                                                :id="`step-${index}-position`"
                                                v-model="step.position"
                                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                            >
                                                <option v-for="pos in stepPositions" :key="pos.value" :value="pos.value">
                                                    {{ pos.label }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="tourStepArticles.length === 0" class="mt-4 rounded-lg border border-orange-200 bg-orange-50 dark:border-orange-900 dark:bg-orange-950 p-4">
                            <p class="text-sm text-orange-800 dark:text-orange-200">
                                No "tour_step" type articles found. Create some articles with type "Tour Step" first to use in tours.
                            </p>
                            <Link href="/admin/help/articles/create" class="mt-2 inline-block text-sm font-medium text-orange-600 hover:underline dark:text-orange-400">
                                Create Tour Step Article
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
