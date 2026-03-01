<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import SparklineChart from './SparklineChart.vue';

type FindingSeverityValue = 'critical' | 'high' | 'medium' | 'low';

interface TrendData {
    percentage: string;
    change: string;
}

interface SeverityBreakdown {
    critical: number;
    high: number;
    medium: number;
    low: number;
}

interface Props {
    title: string;
    value: number;
    trend: TrendData;
    sparklineData: number[];
    bySeverity: SeverityBreakdown;
}

defineProps<Props>();

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
 * Get severity badge styling based on severity value
 * Pattern from Audits/Dashboard.vue
 * Enhanced with larger padding on tablet (md) for better tablet readability
 */
const getSeverityBadgeClass = (severity: FindingSeverityValue): string => {
    // Base classes with responsive sizing: larger padding and text on tablet (md breakpoint)
    const baseClasses =
        'inline-flex items-center justify-center rounded-full px-2 py-0.5 md:px-2.5 md:py-1 text-xs md:text-sm font-medium transition-colors';
    switch (severity) {
        case 'critical':
            return `${baseClasses} bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400`;
        case 'high':
            return `${baseClasses} bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400`;
        case 'medium':
            return `${baseClasses} bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400`;
        case 'low':
            return `${baseClasses} bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400`;
        default:
            return `${baseClasses} bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300`;
    }
};

// Red color for findings sparkline to match severity theme
const sparklineColor = 'rgb(239, 68, 68)'; // red-500
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
                            {{ value.toLocaleString() }}
                        </div>
                        <div
                            :class="getTrendColorClass(trend.percentage)"
                            class="text-sm font-medium transition-colors"
                        >
                            {{ trend.percentage }}
                        </div>
                    </div>
                    <p
                        class="mt-1 text-xs text-muted-foreground md:text-sm dark:text-muted-foreground/80"
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
            <!-- Severity Breakdown with increased badge sizes for tablet readability -->
            <div class="mt-3 flex flex-wrap gap-2 md:gap-3">
                <span
                    v-if="bySeverity.critical > 0"
                    :class="getSeverityBadgeClass('critical')"
                >
                    {{ bySeverity.critical }} Critical
                </span>
                <span
                    v-if="bySeverity.high > 0"
                    :class="getSeverityBadgeClass('high')"
                >
                    {{ bySeverity.high }} High
                </span>
                <span
                    v-if="bySeverity.medium > 0"
                    :class="getSeverityBadgeClass('medium')"
                >
                    {{ bySeverity.medium }} Medium
                </span>
                <span
                    v-if="bySeverity.low > 0"
                    :class="getSeverityBadgeClass('low')"
                >
                    {{ bySeverity.low }} Low
                </span>
            </div>
        </CardContent>
    </Card>
</template>
