<script setup lang="ts">
/**
 * Severity Distribution Donut Chart Component
 *
 * Displays finding severity distribution as a donut chart with interactive
 * click functionality to filter findings by severity level.
 */

import FindingController from '@/actions/App/Http/Controllers/FindingController';
import type { FindingSeverityValue, SeverityMetrics } from '@/types/dashboard';
import { router } from '@inertiajs/vue3';
import {
    ArcElement,
    Chart as ChartJS,
    Legend,
    Tooltip,
    type ChartData,
    type ChartOptions,
} from 'chart.js';
import { computed } from 'vue';
import { Doughnut } from 'vue-chartjs';

// Register Chart.js components
ChartJS.register(ArcElement, Tooltip, Legend);

const props = defineProps<{
    severityMetrics: SeverityMetrics;
}>();

// Map severity to colors matching the UI badges
const severityColors: Record<FindingSeverityValue, string> = {
    critical: 'rgb(239, 68, 68)', // red-500
    high: 'rgb(249, 115, 22)', // orange-500
    medium: 'rgb(234, 179, 8)', // yellow-500
    low: 'rgb(59, 130, 246)', // blue-500
};

// Hover colors (slightly lighter)
const severityHoverColors: Record<FindingSeverityValue, string> = {
    critical: 'rgb(248, 113, 113)', // red-400
    high: 'rgb(251, 146, 60)', // orange-400
    medium: 'rgb(250, 204, 21)', // yellow-400
    low: 'rgb(96, 165, 250)', // blue-400
};

// Severity order for consistent display
const severityOrder: FindingSeverityValue[] = [
    'critical',
    'high',
    'medium',
    'low',
];

// Check if there is any data to display
const hasData = computed(() => props.severityMetrics.total > 0);

// Prepare chart data
const chartData = computed<ChartData<'doughnut', number[], string>>(() => {
    const labels: string[] = [];
    const data: number[] = [];
    const backgroundColor: string[] = [];
    const hoverBackgroundColor: string[] = [];

    severityOrder.forEach((severity) => {
        const metric = props.severityMetrics[severity];
        labels.push(metric.label);
        data.push(metric.count);
        backgroundColor.push(severityColors[severity]);
        hoverBackgroundColor.push(severityHoverColors[severity]);
    });

    return {
        labels,
        datasets: [
            {
                data,
                backgroundColor,
                hoverBackgroundColor,
                borderWidth: 0,
                hoverOffset: 4,
            },
        ],
    };
});

// Chart options with responsive sizing and tooltips
const chartOptions = computed<ChartOptions<'doughnut'>>(() => ({
    responsive: true,
    maintainAspectRatio: true,
    plugins: {
        legend: {
            position: 'bottom',
            labels: {
                padding: 16,
                usePointStyle: true,
                pointStyle: 'circle',
                font: {
                    size: 12,
                },
            },
        },
        tooltip: {
            callbacks: {
                label: (context) => {
                    const label = context.label || '';
                    const value = context.raw as number;
                    const percentage =
                        props.severityMetrics.total > 0
                            ? Math.round(
                                  (value / props.severityMetrics.total) * 100,
                              )
                            : 0;
                    return `${label}: ${value} (${percentage}%)`;
                },
            },
        },
    },
    onClick: (_event, elements) => {
        if (elements.length > 0) {
            const index = elements[0].index;
            const severity = severityOrder[index];
            navigateToFindingsBySeverity(severity);
        }
    },
    onHover: (event, elements) => {
        const target = event.native?.target as HTMLElement | undefined;
        if (target) {
            target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
        }
    },
    cutout: '60%', // Creates the donut hole
}));

// Navigate to findings list filtered by severity
const navigateToFindingsBySeverity = (severity: FindingSeverityValue) => {
    router.get(FindingController.index.url({ query: { severity } }));
};
</script>

<template>
    <div class="w-full">
        <!-- Chart with data -->
        <div v-if="hasData" class="relative mx-auto max-w-xs">
            <Doughnut :data="chartData" :options="chartOptions" />
            <!-- Center text showing total -->
            <div
                class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center"
            >
                <span class="text-3xl font-bold">{{
                    severityMetrics.total
                }}</span>
                <span class="text-sm text-muted-foreground">
                    {{ severityMetrics.total === 1 ? 'Finding' : 'Findings' }}
                </span>
            </div>
        </div>

        <!-- Empty state -->
        <div
            v-else
            class="flex h-48 flex-col items-center justify-center rounded-lg border border-dashed border-muted-foreground/30"
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
                    d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"
                />
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"
                />
            </svg>
            <p class="text-sm text-muted-foreground">
                No findings in selected period
            </p>
        </div>

        <!-- Click hint -->
        <p
            v-if="hasData"
            class="mt-3 text-center text-xs text-muted-foreground"
        >
            Click a segment to view findings
        </p>
    </div>
</template>
