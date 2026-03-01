<script setup lang="ts">
import { generate } from '@/actions/App/Http/Controllers/BulkQrCodeController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { SelectOption } from '@/types/rooms';
import { Head } from '@inertiajs/vue3';
import {
    AlertCircle,
    FileDown,
    Filter,
    HardDrive,
    Info,
    Loader2,
    QrCode,
    Server,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

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
    filterOptions: FilterOptions;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'QR Codes',
        href: '/qr-codes/bulk',
    },
    {
        title: 'Bulk Generate',
        href: '/qr-codes/bulk',
    },
];

// Form state
const selectedEntityType = ref<string>('');
const selectedDatacenterId = ref<number | null>(null);
const selectedRoomId = ref<number | null>(null);
const selectedRowId = ref<number | null>(null);
const selectedRackId = ref<number | null>(null);
const isSubmitting = ref(false);
const submitError = ref<string | null>(null);

// Entity type options with icons
const entityTypeIcons: Record<string, typeof Server> = {
    rack: Server,
    device: HardDrive,
};

// Filtered room options based on selected datacenter
const filteredRooms = computed(() => {
    if (!selectedDatacenterId.value) return [];
    return props.filterOptions.rooms.filter(
        (room) => room.datacenter_id === selectedDatacenterId.value,
    );
});

// Filtered row options based on selected room
const filteredRows = computed(() => {
    if (!selectedRoomId.value) return [];
    return props.filterOptions.rows.filter(
        (row) => row.room_id === selectedRoomId.value,
    );
});

