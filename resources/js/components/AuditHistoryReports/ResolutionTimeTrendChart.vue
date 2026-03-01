<script setup lang="ts">
/**
 * ResolutionTimeTrendChart Component
 *
 * Displays average resolution time and time to first response trends
 * over time as a line chart with two lines.
 */

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    CategoryScale,
    Chart as ChartJS,
    Filler,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Title,
    Tooltip,
    type ChartData,
    type ChartOptions,
} from 'chart.js';
import { computed, onMounted, ref } from 'vue';
import { Line } from 'vue-chartjs';

// Register Chart.js components
ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    Filler,
);

interface ResolutionTimeTrendItem {
    period: string;
    avg_resolution_time: number | null;
    avg_first_response: number | null;
}

interface Props {
    data: ResolutionTimeTrendItem[];
    title?: string;
}

const props = withDefaults(defineProps<Props>(), {
    title: 'Resolution Time Trends',
});

// Ref to track if component is mounted
const isMounted = ref(false);

onMounted(() => {
    isMounted.value = true;
});

// Line colors matching spec
const lineColors = {
    resolution: {
        border: 'rgb(59, 130, 246)', // blue
        background: 'rgba(59, 130, 246, 0.1)',
    },
    firstResponse: {
        border: 'rgb(34, 197, 94)', // green
        background: 'rgba(34, 197, 94, 0.1)',
    },
};

/**
 * Check if there is valid data to display
 */
const hasData = computed(() => {
    return (
        props.data.length > 0 &&
        props.data.some(
            (item) =>
                item.avg_resolution_time !== null ||
                item.avg_first_response !== null,
        )
    );
});

/**
 * Determine the best unit to use based on data values
 */
const timeUnit = computed(() => {
    const allMinutes = props.data
        .flatMap((item) => [item.avg_resolution_time, item.avg_first_response])
        .filter((v): v is number => v !== null);

    if (allMinutes.length === 0) return 'hours';

    const maxMinutes = Math.max(...allMinutes);
    const maxHours = maxMinutes / 60;

    return maxHours >= 24 ? 'days' : 'hours';
});

/**
 * Convert minutes to the selected unit
 */
const convertToUnit = (minutes: number | null): number | null => {
    if (minutes === null) return null;

    const hours = minutes / 60;

    if (timeUnit.value === 'days') {
        return hours / 24;
    }

    return hours;
};

/**
 * Prepare chart data
 */
const chartData = computed<ChartData<'line', (number | null)[], string>>(() => {
    const labels = props.data.map((item) => item.period);

    return {
        labels,
        datasets: [
            {
                label: 'Avg Resolution Time',
                data: props.data.map((item) =>
                    convertToUnit(item.avg_resolution_time),
                ),
                borderColor: lineColors.resolution.border,
                backgroundColor: lineColors.resolution.background,
                fill: false,
                tension: 0.3,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: lineColors.resolution.border,
                pointBorderColor: 'white',
                pointBorderWidth: 2,
                spanGaps: true,
            },
            {
                label: 'Avg First Response',
                data: props.data.map((item) =>
                    convertToUnit(item.avg_first_response),
                ),
                borderColor: lineColors.firstResponse.border,
                backgroundColor: lineColors.firstResponse.background,
                fill: false,
                tension: 0.3,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: lineColors.firstResponse.border,
                pointBorderColor: 'white',
                pointBorderWidth: 2,
                spanGaps: true,
            },
        ],
    };
});

/**
 * Chart options with tooltips
 */
const chartOptions = computed<ChartOptions<'line'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
        mode: 'index',
        intersect: false,
    },
    plugins: {
        legend: {
            display: true,
            position: 'bottom',
            labels: {
                usePointStyle: true,
                padding: 16,
                font: {
                    size: 12,
                },
            },
        },
        tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            titleColor: 'white',
            bodyColor: 'white',
            padding: 12,
            cornerRadius: 8,
            callbacks: {
                title: (context) => {
                    return context[0].label;
                },
                label: (context) => {
                    const value = context.raw as number | null;
                    if (value === null)
                        return `${context.dataset.label}: No data`;
                    return `${context.dataset.label}: ${value.toFixed(1)} ${timeUnit.value}`;
                },
            },
        },
    },
    scales: {
        x: {
            grid: {
                display: false,
            },
            ticks: {
                maxRotation: 45,
                minRotation: 0,
                font: {
                    size: 11,
                },
            },
        },
        y: {
            beginAtZero: true,
            grid: {
                color: 'rgba(0, 0, 0, 0.05)',
            },
            ticks: {
                font: {
                    size: 11,
                },
                callback: function (value) {
                    return value + ' ' + timeUnit.value.charAt(0);
                },
            },
            title: {
                display: true,
                text: `Time (${timeUnit.value})`,
                font: {
                    size: 11,
                },
            },
        },
    },
}));

/**
 * Calculate average values for summary
 */
const averages = computed(() => {
    const resolutionTimes = props.data
        .map((item) => item.avg_resolution_time)
        .filter((v): v is number => v !== null);

    const firstResponses = props.data
        .map((item) => item.avg_first_response)
        .filter((v): v is number => v !== null);

    const avgResolution =
        resolutionTimes.length > 0
            ? resolutionTimes.reduce((a, b) => a + b, 0) /
              resolutionTimes.length
            : null;

    const avgFirstResponse =
        firstResponses.length > 0
            ? firstResponses.reduce((a, b) => a + b, 0) / firstResponses.length
            : null;

    return {
        resolution: convertToUnit(avgResolution),
        firstResponse: convertToUnit(avgFirstResponse),
    };
});

/**
 * Format time value for display
 */
const formatTime = (value: number | null): string => {
    if (value === null) return 'N/A';
    return `${value.toFixed(1)} ${timeUnit.value}`;
};
</script>

<template>
    <Card
        class="transition-all duration-200 hover:border-border/80 hover:shadow-md dark:hover:border-border/60"
    >
        <CardHeader class="pb-2">
            <div class="flex items-center justify-between">
                <CardTitle
                    class="text-sm font-medium text-muted-foreground dark:text-muted-foreground"
                >
                    {{ title }}
                </CardTitle>
                <div
                    v-if="hasData && averages.resolution !== null"
                    class="text-right"
                >
                    <span
                        class="text-lg font-bold text-foreground dark:text-foreground"
                    >
                        {{ formatTime(averages.resolution) }}
                    </span>
                    <span class="ml-1 text-sm text-muted-foreground">avg</span>
                </div>
            </div>
        </CardHeader>
        <CardContent>
            <!-- Chart with data -->
            <div v-if="isMounted && hasData" class="h-64">
                <Line :data="chartData" :options="chartOptions" />
            </div>

            <!-- Empty state -->
            <div
                v-else
                class="flex h-64 flex-col items-center justify-center rounded-lg border border-dashed border-muted-foreground/30"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="mb-2 size-12 text-muted-foreground/50"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
                <p class="text-sm text-muted-foreground">
                    No resolution time data available
                </p>
                <p class="mt-1 text-xs text-muted-foreground/70">
                    Data will appear once findings are resolved
                </p>
            </div>

            <!-- Summary stats -->
            <div
                v-if="hasData"
                class="mt-3 flex flex-wrap justify-between gap-2 text-xs text-muted-foreground"
            >
                <span>{{ data.length }} time periods</span>
                <div class="flex gap-4">
                    <span class="text-blue-600 dark:text-blue-400">
                        Resolution: {{ formatTime(averages.resolution) }}
                    </span>
                    <span class="text-green-600 dark:text-green-400">
                        First Response: {{ formatTime(averages.firstResponse) }}
                    </span>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
