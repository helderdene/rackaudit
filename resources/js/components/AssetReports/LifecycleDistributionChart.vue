<script setup lang="ts">
/**
 * LifecycleDistributionChart Component
 *
 * Displays a pie chart showing the distribution of devices across
 * lifecycle statuses using Chart.js.
 */

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    ArcElement,
    Chart as ChartJS,
    Legend,
    Tooltip,
    type ChartData,
    type ChartOptions,
} from 'chart.js';
import { computed, onMounted, ref } from 'vue';
import { Pie } from 'vue-chartjs';

// Register Chart.js components
ChartJS.register(ArcElement, Tooltip, Legend);

interface LifecycleItem {
    status: string;
    label: string;
    count: number;
    percentage: number;
}

interface Props {
    distribution: LifecycleItem[];
}

const props = defineProps<Props>();

// Ref to track if component is mounted
const isMounted = ref(false);

onMounted(() => {
    isMounted.value = true;
});

// Lifecycle status colors
const statusColors: Record<string, string> = {
    ordered: '#3b82f6', // blue-500
    received: '#06b6d4', // cyan-500
    in_stock: '#14b8a6', // teal-500
    deployed: '#22c55e', // green-500
    maintenance: '#f59e0b', // amber-500
    decommissioned: '#f97316', // orange-500
    disposed: '#6b7280', // gray-500
};

// Check if there is valid data to display
const hasData = computed(() => {
    return (
        props.distribution.length > 0 &&
        props.distribution.some((item) => item.count > 0)
    );
});

// Calculate total devices
const totalDevices = computed(() => {
    return props.distribution.reduce((sum, item) => sum + item.count, 0);
});

// Filter out items with zero count for the chart
const filteredDistribution = computed(() => {
    return props.distribution.filter((item) => item.count > 0);
});

// Prepare chart data
const chartData = computed<ChartData<'pie', number[], string>>(() => {
    const labels = filteredDistribution.value.map((item) => item.label);
    const data = filteredDistribution.value.map((item) => item.count);
    const backgroundColors = filteredDistribution.value.map(
        (item) => statusColors[item.status] || '#6b7280',
    );

    return {
        labels,
        datasets: [
            {
                data,
                backgroundColor: backgroundColors,
                borderColor: 'white',
                borderWidth: 2,
                hoverOffset: 8,
            },
        ],
    };
});

// Chart options
const chartOptions = computed<ChartOptions<'pie'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'right',
            labels: {
                padding: 16,
                usePointStyle: true,
                pointStyle: 'circle',
                font: {
                    size: 12,
                },
                generateLabels: (chart) => {
                    const data = chart.data;
                    if (data.labels && data.datasets) {
                        return data.labels.map((label, i) => {
                            const dataset = data.datasets[0];
                            const value = dataset.data[i] as number;
                            const percentage =
                                totalDevices.value > 0
                                    ? (
                                          (value / totalDevices.value) *
                                          100
                                      ).toFixed(1)
                                    : '0';
                            return {
                                text: `${label} (${value} - ${percentage}%)`,
                                fillStyle: (
                                    dataset.backgroundColor as string[]
                                )[i],
                                strokeStyle: (
                                    dataset.backgroundColor as string[]
                                )[i],
                                hidden: false,
                                index: i,
                            };
                        });
                    }
                    return [];
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
                label: (context) => {
                    const value = context.raw as number;
                    const percentage =
                        totalDevices.value > 0
                            ? ((value / totalDevices.value) * 100).toFixed(1)
                            : '0';
                    return `${context.label}: ${value} devices (${percentage}%)`;
                },
            },
        },
    },
}));
</script>

<template>
    <Card class="transition-all duration-200 hover:shadow-md">
        <CardHeader class="pb-2">
            <CardTitle class="text-base font-medium">
                Lifecycle Distribution
            </CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Chart with data -->
            <div v-if="isMounted && hasData" class="h-64">
                <Pie :data="chartData" :options="chartOptions" />
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
                        d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"
                    />
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"
                    />
                </svg>
                <p class="text-sm text-muted-foreground">
                    No device data available
                </p>
                <p class="mt-1 text-xs text-muted-foreground/70">
                    Add devices to see lifecycle distribution
                </p>
            </div>

            <!-- Summary stats -->
            <div
                v-if="hasData"
                class="mt-4 flex justify-between border-t pt-3 text-xs text-muted-foreground"
            >
                <span>Total Devices: {{ totalDevices }}</span>
                <span>{{ filteredDistribution.length }} active statuses</span>
            </div>
        </CardContent>
    </Card>
</template>
