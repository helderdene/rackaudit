<script setup lang="ts">
/**
 * CableTypeDistributionChart Component
 *
 * Displays a pie/donut chart showing the distribution of connections
 * by cable type using Chart.js.
 */

import { computed, onMounted, ref } from 'vue';
import { Doughnut } from 'vue-chartjs';
import {
    Chart as ChartJS,
    ArcElement,
    Tooltip,
    Legend,
    type ChartOptions,
    type ChartData,
} from 'chart.js';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Cable } from 'lucide-vue-next';

// Register Chart.js components
ChartJS.register(ArcElement, Tooltip, Legend);

interface CableTypeItem {
    type: string;
    label: string;
    count: number;
    percentage: number;
}

interface Props {
    distribution: CableTypeItem[];
}

const props = defineProps<Props>();

// Ref to track if component is mounted
const isMounted = ref(false);

onMounted(() => {
    isMounted.value = true;
});

// Cable type colors - consistent color coding
const cableTypeColors: Record<string, string> = {
    cat5e: '#60a5fa',      // blue-400
    cat6: '#3b82f6',       // blue-500
    cat6a: '#2563eb',      // blue-600
    fiber_sm: '#a855f7',   // purple-500
    fiber_mm: '#9333ea',   // purple-600
    c13: '#f59e0b',        // amber-500
    c14: '#d97706',        // amber-600
    c19: '#f97316',        // orange-500
    c20: '#ea580c',        // orange-600
};

// Get color for cable type with fallback
const getColor = (type: string): string => {
    return cableTypeColors[type.toLowerCase()] ?? '#6b7280'; // gray-500 fallback
};

// Check if there is valid data to display
const hasData = computed(() => {
    return props.distribution.length > 0 && props.distribution.some(item => item.count > 0);
});

// Calculate total connections
const totalConnections = computed(() => {
    return props.distribution.reduce((sum, item) => sum + item.count, 0);
});

// Filter out items with zero count for the chart
const filteredDistribution = computed(() => {
    return props.distribution.filter(item => item.count > 0);
});

// Prepare chart data
const chartData = computed<ChartData<'doughnut', number[], string>>(() => {
    const labels = filteredDistribution.value.map(item => item.label);
    const data = filteredDistribution.value.map(item => item.count);
    const backgroundColors = filteredDistribution.value.map(item => getColor(item.type));
    const hoverBackgroundColors = backgroundColors.map(color =>
        color.replace(/f/, 'a').replace(/e/, 'b') // Slightly lighter on hover
    );

    return {
        labels,
        datasets: [
            {
                data,
                backgroundColor: backgroundColors,
                hoverBackgroundColor: hoverBackgroundColors,
                borderColor: 'white',
                borderWidth: 2,
                hoverOffset: 4,
            },
        ],
    };
});

// Chart options
const chartOptions = computed<ChartOptions<'doughnut'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    cutout: '55%',
    plugins: {
        legend: {
            position: 'right',
            labels: {
                padding: 12,
                usePointStyle: true,
                pointStyle: 'circle',
                font: {
                    size: 11,
                },
                generateLabels: (chart) => {
                    const data = chart.data;
                    if (data.labels && data.datasets) {
                        return data.labels.map((label, i) => {
                            const dataset = data.datasets[0];
                            const value = dataset.data[i] as number;
                            const percentage = totalConnections.value > 0
                                ? ((value / totalConnections.value) * 100).toFixed(1)
                                : '0';
                            return {
                                text: `${label} (${value})`,
                                fillStyle: (dataset.backgroundColor as string[])[i],
                                strokeStyle: (dataset.backgroundColor as string[])[i],
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
                    const percentage = totalConnections.value > 0
                        ? ((value / totalConnections.value) * 100).toFixed(1)
                        : '0';
                    return `${context.label}: ${value} connections (${percentage}%)`;
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
                Cable Type Distribution
            </CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Chart with data -->
            <div v-if="isMounted && hasData" class="relative h-64">
                <Doughnut :data="chartData" :options="chartOptions" />
                <!-- Center text showing total - positioned over donut (left ~50% of container since legend is on right) -->
                <div
                    class="pointer-events-none absolute top-0 bottom-0 left-0 flex w-1/2 flex-col items-center justify-center"
                >
                    <span class="text-2xl font-bold">{{ totalConnections }}</span>
                    <span class="text-xs text-muted-foreground">
                        {{ totalConnections === 1 ? 'Connection' : 'Connections' }}
                    </span>
                </div>
            </div>

            <!-- Empty state -->
            <div
                v-else
                class="flex h-64 flex-col items-center justify-center rounded-lg border border-dashed border-muted-foreground/30"
            >
                <Cable class="mb-2 size-12 text-muted-foreground/50" />
                <p class="text-sm text-muted-foreground">No connection data available</p>
                <p class="mt-1 text-xs text-muted-foreground/70">Add connections to see cable type distribution</p>
            </div>

            <!-- Summary stats -->
            <div v-if="hasData" class="mt-4 flex justify-between border-t pt-3 text-xs text-muted-foreground">
                <span>{{ filteredDistribution.length }} cable types in use</span>
                <span>Total: {{ totalConnections }} connections</span>
            </div>
        </CardContent>
    </Card>
</template>
