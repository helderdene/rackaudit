<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Edit,
    Eye,
    FileText,
    MapPin,
    MoreHorizontal,
    Plus,
    Search,
    Trash2,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface ArticleType {
    value: string;
    label: string;
}

interface Article {
    id: number;
    slug: string;
    title: string;
    article_type: string;
    category: string | null;
    context_key: string | null;
    sort_order: number;
    is_active: boolean;
}

interface Tour {
    id: number;
    slug: string;
    name: string;
    context_key: string | null;
    description: string | null;
    is_active: boolean;
    steps_count: number;
}

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Filters {
    search: string;
    article_type: string;
    category: string;
    is_active: string;
    tour_search: string;
    tour_is_active: string;
}

interface Props {
    articles: PaginatedData<Article>;
    tours: PaginatedData<Tour>;
    categories: string[];
    articleTypes: ArticleType[];
    filters: Filters;
    activeTab: string;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Admin', href: '/admin/help' },
    { title: 'Help Management', href: '/admin/help' },
];

const currentTab = ref(props.activeTab);
const searchQuery = ref(props.filters.search);
const tourSearchQuery = ref(props.filters.tour_search);
const selectedCategory = ref(props.filters.category);
const selectedType = ref(props.filters.article_type);
const selectedActive = ref(props.filters.is_active);
const tourSelectedActive = ref(props.filters.tour_is_active);

const debounceTimer = ref<ReturnType<typeof setTimeout> | null>(null);

