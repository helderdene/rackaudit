<script setup lang="ts">
/**
 * PortUtilizationChart Component
 *
 * Displays a horizontal bar chart showing port utilization by type
 * using Chart.js.
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
import { Activity } from 'lucide-vue-next';
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

interface PortUtilizationByType {
    type: string;
    label: string;
    total: number;
    connected: number;
    percentage: number;
}

interface PortUtilizationOverall {
    total: number;
    connected: number;
    percentage: number;
}

interface Props {
    byType: PortUtilizationByType[];
    overall: PortUtilizationOverall;
}

const props = defineProps<Props>();

// Ref to track if component is mounted
const isMounted = ref(false);

onMounted(() => {
    isMounted.value = true;
});

// Port type colors - consistent color coding
const portTypeColors: Record<string, { connected: string; available: string }> =
    {
        ethernet: {
            connected: 'rgba(59, 130, 246, 0.8)', // blue-500
            available: 'rgba(59, 130, 246, 0.2)',
        },
        fiber: {
            connected: 'rgba(168, 85, 247, 0.8)', // purple-500
            available: 'rgba(168, 85, 247, 0.2)',
        },
        power: {
            connected: 'rgba(245, 158, 11, 0.8)', // amber-500
            available: 'rgba(245, 158, 11, 0.2)',
        },
    };

// Get color for port type with fallback
const getColors = (type: string) => {
    return (
        portTypeColors[type.toLowerCase()] ?? {
            connected: 'rgba(107, 114, 128, 0.8)', // gray-500
            available: 'rgba(107, 114, 128, 0.2)',
        }
    );
};

// Check if there is valid data to display
const hasData = computed(() => {
    return (
        props.byType.length > 0 && props.byType.some((item) => item.total > 0)
    );
});

// Filter to only show types with ports
const activeTypes = computed(() => {
    return props.byType.filter((item) => item.total > 0);
});

// Prepare chart data for stacked horizontal bar
const chartData = computed<ChartData<'bar', number[], string>>(() => {
    const labels = activeTypes.value.map((item) => item.label);
    const connectedData = activeTypes.value.map((item) => item.connected);
    const availableData = activeTypes.value.map(
        (item) => item.total - item.connected,
    );
    const backgroundColors = activeTypes.value.map(
        (item) => getColors(item.type).connected,
    );
    const availableColors = activeTypes.value.map(
        (item) => getColors(item.type).available,
    );

    return {
        labels,
        datasets: [
            {
                label: 'Connected',
                data: connectedData,
                backgroundColor: backgroundColors,
                borderRadius: 4,
                barThickness: 24,
            },
            {
                label: 'Available',
                data: availableData,
                backgroundColor: availableColors,
                borderRadius: 4,
                barThickness: 24,
            },
        ],
    };
});

// Chart options for horizontal stacked bar
const chartOptions = computed<ChartOptions<'bar'>>(() => ({
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    scales: {
        x: {
            stacked: true,
            beginAtZero: true,
            grid: {
                display: true,
                color: 'rgba(0, 0, 0, 0.05)',
            },
            ticks: {
                stepSize: 5,
                font: {
                    size: 11,
                },
            },
        },
        y: {
            stacked: true,
            grid: {
                display: false,
            },
            ticks: {
                font: {
                    size: 12,
                },
            },
        },
    },
    plugins: {
        legend: {
            display: true,
            position: 'top',
            align: 'end',
            labels: {
                boxWidth: 12,
                padding: 12,
                font: {
                    size: 11,
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
                afterTitle: (context) => {
                    const index = context[0].dataIndex;
                    const item = activeTypes.value[index];
                    return `Total: ${item.total} ports`;
                },
                label: (context) => {
                    const value = context.raw as number;
                    const datasetLabel = context.dataset.label;
                    const index = context.dataIndex;
                    const item = activeTypes.value[index];
                    const percentage =
                        item.total > 0
                            ? ((value / item.total) * 100).toFixed(1)
                            : '0';
                    return `${datasetLabel}: ${value} (${percentage}%)`;
                },
            },
        },
    },
}));

// Format percentage for display
const formatPercentage = (value: number): string => {
    return `${value.toFixed(1)}%`;
};
</script>

<template>
    <Card class="transition-all duration-200 hover:shadow-md">
        <CardHeader class="pb-2">
            <div class="flex items-center justify-between">
                <CardTitle class="text-base font-medium">
                    Port Utilization by Type
                </CardTitle>
                <div v-if="hasData" class="flex items-center gap-2 text-sm">
                    <Activity class="size-4 text-muted-foreground" />
                    <span class="font-medium">{{
                        formatPercentage(overall.percentage)
                    }}</span>
                    <span class="text-muted-foreground">overall</span>
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
                <Activity class="mb-2 size-12 text-muted-foreground/50" />
                <p class="text-sm text-muted-foreground">
                    No port data available
                </p>
                <p class="mt-1 text-xs text-muted-foreground/70">
                    Add devices with ports to see utilization
                </p>
            </div>

            <!-- Summary stats -->
            <div
                v-if="hasData"
                class="mt-4 flex justify-between border-t pt-3 text-xs text-muted-foreground"
            >
                <span
                    >{{ overall.connected }} / {{ overall.total }} ports
                    connected</span
                >
                <span>{{ activeTypes.length }} port types</span>
            </div>
        </CardContent>
    </Card>
</template>
