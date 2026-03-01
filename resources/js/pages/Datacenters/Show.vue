<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import DatacenterController from '@/actions/App/Http/Controllers/DatacenterController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import DeleteDatacenterDialog from '@/components/datacenters/DeleteDatacenterDialog.vue';
import FloorPlanDisplay from '@/components/datacenters/FloorPlanDisplay.vue';
import ImplementationFileCard from '@/components/implementation-files/ImplementationFileCard.vue';
import ImplementationFilesWarning from '@/components/implementation-files/ImplementationFilesWarning.vue';
import DiscrepancyWidget from '@/components/Datacenters/DiscrepancyWidget.vue';
import { type ImplementationFile } from '@/components/implementation-files/ImplementationFileList.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Building2, MapPin, Phone, User, Mail, DoorOpen, ChevronRight, GitCompare } from 'lucide-vue-next';
import { index as roomsIndex } from '@/actions/App/Http/Controllers/RoomController';

interface DatacenterData {
    id: number;
    name: string;
    address_line_1: string;
    address_line_2: string | null;
    city: string;
    state_province: string;
    postal_code: string;
    country: string;
    formatted_address: string;
    formatted_location: string;
    company_name: string | null;
    primary_contact_name: string;
    primary_contact_email: string;
    primary_contact_phone: string;
    secondary_contact_name: string | null;
    secondary_contact_email: string | null;
    secondary_contact_phone: string | null;
    floor_plan_path: string | null;
    floor_plan_url: string | null;
    created_at: string;
    updated_at: string;
    has_approved_implementation_files: boolean;
}

interface Props {
    datacenter: DatacenterData;
    implementationFiles: ImplementationFile[];
    canEdit: boolean;
    canDelete: boolean;
    canUploadFiles: boolean;
    canDeleteFiles: boolean;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Datacenters',
        href: DatacenterController.index.url(),
    },
    {
        title: props.datacenter.name,
        href: DatacenterController.show.url(props.datacenter.id),
    },
];

// Format date for display
const formatDate = (dateString: string): string => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <Head :title="datacenter.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <HeadingSmall
                    :title="datacenter.name"
                    :description="datacenter.formatted_location"
                />
                <div class="flex gap-2">
                    <!-- Connection Audit Button - Prominently placed -->
                    <Link
                        v-if="datacenter.has_approved_implementation_files"
                        :href="`/datacenters/${datacenter.id}/connection-comparison`"
                    >
                        <Button variant="default" class="gap-2">
                            <GitCompare class="size-4" />
                            Connection Audit
                        </Button>
                    </Link>
                    <Link v-if="canEdit" :href="DatacenterController.edit.url(datacenter.id)">
                        <Button variant="outline">Edit</Button>
                    </Link>
                    <DeleteDatacenterDialog
                        v-if="canDelete"
                        :datacenter-id="datacenter.id"
                        :datacenter-name="datacenter.name"
                    >
                        <Button variant="destructive">Delete</Button>
                    </DeleteDatacenterDialog>
                </div>
            </div>

            <!-- Warning for no approved implementation files -->
            <ImplementationFilesWarning
                :has-approved-files="datacenter.has_approved_implementation_files"
            />

            <!-- Discrepancy Widget - prominently positioned after warning -->
            <DiscrepancyWidget
                v-if="datacenter.has_approved_implementation_files"
                :datacenter-id="datacenter.id"
            />

            <!-- Content Grid -->
            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Basic Info Card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-lg">
                            <Building2 class="size-5" />
                            Basic Information
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Name</dt>
                            <dd class="text-sm">{{ datacenter.name }}</dd>
                        </div>
                        <div v-if="datacenter.company_name" class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Company</dt>
                            <dd class="text-sm">{{ datacenter.company_name }}</dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Created</dt>
                            <dd class="text-sm text-muted-foreground">{{ formatDate(datacenter.created_at) }}</dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Last Updated</dt>
                            <dd class="text-sm text-muted-foreground">{{ formatDate(datacenter.updated_at) }}</dd>
                        </div>
                    </CardContent>
                </Card>

                <!-- Location Card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-lg">
                            <MapPin class="size-5" />
                            Location
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Address</dt>
                            <dd class="text-sm whitespace-pre-line">{{ datacenter.formatted_address }}</dd>
                        </div>
                    </CardContent>
                </Card>

                <!-- Primary Contact Card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-lg">
                            <User class="size-5" />
                            Primary Contact
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Name</dt>
                            <dd class="text-sm">{{ datacenter.primary_contact_name }}</dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Email</dt>
                            <dd class="text-sm">
                                <a
                                    :href="`mailto:${datacenter.primary_contact_email}`"
                                    class="flex items-center gap-1 text-primary hover:underline"
                                >
                                    <Mail class="size-4" />
                                    {{ datacenter.primary_contact_email }}
                                </a>
                            </dd>
                        </div>
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Phone</dt>
                            <dd class="text-sm">
                                <a
                                    :href="`tel:${datacenter.primary_contact_phone}`"
                                    class="flex items-center gap-1 text-primary hover:underline"
                                >
                                    <Phone class="size-4" />
                                    {{ datacenter.primary_contact_phone }}
                                </a>
                            </dd>
                        </div>
                    </CardContent>
                </Card>

                <!-- Secondary Contact Card (if present) -->
                <Card v-if="datacenter.secondary_contact_name">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-lg">
                            <User class="size-5" />
                            Secondary Contact
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Name</dt>
                            <dd class="text-sm">{{ datacenter.secondary_contact_name }}</dd>
                        </div>
                        <div v-if="datacenter.secondary_contact_email" class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Email</dt>
                            <dd class="text-sm">
                                <a
                                    :href="`mailto:${datacenter.secondary_contact_email}`"
                                    class="flex items-center gap-1 text-primary hover:underline"
                                >
                                    <Mail class="size-4" />
                                    {{ datacenter.secondary_contact_email }}
                                </a>
                            </dd>
                        </div>
                        <div v-if="datacenter.secondary_contact_phone" class="grid gap-2">
                            <dt class="text-sm font-medium text-muted-foreground">Phone</dt>
                            <dd class="text-sm">
                                <a
                                    :href="`tel:${datacenter.secondary_contact_phone}`"
                                    class="flex items-center gap-1 text-primary hover:underline"
                                >
                                    <Phone class="size-4" />
                                    {{ datacenter.secondary_contact_phone }}
                                </a>
                            </dd>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Rooms Section -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <DoorOpen class="size-5" />
                        Rooms
                    </CardTitle>
                    <Link :href="roomsIndex.url(datacenter.id)">
                        <Button variant="outline" size="sm" class="gap-1">
                            Manage Rooms
                            <ChevronRight class="size-4" />
                        </Button>
                    </Link>
                </CardHeader>
                <CardContent>
                    <p class="text-sm text-muted-foreground">
                        View and manage rooms, rows, and PDUs within this datacenter.
                    </p>
                </CardContent>
            </Card>

            <!-- Implementation Files Section -->
            <ImplementationFileCard
                :files="implementationFiles"
                :can-upload="canUploadFiles"
                :can-delete="canDeleteFiles"
                :datacenter-id="datacenter.id"
            />

            <!-- Floor Plan Section -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-lg">Floor Plan</CardTitle>
                </CardHeader>
                <CardContent>
                    <FloorPlanDisplay
                        :floor-plan-url="datacenter.floor_plan_url"
                        :datacenter-name="datacenter.name"
                    />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
