<script setup lang="ts">
import SparklineChart from '@/components/dashboard/SparklineChart.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { computed } from 'vue';

interface SeverityBreakdown {
    critical: number;
    high: number;
    medium: number;
    low: number;
}

interface Props {
    title: string;
    value: number | string;
    unit?: string;
    sparklineData?: number[];
    severityBreakdown?: SeverityBreakdown;
    description?: string;
    color?: string;
}

const props = withDefaults(defineProps<Props>(), {
    unit: '',
    sparklineData: undefined,
    severityBreakdown: undefined,
    description: undefined,
    color: 'rgb(59, 130, 246)', // blue-500 default
});

/**
 * Format value for display
 */
const formatValue = (value: number | string): string => {
    if (typeof value === 'number') {
        return value.toLocaleString();
    }
    return value;
};

/**
 * Calculate total findings from severity breakdown
 */
const totalFromSeverity = computed(() => {
    if (!props.severityBreakdown) return null;
    return Object.values(props.severityBreakdown).reduce(
        (sum, count) => sum + count,
        0,
    );
});
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
                <!-- Left side: metric value and details -->
                <div class="min-w-0 flex-1">
                    <div class="flex items-baseline gap-2">
                        <div
                            class="text-3xl font-bold text-foreground dark:text-foreground"
                        >
                            {{ formatValue(value) }}
                        </div>
                        <div v-if="unit" class="text-lg text-muted-foreground">
                            {{ unit }}
                        </div>
                    </div>

                    <!-- Description text -->
                    <p
                        v-if="description"
                        class="mt-1 text-xs text-muted-foreground dark:text-muted-foreground/80"
                    >
                        {{ description }}
                    </p>

                    <!-- Severity breakdown badges -->
                    <div
                        v-if="severityBreakdown"
                        class="mt-3 flex flex-wrap gap-2"
                    >
                        <span
                            v-if="severityBreakdown.critical > 0"
                            class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400"
                        >
                            {{ severityBreakdown.critical }} Critical
                        </span>
                        <span
                            v-if="severityBreakdown.high > 0"
                            class="inline-flex items-center rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-700 dark:bg-orange-900/30 dark:text-orange-400"
                        >
                            {{ severityBreakdown.high }} High
                        </span>
                        <span
                            v-if="severityBreakdown.medium > 0"
                            class="inline-flex items-center rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400"
                        >
                            {{ severityBreakdown.medium }} Medium
                        </span>
                        <span
                            v-if="severityBreakdown.low > 0"
                            class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-400"
                        >
                            {{ severityBreakdown.low }} Low
                        </span>
                        <!-- Show "None" if all counts are 0 -->
                        <span
                            v-if="totalFromSeverity === 0"
                            class="text-xs text-muted-foreground"
                        >
                            No findings
                        </span>
                    </div>

                    <!-- Slot for additional content -->
                    <slot></slot>
                </div>

                <!-- Right side: sparkline chart -->
                <div
                    v-if="sparklineData && sparklineData.length > 0"
                    class="shrink-0"
                >
                    <SparklineChart :data="sparklineData" :color="color" />
                </div>
            </div>
        </CardContent>
    </Card>
</template>
