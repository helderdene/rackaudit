<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge, type BadgeVariants } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { AlertTriangle, AlertCircle, GitCompare, Shuffle, Settings, ChevronRight, RefreshCw } from 'lucide-vue-next';

interface Props {
    datacenterId: number;
}

const props = defineProps<Props>();

// State
const isLoading = ref(true);
const hasError = ref(false);
const summary = ref<{
    total: number;
    by_type: Record<string, number>;
} | null>(null);

// Type configuration with icons
const typeConfig: Record<string, { icon: typeof AlertTriangle; variant: BadgeVariants['variant'] }> = {
    missing: { icon: AlertTriangle, variant: 'destructive' },
    unexpected: { icon: AlertCircle, variant: 'warning' },
    mismatched: { icon: GitCompare, variant: 'info' },
    conflicting: { icon: Shuffle, variant: 'destructive' },
    configuration_mismatch: { icon: Settings, variant: 'warning' },
};

// Has discrepancies
const hasDiscrepancies = computed(() => {
    return summary.value && summary.value.total > 0;
});

// Get type counts for display
const typeCounts = computed(() => {
    if (!summary.value) return [];
    return Object.entries(summary.value.by_type)
        .filter(([, count]) => count > 0)
        .map(([type, count]) => ({
            type,
            count,
            config: typeConfig[type] || { icon: AlertCircle, variant: 'secondary' as BadgeVariants['variant'] },
            label: type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()),
        }));
});

// Fetch summary data
const fetchSummary = async () => {
    isLoading.value = true;
    hasError.value = false;

    try {
        const response = await fetch(`/api/discrepancies/summary?datacenter_id=${props.datacenterId}`, {
            headers: {
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('Failed to fetch summary');
        }

        const data = await response.json();
        summary.value = {
            total: data.data.total,
            by_type: data.data.by_type,
        };
    } catch (e) {
        console.error('Error fetching discrepancy summary:', e);
        hasError.value = true;
    } finally {
        isLoading.value = false;
    }
};

// Refresh data
const refresh = () => {
    fetchSummary();
};

// Load on mount
onMounted(() => {
    fetchSummary();
});
</script>

<template>
    <Card>
        <CardHeader class="flex flex-row items-center justify-between pb-2">
            <CardTitle class="flex items-center gap-2 text-lg">
                <AlertTriangle class="size-5" />
                Discrepancies
                <Badge
                    v-if="hasDiscrepancies"
                    variant="destructive"
                    class="ml-1"
                >
                    {{ summary?.total }}
                </Badge>
            </CardTitle>
            <div class="flex items-center gap-2">
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-8"
                    @click="refresh"
                    :disabled="isLoading"
                >
                    <RefreshCw class="size-4" :class="{ 'animate-spin': isLoading }" />
                </Button>
            </div>
        </CardHeader>
        <CardContent>
            <!-- Loading State -->
            <div v-if="isLoading" class="space-y-3">
                <Skeleton class="h-4 w-32" />
                <div class="flex flex-wrap gap-2">
                    <Skeleton class="h-6 w-20" />
                    <Skeleton class="h-6 w-24" />
                    <Skeleton class="h-6 w-20" />
                </div>
            </div>

            <!-- Error State -->
            <div v-else-if="hasError" class="text-sm text-muted-foreground">
                <p class="mb-2">Unable to load discrepancy data.</p>
                <Button variant="outline" size="sm" @click="refresh">
                    Try Again
                </Button>
            </div>

            <!-- No Discrepancies -->
            <div v-else-if="!hasDiscrepancies" class="text-sm text-muted-foreground">
                <p class="flex items-center gap-2">
                    <span class="text-green-500">No open discrepancies</span>
                </p>
            </div>

            <!-- Has Discrepancies -->
            <div v-else class="space-y-3">
                <!-- Type Breakdown -->
                <div class="flex flex-wrap gap-2">
                    <div
                        v-for="item in typeCounts"
                        :key="item.type"
                        class="flex items-center gap-1"
                    >
                        <Badge :variant="item.config.variant" class="gap-1">
                            <component :is="item.config.icon" class="size-3" />
                            {{ item.count }} {{ item.label }}
                        </Badge>
                    </div>
                </div>

                <!-- Link to Dashboard -->
                <Link :href="`/discrepancies?datacenter_id=${datacenterId}`">
                    <Button variant="outline" size="sm" class="mt-2 w-full gap-1">
                        View All Discrepancies
                        <ChevronRight class="size-4" />
                    </Button>
                </Link>
            </div>
        </CardContent>
    </Card>
</template>
