<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { router } from '@inertiajs/vue3';
import { FileText } from 'lucide-vue-next';
import ImplementationFileList, {
    type ImplementationFile,
} from './ImplementationFileList.vue';
import UploadImplementationFileDialog from './UploadImplementationFileDialog.vue';

interface Props {
    files: ImplementationFile[];
    canUpload: boolean;
    canDelete: boolean;
    datacenterId: number;
}

defineProps<Props>();

/**
 * Refresh the page to get updated file list after upload
 */
const handleFileUploaded = () => {
    router.reload({ only: ['implementationFiles'] });
};

/**
 * Refresh the page to get updated file list after deletion
 */
const handleFileDeleted = () => {
    router.reload({ only: ['implementationFiles'] });
};
</script>

<template>
    <Card>
        <CardHeader class="flex flex-row items-center justify-between">
            <CardTitle class="flex items-center gap-2 text-lg">
                <FileText class="size-5" />
                Implementation Files
            </CardTitle>
            <UploadImplementationFileDialog
                v-if="canUpload"
                :datacenter-id="datacenterId"
                @file-uploaded="handleFileUploaded"
            />
        </CardHeader>
        <CardContent>
            <ImplementationFileList
                :files="files"
                :can-upload="canUpload"
                :can-delete="canDelete"
                :datacenter-id="datacenterId"
                @file-deleted="handleFileDeleted"
            />
        </CardContent>
    </Card>
</template>
