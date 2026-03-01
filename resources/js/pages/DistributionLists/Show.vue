<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { index, edit, destroy } from '@/actions/App/Http/Controllers/DistributionListController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
import { Mail, ArrowLeft, Pencil, Trash2, Users, Calendar } from 'lucide-vue-next';

interface Member {
    id: number;
    email: string;
    sort_order: number;
}

interface DistributionListData {
    id: number;
    name: string;
    description: string | null;
    members: Member[];
    created_at: string;
}

interface Props {
    distributionList: DistributionListData;
    canEdit: boolean;
    canDelete: boolean;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Distribution Lists',
        href: '/distribution-lists',
    },
    {
        title: props.distributionList.name,
        href: `/distribution-lists/${props.distributionList.id}`,
    },
];

// Delete confirmation dialog state
const showDeleteDialog = ref(false);
const isDeleting = ref(false);

const formatDate = (dateString: string): string => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const handleDelete = () => {
    isDeleting.value = true;
    router.delete(destroy.url(props.distributionList.id), {
        onSuccess: () => {
            showDeleteDialog.value = false;
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
};
</script>

<template>
    <Head :title="distributionList.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="index.url()">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <HeadingSmall
                        :title="distributionList.name"
                        :description="distributionList.description || 'No description'"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <Link v-if="canEdit" :href="edit.url(distributionList.id)">
                        <Button variant="outline">
                            <Pencil class="mr-2 h-4 w-4" />
                            Edit
                        </Button>
                    </Link>
                    <Button
                        v-if="canDelete"
                        variant="outline"
                        class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                        @click="showDeleteDialog = true"
                    >
                        <Trash2 class="mr-2 h-4 w-4" />
                        Delete
                    </Button>
                </div>
            </div>

            <div class="grid max-w-4xl gap-6 lg:grid-cols-3">
                <!-- Details Card -->
                <Card class="lg:col-span-1">
                    <CardHeader>
                        <CardTitle>Details</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex items-center gap-2 text-sm">
                            <Users class="h-4 w-4 text-muted-foreground" />
                            <span class="text-muted-foreground">Members:</span>
                            <Badge variant="secondary">{{ distributionList.members.length }}</Badge>
                        </div>
                        <div class="flex items-center gap-2 text-sm">
                            <Calendar class="h-4 w-4 text-muted-foreground" />
                            <span class="text-muted-foreground">Created:</span>
                            <span>{{ formatDate(distributionList.created_at) }}</span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Members Card -->
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Mail class="h-5 w-5" />
                            Members
                        </CardTitle>
                        <CardDescription>
                            Email recipients in this distribution list.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="distributionList.members.length > 0" class="rounded-md border">
                            <ul class="divide-y">
                                <li
                                    v-for="member in distributionList.members"
                                    :key="member.id"
                                    class="flex items-center gap-3 px-4 py-3"
                                >
                                    <Mail class="h-4 w-4 shrink-0 text-muted-foreground" />
                                    <span class="text-sm">{{ member.email }}</span>
                                </li>
                            </ul>
                        </div>
                        <div
                            v-else
                            class="flex flex-col items-center justify-center rounded-lg border border-dashed py-8 text-center"
                        >
                            <Mail class="h-8 w-8 text-muted-foreground" />
                            <p class="mt-2 text-sm text-muted-foreground">
                                No members in this distribution list.
                            </p>
                            <Link v-if="canEdit" :href="edit.url(distributionList.id)" class="mt-4">
                                <Button variant="outline" size="sm">
                                    Add Members
                                </Button>
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Delete confirmation dialog -->
        <Dialog v-model:open="showDeleteDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Distribution List</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete "{{ distributionList.name }}"? This action cannot be undone.
                        All {{ distributionList.members.length }} member(s) will be removed from this list.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="showDeleteDialog = false" :disabled="isDeleting">
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
