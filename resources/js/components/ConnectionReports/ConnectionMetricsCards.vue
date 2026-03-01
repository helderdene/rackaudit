<script setup lang="ts">
/**
 * ConnectionMetricsCards Component
 *
 * Displays metric cards showing connection statistics:
 * - Total connections count
 * - Connections breakdown by port type (Ethernet, Fiber, Power)
 * - Cable length statistics (mean, min, max)
 */

import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Cable, Network, Zap, Waves, Ruler } from 'lucide-vue-next';

interface PortTypeDistributionItem {
    type: string;
    label: string;
    count: number;
    percentage: number;
}

interface CableLengthStats {
    mean: number | null;
    min: number | null;
    max: number | null;
    count: number;
}

interface Props {
    totalConnections: number;
    portTypeDistribution: PortTypeDistributionItem[];
    cableLengthStats: CableLengthStats;
}

const props = defineProps<Props>();

// Port type icon and color mapping
const portTypeConfig: Record<string, { icon: typeof Cable; colorClass: string; bgClass: string }> = {
    ethernet: {
        icon: Network,
        colorClass: 'text-blue-600 dark:text-blue-400',
        bgClass: 'bg-blue-50 dark:bg-blue-900/20',
    },
    fiber: {
        icon: Waves,
        colorClass: 'text-purple-600 dark:text-purple-400',
        bgClass: 'bg-purple-50 dark:bg-purple-900/20',
    },
    power: {
        icon: Zap,
        colorClass: 'text-amber-600 dark:text-amber-400',
        bgClass: 'bg-amber-50 dark:bg-amber-900/20',
    },
};

// Get config for port type, with fallback
const getPortTypeConfig = (type: string) => {
    return portTypeConfig[type.toLowerCase()] ?? {
        icon: Cable,
        colorClass: 'text-gray-600 dark:text-gray-400',
        bgClass: 'bg-gray-50 dark:bg-gray-900/20',
    };
};

// Get port type items with icons
const portTypeItems = computed(() => {
    return props.portTypeDistribution
        .filter(item => item.count > 0)
        .map(item => ({
            ...item,
            config: getPortTypeConfig(item.type),
        }));
});

// Format cable length with unit
const formatLength = (value: number | null): string => {
    if (value === null) return '-';
    return `${value.toFixed(1)}m`;
};

// Check if cable length stats have data
const hasCableLengthData = computed(() => {
    return props.cableLengthStats.count > 0;
});
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Total Connections Card -->
        <Card class="transition-all duration-200 hover:shadow-md">
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium text-muted-foreground">
                    Total Connections
                </CardTitle>
                <div class="rounded-full bg-primary/10 p-2">
                    <Cable class="size-4 text-primary" />
                </div>
            </CardHeader>
            <CardContent>
                <div class="text-3xl font-bold">{{ totalConnections }}</div>
                <p class="mt-1 text-xs text-muted-foreground">
                    Active connections in scope
                </p>
            </CardContent>
        </Card>

        <!-- Port Type Cards (show up to 3 types) -->
        <Card
            v-for="item in portTypeItems.slice(0, 3)"
            :key="item.type"
            class="transition-all duration-200 hover:shadow-md"
        >
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium text-muted-foreground">
                    {{ item.label }}
                </CardTitle>
                <div :class="['rounded-full p-2', item.config.bgClass]">
                    <component
                        :is="item.config.icon"
                        :class="['size-4', item.config.colorClass]"
                    />
                </div>
            </CardHeader>
            <CardContent>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-bold">{{ item.count }}</span>
                    <span class="text-sm text-muted-foreground">
                        ({{ item.percentage.toFixed(1) }}%)
                    </span>
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ item.label }} connections
                </p>
            </CardContent>
        </Card>
    </div>

    <!-- Cable Length Statistics Card -->
    <Card v-if="hasCableLengthData" class="mt-4 transition-all duration-200 hover:shadow-md">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="flex items-center gap-2 text-base font-medium">
                <Ruler class="size-4 text-muted-foreground" />
                Cable Length Statistics
            </CardTitle>
            <span class="text-xs text-muted-foreground">
                Based on {{ cableLengthStats.count }} connections with length data
            </span>
        </CardHeader>
        <CardContent>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="text-center">
                    <p class="text-sm text-muted-foreground">Average</p>
                    <p class="text-xl font-semibold">{{ formatLength(cableLengthStats.mean) }}</p>
                </div>
                <div class="text-center">
                    <p class="text-sm text-muted-foreground">Minimum</p>
                    <p class="text-xl font-semibold">{{ formatLength(cableLengthStats.min) }}</p>
                </div>
                <div class="text-center">
                    <p class="text-sm text-muted-foreground">Maximum</p>
                    <p class="text-xl font-semibold">{{ formatLength(cableLengthStats.max) }}</p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
