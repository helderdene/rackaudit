<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { create, edit, destroy } from '@/actions/App/Http/Controllers/DistributionListController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Mail, Plus, Pencil, Trash2, Users } from 'lucide-vue-next';

interface DistributionListData {
    id: number;
    name: string;
    description: string | null;
    members_count: number;
    created_at: string;
}

interface Props {
    distributionLists: DistributionListData[];
    canCreate: boolean;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Distribution Lists',
        href: '/distribution-lists',
    },
];

// Delete confirmation dialog state
const showDeleteDialog = ref(false);
const listToDelete = ref<DistributionListData | null>(null);
const isDeleting = ref(false);

const formatDate = (dateString: string): string => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const confirmDelete = (list: DistributionListData) => {
    listToDelete.value = list;
    showDeleteDialog.value = true;
};

const handleDelete = () => {
    if (!listToDelete.value) return;

    isDeleting.value = true;
    router.delete(destroy.url(listToDelete.value.id), {
        onSuccess: () => {
            showDeleteDialog.value = false;
            listToDelete.value = null;
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
};

const cancelDelete = () => {
    showDeleteDialog.value = false;
    listToDelete.value = null;
};
</script>

<template>
    <Head title="Distribution Lists" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <HeadingSmall
                    title="Distribution Lists"
                    description="Manage email recipient groups for scheduled report delivery."
                />
                <Link v-if="canCreate" :href="create.url()">
                    <Button>
                        <Plus class="mr-2 h-4 w-4" />
                        New Distribution List
                    </Button>
                </Link>
            </div>

            <!-- Empty state -->
            <div
                v-if="distributionLists.length === 0"
                class="flex flex-col items-center justify-center rounded-lg border border-dashed py-16"
            >
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-muted">
                    <Mail class="h-6 w-6 text-muted-foreground" />
                </div>
                <h3 class="mt-4 text-sm font-medium">No distribution lists yet</h3>
                <p class="mt-1 text-center text-sm text-muted-foreground">
                    Create a distribution list to organize email recipients for scheduled reports.
                </p>
                <Link v-if="canCreate" :href="create.url()" class="mt-4">
                    <Button variant="outline" size="sm">
                        <Plus class="mr-2 h-4 w-4" />
                        Create your first distribution list
                    </Button>
                </Link>
            </div>

            <!-- Distribution lists table -->
            <div v-else class="overflow-hidden rounded-md border">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/50">
                            <tr>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Name</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Description</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Members</th>
                                <th class="h-12 px-4 text-left font-medium text-muted-foreground">Created</th>
                                <th class="h-12 w-[120px] px-4 text-left font-medium text-muted-foreground">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="list in distributionLists"
                                :key="list.id"
                                class="border-b transition-colors hover:bg-muted/50"
                            >
                                <td class="p-4">
                                    <div class="flex items-center gap-2">
                                        <Mail class="h-4 w-4 text-muted-foreground" />
                                        <span class="font-medium">{{ list.name }}</span>
                                    </div>
                                </td>
                                <td class="p-4 text-muted-foreground">
                                    {{ list.description || '-' }}
                                </td>
                                <td class="p-4">
                                    <Badge variant="secondary" class="gap-1">
                                        <Users class="h-3 w-3" />
                                        {{ list.members_count }}
                                    </Badge>
                                </td>
                                <td class="p-4 text-muted-foreground">
                                    {{ formatDate(list.created_at) }}
                                </td>
                                <td class="p-4">
                                    <div class="flex items-center gap-2">
                                        <Link :href="edit.url(list.id)">
                                            <Button variant="ghost" size="icon-sm" title="Edit">
                                                <Pencil class="h-4 w-4" />
                                            </Button>
                                        </Link>
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            title="Delete"
                                            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                            @click="confirmDelete(list)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Delete confirmation dialog -->
        <Dialog v-model:open="showDeleteDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Distribution List</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete "{{ listToDelete?.name }}"? This action cannot be undone.
                        All {{ listToDelete?.members_count }} member(s) will be removed from this list.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="cancelDelete" :disabled="isDeleting">
                        Cancel
                    </Button>
                    <Button variant="destructive" @click="handleDelete" :disabled="isDeleting">
                        {{ isDeleting ? 'Deleting...' : 'Delete' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
