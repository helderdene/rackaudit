<script setup lang="ts">
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { FileText, FileSpreadsheet, FileJson, Loader2 } from 'lucide-vue-next';
import { exportPdf, exportCsv, exportJson } from '@/actions/App/Http/Controllers/CustomReportBuilderController';

/**
 * Report configuration structure for export requests
 */
interface ReportConfig {
    report_type: string;
    columns: string[];
    filters: Record<string, unknown>;
    sort: Array<{ column: string; direction: 'asc' | 'desc' }>;
    group_by?: string | null;
}

interface Props {
    reportConfig: ReportConfig;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
});

// Track individual loading states for each button
const pdfLoading = ref(false);
const csvLoading = ref(false);
const jsonLoading = ref(false);

/**
 * Create a hidden form and submit it to trigger a file download
 * This approach is needed for POST requests that return file downloads
 */
function submitExportForm(url: string, method: string = 'POST'): void {
    const form = document.createElement('form');
    form.method = method;
    form.action = url;
    form.style.display = 'none';

    // Add CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (csrfToken) {
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = csrfToken;
        form.appendChild(csrfInput);
    }

    // Add report configuration fields
    const configFields: Record<string, unknown> = {
        report_type: props.reportConfig.report_type,
        columns: props.reportConfig.columns,
        filters: props.reportConfig.filters,
        sort: props.reportConfig.sort,
        group_by: props.reportConfig.group_by,
    };

    for (const [key, value] of Object.entries(configFields)) {
        if (value === null || value === undefined) {
            continue;
        }

        if (Array.isArray(value)) {
            // Handle arrays (columns, sort)
            value.forEach((item, index) => {
                if (typeof item === 'object') {
                    // Handle sort array of objects
                    for (const [subKey, subValue] of Object.entries(item)) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = `${key}[${index}][${subKey}]`;
                        input.value = String(subValue);
                        form.appendChild(input);
                    }
                } else {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `${key}[${index}]`;
                    input.value = String(item);
                    form.appendChild(input);
                }
            });
        } else if (typeof value === 'object') {
            // Handle filters object
            for (const [subKey, subValue] of Object.entries(value as Record<string, unknown>)) {
                if (subValue !== null && subValue !== undefined && subValue !== '') {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `${key}[${subKey}]`;
                    input.value = String(subValue);
                    form.appendChild(input);
                }
            }
        } else {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = String(value);
            form.appendChild(input);
        }
    }

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

/**
 * Handle PDF export click
 */
async function handlePdfExport(): Promise<void> {
    pdfLoading.value = true;

    try {
        submitExportForm(exportPdf.url());
    } finally {
        // Reset loading state after a delay to show visual feedback
        setTimeout(() => {
            pdfLoading.value = false;
        }, 2000);
    }
}

/**
 * Handle CSV export click
 */
async function handleCsvExport(): Promise<void> {
    csvLoading.value = true;

    try {
        submitExportForm(exportCsv.url());
    } finally {
        // Reset loading state after a delay to show visual feedback
        setTimeout(() => {
            csvLoading.value = false;
        }, 2000);
    }
}

/**
 * Handle JSON export click using fetch for inline response
 */
async function handleJsonExport(): Promise<void> {
    jsonLoading.value = true;

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        const response = await fetch(exportJson.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken || '',
            },
            body: JSON.stringify({
                report_type: props.reportConfig.report_type,
                columns: props.reportConfig.columns,
                filters: props.reportConfig.filters,
                sort: props.reportConfig.sort,
                group_by: props.reportConfig.group_by,
            }),
        });

        if (!response.ok) {
            throw new Error('Export failed');
        }

        const data = await response.json();

        // Create a download from the JSON response
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `custom-report-${props.reportConfig.report_type}-${new Date().toISOString().slice(0, 10)}.json`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    } catch (error) {
        console.error('JSON export failed:', error);
    } finally {
        jsonLoading.value = false;
    }
}
</script>

<template>
    <div class="flex flex-wrap gap-2">
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

        <!-- JSON Export Button -->
        <Button
            variant="outline"
            size="sm"
            :disabled="loading || jsonLoading"
            @click="handleJsonExport"
        >
            <Loader2 v-if="jsonLoading" class="mr-1 size-4 animate-spin" />
            <FileJson v-else class="mr-1 size-4" />
            JSON
        </Button>
    </div>
</template>
