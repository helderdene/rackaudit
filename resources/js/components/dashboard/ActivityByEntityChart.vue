<script setup lang="ts">
/**
 * ActivityByEntityChart Component
 *
 * Horizontal bar chart showing activity counts by entity type.
 * Entity types: Devices, Racks, Connections, Audits, Findings
 */

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    Title,
    Tooltip,
    type ChartData,
    type ChartOptions,
} from 'chart.js';
import { computed, onMounted, ref } from 'vue';
import { Bar } from 'vue-chartjs';

// Register Chart.js components
ChartJS.register(
    CategoryScale,
    LinearScale,
    BarElement,
    Title,
    Tooltip,
    Legend,
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

// Check if there is any data to display
const hasData = computed(() => {
    return props.data.length > 0 && props.data.some((val) => val > 0);
});

// Total activity count
const totalActivity = computed(() => {
    return props.data.reduce((sum, val) => sum + val, 0);
});

// Entity type colors for visual distinction
const entityColors: Record<string, string> = {
    Devices: 'rgb(59, 130, 246)', // blue-500
    Racks: 'rgb(34, 197, 94)', // green-500
    Connections: 'rgb(168, 85, 247)', // purple-500
    Audits: 'rgb(249, 115, 22)', // orange-500
    Findings: 'rgb(239, 68, 68)', // red-500
};

// Get color based on label
const getColor = (label: string): string => {
    return entityColors[label] || 'rgb(107, 114, 128)'; // gray-500 as fallback
};

// Prepare chart data
const chartData = computed<ChartData<'bar', number[], string>>(() => {
    const backgroundColor = props.labels.map(getColor);

    return {
        labels: props.labels,
        datasets: [
            {
                label: 'Activity Count',
                data: props.data,
                backgroundColor,
                borderRadius: 4,
                borderSkipped: false,
            },
        ],
    };
});

// Chart options with horizontal orientation
const chartOptions = computed<ChartOptions<'bar'>>(() => ({
    indexAxis: 'y', // Horizontal bar chart
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false, // Hide legend
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
                    return `${value.toLocaleString()} activities`;
                },
            },
        },
    },
    scales: {
        x: {
            beginAtZero: true,
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
        y: {
            grid: {
                display: false,
            },
            ticks: {
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
                <CardTitle
                    class="text-sm font-medium text-muted-foreground dark:text-muted-foreground"
                >
                    Activity by Entity Type
                </CardTitle>
                <div v-if="hasData" class="text-right">
                    <span
                        class="text-lg font-bold text-foreground dark:text-foreground"
                    >
                        {{ totalActivity.toLocaleString() }}
                    </span>
                    <span class="ml-1 text-xs text-muted-foreground"
                        >total</span
                    >
                </div>
            </div>
        </CardHeader>
        <CardContent>
            <!-- Chart with data -->
            <div v-if="isMounted && hasData" class="h-48">
                <Bar :data="chartData" :options="chartOptions" />
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
                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                    />
                </svg>
                <p class="text-sm text-muted-foreground">
                    No activity in selected period
                </p>
                <p class="mt-1 text-xs text-muted-foreground/70">
                    Activity will appear as changes are made
                </p>
            </div>
        </CardContent>
    </Card>
</template>
