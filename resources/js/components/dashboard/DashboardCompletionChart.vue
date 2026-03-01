<script setup lang="ts">
/**
 * DashboardCompletionChart Component
 *
 * Simplified version of AuditCompletionTrendChart for the main dashboard.
 * Displays completed audits over time as a line chart.
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
    total: number;
}

const props = defineProps<Props>();

// Ref to track if component is mounted
const isMounted = ref(false);

onMounted(() => {
    isMounted.value = true;
});

// Check if there is any completion data
const hasData = computed(() => {
    return props.data.some((count) => count > 0);
});

/**
 * Calculate appropriate max value for y-axis
 */
const yAxisMax = computed(() => {
    if (!hasData.value) return 10;
    const maxValue = Math.max(...props.data);
    if (maxValue <= 5) return 5;
    if (maxValue <= 10) return 10;
    return Math.ceil(maxValue * 1.2);
});

// Prepare chart data
const chartData = computed<ChartData<'line', number[], string>>(() => {
    return {
        labels: props.labels,
        datasets: [
            {
                label: 'Completed Audits',
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

// Chart options with responsive sizing
const chartOptions = computed<ChartOptions<'line'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
        mode: 'index',
        intersect: false,
    },
    plugins: {
        legend: {
            display: false, // Hide legend since we only have one dataset
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
                    return `${value} audit${value !== 1 ? 's' : ''} completed`;
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
                stepSize: 1,
                precision: 0, // Only show integers
                font: {
                    size: 11,
                },
            },
        },
    },
}));
</script>

<template>
    <Card
        class="transition-all duration-200 hover:border-border/80 hover:shadow-md dark:hover:border-border/60"
    >
        <CardHeader class="pb-2">
            <div class="flex items-center justify-between">
                <CardTitle class="text-sm font-medium text-muted-foreground dark:text-muted-foreground">
                    Audit Completions
                </CardTitle>
                <div class="text-right">
                    <span class="text-lg font-bold text-green-600 dark:text-green-400">
                        {{ total }}
                    </span>
                    <span class="ml-1 text-xs text-muted-foreground">total</span>
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
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
                <p class="text-sm text-muted-foreground">No completed audits in selected period</p>
            </div>
        </CardContent>
    </Card>
</template>
