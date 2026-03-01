<script setup lang="ts">
import RackController from '@/actions/App/Http/Controllers/RackController';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Link } from '@inertiajs/vue3';
import {
    ChevronDown,
    ChevronsUpDown,
    ChevronUp,
    ExternalLink,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface RackCapacityData {
    id: number;
    name: string;
    datacenter_id: number;
    datacenter_name: string;
    room_id: number;
    room_name: string;
    row_id: number;
    row_name: string;
    u_height: number;
    used_u_space: number;
    available_u_space: number;
    utilization_percent: number;
    power_capacity_watts: number | null;
    power_used_watts: number | null;
    power_available_watts: number | null;
    power_utilization_percent: number | null;
    status: 'warning' | 'critical' | 'normal';
}

interface Props {
    racks: RackCapacityData[];
    showTitle?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showTitle: true,
});

type SortKey = 'name' | 'location' | 'u_space' | 'power' | 'status';
type SortDirection = 'asc' | 'desc';

// Sort state
const sortKey = ref<SortKey>('utilization_percent' as any);
const sortDirection = ref<SortDirection>('desc');

// Sort the racks based on current sort state
const sortedRacks = computed(() => {
    const sorted = [...props.racks];

    sorted.sort((a, b) => {
        let comparison = 0;

        switch (sortKey.value) {
            case 'name':
                comparison = a.name.localeCompare(b.name);
                break;
            case 'location':
                const locA = `${a.datacenter_name} / ${a.room_name} / ${a.row_name}`;
                const locB = `${b.datacenter_name} / ${b.room_name} / ${b.row_name}`;
                comparison = locA.localeCompare(locB);
                break;
            case 'u_space':
                comparison = a.utilization_percent - b.utilization_percent;
                break;
            case 'power':
                const powerA = a.power_utilization_percent ?? -1;
                const powerB = b.power_utilization_percent ?? -1;
                comparison = powerA - powerB;
                break;
            case 'status':
                const statusOrder = { critical: 0, warning: 1, normal: 2 };
                comparison = statusOrder[a.status] - statusOrder[b.status];
                break;
            default:
                comparison = a.utilization_percent - b.utilization_percent;
        }

        return sortDirection.value === 'asc' ? comparison : -comparison;
    });

    return sorted;
});

// Toggle sort for a column
const toggleSort = (key: SortKey) => {
    if (sortKey.value === key) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDirection.value = 'desc';
    }
};

// Get sort icon for a column
const getSortIcon = (key: SortKey) => {
    if (sortKey.value !== key) return ChevronsUpDown;
    return sortDirection.value === 'asc' ? ChevronUp : ChevronDown;
};

// Get row highlight class based on utilization threshold
const getRowClass = (rack: RackCapacityData): string => {
    if (rack.status === 'critical') {
        return 'bg-red-50 dark:bg-red-950/20';
    }
    if (rack.status === 'warning') {
        return 'bg-amber-50 dark:bg-amber-950/20';
    }
    return '';
};

// Get status badge variant
const getStatusVariant = (
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (status) {
        case 'critical':
            return 'destructive';
        case 'warning':
            return 'secondary';
        default:
            return 'default';
    }
};

// Get status label
const getStatusLabel = (status: string): string => {
    switch (status) {
        case 'critical':
            return 'Critical (90%+)';
        case 'warning':
            return 'Warning (80%+)';
        default:
            return 'Normal';
    }
};

// Format power value for display
const formatPower = (watts: number | null): string => {
    if (watts === null) return 'Not configured';
    if (watts >= 1000) {
        return `${(watts / 1000).toFixed(1)}kW`;
    }
    return `${watts}W`;
};

// Build rack detail URL
const getRackUrl = (rack: RackCapacityData): string => {
    return RackController.show.url({
        datacenter: rack.datacenter_id,
        room: rack.room_id,
        row: rack.row_id,
        rack: rack.id,
    });
};
</script>

