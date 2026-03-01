<script setup lang="ts">
/**
 * Connection Filters Component
 *
 * Provides cascading filters for Datacenter and Room selection
 * on the Connection Reports page.
 */

import { index as connectionReportsIndex } from '@/actions/App/Http/Controllers/ConnectionReportController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Label } from '@/components/ui/label';
import { debounce } from '@/lib/utils';
import { router } from '@inertiajs/vue3';
import { ChevronDown, Filter, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface FilterOption {
    id: number;
    name: string;
}

interface Filters {
    datacenter_id: number | null;
    room_id: number | null;
}

interface Props {
    filters: Filters;
    datacenters: FilterOption[];
    rooms: FilterOption[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'filtering', value: boolean): void;
}>();

// Local filter state
const datacenterId = ref(
    props.filters.datacenter_id ? String(props.filters.datacenter_id) : '',
);
const roomId = ref(props.filters.room_id ? String(props.filters.room_id) : '');

// Mobile collapsible state
const isOpen = ref(false);

// Common select styling
const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring dark:border-input dark:bg-transparent dark:text-foreground';

// Check if any filters are active
const hasActiveFilters = computed(() => {
    return !!(datacenterId.value || roomId.value);
});

// Apply filters with Inertia
const applyFilters = () => {
    emit('filtering', true);

    const params: Record<string, string | undefined> = {
        datacenter_id: datacenterId.value || undefined,
        room_id: roomId.value || undefined,
    };

    // Remove undefined values
    Object.keys(params).forEach((key) => {
        if (params[key] === undefined) {
            delete params[key];
        }
    });

    router.get(connectionReportsIndex.url(), params, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            emit('filtering', false);
        },
    });
};

// Debounced filter application
const debouncedApplyFilters = debounce(applyFilters, 300);

// Clear all filters
const clearFilters = () => {
    datacenterId.value = '';
    roomId.value = '';

    emit('filtering', true);
    router.get(
        connectionReportsIndex.url(),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                emit('filtering', false);
            },
        },
    );
};

// Watch for datacenter changes to reset room filter
watch(datacenterId, () => {
    roomId.value = '';
    debouncedApplyFilters();
});

// Watch for room changes
watch(roomId, () => {
    debouncedApplyFilters();
});
</script>

<template>
    <!-- Mobile: Collapsible -->
    <div class="lg:hidden">
        <Collapsible v-model:open="isOpen">
            <Card>
                <CardHeader class="p-3">
                    <CollapsibleTrigger
                        class="flex w-full items-center justify-between"
                    >
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Filter class="size-4" />
                            Filters
                            <span
                                v-if="hasActiveFilters"
                                class="rounded-full bg-primary px-2 py-0.5 text-xs text-primary-foreground"
                            >
                                Active
                            </span>
                        </CardTitle>
                        <ChevronDown
                            class="size-4 transition-transform"
                            :class="{ 'rotate-180': isOpen }"
                        />
                    </CollapsibleTrigger>
                </CardHeader>
                <CollapsibleContent>
                    <CardContent class="space-y-4 pt-0">
                        <!-- Datacenter Filter -->
                        <div class="space-y-2">
                            <Label for="datacenter-mobile">Datacenter</Label>
                            <select
                                id="datacenter-mobile"
                                v-model="datacenterId"
                                :class="selectClass"
                            >
                                <option value="">All Datacenters</option>
                                <option
                                    v-for="dc in datacenters"
                                    :key="dc.id"
                                    :value="String(dc.id)"
                                >
                                    {{ dc.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Room Filter (visible when datacenter selected) -->
                        <div
                            v-if="datacenterId && rooms.length > 0"
                            class="space-y-2"
                        >
                            <Label for="room-mobile">Room</Label>
                            <select
                                id="room-mobile"
                                v-model="roomId"
                                :class="selectClass"
                            >
                                <option value="">All Rooms</option>
                                <option
                                    v-for="room in rooms"
                                    :key="room.id"
                                    :value="String(room.id)"
                                >
                                    {{ room.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Clear Filters -->
                        <Button
                            v-if="hasActiveFilters"
                            variant="ghost"
                            size="sm"
                            class="w-full"
                            @click="clearFilters"
                        >
                            <X class="mr-2 size-4" />
                            Clear Filters
                        </Button>
                    </CardContent>
                </CollapsibleContent>
            </Card>
        </Collapsible>
    </div>

    <!-- Desktop: Inline filter row -->
    <div class="hidden lg:block">
        <Card>
            <CardContent class="pt-4">
                <div class="flex flex-row items-end gap-4">
                    <!-- Datacenter Filter -->
                    <div class="flex-1">
                        <Label
                            for="datacenter-desktop"
                            class="mb-1 block text-sm font-medium text-muted-foreground"
                        >
                            Datacenter
                        </Label>
                        <select
                            id="datacenter-desktop"
                            v-model="datacenterId"
                            :class="selectClass"
                        >
                            <option value="">All Datacenters</option>
                            <option
                                v-for="dc in datacenters"
                                :key="dc.id"
                                :value="String(dc.id)"
                            >
                                {{ dc.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Room Filter (visible when datacenter selected) -->
                    <div v-if="datacenterId && rooms.length > 0" class="flex-1">
                        <Label
                            for="room-desktop"
                            class="mb-1 block text-sm font-medium text-muted-foreground"
                        >
                            Room
                        </Label>
                        <select
                            id="room-desktop"
                            v-model="roomId"
                            :class="selectClass"
                        >
                            <option value="">All Rooms</option>
                            <option
                                v-for="room in rooms"
                                :key="room.id"
                                :value="String(room.id)"
                            >
                                {{ room.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Clear Filters -->
                    <div v-if="hasActiveFilters" class="shrink-0">
                        <Button variant="ghost" size="sm" @click="clearFilters">
                            <X class="mr-1 size-4" />
                            Clear
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
