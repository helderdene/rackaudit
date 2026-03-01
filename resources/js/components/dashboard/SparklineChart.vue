<script setup lang="ts">
/**
 * SparklineChart Component
 *
 * A minimal line chart component for displaying 7-day trend data.
 * Renders at fixed dimensions (80px x 30px) without axes, labels, or tooltips.
 */

import {
    CategoryScale,
    Chart as ChartJS,
    LinearScale,
    LineElement,
    PointElement,
    type ChartData,
    type ChartOptions,
} from 'chart.js';
import { computed, onMounted, ref, watch } from 'vue';
import { Line } from 'vue-chartjs';

// Register only the Chart.js components needed for sparklines
ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement);

interface Props {
    data: number[];
    color?: string;
}

const props = withDefaults(defineProps<Props>(), {
    color: 'rgb(59, 130, 246)', // blue-500 default
});

// Ref to track if component is mounted
const isMounted = ref(false);

onMounted(() => {
    isMounted.value = true;
});

// Prepare chart data
const chartData = computed<ChartData<'line', number[], string>>(() => {
    // Use empty labels since we hide the x-axis
    const labels = props.data.map((_, index) => String(index + 1));

    return {
        labels,
        datasets: [
            {
                data: props.data,
                borderColor: props.color,
                borderWidth: 2,
                fill: false,
                tension: 0.4, // Smooth curve interpolation
                pointRadius: 0, // Hide data points
                pointHoverRadius: 0, // Hide hover points
            },
        ],
    };
});

// Chart options configured for sparkline appearance
const chartOptions = computed<ChartOptions<'line'>>(() => ({
    responsive: false, // Fixed dimensions
    maintainAspectRatio: false,
    animation: false, // Disable animations for performance
    plugins: {
        legend: {
            display: false, // Hide legend
        },
        title: {
            display: false, // Hide title
        },
        tooltip: {
            enabled: false, // Hide tooltips
        },
    },
    scales: {
        x: {
            display: false, // Hide x-axis
            grid: {
                display: false, // Hide grid lines
            },
        },
        y: {
            display: false, // Hide y-axis
            grid: {
                display: false, // Hide grid lines
            },
            // Set min/max to give some padding to the line
            suggestedMin: Math.min(...props.data) * 0.9,
            suggestedMax: Math.max(...props.data) * 1.1,
        },
    },
    elements: {
        line: {
            borderWidth: 2,
        },
    },
}));

// Watch for data changes to update chart
watch(
    () => props.data,
    () => {
        // Chart will automatically update via computed properties
    },
    { deep: true },
);
</script>

<template>
    <div class="sparkline-chart" style="width: 80px; height: 30px">
        <Line
            v-if="isMounted && data.length > 0"
            :data="chartData"
            :options="chartOptions"
        />
    </div>
</template>

<style scoped>
.sparkline-chart {
    display: inline-block;
}

.sparkline-chart canvas {
    width: 80px !important;
    height: 30px !important;
}
</style>
