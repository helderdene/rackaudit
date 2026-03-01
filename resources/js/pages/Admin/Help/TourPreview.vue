<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { MarkdownRenderer } from '@/components/help';
import { ArrowLeft, Edit, ChevronLeft, ChevronRight, X, Play } from 'lucide-vue-next';
import type { BreadcrumbItemType } from '@/types';

interface TourStepArticle {
    id: number;
    slug: string;
    title: string;
    content: string;
}

interface TourStep {
    id: number;
    step_order: number;
    target_selector: string;
    position: string;
    position_label: string;
    article: TourStepArticle | null;
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
    tour: Tour;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Admin', href: '/admin/help' },
    { title: 'Help Management', href: '/admin/help?tab=tours' },
    { title: 'Preview Tour', href: '#' },
];

const isPlaying = ref(false);
const currentStepIndex = ref(0);

const sortedSteps = computed(() => {
    return [...props.tour.steps].sort((a, b) => a.step_order - b.step_order);
});

const currentStep = computed(() => {
    return sortedSteps.value[currentStepIndex.value] ?? null;
});

const totalSteps = computed(() => sortedSteps.value.length);

function startTour() {
    isPlaying.value = true;
    currentStepIndex.value = 0;
}

function stopTour() {
    isPlaying.value = false;
    currentStepIndex.value = 0;
}

function nextStep() {
    if (currentStepIndex.value < totalSteps.value - 1) {
        currentStepIndex.value++;
    } else {
        stopTour();
    }
}

function previousStep() {
    if (currentStepIndex.value > 0) {
        currentStepIndex.value--;
    }
}

