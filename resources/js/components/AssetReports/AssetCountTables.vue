<script setup lang="ts">
/**
 * AssetCountTables Component
 *
 * Displays two side-by-side summary tables:
 * - Counts by Device Type
 * - Counts by Manufacturer
 */

import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Cpu, Building2 } from 'lucide-vue-next';

interface CountItem {
    name: string;
    count: number;
}

interface Props {
    countsByType: CountItem[];
    countsByManufacturer: CountItem[];
}

const props = defineProps<Props>();

// Calculate totals
const totalByType = computed(() => {
    return props.countsByType.reduce((sum, item) => sum + item.count, 0);
});

const totalByManufacturer = computed(() => {
    return props.countsByManufacturer.reduce((sum, item) => sum + item.count, 0);
});

// Sort by count descending
const sortedByType = computed(() => {
    return [...props.countsByType].sort((a, b) => b.count - a.count);
});

const sortedByManufacturer = computed(() => {
    return [...props.countsByManufacturer].sort((a, b) => b.count - a.count);
});

// Get percentage for a count
const getPercentage = (count: number, total: number): string => {
    if (total === 0) return '0';
    return ((count / total) * 100).toFixed(1);
};

// Get progress bar width
const getBarWidth = (count: number, total: number): string => {
    if (total === 0) return '0%';
    return `${(count / total) * 100}%`;
};
</script>

<template>
    <div class="grid gap-4 md:grid-cols-2">
        <!-- Counts by Device Type -->
        <Card class="transition-all duration-200 hover:shadow-md">
            <CardHeader class="pb-3">
                <CardTitle class="flex items-center gap-2 text-base">
                    <Cpu class="size-5 text-primary" />
                    By Device Type
                </CardTitle>
            </CardHeader>
            <CardContent>
                <!-- Empty state -->
                <div
                    v-if="countsByType.length === 0"
                    class="py-8 text-center text-muted-foreground"
                >
                    No device types found
                </div>

                <!-- Table -->
                <div v-else class="space-y-3">
                    <div
                        v-for="item in sortedByType"
                        :key="item.name"
                        class="space-y-1"
                    >
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-medium">{{ item.name }}</span>
                            <span class="text-muted-foreground">
                                {{ item.count }}
                                <span class="text-xs">({{ getPercentage(item.count, totalByType) }}%)</span>
                            </span>
                        </div>
                        <!-- Progress bar -->
                        <div class="h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full rounded-full bg-primary transition-all duration-300"
                                :style="{ width: getBarWidth(item.count, totalByType) }"
                            />
                        </div>
                    </div>

                    <!-- Total -->
                    <div class="mt-4 flex items-center justify-between border-t pt-3 text-sm font-medium">
                        <span>Total</span>
                        <span>{{ totalByType }} devices</span>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Counts by Manufacturer -->
        <Card class="transition-all duration-200 hover:shadow-md">
            <CardHeader class="pb-3">
                <CardTitle class="flex items-center gap-2 text-base">
                    <Building2 class="size-5 text-primary" />
                    By Manufacturer
                </CardTitle>
            </CardHeader>
            <CardContent>
                <!-- Empty state -->
                <div
                    v-if="countsByManufacturer.length === 0"
                    class="py-8 text-center text-muted-foreground"
                >
                    No manufacturers found
                </div>

                <!-- Table -->
                <div v-else class="space-y-3">
                    <div
                        v-for="item in sortedByManufacturer"
                        :key="item.name"
                        class="space-y-1"
                    >
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-medium">{{ item.name || 'Unknown' }}</span>
                            <span class="text-muted-foreground">
                                {{ item.count }}
                                <span class="text-xs">({{ getPercentage(item.count, totalByManufacturer) }}%)</span>
                            </span>
                        </div>
                        <!-- Progress bar -->
                        <div class="h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full rounded-full bg-teal-500 transition-all duration-300"
                                :style="{ width: getBarWidth(item.count, totalByManufacturer) }"
                            />
                        </div>
                    </div>

                    <!-- Total -->
                    <div class="mt-4 flex items-center justify-between border-t pt-3 text-sm font-medium">
                        <span>Total</span>
                        <span>{{ totalByManufacturer }} devices</span>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
