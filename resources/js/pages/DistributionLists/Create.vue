<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { Form } from '@inertiajs/vue3';
import { index, store } from '@/actions/App/Http/Controllers/DistributionListController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import DistributionListMemberInput from '@/components/DistributionLists/DistributionListMemberInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Mail, ArrowLeft } from 'lucide-vue-next';

interface Member {
    email: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Distribution Lists',
        href: '/distribution-lists',
    },
    {
        title: 'Create',
        href: '/distribution-lists/create',
    },
];

// Form state
const members = ref<Member[]>([]);
</script>

<template>
    <Head title="Create Distribution List" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Header -->
            <div class="flex items-center gap-4">
                <Link :href="index.url()">
                    <Button variant="ghost" size="icon">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                </Link>
                <HeadingSmall
                    title="Create Distribution List"
                    description="Create a new email recipient group for scheduled report delivery."
                />
            </div>

            <Form
                :action="store.url()"
                method="post"
                class="max-w-2xl space-y-6"
                #default="{ errors, processing }"
            >
                <!-- Hidden fields for members array -->
                <template v-for="(member, index) in members" :key="`member-hidden-${index}`">
                    <input type="hidden" :name="`members[${index}][email]`" :value="member.email" />
                </template>

                <!-- Basic Information Card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Mail class="h-5 w-5" />
                            List Details
                        </CardTitle>
                        <CardDescription>
                            Enter the basic information for your distribution list.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">Name <span class="text-destructive">*</span></Label>
                            <Input
                                id="name"
                                name="name"
                                type="text"
                                placeholder="e.g., Finance Team, Weekly Report Recipients"
                                :aria-invalid="!!errors.name"
                                required
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="description">Description</Label>
                            <Textarea
                                id="description"
                                name="description"
                                placeholder="Optional description for this distribution list"
                                :aria-invalid="!!errors.description"
                            />
                            <InputError :message="errors.description" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Members Card -->
                <Card>
                    <CardHeader>
                        <CardTitle>Members</CardTitle>
                        <CardDescription>
                            Add email addresses of recipients who should receive reports sent to this list.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <DistributionListMemberInput
                            v-model="members"
                            :errors="errors"
                        />
                    </CardContent>
                </Card>

                <!-- Submit Actions -->
                <div class="flex items-center gap-4">
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" class="mr-2 h-4 w-4" />
                        {{ processing ? 'Creating...' : 'Create Distribution List' }}
                    </Button>
                    <Link :href="index.url()">
                        <Button type="button" variant="outline">
                            Cancel
                        </Button>
                    </Link>
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
