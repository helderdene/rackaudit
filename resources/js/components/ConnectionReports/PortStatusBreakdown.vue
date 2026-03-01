<script setup lang="ts">
/**
 * PortStatusBreakdown Component
 *
 * Displays port counts by status (Available, Connected, Reserved, Disabled)
 * with small bar/pill visualizations and percentages.
 */

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CheckCircle2, Circle, Lock, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';

interface PortUtilizationByStatus {
    status: string;
    label: string;
    count: number;
    percentage: number;
}

interface Props {
    byStatus: PortUtilizationByStatus[];
    totalPorts: number;
}

const props = defineProps<Props>();

// Status configuration with icons and colors
const statusConfig: Record<
    string,
    {
        icon: typeof Circle;
        colorClass: string;
        bgClass: string;
        barClass: string;
    }
> = {
    available: {
        icon: Circle,
        colorClass: 'text-gray-500 dark:text-gray-400',
        bgClass: 'bg-gray-50 dark:bg-gray-900/20',
        barClass: 'bg-gray-400 dark:bg-gray-500',
    },
    connected: {
        icon: CheckCircle2,
        colorClass: 'text-green-600 dark:text-green-400',
        bgClass: 'bg-green-50 dark:bg-green-900/20',
        barClass: 'bg-green-500 dark:bg-green-400',
    },
    reserved: {
        icon: Lock,
        colorClass: 'text-amber-600 dark:text-amber-400',
        bgClass: 'bg-amber-50 dark:bg-amber-900/20',
        barClass: 'bg-amber-500 dark:bg-amber-400',
    },
    disabled: {
        icon: XCircle,
        colorClass: 'text-red-600 dark:text-red-400',
        bgClass: 'bg-red-50 dark:bg-red-900/20',
        barClass: 'bg-red-500 dark:bg-red-400',
    },
};

// Get config for status, with fallback
const getStatusConfig = (status: string) => {
    return (
        statusConfig[status.toLowerCase()] ?? {
            icon: Circle,
            colorClass: 'text-gray-500 dark:text-gray-400',
            bgClass: 'bg-gray-50 dark:bg-gray-900/20',
            barClass: 'bg-gray-400 dark:bg-gray-500',
        }
    );
};

// Prepare status items with config
const statusItems = computed(() => {
    // Define the order of statuses
    const statusOrder = ['available', 'connected', 'reserved', 'disabled'];

    return statusOrder
        .map((status) => {
            const item = props.byStatus.find(
                (s) => s.status.toLowerCase() === status,
            );
            if (!item) return null;
            return {
                ...item,
                config: getStatusConfig(item.status),
            };
        })
        .filter((item): item is NonNullable<typeof item> => item !== null);
});

// Check if there is any data
const hasData = computed(() => {
    return props.totalPorts > 0;
});

// Calculate max count for bar scaling
const maxCount = computed(() => {
    return Math.max(...statusItems.value.map((item) => item.count), 1);
});

// Get bar width as percentage
const getBarWidth = (count: number): string => {
    return `${(count / maxCount.value) * 100}%`;
};
</script>

<template>
    <Card class="transition-all duration-200 hover:shadow-md">
        <CardHeader class="pb-2">
            <div class="flex items-center justify-between">
                <CardTitle class="text-base font-medium">
                    Port Status Breakdown
                </CardTitle>
                <span v-if="hasData" class="text-sm text-muted-foreground">
                    {{ totalPorts }} total ports
                </span>
            </div>
        </CardHeader>
        <CardContent>
            <!-- Status breakdown list -->
            <div v-if="hasData" class="space-y-4">
                <div
                    v-for="item in statusItems"
                    :key="item.status"
                    class="flex items-center gap-3"
                >
                    <!-- Status Icon and Label -->
                    <div class="flex w-28 shrink-0 items-center gap-2">
                        <div :class="['rounded-full p-1', item.config.bgClass]">
                            <component
                                :is="item.config.icon"
                                :class="['size-3.5', item.config.colorClass]"
                            />
                        </div>
                        <span class="text-sm font-medium">{{
                            item.label
                        }}</span>
                    </div>

                    <!-- Progress Bar -->
                    <div class="flex flex-1 items-center gap-3">
                        <div
                            class="relative h-2 flex-1 overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                :class="[
                                    'h-full rounded-full transition-all duration-300',
                                    item.config.barClass,
                                ]"
                                :style="{ width: getBarWidth(item.count) }"
                            />
                        </div>
                    </div>

                    <!-- Count and Percentage -->
                    <div
                        class="flex w-24 shrink-0 items-center justify-end gap-2 text-right"
                    >
                        <span class="font-medium">{{ item.count }}</span>
                        <span class="w-12 text-xs text-muted-foreground">
                            ({{ item.percentage.toFixed(1) }}%)
                        </span>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div
                v-else
                class="flex h-32 flex-col items-center justify-center rounded-lg border border-dashed border-muted-foreground/30"
            >
                <Circle class="mb-2 size-10 text-muted-foreground/50" />
                <p class="text-sm text-muted-foreground">
                    No port data available
                </p>
            </div>
        </CardContent>
    </Card>
</template>
