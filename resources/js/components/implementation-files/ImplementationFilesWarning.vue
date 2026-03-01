<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { AlertTriangle } from 'lucide-vue-next';

interface Props {
    /**
     * Whether the datacenter has approved implementation files.
     * When false, the warning will be displayed.
     */
    hasApprovedFiles: boolean;
}

defineProps<Props>();
</script>

<template>
    <Alert v-if="!hasApprovedFiles" variant="warning">
        <AlertTriangle class="size-4" />
        <AlertTitle>No Approved Implementation Files</AlertTitle>
        <AlertDescription>
            This datacenter has no approved implementation files. Audits require approved
            implementation files as the authoritative source for expected connections.
        </AlertDescription>
    </Alert>
</template>