<template>
    <Card>
        <CardHeader v-if="showTitle">
            <CardTitle class="text-base">Rack Capacity Details</CardTitle>
        </CardHeader>
        <CardContent :class="{ 'pt-6': !showTitle }">
            <div v-if="racks.length > 0" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th
                                class="h-12 cursor-pointer px-4 text-left font-medium text-muted-foreground hover:text-foreground"
                                @click="toggleSort('name')"
                            >
                                <div class="flex items-center gap-1">
                                    Rack Name
                                    <component
                                        :is="getSortIcon('name')"
                                        class="size-4"
                                    />
                                </div>
                            </th>
                            <th
                                class="h-12 cursor-pointer px-4 text-left font-medium text-muted-foreground hover:text-foreground"
                                @click="toggleSort('location')"
                            >
                                <div class="flex items-center gap-1">
                                    Location
                                    <component
                                        :is="getSortIcon('location')"
                                        class="size-4"
                                    />
                                </div>
                            </th>
                            <th
                                class="h-12 cursor-pointer px-4 text-left font-medium text-muted-foreground hover:text-foreground"
                                @click="toggleSort('u_space')"
                            >
                                <div class="flex items-center gap-1">
                                    U-Space Used/Total
                                    <component
                                        :is="getSortIcon('u_space')"
                                        class="size-4"
                                    />
                                </div>
                            </th>
                            <th
                                class="h-12 cursor-pointer px-4 text-left font-medium text-muted-foreground hover:text-foreground"
                                @click="toggleSort('power')"
                            >
                                <div class="flex items-center gap-1">
                                    Power Used/Capacity
                                    <component
                                        :is="getSortIcon('power')"
                                        class="size-4"
                                    />
                                </div>
                            </th>
                            <th
                                class="h-12 cursor-pointer px-4 text-left font-medium text-muted-foreground hover:text-foreground"
                                @click="toggleSort('status')"
                            >
                                <div class="flex items-center gap-1">
                                    Status
                                    <component
                                        :is="getSortIcon('status')"
                                        class="size-4"
                                    />
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="rack in sortedRacks"
                            :key="rack.id"
                            :class="[
                                'border-b transition-colors hover:bg-muted/50',
                                getRowClass(rack),
                            ]"
                        >
                            <td class="p-4">
                                <Link
                                    :href="getRackUrl(rack)"
                                    class="flex items-center gap-1 font-medium text-primary hover:underline"
                                >
                                    {{ rack.name }}
                                    <ExternalLink class="size-3" />
                                </Link>
                            </td>
                            <td class="p-4 text-muted-foreground">
                                {{ rack.datacenter_name }} /
                                {{ rack.room_name }} / {{ rack.row_name }}
                            </td>
                            <td class="p-4">
                                <div class="flex items-center gap-2">
                                    <span
                                        >{{ rack.used_u_space }}U /
                                        {{ rack.u_height }}U</span
                                    >
                                    <span
                                        class="text-xs font-medium"
                                        :class="{
                                            'text-red-600 dark:text-red-400':
                                                rack.utilization_percent >= 90,
                                            'text-amber-600 dark:text-amber-400':
                                                rack.utilization_percent >=
                                                    80 &&
                                                rack.utilization_percent < 90,
                                            'text-green-600 dark:text-green-400':
                                                rack.utilization_percent < 80,
                                        }"
                                    >
                                        ({{ rack.utilization_percent }}%)
                                    </span>
                                </div>
                            </td>
                            <td class="p-4">
                                <template
                                    v-if="rack.power_capacity_watts !== null"
                                >
                                    <div class="flex items-center gap-2">
                                        <span
                                            >{{
                                                formatPower(
                                                    rack.power_used_watts,
                                                )
                                            }}
                                            /
                                            {{
                                                formatPower(
                                                    rack.power_capacity_watts,
                                                )
                                            }}</span
                                        >
                                        <span
                                            v-if="
                                                rack.power_utilization_percent !==
                                                null
                                            "
                                            class="text-xs font-medium"
                                            :class="{
                                                'text-red-600 dark:text-red-400':
                                                    rack.power_utilization_percent >=
                                                    90,
                                                'text-amber-600 dark:text-amber-400':
                                                    rack.power_utilization_percent >=
                                                        80 &&
                                                    rack.power_utilization_percent <
                                                        90,
                                                'text-green-600 dark:text-green-400':
                                                    rack.power_utilization_percent <
                                                    80,
                                            }"
                                        >
                                            ({{
                                                rack.power_utilization_percent
                                            }}%)
                                        </span>
                                    </div>
                                </template>
                                <span v-else class="text-muted-foreground"
                                    >Not configured</span
                                >
                            </td>
                            <td class="p-4">
                                <Badge :variant="getStatusVariant(rack.status)">
                                    {{ getStatusLabel(rack.status) }}
                                </Badge>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty state -->
            <div v-else class="py-8 text-center text-muted-foreground">
                No rack data available
            </div>
        </CardContent>
    </Card>
</template>
