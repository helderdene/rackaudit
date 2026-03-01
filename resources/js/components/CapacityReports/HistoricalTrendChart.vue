<script setup lang="ts">
/**
 * HistoricalTrendChart Component
 *
 * Displays historical capacity trend data as a line chart with interactive
 * tooltips. Used to visualize U-space and power utilization over time.
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

interface Props {
    data: number[];
    labels: string[];
    title: string;
    unit?: string;
    color?: string;
    fillColor?: string;
}

const props = withDefaults(defineProps<Props>(), {
    unit: '%',
    color: 'rgb(59, 130, 246)', // blue-500
    fillColor: 'rgba(59, 130, 246, 0.1)',
});

// Ref to track if component is mounted
const isMounted = ref(false);

onMounted(() => {
    isMounted.value = true;
});

/**
 * Check if there is valid data to display
 */
const hasData = computed(() => {
    return (
        props.data.length > 0 &&
        props.data.some((val) => val !== null && val !== undefined)
    );
});

/**
 * Format date labels for readability
 */
const formatDateLabel = (dateStr: string): string => {
    // Handle YYYY-MM-DD format
    if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
        });
    }
    return dateStr;
};

/**
 * Prepare chart data
 */
const chartData = computed<ChartData<'line', number[], string>>(() => {
    const formattedLabels = props.labels.map(formatDateLabel);

    return {
        labels: formattedLabels,
        datasets: [
            {
                label: props.title,
                data: props.data,
                borderColor: props.color,
                backgroundColor: props.fillColor,
                fill: true,
                tension: 0.3, // Smooth curve
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: props.color,
                pointBorderColor: 'white',
                pointBorderWidth: 2,
            },
        ],
    };
});

/**
 * Chart options with responsive sizing and tooltips
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
            display: false, // Hide legend since we show title separately
        },
        tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            titleColor: 'white',
            bodyColor: 'white',
            padding: 12,
            cornerRadius: 8,
            callbacks: {
                title: (context) => {
                    // Show full date in tooltip
                    const index = context[0].dataIndex;
                    return props.labels[index] || '';
                },
                label: (context) => {
                    const value = context.raw as number;
                    return `${value.toFixed(1)}${props.unit}`;
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
            max: 100, // Percentage scale
            grid: {
                color: 'rgba(0, 0, 0, 0.05)',
            },
            ticks: {
                stepSize: 20,
                font: {
                    size: 11,
                },
                callback: function (value) {
                    return value + props.unit;
                },
            },
        },
    },
}));

/**
 * Calculate the average value for display
 */
const averageValue = computed(() => {
    if (!hasData.value) return null;
    const validValues = props.data.filter((v) => v !== null && v !== undefined);
    if (validValues.length === 0) return null;
    const sum = validValues.reduce((a, b) => a + b, 0);
    return (sum / validValues.length).toFixed(1);
});

/**
 * Get the latest value for display
 */
const latestValue = computed(() => {
    if (!hasData.value) return null;
    const validValues = props.data.filter((v) => v !== null && v !== undefined);
    if (validValues.length === 0) return null;
    return validValues[validValues.length - 1].toFixed(1);
});

/**
 * Calculate the trend direction
 */
const trendDirection = computed((): 'up' | 'down' | 'stable' | null => {
    if (props.data.length < 2) return null;
    const validValues = props.data.filter((v) => v !== null && v !== undefined);
    if (validValues.length < 2) return null;

    const latest = validValues[validValues.length - 1];
    const previous = validValues[validValues.length - 2];
    const diff = latest - previous;

    if (Math.abs(diff) < 0.5) return 'stable';
    return diff > 0 ? 'up' : 'down';
});
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
                <div v-if="latestValue !== null" class="text-right">
                    <span
                        class="text-lg font-bold text-foreground dark:text-foreground"
                    >
                        {{ latestValue }}{{ unit }}
                    </span>
                    <span v-if="trendDirection" class="ml-2 text-xs">
                        <span
                            v-if="trendDirection === 'up'"
                            class="text-amber-600 dark:text-amber-400"
                            title="Increasing"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="inline-block size-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M18 15l-6-6-6 6" />
                            </svg>
                        </span>
                        <span
                            v-else-if="trendDirection === 'down'"
                            class="text-green-600 dark:text-green-400"
                            title="Decreasing"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="inline-block size-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M6 9l6 6 6-6" />
                            </svg>
                        </span>
                        <span
                            v-else
                            class="text-muted-foreground"
                            title="Stable"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="inline-block size-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M5 12h14" />
                            </svg>
                        </span>
                    </span>
                </div>
            </div>
        </CardHeader>
        <CardContent>
            <!-- Chart with data -->
            <div v-if="isMounted && hasData" class="h-48">
                <Line :data="chartData" :options="chartOptions" />
            </div>

            <!-- Empty state -->
            <div
                v-else
                class="flex h-48 flex-col items-center justify-center rounded-lg border border-dashed border-muted-foreground/30"
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
                        d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"
                    />
                </svg>
                <p class="text-sm text-muted-foreground">
                    No historical data available
                </p>
                <p class="mt-1 text-xs text-muted-foreground/70">
                    Data will appear after snapshots are captured
                </p>
            </div>

            <!-- Summary stats -->
            <div
                v-if="hasData && averageValue !== null"
                class="mt-3 flex justify-between text-xs text-muted-foreground"
            >
                <span>Avg: {{ averageValue }}{{ unit }}</span>
                <span>{{ data.length }} data points</span>
            </div>
        </CardContent>
    </Card>
</template>
