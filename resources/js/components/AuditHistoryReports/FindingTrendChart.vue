<script setup lang="ts">
/**
 * FindingTrendChart Component
 *
 * Displays finding counts over time as a stacked area chart,
 * grouped by severity (Critical, High, Medium, Low).
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

interface FindingTrendItem {
    period: string;
    critical: number;
    high: number;
    medium: number;
    low: number;
}

interface Props {
    data: FindingTrendItem[];
    title?: string;
}

const props = withDefaults(defineProps<Props>(), {
    title: 'Finding Trends by Severity',
});

// Ref to track if component is mounted
const isMounted = ref(false);

onMounted(() => {
    isMounted.value = true;
});

// Severity colors matching spec
const severityColors = {
    critical: {
        border: 'rgb(239, 68, 68)', // red
        background: 'rgba(239, 68, 68, 0.3)',
    },
    high: {
        border: 'rgb(249, 115, 22)', // orange
        background: 'rgba(249, 115, 22, 0.3)',
    },
    medium: {
        border: 'rgb(234, 179, 8)', // yellow
        background: 'rgba(234, 179, 8, 0.3)',
    },
    low: {
        border: 'rgb(59, 130, 246)', // blue
        background: 'rgba(59, 130, 246, 0.3)',
    },
};

/**
 * Check if there is valid data to display
 */
const hasData = computed(() => {
    return props.data.length > 0 && props.data.some(item =>
        item.critical > 0 || item.high > 0 || item.medium > 0 || item.low > 0
    );
});

/**
 * Prepare chart data
 */
const chartData = computed<ChartData<'line', number[], string>>(() => {
    const labels = props.data.map(item => item.period);

    return {
        labels,
        datasets: [
            {
                label: 'Critical',
                data: props.data.map(item => item.critical),
                borderColor: severityColors.critical.border,
                backgroundColor: severityColors.critical.background,
                fill: true,
                tension: 0.3,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: severityColors.critical.border,
                pointBorderColor: 'white',
                pointBorderWidth: 2,
                order: 4, // Draw on top
            },
            {
                label: 'High',
                data: props.data.map(item => item.high),
                borderColor: severityColors.high.border,
                backgroundColor: severityColors.high.background,
                fill: true,
                tension: 0.3,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: severityColors.high.border,
                pointBorderColor: 'white',
                pointBorderWidth: 2,
                order: 3,
            },
            {
                label: 'Medium',
                data: props.data.map(item => item.medium),
                borderColor: severityColors.medium.border,
                backgroundColor: severityColors.medium.background,
                fill: true,
                tension: 0.3,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: severityColors.medium.border,
                pointBorderColor: 'white',
                pointBorderWidth: 2,
                order: 2,
            },
            {
                label: 'Low',
                data: props.data.map(item => item.low),
                borderColor: severityColors.low.border,
                backgroundColor: severityColors.low.background,
                fill: true,
                tension: 0.3,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: severityColors.low.border,
                pointBorderColor: 'white',
                pointBorderWidth: 2,
                order: 1, // Draw on bottom
            },
        ],
    };
});

/**
 * Chart options with stacked configuration and tooltips
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
                    const value = context.raw as number;
                    return `${context.dataset.label}: ${value} finding${value !== 1 ? 's' : ''}`;
                },
                footer: (tooltipItems) => {
                    const total = tooltipItems.reduce((sum, item) => sum + (item.raw as number), 0);
                    return `Total: ${total} findings`;
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
            stacked: true,
            beginAtZero: true,
            grid: {
                color: 'rgba(0, 0, 0, 0.05)',
            },
            ticks: {
                stepSize: 1,
                font: {
                    size: 11,
                },
            },
        },
    },
}));

/**
 * Calculate totals for summary
 */
const totals = computed(() => {
    return {
        critical: props.data.reduce((sum, item) => sum + item.critical, 0),
        high: props.data.reduce((sum, item) => sum + item.high, 0),
        medium: props.data.reduce((sum, item) => sum + item.medium, 0),
        low: props.data.reduce((sum, item) => sum + item.low, 0),
    };
});

const totalFindings = computed(() => {
    return totals.value.critical + totals.value.high + totals.value.medium + totals.value.low;
});
</script>

<template>
    <Card class="transition-all duration-200 hover:border-border/80 hover:shadow-md dark:hover:border-border/60">
        <CardHeader class="pb-2">
            <div class="flex items-center justify-between">
                <CardTitle class="text-sm font-medium text-muted-foreground dark:text-muted-foreground">
                    {{ title }}
                </CardTitle>
                <div v-if="hasData" class="text-right">
                    <span class="text-lg font-bold text-foreground dark:text-foreground">
                        {{ totalFindings.toLocaleString() }}
                    </span>
                    <span class="ml-1 text-sm text-muted-foreground">total</span>
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
                        d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"
                    />
                </svg>
                <p class="text-sm text-muted-foreground">No finding data available</p>
                <p class="mt-1 text-xs text-muted-foreground/70">Data will appear once audits have findings</p>
            </div>

            <!-- Summary stats -->
            <div v-if="hasData" class="mt-3 flex flex-wrap justify-between gap-2 text-xs text-muted-foreground">
                <span>{{ data.length }} time periods</span>
                <div class="flex gap-3">
                    <span class="text-red-600 dark:text-red-400">{{ totals.critical }} Critical</span>
                    <span class="text-orange-600 dark:text-orange-400">{{ totals.high }} High</span>
                    <span class="text-yellow-600 dark:text-yellow-400">{{ totals.medium }} Medium</span>
                    <span class="text-blue-600 dark:text-blue-400">{{ totals.low }} Low</span>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
