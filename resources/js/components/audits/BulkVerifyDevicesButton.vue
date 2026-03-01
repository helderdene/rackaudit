<script setup lang="ts">
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
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
import { Spinner } from '@/components/ui/spinner';
import { CheckCircle, AlertTriangle, Server } from 'lucide-vue-next';

interface Props {
    selectedCount: number;
    verifiableCount: number;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'bulk-verify'): Promise<{ verified: number; skipped: number }>;
}>();

// State
const dialogOpen = ref(false);
const isLoading = ref(false);
const result = ref<{ verified: number; skipped: number } | null>(null);
const error = ref<string | null>(null);

/**
 * Handle bulk verify action
 */
async function handleBulkVerify(): Promise<void> {
    isLoading.value = true;
    error.value = null;
    result.value = null;

    try {
        const response = await emit('bulk-verify');
        result.value = response;
    } catch (err) {
        console.error('Bulk verify error:', err);
        error.value = 'Failed to process bulk verification.';
    } finally {
        isLoading.value = false;
    }
}

/**
 * Close dialog and reset state
 */
function handleClose(): void {
    dialogOpen.value = false;
    // Reset after animation
    setTimeout(() => {
        result.value = null;
        error.value = null;
    }, 200);
}
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <DialogTrigger as-child>
            <Button
                size="sm"
                :disabled="verifiableCount === 0"
                class="bg-green-600 hover:bg-green-700"
            >
                <CheckCircle class="mr-1 size-3.5" />
                Bulk Verify ({{ verifiableCount }})
            </Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Bulk Verify Devices</DialogTitle>
                <DialogDescription>
                    This will mark all selected pending devices as verified.
                </DialogDescription>
            </DialogHeader>

            <div class="py-4">
                <!-- Before action -->
                <div v-if="!result && !error" class="space-y-4">
                    <div class="rounded-lg border bg-muted/30 p-4 text-sm">
                        <div class="flex items-center gap-2">
                            <Server class="size-5 text-green-600" />
                            <span>
                                <strong>{{ verifiableCount }}</strong>
                                {{ verifiableCount === 1 ? 'device' : 'devices' }}
                                will be marked as verified.
                            </span>
                        </div>
                        <div v-if="selectedCount !== verifiableCount" class="mt-2 flex items-center gap-2 text-amber-600 dark:text-amber-400">
                            <AlertTriangle class="size-4" />
                            <span>
                                {{ selectedCount - verifiableCount }}
                                {{ selectedCount - verifiableCount === 1 ? 'device' : 'devices' }}
                                will be skipped (already verified or locked).
                            </span>
                        </div>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Only devices with "Pending" verification status that are not locked by another user can be bulk verified.
                    </p>
                </div>

                <!-- Success result -->
                <div
                    v-else-if="result"
                    class="space-y-4"
                >
                    <div class="flex items-center gap-3 rounded-lg border border-green-200 bg-green-50 p-4 text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400">
                        <CheckCircle class="size-6" />
                        <div>
                            <div class="font-medium">Bulk Verification Complete</div>
                            <div class="text-sm">
                                {{ result.verified }}
                                {{ result.verified === 1 ? 'device' : 'devices' }}
                                verified successfully.
                            </div>
                        </div>
                    </div>
                    <div v-if="result.skipped > 0" class="flex items-center gap-2 text-sm text-amber-600 dark:text-amber-400">
                        <AlertTriangle class="size-4" />
                        <span>{{ result.skipped }} devices were skipped (locked by another user).</span>
                    </div>
                </div>

                <!-- Error result -->
                <div
                    v-else-if="error"
                    class="flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400"
                >
                    <AlertTriangle class="size-6" />
                    <div>
                        <div class="font-medium">Bulk Verification Failed</div>
                        <div class="text-sm">{{ error }}</div>
                    </div>
                </div>
            </div>

            <DialogFooter class="gap-2">
                <template v-if="!result && !error">
                    <DialogClose as-child>
                        <Button variant="secondary" :disabled="isLoading">Cancel</Button>
                    </DialogClose>
                    <Button
                        :disabled="isLoading || verifiableCount === 0"
                        class="bg-green-600 hover:bg-green-700"
                        @click="handleBulkVerify"
                    >
                        <Spinner v-if="isLoading" class="mr-2 size-4" />
                        <CheckCircle v-else class="mr-1 size-4" />
                        Confirm Bulk Verify
                    </Button>
                </template>
                <template v-else>
                    <Button @click="handleClose">Done</Button>
                </template>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
