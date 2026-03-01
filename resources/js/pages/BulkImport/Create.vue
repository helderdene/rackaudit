<script setup lang="ts">
import { store } from '@/actions/App/Http/Controllers/BulkImportController';
import {
    download,
    downloadCombined,
} from '@/actions/App/Http/Controllers/TemplateDownloadController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import FileDropzone from '@/components/imports/FileDropzone.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { SelectOption } from '@/types/rooms';
import { Head, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    Building2,
    Cable,
    Download,
    FileSpreadsheet,
    HardDrive,
    Layers,
    LayoutGrid,
    Loader2,
    Server,
    Upload,
} from 'lucide-vue-next';
import { ref } from 'vue';

interface Props {
    entityTypeOptions: SelectOption[];
    maxFileSizeMB: number;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Imports',
        href: '/imports',
    },
    {
        title: 'New Import',
        href: '/imports/create',
    },
];

// Form state
const selectedFile = ref<File | null>(null);
const selectedEntityType = ref<string>('');
const isSubmitting = ref(false);
const submitError = ref<string | null>(null);

// Template types with their icons
const templateTypes = [
    { value: 'datacenter', label: 'Datacenters', icon: Building2 },
    { value: 'room', label: 'Rooms', icon: LayoutGrid },
    { value: 'row', label: 'Rows', icon: Layers },
    { value: 'rack', label: 'Racks', icon: Server },
    { value: 'device', label: 'Devices', icon: HardDrive },
    { value: 'port', label: 'Ports', icon: Cable },
];

const handleFileSelected = (file: File) => {
    selectedFile.value = file;
    submitError.value = null;
};

const handleFileRemoved = () => {
    selectedFile.value = null;
};

const handleValidationError = (message: string) => {
    submitError.value = message;
};

const handleSubmit = async () => {
    if (!selectedFile.value || isSubmitting.value) return;

    isSubmitting.value = true;
    submitError.value = null;

    const formData = new FormData();
    formData.append('file', selectedFile.value);

    if (selectedEntityType.value) {
        formData.append('entity_type', selectedEntityType.value);
    }

    router.post(store.url(), formData, {
        forceFormData: true,
        onSuccess: () => {
            // Redirect is handled by the controller
        },
        onError: (errors) => {
            if (errors.file) {
                submitError.value = errors.file as string;
            } else {
                submitError.value =
                    'An error occurred while uploading the file.';
            }
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const handleDownloadTemplate = (entityType: string) => {
    window.open(download.url(entityType), '_blank');
};

const handleDownloadCombinedTemplate = () => {
    window.open(downloadCombined.url(), '_blank');
};
</script>

<template>
    <Head title="New Import" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Header -->
            <HeadingSmall
                title="New Bulk Import"
                description="Upload a CSV or XLSX file to import datacenter infrastructure data."
            />

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Upload Section -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Upload class="h-5 w-5" />
                            Upload File
                        </CardTitle>
                        <CardDescription>
                            Select a CSV or XLSX file containing your import
                            data. Maximum file size: {{ maxFileSizeMB }}MB.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <!-- File dropzone -->
                        <FileDropzone
                            :accepted-types="['.csv', '.xlsx']"
                            :max-size-m-b="maxFileSizeMB"
                            :disabled="isSubmitting"
                            @file-selected="handleFileSelected"
                            @file-removed="handleFileRemoved"
                            @validation-error="handleValidationError"
                        />

                        <!-- Entity type selector -->
                        <div class="space-y-2">
                            <Label for="entity-type"
                                >Entity Type (Optional)</Label
                            >
                            <select
                                id="entity-type"
                                v-model="selectedEntityType"
                                :disabled="isSubmitting"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option value="">Auto-detect from file</option>
                                <option
                                    v-for="option in entityTypeOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <p class="text-xs text-muted-foreground">
                                If not specified, the entity type will be
                                detected based on the file columns.
                            </p>
                        </div>

                        <!-- Submit error -->
                        <div
                            v-if="submitError"
                            class="flex items-center gap-2 rounded-md bg-destructive/10 p-3 text-sm text-destructive"
                        >
                            <AlertCircle class="h-4 w-4 shrink-0" />
                            <span>{{ submitError }}</span>
                        </div>

                        <!-- Submit button -->
                        <Button
                            :disabled="!selectedFile || isSubmitting"
                            class="w-full"
                            @click="handleSubmit"
                        >
                            <Loader2
                                v-if="isSubmitting"
                                class="mr-2 h-4 w-4 animate-spin"
                            />
                            <Upload v-else class="mr-2 h-4 w-4" />
                            {{ isSubmitting ? 'Uploading...' : 'Start Import' }}
                        </Button>
                    </CardContent>
                </Card>

                <!-- Templates Section -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Download class="h-5 w-5" />
                            Download Templates
                        </CardTitle>
                        <CardDescription>
                            Download pre-formatted templates with example data
                            and dropdown validation for each entity type.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <!-- Combined template button -->
                        <Button
                            variant="outline"
                            class="w-full justify-start"
                            @click="handleDownloadCombinedTemplate"
                        >
                            <FileSpreadsheet class="mr-2 h-4 w-4" />
                            Combined Template (All Entities)
                        </Button>

                        <div class="relative">
                            <div class="absolute inset-0 flex items-center">
                                <span class="w-full border-t" />
                            </div>
                            <div
                                class="relative flex justify-center text-xs uppercase"
                            >
                                <span class="bg-card px-2 text-muted-foreground"
                                    >Or download individual templates</span
                                >
                            </div>
                        </div>

                        <!-- Individual template buttons -->
                        <div class="grid gap-2 sm:grid-cols-2">
                            <Button
                                v-for="template in templateTypes"
                                :key="template.value"
                                variant="ghost"
                                class="justify-start"
                                @click="handleDownloadTemplate(template.value)"
                            >
                                <component
                                    :is="template.icon"
                                    class="mr-2 h-4 w-4"
                                />
                                {{ template.label }}
                            </Button>
                        </div>

                        <!-- Help text -->
                        <div
                            class="rounded-lg bg-muted/50 p-4 text-sm text-muted-foreground"
                        >
                            <h4 class="mb-2 font-medium text-foreground">
                                Template Tips
                            </h4>
                            <ul class="list-inside list-disc space-y-1">
                                <li>
                                    Templates include example data and required
                                    field markers
                                </li>
                                <li>
                                    Dropdown fields have pre-populated valid
                                    values
                                </li>
                                <li>
                                    Parent entities are referenced by name
                                    (e.g., datacenter_name)
                                </li>
                                <li>
                                    Delete example rows before importing your
                                    data
                                </li>
                            </ul>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
