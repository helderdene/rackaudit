<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import SparklineChart from './SparklineChart.vue';

interface TrendData {
    percentage: string;
    change: string;
}

interface Props {
    title: string;
    value: number | string;
    unit?: string;
    trend: TrendData;
    sparklineData: number[];
    sparklineColor?: string;
    icon?: string;
}

withDefaults(defineProps<Props>(), {
    unit: '',
    sparklineColor: 'rgb(59, 130, 246)', // blue-500 default
    icon: undefined,
});

/**
 * Get trend color class based on percentage value
 * Green for positive changes, red for negative, muted for N/A or zero
 */
const getTrendColorClass = (percentage: string): string => {
    if (percentage === 'N/A') {
        return 'text-muted-foreground';
    }
    if (percentage.startsWith('-')) {
        return 'text-red-600 dark:text-red-400';
    }
    if (percentage.startsWith('+') && percentage !== '+0%') {
        return 'text-green-600 dark:text-green-400';
    }
    return 'text-muted-foreground';
};

/**
 * Format value for display (with locale formatting for large numbers)
 */
const formatValue = (value: number | string): string => {
    if (typeof value === 'number') {
        return value.toLocaleString();
    }
    return value;
};
</script>

<template>
    <Card
        class="relative transition-all duration-200 hover:border-border/80 hover:shadow-md dark:hover:border-border/60"
    >
        <CardHeader class="pb-2">
            <CardTitle
                class="text-sm font-medium text-muted-foreground dark:text-muted-foreground"
            >
                {{ title }}
            </CardTitle>
        </CardHeader>
        <CardContent>
            <div class="flex items-start justify-between gap-4">
                <!-- Left side: metric value and trend -->
                <div class="min-w-0 flex-1">
                    <div class="flex items-baseline gap-2">
                        <div
                            class="text-3xl font-bold text-foreground dark:text-foreground"
                        >
                            {{ formatValue(value) }}{{ unit }}
                        </div>
                        <div
                            :class="getTrendColorClass(trend.percentage)"
                            class="text-sm font-medium transition-colors"
                        >
                            {{ trend.percentage }}
                        </div>
                    </div>
                    <p
                        class="mt-1 text-xs text-muted-foreground dark:text-muted-foreground/80"
                    >
                        {{ trend.change }}
                    </p>
                </div>
                <!-- Right side: sparkline chart -->
                <div class="shrink-0">
                    <SparklineChart
                        v-if="sparklineData && sparklineData.length > 0"
                        :data="sparklineData"
                        :color="sparklineColor"
                    />
                </div>
            </div>
            <!-- Slot for additional content (e.g., severity breakdown, past due count) -->
            <slot></slot>
        </CardContent>
    </Card>
</template>
