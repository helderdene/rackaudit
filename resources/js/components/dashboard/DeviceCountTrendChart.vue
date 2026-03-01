<script setup lang="ts">
/**
 * DeviceCountTrendChart Component
 *
 * Displays device count over time as a line chart with filled area.
 * Uses green color for visual distinction from capacity chart.
 */

import { computed, onMounted, ref } from 'vue';
import { Line } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    Filler,
    type ChartOptions,
    type ChartData,
} from 'chart.js';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

// Register Chart.js components
ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    Filler
);

interface Props {
    data: number[];
    labels: string[];
}

const props = defineProps<Props>();

// Ref to track if component is mounted
const isMounted = ref(false);

onMounted(() => {
    isMounted.value = true;
});

/**
 * Check if there is valid data to display
 */
const hasData = computed(() => {
    return props.data.length > 0 && props.data.some((val) => val !== null && val !== undefined);
});

/**
 * Calculate appropriate max value for y-axis
 */
const yAxisMax = computed(() => {
    if (!hasData.value) return 100;
    const maxValue = Math.max(...props.data.filter((v) => v !== null && v !== undefined));
    // Add 20% padding and round to a nice number
    const padded = maxValue * 1.2;
    if (padded <= 50) return Math.ceil(padded / 10) * 10;
    if (padded <= 100) return Math.ceil(padded / 25) * 25;
    if (padded <= 500) return Math.ceil(padded / 50) * 50;
    return Math.ceil(padded / 100) * 100;
});

/**
 * Prepare chart data
 */
const chartData = computed<ChartData<'line', number[], string>>(() => {
    return {
        labels: props.labels,
        datasets: [
            {
                label: 'Device Count',
                data: props.data,
                borderColor: 'rgb(34, 197, 94)', // green-500
                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                fill: true,
                tension: 0.3, // Smooth curve
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: 'rgb(34, 197, 94)',
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
                label: (context) => {
                    const value = context.raw as number;
                    return `${value.toLocaleString()} devices`;
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
            max: yAxisMax.value,
            grid: {
                color: 'rgba(0, 0, 0, 0.05)',
            },
            ticks: {
                precision: 0, // Only integers
                font: {
                    size: 11,
                },
            },
        },
    },
}));

/**
 * Get the latest value for display
 */
const latestValue = computed(() => {
    if (!hasData.value) return null;
    const validValues = props.data.filter((v) => v !== null && v !== undefined);
    if (validValues.length === 0) return null;
    return validValues[validValues.length - 1];
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

    if (diff === 0) return 'stable';
    return diff > 0 ? 'up' : 'down';
});

/**
 * Calculate trend change amount
 */
const trendChange = computed(() => {
    if (props.data.length < 2) return null;
    const validValues = props.data.filter((v) => v !== null && v !== undefined);
    if (validValues.length < 2) return null;

    const latest = validValues[validValues.length - 1];
    const previous = validValues[validValues.length - 2];
    return latest - previous;
});
</script>

<template>
    <Card
        class="transition-all duration-200 hover:border-border/80 hover:shadow-md dark:hover:border-border/60"
    >
        <CardHeader class="pb-2">
            <div class="flex items-center justify-between">
                <CardTitle class="text-sm font-medium text-muted-foreground dark:text-muted-foreground">
                    Device Count Trend
                </CardTitle>
                <div v-if="latestValue !== null" class="text-right">
                    <span class="text-lg font-bold text-foreground dark:text-foreground">
                        {{ latestValue.toLocaleString() }}
                    </span>
                    <span v-if="trendDirection && trendChange !== null" class="ml-2 text-xs">
                        <span
                            v-if="trendDirection === 'up'"
                            class="text-green-600 dark:text-green-400"
                            :title="`+${trendChange} devices`"
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
                            class="text-red-600 dark:text-red-400"
                            :title="`${trendChange} devices`"
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
                        <span v-else class="text-muted-foreground" title="No change">
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
                <p class="text-sm text-muted-foreground">No device data available</p>
                <p class="mt-1 text-xs text-muted-foreground/70">
                    Data will appear after snapshots are captured
                </p>
            </div>
        </CardContent>
    </Card>
</template>
