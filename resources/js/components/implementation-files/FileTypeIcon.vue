<script setup lang="ts">
import { cn } from '@/lib/utils';
import { File, FileSpreadsheet, FileText, FileType } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    mimeType: string;
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    class: '',
});

/**
 * Determines the appropriate icon component based on MIME type
 */
const iconComponent = computed(() => {
    const mimeType = props.mimeType;

    // PDF files
    if (mimeType === 'application/pdf') {
        return FileText;
    }

    // Excel/Spreadsheet files
    if (
        mimeType ===
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ||
        mimeType === 'application/vnd.ms-excel' ||
        mimeType === 'text/csv'
    ) {
        return FileSpreadsheet;
    }

    // Word documents
    if (
        mimeType ===
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ) {
        return FileType;
    }

    // Text files
    if (mimeType === 'text/plain') {
        return FileText;
    }

    // Default fallback
    return File;
});

/**
 * Determines the icon color based on MIME type
 */
const iconColorClass = computed(() => {
    const mimeType = props.mimeType;

    // PDF files - red
    if (mimeType === 'application/pdf') {
        return 'text-red-600 dark:text-red-400';
    }

    // Excel/CSV files - green
    if (
        mimeType ===
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ||
        mimeType === 'application/vnd.ms-excel' ||
        mimeType === 'text/csv'
    ) {
        return 'text-green-600 dark:text-green-400';
    }

    // Word documents - blue
    if (
        mimeType ===
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ) {
        return 'text-blue-600 dark:text-blue-400';
    }

    // Text files - gray
    if (mimeType === 'text/plain') {
        return 'text-gray-600 dark:text-gray-400';
    }

    // Default - gray
    return 'text-gray-500 dark:text-gray-400';
});
</script>

<template>
    <component
        :is="iconComponent"
        :class="cn('size-5 shrink-0', iconColorClass, props.class)"
    />
</template>
