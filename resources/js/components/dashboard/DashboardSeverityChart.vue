<script setup lang="ts">
/**
 * DashboardSeverityChart Component
 *
 * Simplified version of SeverityDistributionChart for the main dashboard.
 * Displays finding severity distribution as a donut chart with click-to-navigate.
 */

import { computed, ref } from 'vue';
import { Doughnut } from 'vue-chartjs';
import { Chart as ChartJS, ArcElement, Tooltip, Legend, type ChartOptions, type ChartData } from 'chart.js';
import { router } from '@inertiajs/vue3';
import FindingController from '@/actions/App/Http/Controllers/FindingController';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { SeverityMetrics, FindingSeverityValue } from '@/types/dashboard';

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
const severityOrder: FindingSeverityValue[] = ['critical', 'high', 'medium', 'low'];

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
                padding: 12,
                usePointStyle: true,
                pointStyle: 'circle',
                font: {
                    size: 11,
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
                            ? Math.round((value / props.severityMetrics.total) * 100)
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
    <Card
        class="transition-all duration-200 hover:border-border/80 hover:shadow-md dark:hover:border-border/60"
    >
        <CardHeader class="pb-2">
            <CardTitle class="text-sm font-medium text-muted-foreground dark:text-muted-foreground">
                Open Findings by Severity
            </CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Chart with data -->
            <div v-if="hasData" class="relative mx-auto max-w-xs">
                <Doughnut :data="chartData" :options="chartOptions" />
                <!-- Center text showing total -->
                <div
                    class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center"
                >
                    <span class="text-2xl font-bold">{{ severityMetrics.total }}</span>
                    <span class="text-xs text-muted-foreground">
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
                <p class="text-sm text-muted-foreground">No open findings</p>
                <p class="mt-1 text-xs text-muted-foreground/70">All findings have been resolved</p>
            </div>

            <!-- Click hint -->
            <p v-if="hasData" class="mt-3 text-center text-xs text-muted-foreground">
                Click a segment to view findings
            </p>
        </CardContent>
    </Card>
</template>
