<script setup lang="ts">
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type {
    AvailablePortOption,
    HierarchicalFilterOptions,
    PortTypeValue,
} from '@/types/connections';
import { computed, ref, watch } from 'vue';

interface DeviceOption {
    id: number;
    name: string;
    rack_id: number;
}

interface Props {
    /** Hierarchical filter options for datacenter/room/row/rack */
    filterOptions: HierarchicalFilterOptions;
    /** Source port type to filter destination ports by matching type */
    sourcePortType: PortTypeValue;
    /** Device ID to exclude from selection (current device) */
    excludeDeviceId?: number;
    /** Currently selected port */
    modelValue: AvailablePortOption | null;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: AvailablePortOption | null): void;
}>();

// Selection state for cascading dropdowns
const selectedDatacenterId = ref<number | null>(null);
const selectedRoomId = ref<number | null>(null);
const selectedRowId = ref<number | null>(null);
const selectedRackId = ref<number | null>(null);
const selectedDeviceId = ref<number | null>(null);
const selectedPortId = ref<number | null>(null);

// Loading states
const isLoadingDevices = ref(false);
const isLoadingPorts = ref(false);

// Fetched data
const devices = ref<DeviceOption[]>([]);
const ports = ref<AvailablePortOption[]>([]);

// Filtered options based on parent selections
const filteredRooms = computed(() => {
    if (!selectedDatacenterId.value) return [];
    return props.filterOptions.rooms.filter(
        (room) => room.datacenter_id === selectedDatacenterId.value,
    );
});

const filteredRows = computed(() => {
    if (!selectedRoomId.value) return [];
    return props.filterOptions.rows.filter(
        (row) => row.room_id === selectedRoomId.value,
    );
});

const filteredRacks = computed(() => {
    if (!selectedRowId.value) return [];
    return props.filterOptions.racks.filter(
        (rack) => rack.row_id === selectedRowId.value,
    );
});

// Filtered devices: exclude current device
const filteredDevices = computed(() => {
    if (!props.excludeDeviceId) return devices.value;
    return devices.value.filter(
        (device) => device.id !== props.excludeDeviceId,
    );
});

// Filtered ports: only available and matching type
const filteredPorts = computed(() => {
    return ports.value.filter(
        (port) =>
            port.status === 'available' && port.type === props.sourcePortType,
    );
});

// Reset cascading selections when parent changes
watch(selectedDatacenterId, () => {
    selectedRoomId.value = null;
    selectedRowId.value = null;
    selectedRackId.value = null;
    selectedDeviceId.value = null;
    selectedPortId.value = null;
    devices.value = [];
    ports.value = [];
    emit('update:modelValue', null);
});

watch(selectedRoomId, () => {
    selectedRowId.value = null;
    selectedRackId.value = null;
    selectedDeviceId.value = null;
    selectedPortId.value = null;
    devices.value = [];
    ports.value = [];
    emit('update:modelValue', null);
});

watch(selectedRowId, () => {
    selectedRackId.value = null;
    selectedDeviceId.value = null;
    selectedPortId.value = null;
    devices.value = [];
    ports.value = [];
    emit('update:modelValue', null);
});

watch(selectedRackId, async (newRackId) => {
    selectedDeviceId.value = null;
    selectedPortId.value = null;
    ports.value = [];
    emit('update:modelValue', null);

    if (newRackId) {
        await fetchDevicesForRack(newRackId);
    } else {
        devices.value = [];
    }
});

watch(selectedDeviceId, async (newDeviceId) => {
    selectedPortId.value = null;
    emit('update:modelValue', null);

    if (newDeviceId) {
        await fetchPortsForDevice(newDeviceId);
    } else {
        ports.value = [];
    }
});

watch(selectedPortId, (newPortId) => {
    if (newPortId) {
        const selectedPort = filteredPorts.value.find(
            (p) => p.id === newPortId,
        );
        emit('update:modelValue', selectedPort || null);
    } else {
        emit('update:modelValue', null);
    }
});

/**
 * Fetch devices for a specific rack
 */
