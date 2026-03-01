<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Play, ChevronDown, Loader2, CheckCircle, AlertTriangle, Building2 } from 'lucide-vue-next';

interface DatacenterOption {
    id: number;
    name: string;
}

interface Props {
    datacenters: DatacenterOption[];
}

const props = defineProps<Props>();

// State
const isDetecting = ref(false);
const showSuccess = ref(false);
const showError = ref(false);
const errorMessage = ref('');
const selectedScope = ref<string | null>(null);

// Get CSRF token from cookies
const getCsrfToken = (): string => {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
};

// Run detection for all datacenters
const runDetectionAll = async () => {
    if (props.datacenters.length === 0) {
        errorMessage.value = 'No datacenters available for detection.';
        showError.value = true;
        return;
    }

    selectedScope.value = 'All Datacenters';
    await runDetection(props.datacenters[0].id); // For now, run for first datacenter
    // In a real implementation, you might want to run for all or have a separate "all" endpoint
};

// Run detection for a specific datacenter
const runDetectionForDatacenter = async (datacenter: DatacenterOption) => {
    selectedScope.value = datacenter.name;
    await runDetection(datacenter.id);
};

// Run detection
const runDetection = async (datacenterId: number) => {
    isDetecting.value = true;
    showSuccess.value = false;
    showError.value = false;

    try {
        const response = await fetch('/api/discrepancies/detect', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                datacenter_id: datacenterId,
            }),
        });

        if (!response.ok) {
            const data = await response.json();
            throw new Error(data.message || 'Failed to start detection');
        }

        showSuccess.value = true;

        // Auto-hide success message and refresh after delay
        setTimeout(() => {
            showSuccess.value = false;
            router.reload({ preserveScroll: true });
        }, 3000);
    } catch (e) {
        errorMessage.value = e instanceof Error ? e.message : 'An error occurred';
        showError.value = true;

        // Auto-hide error after delay
        setTimeout(() => {
            showError.value = false;
        }, 5000);
    } finally {
        isDetecting.value = false;
    }
};
</script>

<template>
    <div class="relative">
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button :disabled="isDetecting" class="gap-2">
                    <Loader2 v-if="isDetecting" class="size-4 animate-spin" />
                    <Play v-else class="size-4" />
                    <span class="hidden sm:inline">Run Detection</span>
                    <ChevronDown class="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-56">
                <DropdownMenuLabel>Select Scope</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    v-if="datacenters.length > 1"
                    @click="runDetectionAll"
                    class="cursor-pointer"
                >
                    <Building2 class="mr-2 size-4" />
                    All Datacenters
                </DropdownMenuItem>
                <DropdownMenuSeparator v-if="datacenters.length > 1" />
                <DropdownMenuItem
                    v-for="dc in datacenters"
                    :key="dc.id"
                    @click="runDetectionForDatacenter(dc)"
                    class="cursor-pointer"
                >
                    <Building2 class="mr-2 size-4" />
                    {{ dc.name }}
                </DropdownMenuItem>
                <div v-if="datacenters.length === 0" class="px-2 py-1.5 text-sm text-muted-foreground">
                    No datacenters available
                </div>
            </DropdownMenuContent>
        </DropdownMenu>

        <!-- Success/Error Feedback -->
        <div
            v-if="showSuccess || showError"
            class="absolute right-0 top-full z-50 mt-2 w-64"
        >
            <Alert v-if="showSuccess" class="border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950">
                <CheckCircle class="size-4 text-green-600 dark:text-green-400" />
                <AlertDescription class="text-green-600 dark:text-green-400">
                    Detection started for {{ selectedScope }}.
                    <span class="block text-xs text-green-500">Results will appear shortly...</span>
                </AlertDescription>
            </Alert>
            <Alert v-if="showError" variant="destructive">
                <AlertTriangle class="size-4" />
                <AlertDescription>{{ errorMessage }}</AlertDescription>
            </Alert>
        </div>

        <!-- Loading Overlay -->
        <div
            v-if="isDetecting"
            class="absolute right-0 top-full z-50 mt-2 flex w-64 items-center gap-2 rounded-md border bg-background p-3 shadow-lg"
        >
            <Loader2 class="size-4 animate-spin text-primary" />
            <div class="text-sm">
                <div class="font-medium">Running Detection</div>
                <div class="text-xs text-muted-foreground">{{ selectedScope }}</div>
            </div>
        </div>
    </div>
</template>
