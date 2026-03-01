<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { index as discrepanciesIndex } from '@/actions/App/Http/Controllers/DiscrepancyController';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge, type BadgeVariants } from '@/components/ui/badge';
import { AlertTriangle, AlertCircle, GitCompare, Shuffle, Settings, Building2 } from 'lucide-vue-next';

interface SummaryData {
    total: number;
    by_type: Record<string, number>;
    by_status: Record<string, number>;
    by_datacenter: Array<{
        id: number;
        name: string;
        count: number;
    }>;
}

interface Filters {
    discrepancy_type: string;
    datacenter_id: string;
    room_id: string;
    status: string;
    date_from: string;
    date_to: string;
    sort_by: string;
    sort_order: string;
}

interface Props {
    summary: SummaryData;
    filters: Filters;
}

const props = defineProps<Props>();

// Type configuration with icons, labels, and colors
const typeConfig = {
    missing: {
        label: 'Missing',
        icon: AlertTriangle,
        variant: 'destructive' as BadgeVariants['variant'],
        description: 'Expected connections not found',
    },
    unexpected: {
        label: 'Unexpected',
        icon: AlertCircle,
        variant: 'warning' as BadgeVariants['variant'],
        description: 'Connections not in implementation files',
    },
    mismatched: {
        label: 'Mismatched',
        icon: GitCompare,
        variant: 'info' as BadgeVariants['variant'],
        description: 'Connections to different endpoints',
    },
    conflicting: {
        label: 'Conflicting',
        icon: Shuffle,
        variant: 'destructive' as BadgeVariants['variant'],
        description: 'Conflicting entries across files',
    },
    configuration_mismatch: {
        label: 'Config Mismatch',
        icon: Settings,
        variant: 'warning' as BadgeVariants['variant'],
        description: 'Cable type or length differs',
    },
};

// Get counts for display
const typeCounts = computed(() => {
    return Object.entries(typeConfig).map(([key, config]) => ({
        key,
        ...config,
        count: props.summary.by_type[key] || 0,
    }));
});

// Get active datacenter if filtering
const activeDatacenter = computed(() => {
    if (!props.filters.datacenter_id) return null;
    return props.summary.by_datacenter.find(
        dc => String(dc.id) === props.filters.datacenter_id
    );
});

// Filter by type
const filterByType = (type: string) => {
    const params: Record<string, string> = {
        discrepancy_type: type,
    };
    if (props.filters.datacenter_id) {
        params.datacenter_id = props.filters.datacenter_id;
    }
    router.get(discrepanciesIndex.url(), params, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Filter by datacenter
const filterByDatacenter = (datacenterId: number) => {
    router.get(discrepanciesIndex.url(), {
        datacenter_id: String(datacenterId),
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Clear type filter
const clearTypeFilter = () => {
    const params: Record<string, string> = {};
    if (props.filters.datacenter_id) {
        params.datacenter_id = props.filters.datacenter_id;
    }
    router.get(discrepanciesIndex.url(), params, {
        preserveState: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="space-y-4">
        <!-- Total and Type Summary -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
            <!-- Total Count Card -->
            <Card class="sm:col-span-2 lg:col-span-1">
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        Total Open
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-3xl font-bold">{{ summary.total }}</div>
                    <p class="text-xs text-muted-foreground">
                        Active discrepancies
                    </p>
                </CardContent>
            </Card>

            <!-- Type Count Cards -->
            <Card
                v-for="type in typeCounts"
                :key="type.key"
                class="cursor-pointer transition-colors hover:bg-muted/50"
                :class="{
                    'ring-2 ring-primary': filters.discrepancy_type === type.key
                }"
                @click="filters.discrepancy_type === type.key ? clearTypeFilter() : filterByType(type.key)"
            >
                <CardHeader class="pb-2">
                    <CardTitle class="flex items-center justify-between text-sm font-medium text-muted-foreground">
                        <span class="flex items-center gap-1">
                            <component :is="type.icon" class="size-4" />
                            {{ type.label }}
                        </span>
                        <Badge v-if="type.count > 0" :variant="type.variant" class="ml-2">
                            {{ type.count }}
                        </Badge>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ type.count }}</div>
                    <p class="text-xs text-muted-foreground">
                        {{ type.description }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Datacenter Summary (when not filtered by datacenter) -->
        <Card v-if="!filters.datacenter_id && summary.by_datacenter.length > 0">
            <CardHeader class="pb-3">
                <CardTitle class="flex items-center gap-2 text-sm font-medium">
                    <Building2 class="size-4" />
                    By Datacenter
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="dc in summary.by_datacenter"
                        :key="dc.id"
                        class="inline-flex items-center gap-2 rounded-lg border bg-card px-3 py-2 text-sm transition-colors hover:bg-muted"
                        @click="filterByDatacenter(dc.id)"
                    >
                        <span class="font-medium">{{ dc.name }}</span>
                        <Badge variant="secondary">{{ dc.count }}</Badge>
                    </button>
                </div>
            </CardContent>
        </Card>

        <!-- Active Datacenter Filter Indicator -->
        <div v-if="activeDatacenter" class="flex items-center gap-2 text-sm text-muted-foreground">
            <Building2 class="size-4" />
            <span>Showing discrepancies for <strong class="text-foreground">{{ activeDatacenter.name }}</strong></span>
        </div>
    </div>
</template>
