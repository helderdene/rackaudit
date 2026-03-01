<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { UtilizationStats } from '@/types/rooms';
import { BarChart3 } from 'lucide-vue-next';

interface Props {
    /** Utilization statistics to display */
    stats: UtilizationStats;
    /** Additional CSS classes */
    class?: HTMLAttributes['class'];
}

const props = defineProps<Props>();

/**
 * Get color class based on utilization percentage
 */
const utilizationColor = computed(() => {
    const percent = props.stats.utilizationPercent;
    if (percent >= 90) return 'bg-red-500';
    if (percent >= 70) return 'bg-yellow-500';
    return 'bg-green-500';
});

/**
 * Format percentage for display
 */
const formattedPercent = computed(() => {
    return `${Math.round(props.stats.utilizationPercent)}%`;
});

/**
 * Check if we have separate front/rear stats
 */
const hasSeparateStats = computed(() => {
    return props.stats.frontUsedU !== undefined && props.stats.rearUsedU !== undefined;
});
</script>

<template>
    <Card :class="cn('', props.class)">
        <CardHeader>
            <CardTitle class="flex items-center gap-2 text-base">
                <BarChart3 class="size-4" />
                Rack Utilization
            </CardTitle>
        </CardHeader>
        <CardContent>
            <div class="space-y-4">
                <!-- Progress bar -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-muted-foreground">Capacity</span>
                        <span class="font-medium">{{ formattedPercent }}</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-muted">
                        <div
                            :class="cn('h-full transition-all', utilizationColor)"
                            :style="{ width: `${stats.utilizationPercent}%` }"
                        />
                    </div>
                </div>

                <!-- Statistics grid -->
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div>
                        <div class="text-2xl font-bold">{{ stats.totalU }}</div>
                        <div class="text-xs text-muted-foreground">Total U</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-primary">{{ stats.usedU }}</div>
                        <div class="text-xs text-muted-foreground">Used</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ stats.availableU }}</div>
                        <div class="text-xs text-muted-foreground">Available</div>
                    </div>
                </div>

                <!-- Front/Rear breakdown (if available) -->
                <div v-if="hasSeparateStats" class="border-t pt-4">
                    <div class="grid grid-cols-2 gap-4 text-center">
                        <div>
                            <div class="text-lg font-semibold">{{ stats.frontUsedU }}</div>
                            <div class="text-xs text-muted-foreground">Front Used</div>
                        </div>
                        <div>
                            <div class="text-lg font-semibold">{{ stats.rearUsedU }}</div>
                            <div class="text-xs text-muted-foreground">Rear Used</div>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
