<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { index, store } from '@/actions/App/Http/Controllers/BulkExportController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { SelectOption } from '@/types/rooms';
import {
    Download,
    Building2,
    LayoutGrid,
    Layers,
    Server,
    HardDrive,
    Cable,
    FileSpreadsheet,
    Loader2,
    AlertCircle,
    Filter
} from 'lucide-vue-next';

interface FilterOption {
    value: number;
    label: string;
    datacenter_id?: number;
    room_id?: number;
    row_id?: number;
}

interface FilterOptions {
    datacenters: FilterOption[];
    rooms: FilterOption[];
    rows: FilterOption[];
    racks: FilterOption[];
}

interface Props {
    entityTypeOptions: SelectOption[];
    formatOptions: SelectOption[];
    filterOptions: FilterOptions;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Exports',
        href: '/exports',
    },
    {
        title: 'New Export',
        href: '/exports/create',
    },
];

// Form state
const selectedEntityType = ref<string>('');
const selectedFormat = ref<string>('xlsx');
const selectedDatacenterId = ref<number | null>(null);
const selectedRoomId = ref<number | null>(null);
const selectedRowId = ref<number | null>(null);
const selectedRackId = ref<number | null>(null);
const isSubmitting = ref(false);
const submitError = ref<string | null>(null);

// Entity type options with icons
const entityTypeIcons: Record<string, typeof Building2> = {
    datacenter: Building2,
    room: LayoutGrid,
    row: Layers,
    rack: Server,
    device: HardDrive,
    port: Cable,
};

// Filtered room options based on selected datacenter
const filteredRooms = computed(() => {
    if (!selectedDatacenterId.value) return [];
    return props.filterOptions.rooms.filter(
        room => room.datacenter_id === selectedDatacenterId.value
    );
});

// Filtered row options based on selected room
const filteredRows = computed(() => {
    if (!selectedRoomId.value) return [];
    return props.filterOptions.rows.filter(
        row => row.room_id === selectedRoomId.value
    );
});

// Filtered rack options based on selected row
const filteredRacks = computed(() => {
    if (!selectedRowId.value) return [];
    return props.filterOptions.racks.filter(
        rack => rack.row_id === selectedRowId.value
    );
});

// Reset cascading filters when parent changes
watch(selectedDatacenterId, () => {
    selectedRoomId.value = null;
    selectedRowId.value = null;
    selectedRackId.value = null;
});

watch(selectedRoomId, () => {
    selectedRowId.value = null;
    selectedRackId.value = null;
});

watch(selectedRowId, () => {
    selectedRackId.value = null;
});

// Check if form is valid
const isFormValid = computed(() => {
    return selectedEntityType.value !== '';
});

