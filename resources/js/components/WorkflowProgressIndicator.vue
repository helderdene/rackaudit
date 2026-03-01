<script setup lang="ts">
import type { FindingStatusValue } from '@/types/finding';
import { CheckCircle, Circle, Clock, Pause, RotateCcw } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    currentStatus: FindingStatusValue;
}

const props = defineProps<Props>();

// Define workflow steps in order
const workflowSteps = [
    { status: 'open' as FindingStatusValue, label: 'Open', icon: Circle },
    {
        status: 'in_progress' as FindingStatusValue,
        label: 'In Progress',
        icon: Clock,
    },
    {
        status: 'pending_review' as FindingStatusValue,
        label: 'Pending Review',
        icon: RotateCcw,
    },
    {
        status: 'resolved' as FindingStatusValue,
        label: 'Resolved',
        icon: CheckCircle,
    },
];

// Get the index of the current status in the workflow
const currentStepIndex = computed(() => {
    return workflowSteps.findIndex(
        (step) => step.status === props.currentStatus,
    );
});

// Check if status is deferred (shown as side branch)
const isDeferred = computed(() => props.currentStatus === 'deferred');

// Get step state (completed, current, pending)
const getStepState = (index: number): 'completed' | 'current' | 'pending' => {
    if (isDeferred.value) {
        // When deferred, all main workflow steps are shown as pending
        return 'pending';
    }

    if (index < currentStepIndex.value) {
        return 'completed';
    } else if (index === currentStepIndex.value) {
        return 'current';
    }
    return 'pending';
};

// Get status color classes
const getStatusClasses = (
    state: 'completed' | 'current' | 'pending',
): string => {
    switch (state) {
        case 'completed':
            return 'text-green-600 dark:text-green-400';
        case 'current':
            return 'text-blue-600 dark:text-blue-400';
        case 'pending':
            return 'text-muted-foreground';
    }
};

// Get connector line classes
const getConnectorClasses = (stepIndex: number): string => {
    const baseClasses = 'flex-1 h-0.5 mx-2';

    if (isDeferred.value) {
        return `${baseClasses} bg-muted`;
    }

    if (stepIndex < currentStepIndex.value) {
        return `${baseClasses} bg-green-500 dark:bg-green-400`;
    }
    return `${baseClasses} bg-muted`;
};

// Get step circle background classes
const getCircleClasses = (
    state: 'completed' | 'current' | 'pending',
): string => {
    switch (state) {
        case 'completed':
            return 'bg-green-100 border-green-500 dark:bg-green-900/30 dark:border-green-400';
        case 'current':
            return 'bg-blue-100 border-blue-500 dark:bg-blue-900/30 dark:border-blue-400';
        case 'pending':
            return 'bg-muted border-muted-foreground/30';
    }
};
</script>

<template>
    <div class="w-full">
        <!-- Main workflow progress -->
        <div class="flex items-center justify-between">
            <template v-for="(step, index) in workflowSteps" :key="step.status">
                <!-- Step -->
                <div class="flex flex-col items-center">
                    <div
                        :class="[
                            'flex size-10 items-center justify-center rounded-full border-2 transition-colors',
                            getCircleClasses(getStepState(index)),
                        ]"
                    >
                        <component
                            :is="step.icon"
                            :class="[
                                'size-5',
                                getStatusClasses(getStepState(index)),
                            ]"
                        />
                    </div>
                    <span
                        :class="[
                            'mt-2 text-xs font-medium',
                            getStatusClasses(getStepState(index)),
                        ]"
                    >
                        {{ step.label }}
                    </span>
                </div>

                <!-- Connector line (except after last step) -->
                <div
                    v-if="index < workflowSteps.length - 1"
                    :class="getConnectorClasses(index)"
                />
            </template>
        </div>

        <!-- Deferred side branch indicator -->
        <div v-if="isDeferred" class="mt-4 flex items-center justify-center">
            <div
                class="flex items-center gap-2 rounded-full bg-gray-100 px-4 py-2 dark:bg-gray-800"
            >
                <Pause class="size-4 text-gray-600 dark:text-gray-400" />
                <span
                    class="text-sm font-medium text-gray-600 dark:text-gray-400"
                >
                    Currently Deferred
                </span>
            </div>
        </div>
    </div>
</template>
