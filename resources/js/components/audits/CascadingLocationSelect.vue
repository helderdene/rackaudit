<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';

interface DatacenterOption {
    id: number;
    name: string;
    formatted_location: string;
    has_approved_implementation_files: boolean;
}

interface RoomOption {
    id: number;
    name: string;
    rack_count: number;
}

interface Props {
    /** Available datacenters to select from */
    datacenters: DatacenterOption[];
    /** Selected scope type (datacenter, room, or racks) */
    scopeType: string;
    /** Currently selected datacenter ID */
    datacenterId: number | null;
    /** Currently selected room ID */
    roomId: number | null;
    /** Error for datacenter field */
    datacenterError?: string;
    /** Error for room field */
    roomError?: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:datacenterId': [value: number | null];
    'update:roomId': [value: number | null];
    'rooms-loaded': [rooms: RoomOption[]];
}>();

// Internal state for rooms
const rooms = ref<RoomOption[]>([]);
const isLoadingRooms = ref(false);

// Computed values for v-model bindings
const selectedDatacenterId = computed({
    get: () => props.datacenterId,
    set: (value: number | null) => emit('update:datacenterId', value),
});

const selectedRoomId = computed({
    get: () => props.roomId,
    set: (value: number | null) => emit('update:roomId', value),
});

// Determine if room dropdown should be visible
const showRoomDropdown = computed(() => {
    return props.scopeType === 'room' || props.scopeType === 'racks';
});

// Get selected datacenter for display
const selectedDatacenter = computed(() => {
    return props.datacenters.find(dc => dc.id === props.datacenterId);
});

// Get selected room for display
const selectedRoom = computed(() => {
    return rooms.value.find(room => room.id === props.roomId);
});

// Calculate total rack count for datacenter scope
const totalRackCount = computed(() => {
    return rooms.value.reduce((sum, room) => sum + room.rack_count, 0);
});

// Watch for datacenter changes and fetch rooms
watch(() => props.datacenterId, async (newDatacenterId) => {
    // Reset room selection when datacenter changes
    emit('update:roomId', null);
    rooms.value = [];

    if (newDatacenterId) {
        await fetchRooms(newDatacenterId);
    }
});

// Watch for scope type changes and reset room if not applicable
watch(() => props.scopeType, (newScopeType) => {
    if (newScopeType === 'datacenter') {
        emit('update:roomId', null);
    }
});

/**
 * Fetch rooms for the selected datacenter
 */
async function fetchRooms(datacenterId: number): Promise<void> {
    isLoadingRooms.value = true;
    try {
        const response = await fetch(`/api/audits/datacenters/${datacenterId}/rooms`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('Failed to fetch rooms');
        }

        const data = await response.json();
        rooms.value = data.data || [];
        emit('rooms-loaded', rooms.value);
    } catch (error) {
        console.error('Error fetching rooms:', error);
        rooms.value = [];
    } finally {
        isLoadingRooms.value = false;
    }
}

// Common select styling
const selectClass = 'flex h-10 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50';
</script>

<template>
    <div class="space-y-4">
        <!-- Responsive grid: single column on mobile, two columns on tablet+ -->
        <div class="grid gap-4 md:grid-cols-2">
            <!-- Datacenter Selection -->
            <div class="grid gap-2">
                <Label for="datacenter">
                    Datacenter <span class="text-red-500">*</span>
                </Label>
                <select
                    id="datacenter"
                    v-model="selectedDatacenterId"
                    :class="[selectClass, datacenterError ? 'border-destructive' : '']"
                    name="datacenter_id"
                >
                    <option :value="null">Select a datacenter</option>
                    <option
                        v-for="dc in datacenters"
                        :key="dc.id"
                        :value="dc.id"
                    >
                        {{ dc.name }} - {{ dc.formatted_location }}
                    </option>
                </select>
                <p v-if="datacenterError" class="text-sm text-destructive">
                    {{ datacenterError }}
                </p>
            </div>

            <!-- Room Selection (visible for room and racks scope) -->
            <div v-if="showRoomDropdown" class="grid gap-2">
                <Label for="room">
                    Room
                    <span v-if="scopeType === 'room'" class="text-red-500">*</span>
                    <span v-else class="text-muted-foreground text-xs">(optional filter)</span>
                </Label>
                <div class="relative">
                    <!-- Skeleton loading state -->
                    <template v-if="isLoadingRooms && selectedDatacenterId">
                        <Skeleton class="h-10 w-full" />
                    </template>
                    <template v-else>
                        <select
                            id="room"
                            v-model="selectedRoomId"
                            :class="[selectClass, roomError ? 'border-destructive' : '']"
                            :disabled="!selectedDatacenterId || isLoadingRooms"
                            name="room_id"
                        >
                            <option :value="null">
                                <template v-if="isLoadingRooms">Loading rooms...</template>
                                <template v-else-if="!selectedDatacenterId">Select datacenter first</template>
                                <template v-else-if="rooms.length === 0">No rooms available</template>
                                <template v-else>
                                    {{ scopeType === 'room' ? 'Select a room' : 'All rooms' }}
                                </template>
                            </option>
                            <option
                                v-for="room in rooms"
                                :key="room.id"
                                :value="room.id"
                            >
                                {{ room.name }} ({{ room.rack_count }} {{ room.rack_count === 1 ? 'rack' : 'racks' }})
                            </option>
                        </select>
                    </template>
                    <Spinner
                        v-if="isLoadingRooms && !selectedDatacenterId"
                        class="absolute top-1/2 right-8 -translate-y-1/2"
                    />
                </div>
                <p v-if="roomError" class="text-sm text-destructive">
                    {{ roomError }}
                </p>
            </div>
        </div>

        <!-- Datacenter info badge - shown when datacenter scope is selected -->
        <div v-if="selectedDatacenter && scopeType === 'datacenter'" class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
            <template v-if="isLoadingRooms">
                <Spinner class="h-3 w-3" />
                <span>Loading rack count...</span>
            </template>
            <template v-else>
                <Badge variant="secondary" class="font-normal">
                    {{ totalRackCount }} {{ totalRackCount === 1 ? 'rack' : 'racks' }}
                </Badge>
                <span>will be included in this audit</span>
            </template>
        </div>

        <!-- Room info badge - shown when room scope is selected -->
        <div v-if="selectedRoom && scopeType === 'room'" class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
            <Badge variant="secondary" class="font-normal">
                {{ selectedRoom.rack_count }} {{ selectedRoom.rack_count === 1 ? 'rack' : 'racks' }}
            </Badge>
            <span>will be included in this audit</span>
        </div>
    </div>
</template>
