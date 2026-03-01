<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import type { FindingStatusValue } from '@/types/finding';
import {
    CheckCircle,
    Loader2,
    Pause,
    Play,
    RotateCcw,
    Send,
} from 'lucide-vue-next';
import { ref } from 'vue';

interface QuickAction {
    action: string;
    label: string;
    status: FindingStatusValue;
    variant: string;
    requires_notes?: boolean;
}

interface Props {
    currentStatus: FindingStatusValue;
    isAdmin: boolean;
    quickActions: QuickAction[];
    processing?: boolean;
}

interface Emits {
    (e: 'transition', targetStatus: FindingStatusValue, notes?: string): void;
}

withDefaults(defineProps<Props>(), {
    processing: false,
});
const emit = defineEmits<Emits>();

// Dialog state for actions requiring notes
const showNotesDialog = ref(false);
const pendingAction = ref<QuickAction | null>(null);
const resolutionNotes = ref('');
const notesError = ref('');

// Get icon for action
const getActionIcon = (action: string) => {
    switch (action) {
        case 'start_working':
            return Play;
        case 'submit_for_review':
            return Send;
        case 'approve_and_close':
            return CheckCircle;
        case 'defer':
            return Pause;
        case 'reopen':
            return RotateCcw;
        default:
            return Play;
    }
};

// Get button variant based on action variant string
const getButtonVariant = (
    variant: string,
): 'default' | 'secondary' | 'destructive' | 'outline' | 'ghost' | 'link' => {
    switch (variant) {
        case 'primary':
            return 'default';
        case 'success':
            return 'default';
        case 'warning':
            return 'secondary';
        case 'secondary':
            return 'secondary';
        default:
            return 'outline';
    }
};

// Get additional button classes for success variant
const getAdditionalClasses = (variant: string): string => {
    if (variant === 'success') {
        return 'bg-green-600 hover:bg-green-700 dark:bg-green-600 dark:hover:bg-green-700';
    }
    if (variant === 'warning') {
        return 'bg-amber-500 hover:bg-amber-600 text-white dark:bg-amber-600 dark:hover:bg-amber-700';
    }
    return '';
};

// Handle action button click
const handleActionClick = (action: QuickAction) => {
    if (action.requires_notes) {
        pendingAction.value = action;
        resolutionNotes.value = '';
        notesError.value = '';
        showNotesDialog.value = true;
    } else {
        emit('transition', action.status);
    }
};

// Handle dialog confirmation
const handleConfirmWithNotes = () => {
    if (!pendingAction.value) return;

    // Validate minimum length
    if (resolutionNotes.value.length < 10) {
        notesError.value = 'Resolution notes must be at least 10 characters.';
        return;
    }

    emit('transition', pendingAction.value.status, resolutionNotes.value);
    showNotesDialog.value = false;
    pendingAction.value = null;
    resolutionNotes.value = '';
    notesError.value = '';
};

// Handle dialog cancel
const handleCancel = () => {
    showNotesDialog.value = false;
    pendingAction.value = null;
    resolutionNotes.value = '';
    notesError.value = '';
};

// Clear error when notes change
const handleNotesInput = () => {
    if (resolutionNotes.value.length >= 10) {
        notesError.value = '';
    }
};
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <Button
            v-for="action in quickActions"
            :key="action.action"
            :variant="getButtonVariant(action.variant)"
            :class="getAdditionalClasses(action.variant)"
            :disabled="processing"
            size="sm"
            @click="handleActionClick(action)"
        >
            <Loader2 v-if="processing" class="mr-1 size-4 animate-spin" />
            <component
                v-else
                :is="getActionIcon(action.action)"
                class="mr-1 size-4"
            />
            {{ action.label }}
        </Button>

        <!-- No actions available message -->
        <p
            v-if="quickActions.length === 0"
            class="text-sm text-muted-foreground"
        >
            No quick actions available for current status.
        </p>

        <!-- Resolution Notes Dialog -->
        <Dialog :open="showNotesDialog" @update:open="handleCancel">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Resolution Notes Required</DialogTitle>
                    <DialogDescription>
                        Please provide resolution notes to complete this
                        finding.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium"
                            >Resolution Notes</label
                        >
                        <Textarea
                            v-model="resolutionNotes"
                            placeholder="Describe how this finding was resolved..."
                            rows="4"
                            :class="{ 'border-destructive': notesError }"
                            @input="handleNotesInput"
                        />
                        <div class="flex justify-between text-xs">
                            <span v-if="notesError" class="text-destructive">
                                {{ notesError }}
                            </span>
                            <span v-else class="text-muted-foreground">
                                Minimum 10 characters required
                            </span>
                            <span
                                :class="
                                    resolutionNotes.length < 10
                                        ? 'text-muted-foreground'
                                        : 'text-green-600'
                                "
                            >
                                {{ resolutionNotes.length }} characters
                            </span>
                        </div>
                    </div>
                </div>

                <DialogFooter class="gap-2 sm:gap-0">
                    <Button variant="outline" @click="handleCancel">
                        Cancel
                    </Button>
                    <Button
                        :disabled="resolutionNotes.length < 10 || processing"
                        class="bg-green-600 hover:bg-green-700"
                        @click="handleConfirmWithNotes"
                    >
                        <Loader2
                            v-if="processing"
                            class="mr-1 size-4 animate-spin"
                        />
                        <CheckCircle v-else class="mr-1 size-4" />
                        Approve & Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