// Handle form submission
const handleSubmit = async () => {
    if (!isFormValid.value || isSubmitting.value) return;

    isSubmitting.value = true;
    submitError.value = null;

    const formData: Record<string, string | number | null> = {
        entity_type: selectedEntityType.value,
        format: selectedFormat.value,
    };

    // Add filters if selected
    if (selectedDatacenterId.value) {
        formData.datacenter_id = selectedDatacenterId.value;
    }
    if (selectedRoomId.value) {
        formData.room_id = selectedRoomId.value;
    }
    if (selectedRowId.value) {
        formData.row_id = selectedRowId.value;
    }
    if (selectedRackId.value) {
        formData.rack_id = selectedRackId.value;
    }

    router.post(store.url(), formData, {
        onSuccess: () => {
            // Redirect is handled by the controller
        },
        onError: (errors) => {
            if (errors.entity_type) {
                submitError.value = errors.entity_type as string;
            } else {
                submitError.value = 'An error occurred while creating the export.';
            }
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

// Get active filter description for display
const activeFilterDescription = computed(() => {
    const parts: string[] = [];
    if (selectedRackId.value) {
        const rack = filteredRacks.value.find(r => r.value === selectedRackId.value);
        if (rack) parts.push(`Rack: ${rack.label}`);
    } else if (selectedRowId.value) {
        const row = filteredRows.value.find(r => r.value === selectedRowId.value);
        if (row) parts.push(`Row: ${row.label}`);
    } else if (selectedRoomId.value) {
        const room = filteredRooms.value.find(r => r.value === selectedRoomId.value);
        if (room) parts.push(`Room: ${room.label}`);
    } else if (selectedDatacenterId.value) {
        const dc = props.filterOptions.datacenters.find(d => d.value === selectedDatacenterId.value);
        if (dc) parts.push(`Datacenter: ${dc.label}`);
    }
    return parts.length > 0 ? parts.join(' > ') : 'All data (no filter)';
});
</script>

<template>
    <Head title="New Export" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Header -->
            <HeadingSmall
                title="New Bulk Export"
                description="Export datacenter infrastructure data to CSV or Excel format."
            />

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Export Configuration Section -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Download class="h-5 w-5" />
                            Export Configuration
                        </CardTitle>
                        <CardDescription>
                            Select the data type and format for your export.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <!-- Entity type selector -->
                        <div class="space-y-3">
                            <Label>Entity Type</Label>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <button
                                    v-for="option in entityTypeOptions"
                                    :key="option.value"
                                    type="button"
                                    :disabled="isSubmitting"
                                    class="flex items-center gap-3 rounded-lg border p-4 text-left transition-colors hover:bg-muted/50"
                                    :class="{
                                        'border-primary bg-primary/5 ring-1 ring-primary': selectedEntityType === option.value,
                                        'border-muted': selectedEntityType !== option.value,
                                        'cursor-not-allowed opacity-50': isSubmitting,
                                    }"
                                    @click="selectedEntityType = option.value"
                                >
                                    <component
                                        :is="entityTypeIcons[option.value] || FileSpreadsheet"
                                        class="h-5 w-5"
                                        :class="selectedEntityType === option.value ? 'text-primary' : 'text-muted-foreground'"
                                    />
                                    <span class="font-medium">{{ option.label }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Format selector -->
                        <div class="space-y-3">
                            <Label>Export Format</Label>
                            <div class="flex gap-4">
                                <label
                                    v-for="option in formatOptions"
                                    :key="option.value"
                                    class="flex cursor-pointer items-center gap-2"
                                    :class="{ 'opacity-50': isSubmitting }"
                                >
                                    <input
                                        type="radio"
                                        :value="option.value"
                                        v-model="selectedFormat"
                                        :disabled="isSubmitting"
                                        class="h-4 w-4 border-primary text-primary focus:ring-primary"
                                    />
                                    <span class="text-sm font-medium">{{ option.label }}</span>
                                </label>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                CSV is simpler, while Excel (XLSX) supports formatting and filtering.
                            </p>
                        </div>

                        <!-- Submit error -->
                        <div
                            v-if="submitError"
                            class="flex items-center gap-2 rounded-md bg-destructive/10 p-3 text-sm text-destructive"
                        >
                            <AlertCircle class="h-4 w-4 shrink-0" />
                            <span>{{ submitError }}</span>
                        </div>

                        <!-- Submit button -->
                        <Button
                            :disabled="!isFormValid || isSubmitting"
                            class="w-full"
                            @click="handleSubmit"
                        >
                            <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                            <Download v-else class="mr-2 h-4 w-4" />
                            {{ isSubmitting ? 'Creating Export...' : 'Start Export' }}
                        </Button>
                    </CardContent>
                </Card>

                <!-- Filter Section -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Filter class="h-5 w-5" />
                            Filter Data (Optional)
                        </CardTitle>
                        <CardDescription>
                            Narrow down your export to specific locations in the hierarchy.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <!-- Datacenter filter -->
                        <div class="space-y-2">
                            <Label for="datacenter-filter">Datacenter</Label>
                            <select
                                id="datacenter-filter"
                                v-model="selectedDatacenterId"
                                :disabled="isSubmitting || filterOptions.datacenters.length === 0"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option :value="null">All Datacenters</option>
                                <option
                                    v-for="dc in filterOptions.datacenters"
                                    :key="dc.value"
                                    :value="dc.value"
                                >
                                    {{ dc.label }}
                                </option>
                            </select>
                        </div>

                        <!-- Room filter -->
                        <div class="space-y-2">
                            <Label for="room-filter">Room</Label>
                            <select
                                id="room-filter"
                                v-model="selectedRoomId"
                                :disabled="isSubmitting || !selectedDatacenterId || filteredRooms.length === 0"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option :value="null">All Rooms</option>
                                <option
                                    v-for="room in filteredRooms"
                                    :key="room.value"
                                    :value="room.value"
                                >
                                    {{ room.label }}
                                </option>
                            </select>
                        </div>

                        <!-- Row filter -->
                        <div class="space-y-2">
                            <Label for="row-filter">Row</Label>
                            <select
                                id="row-filter"
                                v-model="selectedRowId"
                                :disabled="isSubmitting || !selectedRoomId || filteredRows.length === 0"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option :value="null">All Rows</option>
                                <option
                                    v-for="row in filteredRows"
                                    :key="row.value"
                                    :value="row.value"
                                >
                                    {{ row.label }}
                                </option>
                            </select>
                        </div>

                        <!-- Rack filter -->
                        <div class="space-y-2">
                            <Label for="rack-filter">Rack</Label>
                            <select
                                id="rack-filter"
                                v-model="selectedRackId"
                                :disabled="isSubmitting || !selectedRowId || filteredRacks.length === 0"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option :value="null">All Racks</option>
                                <option
                                    v-for="rack in filteredRacks"
                                    :key="rack.value"
                                    :value="rack.value"
                                >
                                    {{ rack.label }}
                                </option>
                            </select>
                        </div>

                        <!-- Active filter summary -->
                        <div class="rounded-lg bg-muted/50 p-4 text-sm">
                            <h4 class="mb-2 font-medium text-foreground">Export Scope</h4>
                            <p class="text-muted-foreground">{{ activeFilterDescription }}</p>
                        </div>

                        <!-- Help text -->
                        <div class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                            <h4 class="mb-2 font-medium text-foreground">Filter Tips</h4>
                            <ul class="list-inside list-disc space-y-1">
                                <li>Filters cascade: selecting a datacenter filters rooms</li>
                                <li>Leave filters empty to export all data</li>
                                <li>Filters apply to entities within the hierarchy</li>
                                <li>Device exports include rack relationship data</li>
                            </ul>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
