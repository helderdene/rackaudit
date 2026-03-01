<script setup lang="ts">
/**
 * Audit Completion Trend Line Chart Component
 *
 * Displays audit completion counts over time as a line chart with
 * responsive sizing and hover tooltips.
 */

import { computed } from 'vue';
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
import type { TrendDataPoint } from '@/types/dashboard';

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

const props = defineProps<{
    trendData: TrendDataPoint[];
    timePeriod?: string;
}>();

// Check if there is any completion data
const hasData = computed(() => {
    return props.trendData.some((point) => point.count > 0);
});

// Format period labels for readability
const formatPeriodLabel = (period: string): string => {
    // YYYY-MM-DD (daily)
    if (/^\d{4}-\d{2}-\d{2}$/.test(period)) {
        const date = new Date(period);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }
    // YYYY-Www (weekly)
    if (/^\d{4}-W\d{2}$/.test(period)) {
        const [year, week] = period.split('-W');
        return `W${week}`;
    }
    // YYYY-MM (monthly)
    if (/^\d{4}-\d{2}$/.test(period)) {
        const [year, month] = period.split('-');
        const date = new Date(parseInt(year), parseInt(month) - 1, 1);
        return date.toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
    }
    return period;
};

// Prepare chart data
const chartData = computed<ChartData<'line', number[], string>>(() => {
    // For daily data with many points, sample to prevent overcrowding
    let dataPoints = props.trendData;
    const maxLabels = 15; // Maximum number of labels to show

    if (dataPoints.length > maxLabels) {
        // Sample data points evenly
        const step = Math.floor(dataPoints.length / maxLabels);
        const sampled: TrendDataPoint[] = [];
        for (let i = 0; i < dataPoints.length; i += step) {
            sampled.push(dataPoints[i]);
        }
        // Always include the last point
        if (sampled[sampled.length - 1] !== dataPoints[dataPoints.length - 1]) {
            sampled.push(dataPoints[dataPoints.length - 1]);
        }
        dataPoints = sampled;
    }

    const labels = dataPoints.map((point) => formatPeriodLabel(point.period));
    const data = dataPoints.map((point) => point.count);

    return {
        labels,
        datasets: [
            {
                label: 'Completed Audits',
                data,
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
                title: (context) => {
                    const index = context[0].dataIndex;
                    // Get the original period for the tooltip title
                    const dataPoints = props.trendData;
                    const maxLabels = 15;
                    if (dataPoints.length > maxLabels) {
                        const step = Math.floor(dataPoints.length / maxLabels);
                        const originalIndex = index * step;
                        if (originalIndex < dataPoints.length) {
                            return dataPoints[originalIndex].period;
                        }
                    }
                    return dataPoints[index]?.period || '';
                },
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

// Get time period description for display
const timePeriodLabel = computed(() => {
    switch (props.timePeriod) {
        case '30_days':
            return 'Last 30 Days';
        case '90_days':
            return 'Last 90 Days';
        case 'quarter':
            return 'This Quarter';
        case 'year':
            return 'This Year';
        case 'all':
            return 'All Time';
        default:
            return 'Selected Period';
    }
});

// Calculate total completions for summary
const totalCompletions = computed(() => {
    return props.trendData.reduce((sum, point) => sum + point.count, 0);
});
</script>

<template>
    <div class="w-full">
        <!-- Header with total -->
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h4 class="text-sm font-medium text-muted-foreground">Audit Completions</h4>
                <p class="text-xs text-muted-foreground/70">{{ timePeriodLabel }}</p>
            </div>
            <div class="text-right">
                <span class="text-2xl font-bold text-green-600 dark:text-green-400">
                    {{ totalCompletions }}
                </span>
                <span class="ml-1 text-sm text-muted-foreground">total</span>
            </div>
        </div>

        <!-- Chart with data -->
        <div v-if="hasData" class="h-64">
            <Line :data="chartData" :options="chartOptions" />
        </div>

        <!-- Empty state -->
        <div
            v-else
            class="flex h-64 flex-col items-center justify-center rounded-lg border border-dashed border-muted-foreground/30"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="mb-2 h-12 w-12 text-muted-foreground/50"
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
            <p class="text-sm text-muted-foreground">No completed audits in selected period</p>
        </div>
    </div>
</template>