async function fetchDevicesForRack(rackId: number): Promise<void> {
    isLoadingDevices.value = true;
    try {
        const response = await fetch(`/devices?rack_id=${rackId}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('Failed to fetch devices');
        }

        const data = await response.json();

        // Handle both Inertia page response and JSON API response formats
        if (data.props?.devices?.data) {
            // Inertia response format
            devices.value = data.props.devices.data.map(
                (device: { id: number; name: string; rack_id: number }) => ({
                    id: device.id,
                    name: device.name,
                    rack_id: device.rack_id,
                }),
            );
        } else if (Array.isArray(data.data)) {
            // JSON API response format
            devices.value = data.data.map(
                (device: { id: number; name: string; rack_id: number }) => ({
                    id: device.id,
                    name: device.name,
                    rack_id: device.rack_id,
                }),
            );
        } else if (Array.isArray(data)) {
            // Direct array response
            devices.value = data.map(
                (device: { id: number; name: string; rack_id: number }) => ({
                    id: device.id,
                    name: device.name,
                    rack_id: device.rack_id,
                }),
            );
        } else {
            devices.value = [];
        }
    } catch (error) {
        console.error('Error fetching devices:', error);
        devices.value = [];
    } finally {
        isLoadingDevices.value = false;
    }
}

/**
 * Fetch ports for a specific device
 */
async function fetchPortsForDevice(deviceId: number): Promise<void> {
    isLoadingPorts.value = true;
    try {
        const response = await fetch(`/devices/${deviceId}/ports`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('Failed to fetch ports');
        }

        const data = await response.json();
        const portsData = Array.isArray(data.data)
            ? data.data
            : Array.isArray(data)
              ? data
              : [];

        // Get device name for display
        const device = filteredDevices.value.find((d) => d.id === deviceId);
        const deviceName = device?.name || 'Unknown Device';

        ports.value = portsData.map(
            (port: {
                id: number;
                label: string;
                type: PortTypeValue;
                status: string;
                subtype_label?: string;
                device_id: number;
            }) => ({
                id: port.id,
                label: port.label,
                device_name: deviceName,
                device_id: port.device_id,
                type: port.type,
                status: port.status,
                subtype_label: port.subtype_label,
            }),
        );
    } catch (error) {
        console.error('Error fetching ports:', error);
        ports.value = [];
    } finally {
        isLoadingPorts.value = false;
    }
}

// Select styling class matching existing patterns
const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50';
</script>

<template>
    <div class="space-y-4">
        <!-- Datacenter -->
        <div class="grid gap-2">
            <Label for="selector-datacenter">Datacenter</Label>
            <select
                id="selector-datacenter"
                v-model="selectedDatacenterId"
                :class="selectClass"
            >
                <option :value="null">Select datacenter</option>
                <option
                    v-for="dc in filterOptions.datacenters"
                    :key="dc.value"
                    :value="dc.value"
                >
                    {{ dc.label }}
                </option>
            </select>
        </div>

        <!-- Room -->
        <div class="grid gap-2">
            <Label for="selector-room">Room</Label>
            <select
                id="selector-room"
                v-model="selectedRoomId"
                :disabled="!selectedDatacenterId || filteredRooms.length === 0"
                :class="selectClass"
            >
                <option :value="null">
                    {{
                        selectedDatacenterId
                            ? filteredRooms.length > 0
                                ? 'Select room'
                                : 'No rooms available'
                            : 'Select datacenter first'
                    }}
                </option>
                <option
                    v-for="room in filteredRooms"
                    :key="room.value"
                    :value="room.value"
                >
                    {{ room.label }}
                </option>
            </select>
        </div>

        <!-- Row -->
        <div class="grid gap-2">
            <Label for="selector-row">Row</Label>
            <select
                id="selector-row"
                v-model="selectedRowId"
                :disabled="!selectedRoomId || filteredRows.length === 0"
                :class="selectClass"
            >
                <option :value="null">
                    {{
                        selectedRoomId
                            ? filteredRows.length > 0
                                ? 'Select row'
                                : 'No rows available'
                            : 'Select room first'
                    }}
                </option>
                <option
                    v-for="row in filteredRows"
                    :key="row.value"
                    :value="row.value"
                >
                    {{ row.label }}
                </option>
            </select>
        </div>

        <!-- Rack -->
        <div class="grid gap-2">
            <Label for="selector-rack">Rack</Label>
            <select
                id="selector-rack"
                v-model="selectedRackId"
                :disabled="!selectedRowId || filteredRacks.length === 0"
                :class="selectClass"
            >
                <option :value="null">
                    {{
                        selectedRowId
                            ? filteredRacks.length > 0
                                ? 'Select rack'
                                : 'No racks available'
                            : 'Select row first'
                    }}
                </option>
                <option
                    v-for="rack in filteredRacks"
                    :key="rack.value"
                    :value="rack.value"
                >
                    {{ rack.label }}
                </option>
            </select>
        </div>

        <!-- Device -->
        <div class="grid gap-2">
            <Label for="selector-device">Device</Label>
            <div class="relative">
                <select
                    id="selector-device"
                    v-model="selectedDeviceId"
                    :disabled="
                        !selectedRackId ||
                        isLoadingDevices ||
                        filteredDevices.length === 0
                    "
                    :class="selectClass"
                >
                    <option :value="null">
                        <template v-if="isLoadingDevices"
                            >Loading devices...</template
                        >
                        <template v-else-if="!selectedRackId"
                            >Select rack first</template
                        >
                        <template v-else-if="filteredDevices.length === 0"
                            >No devices available</template
                        >
                        <template v-else>Select device</template>
                    </option>
                    <option
                        v-for="device in filteredDevices"
                        :key="device.id"
                        :value="device.id"
                    >
                        {{ device.name }}
                    </option>
                </select>
                <Spinner
                    v-if="isLoadingDevices"
                    class="absolute top-1/2 right-8 -translate-y-1/2"
                />
            </div>
        </div>

        <!-- Port -->
        <div class="grid gap-2">
            <Label for="selector-port">Port</Label>
            <div class="relative">
                <select
                    id="selector-port"
                    v-model="selectedPortId"
                    :disabled="
                        !selectedDeviceId ||
                        isLoadingPorts ||
                        filteredPorts.length === 0
                    "
                    :class="selectClass"
                >
                    <option :value="null">
                        <template v-if="isLoadingPorts"
                            >Loading ports...</template
                        >
                        <template v-else-if="!selectedDeviceId"
                            >Select device first</template
                        >
                        <template v-else-if="filteredPorts.length === 0"
                            >No available ports of matching type</template
                        >
                        <template v-else>Select port</template>
                    </option>
                    <option
                        v-for="port in filteredPorts"
                        :key="port.id"
                        :value="port.id"
                    >
                        {{ port.label
                        }}{{
                            port.subtype_label ? ` (${port.subtype_label})` : ''
                        }}
                    </option>
                </select>
                <Spinner
                    v-if="isLoadingPorts"
                    class="absolute top-1/2 right-8 -translate-y-1/2"
                />
            </div>
            <p
                v-if="
                    selectedDeviceId &&
                    !isLoadingPorts &&
                    filteredPorts.length === 0
                "
                class="text-xs text-muted-foreground"
            >
                No available {{ sourcePortType }} ports found on this device.
            </p>
        </div>
    </div>
</template>