// Filtered rack options based on selected row
const filteredRacks = computed(() => {
    if (!selectedRowId.value) return [];
    return props.filterOptions.racks.filter(
        (rack) => rack.row_id === selectedRowId.value,
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

// Handle form submission (triggers PDF download)
const handleSubmit = async () => {
    if (!isFormValid.value || isSubmitting.value) return;

    isSubmitting.value = true;
    submitError.value = null;

    const formData: Record<string, string | number | null> = {
        entity_type: selectedEntityType.value,
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

    // For PDF download, we need to submit as a form to get the file download
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = generate.url();
    form.style.display = 'none';

    // Add CSRF token
    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');
    if (csrfToken) {
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = csrfToken;
        form.appendChild(csrfInput);
    }

    // Add form data
    Object.entries(formData).forEach(([key, value]) => {
        if (value !== null) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = String(value);
            form.appendChild(input);
        }
    });

    document.body.appendChild(form);
    form.submit();

    // Clean up and reset state after a delay
    setTimeout(() => {
        document.body.removeChild(form);
        isSubmitting.value = false;
    }, 1000);
};

// Get active filter description for display
const activeFilterDescription = computed(() => {
    const parts: string[] = [];
    if (selectedRackId.value) {
        const rack = filteredRacks.value.find(
            (r) => r.value === selectedRackId.value,
        );
        if (rack) parts.push(`Rack: ${rack.label}`);
    } else if (selectedRowId.value) {
        const row = filteredRows.value.find(
            (r) => r.value === selectedRowId.value,
        );
        if (row) parts.push(`Row: ${row.label}`);
    } else if (selectedRoomId.value) {
        const room = filteredRooms.value.find(
            (r) => r.value === selectedRoomId.value,
        );
        if (room) parts.push(`Room: ${room.label}`);
    } else if (selectedDatacenterId.value) {
        const dc = props.filterOptions.datacenters.find(
            (d) => d.value === selectedDatacenterId.value,
        );
        if (dc) parts.push(`Datacenter: ${dc.label}`);
    }
    return parts.length > 0 ? parts.join(' > ') : 'All items (no filter)';
});

// Get entity type label for display
const selectedEntityTypeLabel = computed(() => {
    const option = props.entityTypeOptions.find(
        (o) => o.value === selectedEntityType.value,
    );
    return option?.label || '';
});
</script>

<template>
    <Head title="Bulk QR Codes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Header -->
            <HeadingSmall
                title="Bulk QR Code Generation"
                description="Generate printable QR code labels for racks and devices in PDF format."
            />

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- QR Code Configuration Section -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <QrCode class="h-5 w-5" />
                            QR Code Configuration
                        </CardTitle>
                        <CardDescription>
                            Select the entity type to generate QR codes for.
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
                                        'border-primary bg-primary/5 ring-1 ring-primary':
                                            selectedEntityType === option.value,
                                        'border-muted':
                                            selectedEntityType !== option.value,
                                        'cursor-not-allowed opacity-50':
                                            isSubmitting,
                                    }"
                                    @click="selectedEntityType = option.value"
                                >
                                    <component
                                        :is="
                                            entityTypeIcons[option.value] ||
                                            QrCode
                                        "
                                        class="h-5 w-5"
                                        :class="
                                            selectedEntityType === option.value
                                                ? 'text-primary'
                                                : 'text-muted-foreground'
                                        "
                                    />
                                    <span class="font-medium">{{
                                        option.label
                                    }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- PDF Format Info -->
                        <div class="rounded-lg bg-muted/50 p-4">
                            <div class="flex items-start gap-3">
                                <Info
                                    class="mt-0.5 h-4 w-4 text-muted-foreground"
                                />
                                <div class="text-sm text-muted-foreground">
                                    <p class="font-medium text-foreground">
                                        Avery 5160 Format
                                    </p>
                                    <p>
                                        Labels are formatted for standard label
                                        sheets (30 labels per page, 3 columns x
                                        10 rows). Each label includes a QR code,
                                        name, and secondary identifier.
                                    </p>
                                </div>
                            </div>
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
                            <Loader2
                                v-if="isSubmitting"
                                class="mr-2 h-4 w-4 animate-spin"
                            />
                            <FileDown v-else class="mr-2 h-4 w-4" />
                            {{
                                isSubmitting
                                    ? 'Generating PDF...'
                                    : 'Generate PDF'
                            }}
                        </Button>
                    </CardContent>
                </Card>

                <!-- Filter Section -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Filter class="h-5 w-5" />
                            Filter Items (Optional)
                        </CardTitle>
                        <CardDescription>
                            Narrow down your QR codes to specific locations in
                            the hierarchy.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <!-- Datacenter filter -->
                        <div class="space-y-2">
                            <Label for="datacenter-filter">Datacenter</Label>
                            <select
                                id="datacenter-filter"
                                v-model="selectedDatacenterId"
                                :disabled="
                                    isSubmitting ||
                                    filterOptions.datacenters.length === 0
                                "
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
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
                                :disabled="
                                    isSubmitting ||
                                    !selectedDatacenterId ||
                                    filteredRooms.length === 0
                                "
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
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
                                :disabled="
                                    isSubmitting ||
                                    !selectedRoomId ||
                                    filteredRows.length === 0
                                "
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
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
                                :disabled="
                                    isSubmitting ||
                                    !selectedRowId ||
                                    filteredRacks.length === 0
                                "
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
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
                            <h4 class="mb-2 font-medium text-foreground">
                                Generation Scope
                            </h4>
                            <p class="text-muted-foreground">
                                <span v-if="selectedEntityType"
                                    >{{ selectedEntityTypeLabel }}:
                                </span>
                                {{ activeFilterDescription }}
                            </p>
                        </div>

                        <!-- Help text -->
                        <div
                            class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground"
                        >
                            <h4 class="mb-2 font-medium text-foreground">
                                Filter Tips
                            </h4>
                            <ul class="list-inside list-disc space-y-1">
                                <li>
                                    Filters cascade: selecting a datacenter
                                    filters rooms
                                </li>
                                <li>
                                    Leave filters empty to generate QR codes for
                                    all items
                                </li>
                                <li>
                                    For devices, rack filter limits to devices
                                    in that rack
                                </li>
                                <li>
                                    Each QR code links directly to the item's
                                    detail page
                                </li>
                            </ul>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