function applyFilters() {
    router.get(
        '/admin/help',
        {
            search: searchQuery.value || undefined,
            article_type: selectedType.value || undefined,
            category: selectedCategory.value || undefined,
            is_active: selectedActive.value || undefined,
            tour_search: tourSearchQuery.value || undefined,
            tour_is_active: tourSelectedActive.value || undefined,
            tab: currentTab.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function debouncedSearch() {
    if (debounceTimer.value) {
        clearTimeout(debounceTimer.value);
    }
    debounceTimer.value = setTimeout(() => {
        applyFilters();
    }, 300);
}

watch(searchQuery, debouncedSearch);
watch(tourSearchQuery, debouncedSearch);
watch(
    [selectedCategory, selectedType, selectedActive, tourSelectedActive],
    applyFilters,
);

watch(currentTab, (newTab) => {
    router.get(
        '/admin/help',
        { tab: newTab },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
});

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

function deleteArticle(id: number) {
    if (confirm('Are you sure you want to delete this article?')) {
        router.delete(`/admin/help/articles/${id}`, {
            preserveScroll: true,
        });
    }
}

function deleteTour(id: number) {
    if (
        confirm(
            'Are you sure you want to delete this tour? All associated steps will also be deleted.',
        )
    ) {
        router.delete(`/admin/help/tours/${id}`, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Help Management" />

        <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        Help Management
                    </h1>
                    <p class="text-muted-foreground">
                        Manage help articles, tooltips, and guided tours for
                        users.
                    </p>
                </div>
            </div>

            <Tabs v-model="currentTab" class="w-full">
                <TabsList>
                    <TabsTrigger
                        value="articles"
                        class="flex items-center gap-2"
                    >
                        <FileText class="h-4 w-4" />
                        Articles
                        <Badge variant="secondary" class="ml-1">
                            {{ articles.total }}
                        </Badge>
                    </TabsTrigger>
                    <TabsTrigger value="tours" class="flex items-center gap-2">
                        <MapPin class="h-4 w-4" />
                        Tours
                        <Badge variant="secondary" class="ml-1">
                            {{ tours.total }}
                        </Badge>
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="articles" class="mt-4">
                    <Card>
                        <CardHeader>
                            <div
                                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div>
                                    <CardTitle>Help Articles</CardTitle>
                                    <CardDescription>
                                        Create and manage tooltips, tour steps,
                                        and full articles.
                                    </CardDescription>
                                </div>
                                <Link href="/admin/help/articles/create">
                                    <Button>
                                        <Plus class="mr-2 h-4 w-4" />
                                        New Article
                                    </Button>
                                </Link>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div
                                class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center"
                            >
                                <div class="relative flex-1">
                                    <Search
                                        class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                                    />
                                    <Input
                                        v-model="searchQuery"
                                        placeholder="Search articles..."
                                        class="pl-9"
                                    />
                                </div>
                                <select
                                    v-model="selectedType"
                                    class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none"
                                >
                                    <option value="">All Types</option>
                                    <option
                                        v-for="type in articleTypes"
                                        :key="type.value"
                                        :value="type.value"
                                    >
                                        {{ type.label }}
                                    </option>
                                </select>
                                <select
                                    v-model="selectedCategory"
                                    class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none"
                                >
                                    <option value="">All Categories</option>
                                    <option
                                        v-for="cat in categories"
                                        :key="cat"
                                        :value="cat"
                                    >
                                        {{ cat }}
                                    </option>
                                </select>
                                <select
                                    v-model="selectedActive"
                                    class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none"
                                >
                                    <option value="">All Status</option>
                                    <option value="true">Active</option>
                                    <option value="false">Inactive</option>
                                </select>
                            </div>

                            <div class="overflow-hidden rounded-md border">
                                <table class="w-full text-sm">
                                    <thead class="border-b bg-muted/50">
                                        <tr>
                                            <th
                                                class="h-12 px-4 text-left font-medium text-muted-foreground"
                                            >
                                                Title
                                            </th>
                                            <th
                                                class="h-12 px-4 text-left font-medium text-muted-foreground"
                                            >
                                                Type
                                            </th>
                                            <th
                                                class="h-12 px-4 text-left font-medium text-muted-foreground"
                                            >
                                                Category
                                            </th>
                                            <th
                                                class="h-12 px-4 text-left font-medium text-muted-foreground"
                                            >
                                                Context Key
                                            </th>
                                            <th
                                                class="h-12 px-4 text-left font-medium text-muted-foreground"
                                            >
                                                Status
                                            </th>
                                            <th
                                                class="h-12 w-[70px] px-4 text-left font-medium text-muted-foreground"
                                            >
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="article in articles.data"
                                            :key="article.id"
                                            class="border-b last:border-0 hover:bg-muted/50"
                                        >
                                            <td class="px-4 py-3 font-medium">
                                                {{ article.title }}
                                            </td>
                                            <td class="px-4 py-3">
                                                <Badge
                                                    :class="
                                                        getArticleTypeBadge(
                                                            article.article_type,
                                                        )
                                                    "
                                                >
                                                    {{ article.article_type }}
                                                </Badge>
                                            </td>
                                            <td class="px-4 py-3">
                                                {{ article.category || '-' }}
                                            </td>
                                            <td class="px-4 py-3">
                                                <code
                                                    v-if="article.context_key"
                                                    class="rounded bg-muted px-1 py-0.5 text-xs"
                                                >
                                                    {{ article.context_key }}
                                                </code>
                                                <span
                                                    v-else
                                                    class="text-muted-foreground"
                                                    >-</span
                                                >
                                            </td>
                                            <td class="px-4 py-3">
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
                                            </td>
                                            <td class="px-4 py-3">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        as-child
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                        >
                                                            <MoreHorizontal
                                                                class="h-4 w-4"
                                                            />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent
                                                        align="end"
                                                    >
                                                        <DropdownMenuItem
                                                            as-child
                                                        >
                                                            <Link
                                                                :href="`/admin/help/articles/${article.id}/preview`"
                                                                class="flex items-center"
                                                            >
                                                                <Eye
                                                                    class="mr-2 h-4 w-4"
                                                                />
                                                                Preview
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            as-child
                                                        >
                                                            <Link
                                                                :href="`/admin/help/articles/${article.id}/edit`"
                                                                class="flex items-center"
                                                            >
                                                                <Edit
                                                                    class="mr-2 h-4 w-4"
                                                                />
                                                                Edit
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            class="text-destructive focus:text-destructive"
                                                            @click="
                                                                deleteArticle(
                                                                    article.id,
                                                                )
                                                            "
                                                        >
                                                            <Trash2
                                                                class="mr-2 h-4 w-4"
                                                            />
                                                            Delete
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </td>
                                        </tr>
                                        <tr v-if="articles.data.length === 0">
                                            <td
                                                colspan="6"
                                                class="h-24 text-center text-muted-foreground"
                                            >
                                                No articles found.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div
                                v-if="articles.last_page > 1"
                                class="mt-4 flex items-center justify-center gap-2"
                            >
                                <template
                                    v-for="link in articles.links"
                                    :key="link.label"
                                >
                                    <Button
                                        v-if="link.url"
                                        :variant="
                                            link.active ? 'default' : 'outline'
                                        "
                                        size="sm"
                                        @click="router.visit(link.url)"
                                        ><span v-html="link.label"
                                    /></Button>
                                    <span
                                        v-else
                                        class="px-2 text-muted-foreground"
                                        v-html="link.label"
                                    />
                                </template>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="tours" class="mt-4">
                    <Card>
                        <CardHeader>
                            <div
                                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div>
                                    <CardTitle>Guided Tours</CardTitle>
                                    <CardDescription>
                                        Create and manage step-by-step guided
                                        tours for users.
                                    </CardDescription>
                                </div>
                                <Link href="/admin/help/tours/create">
                                    <Button>
                                        <Plus class="mr-2 h-4 w-4" />
                                        New Tour
                                    </Button>
                                </Link>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div
                                class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center"
                            >
                                <div class="relative flex-1">
                                    <Search
                                        class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                                    />
                                    <Input
                                        v-model="tourSearchQuery"
                                        placeholder="Search tours..."
                                        class="pl-9"
                                    />
                                </div>
                                <select
                                    v-model="tourSelectedActive"
                                    class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none"
                                >
                                    <option value="">All Status</option>
                                    <option value="true">Active</option>
                                    <option value="false">Inactive</option>
                                </select>
                            </div>

                            <div class="overflow-hidden rounded-md border">
                                <table class="w-full text-sm">
                                    <thead class="border-b bg-muted/50">
                                        <tr>
                                            <th
                                                class="h-12 px-4 text-left font-medium text-muted-foreground"
                                            >
                                                Name
                                            </th>
                                            <th
                                                class="h-12 px-4 text-left font-medium text-muted-foreground"
                                            >
                                                Context Key
                                            </th>
                                            <th
                                                class="h-12 px-4 text-left font-medium text-muted-foreground"
                                            >
                                                Steps
                                            </th>
                                            <th
                                                class="h-12 px-4 text-left font-medium text-muted-foreground"
                                            >
                                                Status
                                            </th>
                                            <th
                                                class="h-12 w-[70px] px-4 text-left font-medium text-muted-foreground"
                                            >
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="tour in tours.data"
                                            :key="tour.id"
                                            class="border-b last:border-0 hover:bg-muted/50"
                                        >
                                            <td class="px-4 py-3">
                                                <div class="font-medium">
                                                    {{ tour.name }}
                                                </div>
                                                <div
                                                    v-if="tour.description"
                                                    class="text-sm text-muted-foreground"
                                                >
                                                    {{ tour.description }}
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <code
                                                    v-if="tour.context_key"
                                                    class="rounded bg-muted px-1 py-0.5 text-xs"
                                                >
                                                    {{ tour.context_key }}
                                                </code>
                                                <span
                                                    v-else
                                                    class="text-muted-foreground"
                                                    >-</span
                                                >
                                            </td>
                                            <td class="px-4 py-3">
                                                <Badge variant="outline">
                                                    {{ tour.steps_count }}
                                                    step{{
                                                        tour.steps_count !== 1
                                                            ? 's'
                                                            : ''
                                                    }}
                                                </Badge>
                                            </td>
                                            <td class="px-4 py-3">
                                                <Badge
                                                    :variant="
                                                        tour.is_active
                                                            ? 'default'
                                                            : 'secondary'
                                                    "
                                                >
                                                    {{
                                                        tour.is_active
                                                            ? 'Active'
                                                            : 'Inactive'
                                                    }}
                                                </Badge>
                                            </td>
                                            <td class="px-4 py-3">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        as-child
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                        >
                                                            <MoreHorizontal
                                                                class="h-4 w-4"
                                                            />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent
                                                        align="end"
                                                    >
                                                        <DropdownMenuItem
                                                            as-child
                                                        >
                                                            <Link
                                                                :href="`/admin/help/tours/${tour.id}/preview`"
                                                                class="flex items-center"
                                                            >
                                                                <Eye
                                                                    class="mr-2 h-4 w-4"
                                                                />
                                                                Preview
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            as-child
                                                        >
                                                            <Link
                                                                :href="`/admin/help/tours/${tour.id}/edit`"
                                                                class="flex items-center"
                                                            >
                                                                <Edit
                                                                    class="mr-2 h-4 w-4"
                                                                />
                                                                Edit
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            class="text-destructive focus:text-destructive"
                                                            @click="
                                                                deleteTour(
                                                                    tour.id,
                                                                )
                                                            "
                                                        >
                                                            <Trash2
                                                                class="mr-2 h-4 w-4"
                                                            />
                                                            Delete
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </td>
                                        </tr>
                                        <tr v-if="tours.data.length === 0">
                                            <td
                                                colspan="5"
                                                class="h-24 text-center text-muted-foreground"
                                            >
                                                No tours found.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div
                                v-if="tours.last_page > 1"
                                class="mt-4 flex items-center justify-center gap-2"
                            >
                                <template
                                    v-for="link in tours.links"
                                    :key="link.label"
                                >
                                    <Button
                                        v-if="link.url"
                                        :variant="
                                            link.active ? 'default' : 'outline'
                                        "
                                        size="sm"
                                        @click="router.visit(link.url)"
                                        ><span v-html="link.label"
                                    /></Button>
                                    <span
                                        v-else
                                        class="px-2 text-muted-foreground"
                                        v-html="link.label"
                                    />
                                </template>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>
