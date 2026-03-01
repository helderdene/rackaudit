<script setup lang="ts">
import { show as showMove } from '@/actions/App/Http/Controllers/EquipmentMoveController';
import MoveWizard from '@/components/EquipmentMoves/MoveWizard.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { debounce } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, Plus, RefreshCw, Server, User, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface MoveData {
    id: number;
    status: string;
    status_label: string;
    is_pending: boolean;
    is_approved: boolean;
    is_executed: boolean;
    is_rejected: boolean;
    is_cancelled: boolean;
    device: {
        id: number;
        name: string;
        asset_tag: string;
        device_type?: { name: string } | null;
    } | null;
    source_rack: {
        id: number;
        name: string;
        location_path: string;
    } | null;
    destination_rack: {
        id: number;
        name: string;
        location_path: string;
    } | null;
    source_start_u: number | null;
    destination_start_u: number | null;
    requester: { id: number; name: string } | null;
    requested_at_formatted: string | null;
    approved_at_formatted: string | null;
    executed_at_formatted: string | null;
    can_approve: boolean;
    can_reject: boolean;
    can_cancel: boolean;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface StatusOption {
    value: string;
    label: string;
}

interface Props {
    moves: {
        data: MoveData[];
        links: PaginationLink[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    statusOptions: StatusOption[];
    filters: {
        status: string;
        device_id: string;
        rack_id: string;
        start_date: string;
        end_date: string;
    };
    canCreate: boolean;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Equipment Moves',
        href: '/equipment-moves',
    },
];

// Filter state
const statusFilter = ref(props.filters.status);
const deviceSearch = ref('');
const startDate = ref(props.filters.start_date);
const endDate = ref(props.filters.end_date);

// Wizard state
const isWizardOpen = ref(false);

/**
 * Get status badge variant
 */
function getStatusVariant(
    status: string,
):
    | 'default'
    | 'secondary'
    | 'destructive'
    | 'outline'
    | 'success'
    | 'warning'
    | 'info' {
    switch (status) {
        case 'pending_approval':
            return 'warning';
        case 'approved':
            return 'info';
        case 'executed':
            return 'success';
        case 'rejected':
            return 'destructive';
        case 'cancelled':
            return 'secondary';
        default:
            return 'outline';
    }
}

/**
 * Apply filters
 */
function applyFilters(): void {
    router.get(
        '/equipment-moves',
        {
            status: statusFilter.value || undefined,
            start_date: startDate.value || undefined,
            end_date: endDate.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

/**
 * Clear all filters
 */
function clearFilters(): void {
    statusFilter.value = '';
    deviceSearch.value = '';
    startDate.value = '';
    endDate.value = '';
    router.get(
        '/equipment-moves',
        {},
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

/**
 * Check if any filters are active
 */
const hasActiveFilters = (): boolean => {
    return !!(statusFilter.value || startDate.value || endDate.value);
};

// Debounced filter application
const debouncedApplyFilters = debounce(applyFilters, 300);

// Watch filter changes
watch(statusFilter, debouncedApplyFilters);
watch(startDate, debouncedApplyFilters);
watch(endDate, debouncedApplyFilters);

/**
 * Handle wizard completion
 */
function handleWizardComplete(_moveId: number): void {
    // Router will reload after wizard closes
}

/**
 * Refresh the page data
 */
function refreshData(): void {
    router.reload({ preserveScroll: true });
}
</script>

<template>
    <Head title="Equipment Moves" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <HeadingSmall
                    title="Equipment Moves"
                    description="Track and manage equipment move requests."
                />
                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" @click="refreshData">
                        <RefreshCw class="mr-2 h-4 w-4" />
                        Refresh
                    </Button>
                    <Button v-if="canCreate" @click="isWizardOpen = true">
                        <Plus class="mr-2 h-4 w-4" />
                        New Move Request
                    </Button>
                </div>
            </div>

            <!-- Filters -->
            <Card>
                <CardContent class="pt-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                        <div class="flex-1 space-y-2">
                            <label class="text-sm font-medium">Status</label>
                            <select
                                v-model="statusFilter"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <option value="">All Statuses</option>
                                <option
                                    v-for="option in statusOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>
                        <div class="flex-1 space-y-2">
                            <label class="text-sm font-medium"
                                >Start Date</label
                            >
                            <Input v-model="startDate" type="date" />
                        </div>
                        <div class="flex-1 space-y-2">
                            <label class="text-sm font-medium">End Date</label>
                            <Input v-model="endDate" type="date" />
                        </div>
                        <Button
                            v-if="hasActiveFilters()"
                            variant="ghost"
                            size="sm"
                            @click="clearFilters"
                        >
                            <X class="mr-2 h-4 w-4" />
                            Clear
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Moves Table -->
            <div class="overflow-hidden rounded-md border">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/50">
                            <tr>
                                <th
                                    class="h-12 px-4 text-left font-medium text-muted-foreground"
                                >
                                    Device
                                </th>
                                <th
                                    class="h-12 px-4 text-left font-medium text-muted-foreground"
                                >
                                    Source
                                </th>
                                <th
                                    class="h-12 px-4 text-left font-medium text-muted-foreground"
                                >
                                    Destination
                                </th>
                                <th
                                    class="h-12 px-4 text-left font-medium text-muted-foreground"
                                >
                                    Status
                                </th>
                                <th
                                    class="h-12 px-4 text-left font-medium text-muted-foreground"
                                >
                                    Requested
                                </th>
                                <th
                                    class="h-12 w-[100px] px-4 text-left font-medium text-muted-foreground"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="move in moves.data"
                                :key="move.id"
                                class="border-b transition-colors hover:bg-muted/50"
                            >
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-9 w-9 items-center justify-center rounded bg-muted"
                                        >
                                            <Server
                                                class="h-4 w-4 text-muted-foreground"
                                            />
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate font-medium">
                                                {{
                                                    move.device?.name ||
                                                    'Unknown'
                                                }}
                                            </p>
                                            <p
                                                class="truncate text-xs text-muted-foreground"
                                            >
                                                {{ move.device?.asset_tag }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm">
                                            {{ move.source_rack?.name || '-' }}
                                        </p>
                                        <p
                                            v-if="move.source_start_u"
                                            class="text-xs text-muted-foreground"
                                        >
                                            U{{ move.source_start_u }}
                                        </p>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <div class="flex items-center gap-2">
                                        <ArrowRight
                                            class="h-4 w-4 shrink-0 text-muted-foreground"
                                        />
                                        <div class="min-w-0">
                                            <p class="truncate text-sm">
                                                {{
                                                    move.destination_rack
                                                        ?.name || '-'
                                                }}
                                            </p>
                                            <p
                                                v-if="move.destination_start_u"
                                                class="text-xs text-muted-foreground"
                                            >
                                                U{{ move.destination_start_u }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <Badge
                                        :variant="getStatusVariant(move.status)"
                                    >
                                        {{ move.status_label }}
                                    </Badge>
                                </td>
                                <td class="p-4">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm">
                                            {{
                                                move.requested_at_formatted ||
                                                '-'
                                            }}
                                        </p>
                                        <p
                                            v-if="move.requester"
                                            class="flex items-center gap-1 text-xs text-muted-foreground"
                                        >
                                            <User class="h-3 w-3" />
                                            {{ move.requester.name }}
                                        </p>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <Link :href="showMove.url(move.id)">
                                        <Button variant="outline" size="sm"
                                            >View</Button
                                        >
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="moves.data.length === 0">
                                <td
                                    colspan="6"
                                    class="p-8 text-center text-muted-foreground"
                                >
                                    No move requests found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div
                v-if="moves.last_page > 1"
                class="flex items-center justify-between"
            >
                <p class="text-sm text-muted-foreground">
                    Showing
                    {{ (moves.current_page - 1) * moves.per_page + 1 }} to
                    {{
                        Math.min(
                            moves.current_page * moves.per_page,
                            moves.total,
                        )
                    }}
                    of {{ moves.total }} moves
                </p>
                <div class="flex gap-1">
                    <template v-for="link in moves.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-state
                            preserve-scroll
                        >
                            <Button
                                variant="outline"
                                size="sm"
                                :class="{ 'bg-muted': link.active }"
                                ><span v-html="link.label"
                            /></Button>
                        </Link>
                        <Button v-else variant="outline" size="sm" disabled
                            ><span v-html="link.label"
                        /></Button>
                    </template>
                </div>
            </div>
        </div>

        <!-- Move Wizard Dialog -->
        <MoveWizard
            :is-open="isWizardOpen"
            @close="isWizardOpen = false"
            @complete="handleWizardComplete"
        />
    </AppLayout>
</template>