function goToStep(index: number) {
    currentStepIndex.value = index;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Preview Tour: ${tour.name}`" />

        <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link href="/admin/help?tab=tours">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight">Preview: {{ tour.name }}</h1>
                        <div class="flex items-center gap-2 mt-1">
                            <Badge variant="outline">{{ totalSteps }} step{{ totalSteps !== 1 ? 's' : '' }}</Badge>
                            <Badge :variant="tour.is_active ? 'default' : 'secondary'">
                                {{ tour.is_active ? 'Active' : 'Inactive' }}
                            </Badge>
                            <span v-if="tour.context_key" class="text-sm text-muted-foreground">
                                Context: <code class="bg-muted px-1 py-0.5 rounded text-xs">{{ tour.context_key }}</code>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button v-if="!isPlaying && totalSteps > 0" @click="startTour">
                        <Play class="mr-2 h-4 w-4" />
                        Start Tour Preview
                    </Button>
                    <Link :href="`/admin/help/tours/${tour.id}/edit`">
                        <Button variant="outline">
                            <Edit class="mr-2 h-4 w-4" />
                            Edit Tour
                        </Button>
                    </Link>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Tour Steps</CardTitle>
                        <CardDescription>
                            Overview of all steps in this tour.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="sortedSteps.length === 0" class="text-center py-8 text-muted-foreground">
                            No steps in this tour yet.
                        </div>

                        <div v-else class="space-y-3">
                            <button
                                v-for="(step, index) in sortedSteps"
                                :key="step.id"
                                class="w-full text-left rounded-lg border p-4 transition-colors hover:bg-muted/50"
                                :class="{
                                    'border-primary bg-primary/5': isPlaying && currentStepIndex === index,
                                    'border-muted': !isPlaying || currentStepIndex !== index,
                                }"
                                @click="isPlaying ? goToStep(index) : null"
                            >
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <Badge
                                            :variant="isPlaying && currentStepIndex === index ? 'default' : 'outline'"
                                        >
                                            Step {{ index + 1 }}
                                        </Badge>
                                        <span class="font-medium">{{ step.article?.title ?? 'No article' }}</span>
                                    </div>
                                    <Badge variant="secondary" class="text-xs">
                                        {{ step.position_label }}
                                    </Badge>
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    Target: <code class="bg-muted px-1 py-0.5 rounded text-xs">{{ step.target_selector }}</code>
                                </div>
                            </button>
                        </div>
                    </CardContent>
                </Card>

                <div class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Tour Simulation</CardTitle>
                            <CardDescription>
                                Interactive preview of the tour experience.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="!isPlaying" class="text-center py-12">
                                <div class="mx-auto mb-4 h-12 w-12 rounded-full bg-primary/10 flex items-center justify-center">
                                    <Play class="h-6 w-6 text-primary" />
                                </div>
                                <h3 class="font-semibold mb-2">Tour Preview</h3>
                                <p class="text-sm text-muted-foreground mb-4">
                                    Click "Start Tour Preview" to simulate the user experience.
                                </p>
                                <Button
                                    v-if="totalSteps > 0"
                                    @click="startTour"
                                >
                                    <Play class="mr-2 h-4 w-4" />
                                    Start Tour
                                </Button>
                                <p v-else class="text-sm text-muted-foreground">
                                    Add steps to this tour to preview it.
                                </p>
                            </div>

                            <div v-else class="relative">
                                <div class="mb-4 flex items-center justify-between">
                                    <span class="text-sm text-muted-foreground">
                                        Step {{ currentStepIndex + 1 }} of {{ totalSteps }}
                                    </span>
                                    <Button variant="ghost" size="icon" @click="stopTour">
                                        <X class="h-4 w-4" />
                                    </Button>
                                </div>

                                <div class="rounded-lg border bg-popover p-4 shadow-lg">
                                    <h4 class="font-semibold mb-2">
                                        {{ currentStep?.article?.title ?? 'Step ' + (currentStepIndex + 1) }}
                                    </h4>
                                    <div v-if="currentStep?.article?.content" class="text-sm mb-4">
                                        <MarkdownRenderer :content="currentStep.article.content" />
                                    </div>
                                    <p v-else class="text-sm text-muted-foreground mb-4">
                                        No content for this step.
                                    </p>

                                    <div class="flex items-center justify-between pt-2 border-t">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            :disabled="currentStepIndex === 0"
                                            @click="previousStep"
                                        >
                                            <ChevronLeft class="mr-1 h-4 w-4" />
                                            Previous
                                        </Button>
                                        <div class="flex gap-1">
                                            <button
                                                v-for="(_, index) in sortedSteps"
                                                :key="index"
                                                class="h-2 w-2 rounded-full transition-colors"
                                                :class="index === currentStepIndex ? 'bg-primary' : 'bg-muted'"
                                                @click="goToStep(index)"
                                            />
                                        </div>
                                        <Button
                                            size="sm"
                                            @click="nextStep"
                                        >
                                            {{ currentStepIndex === totalSteps - 1 ? 'Finish' : 'Next' }}
                                            <ChevronRight v-if="currentStepIndex !== totalSteps - 1" class="ml-1 h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>

                                <div class="mt-4 rounded-lg border border-dashed p-4 text-center">
                                    <p class="text-xs text-muted-foreground">
                                        Target element:
                                        <code class="bg-muted px-1 py-0.5 rounded">{{ currentStep?.target_selector }}</code>
                                    </p>
                                    <p class="text-xs text-muted-foreground mt-1">
                                        Position: {{ currentStep?.position_label }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Tour Details</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl class="space-y-4">
                                <div>
                                    <dt class="text-sm font-medium text-muted-foreground">Slug</dt>
                                    <dd class="mt-1 text-sm"><code class="bg-muted px-1 py-0.5 rounded">{{ tour.slug }}</code></dd>
                                </div>
                                <div v-if="tour.description">
                                    <dt class="text-sm font-medium text-muted-foreground">Description</dt>
                                    <dd class="mt-1 text-sm">{{ tour.description }}</dd>
                                </div>
                                <div v-if="tour.context_key">
                                    <dt class="text-sm font-medium text-muted-foreground">Context Key</dt>
                                    <dd class="mt-1 text-sm"><code class="bg-muted px-1 py-0.5 rounded">{{ tour.context_key }}</code></dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-muted-foreground">Status</dt>
                                    <dd class="mt-1">
                                        <Badge :variant="tour.is_active ? 'default' : 'secondary'">
                                            {{ tour.is_active ? 'Active' : 'Inactive' }}
                                        </Badge>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-muted-foreground">Total Steps</dt>
                                    <dd class="mt-1 text-sm">{{ totalSteps }}</dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
