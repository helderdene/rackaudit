<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { FileSearch, AlertCircle } from 'lucide-vue-next';
import { parse } from '@/actions/App/Http/Controllers/ParseConnectionsController';
import axios from 'axios';

interface Props {
    implementationFileId: number;
    datacenterId: number;
    mimeType: string;
    isApproved: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'parse-complete', result: ParseResult): void;
    (e: 'parse-error', error: string): void;
}>();

interface ParseResult {
    success: boolean;
    connections?: Array<{
        id: number;
        row_number: number;
        source_device: { id: number | null; original: string; matched_name: string | null; confidence: number; match_type: string };
        source_port: { id: number | null; original: string; matched_label: string | null; confidence: number; match_type: string };
        dest_device: { id: number | null; original: string; matched_name: string | null; confidence: number; match_type: string };
        dest_port: { id: number | null; original: string; matched_label: string | null; confidence: number; match_type: string };
    }>;
    statistics?: {
        total: number;
        exact: number;
        suggested: number;
        unrecognized: number;
    };
    error?: string;
}

const isParsing = ref(false);
const parseError = ref<string | null>(null);
const isDialogOpen = ref(false);

/**
 * Check if the file type supports connection parsing
 * Only Excel (.xlsx, .xls) and CSV files can be parsed
 */
const isParseable = (): boolean => {
    const parseableMimeTypes = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // xlsx
        'application/vnd.ms-excel', // xls
        'text/csv', // csv
    ];
    return parseableMimeTypes.includes(props.mimeType);
};

/**
 * Handle the parse action
 */
const handleParse = async () => {
    if (!isParseable() || !props.isApproved) {
        return;
    }

    isParsing.value = true;
    parseError.value = null;

    try {
        const response = await axios.post(parse.url(props.implementationFileId));

        if (response.data.success) {
            emit('parse-complete', response.data);
            isDialogOpen.value = false;
            // Navigate to review page
            router.visit(`/expected-connections/review?implementation_file=${props.implementationFileId}`);
        } else {
            parseError.value = response.data.error || 'Failed to parse the file.';
            emit('parse-error', parseError.value);
        }
    } catch (error: unknown) {
        let errorMessage = 'An error occurred while parsing the file.';

        if (axios.isAxiosError(error) && error.response?.data?.error) {
            errorMessage = error.response.data.error;
        } else if (axios.isAxiosError(error) && error.response?.data?.message) {
            errorMessage = error.response.data.message;
        } else if (error instanceof Error) {
            errorMessage = error.message;
        }

        parseError.value = errorMessage;
        emit('parse-error', errorMessage);
    } finally {
        isParsing.value = false;
    }
};
</script>

<template>
    <div v-if="isParseable() && isApproved">
        <Dialog v-model:open="isDialogOpen">
            <DialogTrigger as-child>
                <Button
                    variant="outline"
                    size="sm"
                    class="gap-1.5"
                    :disabled="isParsing"
                >
                    <Spinner v-if="isParsing" class="size-3.5" />
                    <FileSearch v-else class="size-3.5" />
                    {{ isParsing ? 'Parsing...' : 'Parse Connections' }}
                </Button>
            </DialogTrigger>

            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Parse Connection Data</DialogTitle>
                    <DialogDescription>
                        This will parse the file to extract connection data. The parser will attempt to
                        match device and port names against existing records in the database.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-3">
                    <div class="rounded-lg border bg-muted/30 p-3 text-sm">
                        <p class="font-medium">What happens next:</p>
                        <ul class="mt-2 list-inside list-disc space-y-1 text-muted-foreground">
                            <li>File will be scanned for connection data</li>
                            <li>Devices and ports will be matched automatically</li>
                            <li>You can review and correct matches before finalizing</li>
                        </ul>
                    </div>

                    <!-- Error message -->
                    <div
                        v-if="parseError"
                        class="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400"
                    >
                        <AlertCircle class="mt-0.5 size-4 shrink-0" />
                        <span>{{ parseError }}</span>
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" :disabled="isParsing">Cancel</Button>
                    </DialogClose>
                    <Button
                        :disabled="isParsing"
                        @click="handleParse"
                    >
                        <Spinner v-if="isParsing" class="mr-2 size-4" />
                        {{ isParsing ? 'Parsing...' : 'Start Parsing' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
