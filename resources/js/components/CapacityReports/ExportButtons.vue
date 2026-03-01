<script setup lang="ts">
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { FileText, FileSpreadsheet, Loader2 } from 'lucide-vue-next';

interface Props {
    pdfUrl: string;
    csvUrl: string;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
});

// Track individual loading states for each button
const pdfLoading = ref(false);
const csvLoading = ref(false);

/**
 * Handle PDF export click
 */
const handlePdfExport = async () => {
    pdfLoading.value = true;

    // Create a temporary link and trigger download
    const link = document.createElement('a');
    link.href = props.pdfUrl;
    link.target = '_blank';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    // Reset loading state after a short delay
    setTimeout(() => {
        pdfLoading.value = false;
    }, 2000);
};

/**
 * Handle CSV export click
 */
const handleCsvExport = async () => {
    csvLoading.value = true;

    // Create a temporary link and trigger download
    const link = document.createElement('a');
    link.href = props.csvUrl;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    // Reset loading state after a short delay
    setTimeout(() => {
        csvLoading.value = false;
    }, 2000);
};
</script>

<template>
    <div class="flex gap-2">
        <!-- PDF Export Button -->
        <Button
            variant="outline"
            size="sm"
            :disabled="loading || pdfLoading"
            @click="handlePdfExport"
        >
            <Loader2 v-if="pdfLoading" class="mr-1 size-4 animate-spin" />
            <FileText v-else class="mr-1 size-4" />
            PDF
        </Button>

        <!-- CSV Export Button -->
        <Button
            variant="outline"
            size="sm"
            :disabled="loading || csvLoading"
            @click="handleCsvExport"
        >
            <Loader2 v-if="csvLoading" class="mr-1 size-4 animate-spin" />
            <FileSpreadsheet v-else class="mr-1 size-4" />
            CSV
        </Button>
    </div>
</template>
