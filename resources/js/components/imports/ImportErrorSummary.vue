<script setup lang="ts">
import { computed } from 'vue';
import { downloadErrors } from '@/actions/App/Http/Controllers/BulkImportController';
import { Button } from '@/components/ui/button';
import { AlertTriangle, Download, FileWarning } from 'lucide-vue-next';

interface Props {
    importId: number;
    failureCount: number;
    hasErrorReport: boolean;
}

const props = defineProps<Props>();

const errorReportUrl = computed(() => downloadErrors.url(props.importId));

const handleDownload = () => {
    // Open download in new tab/window to handle the file download
    window.open(errorReportUrl.value, '_blank');
};
</script>

<template>
    <div
        v-if="failureCount > 0"
        class="rounded-lg border border-destructive/30 bg-destructive/5 p-4"
    >
        <div class="flex items-start gap-3">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-destructive/10 text-destructive">
                <AlertTriangle class="h-5 w-5" />
            </div>
            <div class="flex-1 space-y-3">
                <div>
                    <h4 class="font-medium text-destructive">
                        {{ failureCount }} row{{ failureCount !== 1 ? 's' : '' }} failed to import
                    </h4>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Some rows in your import file could not be processed due to validation errors.
                        Download the error report to see detailed information about each failed row.
                    </p>
                </div>

                <Button
                    v-if="hasErrorReport"
                    variant="outline"
                    size="sm"
                    @click="handleDownload"
                >
                    <Download class="mr-2 h-4 w-4" />
                    Download Error Report
                </Button>

                <div v-else class="flex items-center gap-2 text-sm text-muted-foreground">
                    <FileWarning class="h-4 w-4" />
                    <span>Error report not available or has expired</span>
                </div>
            </div>
        </div>
    </div>
</template>
