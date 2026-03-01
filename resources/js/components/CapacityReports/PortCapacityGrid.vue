<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Network, Zap, Cable } from 'lucide-vue-next';

interface PortCapacityItem {
    total_ports: number;
    connected_ports: number;
    available_ports: number;
    label: string;
}

interface Props {
    portStats: Record<string, PortCapacityItem>;
    showTitle?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showTitle: true,
});

// Check if there's any port data
const hasData = computed(() => {
    return Object.keys(props.portStats).length > 0;
});

// Get icon for port type
const getPortIcon = (portType: string) => {
    const type = portType.toLowerCase();
    if (type.includes('ethernet') || type.includes('network')) {
        return Network;
    }
    if (type.includes('power')) {
        return Zap;
    }
    if (type.includes('fiber')) {
        return Cable;
    }
    return Cable;
};

// Get utilization percentage for a port type
const getUtilization = (stats: PortCapacityItem): number => {
    if (stats.total_ports === 0) return 0;
    return Math.round((stats.connected_ports / stats.total_ports) * 100);
};

// Get color class based on utilization
const getUtilizationColorClass = (utilization: number): string => {
    if (utilization >= 90) return 'text-red-600 dark:text-red-400';
    if (utilization >= 80) return 'text-amber-600 dark:text-amber-400';
    return 'text-green-600 dark:text-green-400';
};

// Get progress bar color class based on utilization
const getProgressBarClass = (utilization: number): string => {
    if (utilization >= 90) return 'bg-red-500';
    if (utilization >= 80) return 'bg-amber-500';
    return 'bg-green-500';
};
</script>

<template>
    <Card>
        <CardHeader v-if="showTitle">
            <CardTitle class="text-base">Port Capacity by Type</CardTitle>
        </CardHeader>
        <CardContent :class="{ 'pt-6': !showTitle }">
            <div v-if="hasData" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="(stats, portType) in portStats"
                    :key="portType"
                    class="rounded-lg border p-4 transition-colors hover:bg-muted/30"
                >
                    <!-- Port type header with icon -->
                    <div class="mb-3 flex items-center gap-2">
                        <component :is="getPortIcon(portType)" class="size-5 text-muted-foreground" />
                        <h4 class="font-medium">{{ stats.label }}</h4>
                    </div>

                    <!-- Utilization bar -->
                    <div class="mb-3">
                        <div class="mb-1 flex items-baseline justify-between">
                            <span class="text-sm text-muted-foreground">Utilization</span>
                            <span
                                class="text-sm font-medium"
                                :class="getUtilizationColorClass(getUtilization(stats))"
                            >
                                {{ getUtilization(stats) }}%
                            </span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full transition-all duration-300"
                                :class="getProgressBarClass(getUtilization(stats))"
                                :style="{ width: `${getUtilization(stats)}%` }"
                            />
                        </div>
                    </div>

                    <!-- Stats grid -->
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Total:</span>
                            <span class="font-medium">{{ stats.total_ports.toLocaleString() }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Connected:</span>
                            <span class="font-medium">{{ stats.connected_ports.toLocaleString() }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Available:</span>
                            <span class="font-medium text-green-600 dark:text-green-400">
                                {{ stats.available_ports.toLocaleString() }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-else class="py-8 text-center text-muted-foreground">
                No port data available
            </div>
        </CardContent>
    </Card>
</template>
