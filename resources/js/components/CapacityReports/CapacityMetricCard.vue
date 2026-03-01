<script setup lang="ts">
import SparklineChart from '@/components/dashboard/SparklineChart.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { computed } from 'vue';

interface TrendData {
    percentage: string;
    change: string;
}

interface Props {
    title: string;
    value: number | string;
    unit?: string;
    total?: number;
    available?: number;
    threshold?: number;
    trend?: TrendData;
    sparklineData?: number[];
}

const props = withDefaults(defineProps<Props>(), {
    unit: '',
    total: undefined,
    available: undefined,
    threshold: 80,
    trend: undefined,
    sparklineData: undefined,
});

/**
 * Calculate utilization percentage for progress bar
 */
const utilizationPercent = computed(() => {
    if (typeof props.value === 'number') {
        return Math.min(props.value, 100);
    }
    return 0;
});

/**
 * Determine if value is a percentage type (for progress bar display)
 */
const isPercentage = computed(() => {
    return props.unit === '%' || props.total !== undefined;
});

/**
 * Get utilization status based on thresholds
 * Warning: 80-89%, Critical: 90%+
 */
const utilizationStatus = computed((): 'normal' | 'warning' | 'critical' => {
    const value = typeof props.value === 'number' ? props.value : 0;
    if (value >= 90) return 'critical';
    if (value >= props.threshold) return 'warning';
    return 'normal';
});

/**
 * Get color class based on utilization status
 */
const getStatusColorClass = computed(() => {
    switch (utilizationStatus.value) {
        case 'critical':
            return 'text-red-600 dark:text-red-400';
        case 'warning':
            return 'text-amber-600 dark:text-amber-400';
        default:
            return 'text-green-600 dark:text-green-400';
    }
});

/**
 * Get progress bar color class based on utilization status
 */
const getProgressBarClass = computed(() => {
    switch (utilizationStatus.value) {
        case 'critical':
            return 'bg-red-500';
        case 'warning':
            return 'bg-amber-500';
        default:
            return 'bg-green-500';
    }
});

/**
 * Get sparkline color based on utilization status
 */
const sparklineColor = computed(() => {
    switch (utilizationStatus.value) {
        case 'critical':
            return 'rgb(239, 68, 68)'; // red-500
        case 'warning':
            return 'rgb(245, 158, 11)'; // amber-500
        default:
            return 'rgb(34, 197, 94)'; // green-500
    }
});

/**
 * Get trend color class based on percentage value
 */
const getTrendColorClass = (percentage: string): string => {
    if (percentage === 'N/A') {
        return 'text-muted-foreground';
    }
    // For utilization metrics, positive trend (increase) is often bad
    if (percentage.startsWith('+') && percentage !== '+0%') {
        return 'text-amber-600 dark:text-amber-400';
    }
    if (percentage.startsWith('-')) {
        return 'text-green-600 dark:text-green-400';
    }
    return 'text-muted-foreground';
};

/**
 * Format value for display
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
                            class="text-3xl font-bold"
                            :class="
                                isPercentage
                                    ? getStatusColorClass
                                    : 'text-foreground dark:text-foreground'
                            "
                        >
                            {{ formatValue(value) }}{{ unit }}
                        </div>
                        <div
                            v-if="trend"
                            :class="getTrendColorClass(trend.percentage)"
                            class="text-sm font-medium transition-colors"
                        >
                            {{ trend.percentage }}
                        </div>
                    </div>

                    <!-- Progress bar for percentage metrics -->
                    <div
                        v-if="isPercentage"
                        class="mt-2 h-2 w-full overflow-hidden rounded-full bg-muted"
                    >
                        <div
                            class="h-full transition-all duration-300"
                            :class="getProgressBarClass"
                            :style="{ width: `${utilizationPercent}%` }"
                        />
                    </div>

                    <!-- Usage details -->
                    <div
                        v-if="total !== undefined && available !== undefined"
                        class="mt-2 flex justify-between text-xs text-muted-foreground"
                    >
                        <span
                            >{{
                                (total - available).toLocaleString()
                            }}
                            used</span
                        >
                        <span>{{ available.toLocaleString() }} available</span>
                    </div>

                    <!-- Total display -->
                    <div
                        v-if="total !== undefined"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        Total: {{ total.toLocaleString()
                        }}{{ unit !== '%' ? unit : '' }}
                    </div>

                    <!-- Trend change text -->
                    <p
                        v-if="trend"
                        class="mt-1 text-xs text-muted-foreground dark:text-muted-foreground/80"
                    >
                        {{ trend.change }}
                    </p>
                </div>

                <!-- Right side: sparkline chart -->
                <div
                    v-if="sparklineData && sparklineData.length > 0"
                    class="shrink-0"
                >
                    <SparklineChart
                        :data="sparklineData"
                        :color="sparklineColor"
                    />
                </div>
            </div>

            <!-- Slot for additional content -->
            <slot></slot>
        </CardContent>
    </Card>
</template>
