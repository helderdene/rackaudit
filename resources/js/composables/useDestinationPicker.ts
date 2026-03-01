import type { DeviceWidth, RackFace, UtilizationStats } from '@/types/rooms';
import axios from 'axios';
import { computed, ref, type Ref } from 'vue';

/**
 * Interfaces for destination picker data
 */
export interface LocationHierarchy {
    datacenters: DatacenterOption[];
    rooms: RoomOption[];
    rows: RowOption[];
    racks: RackOption[];
}

export interface DatacenterOption {
    id: number;
    name: string;
}

export interface RoomOption {
    id: number;
    name: string;
    datacenter_id: number;
}

export interface RowOption {
    id: number;
    name: string;
    room_id: number;
}

export interface RackOption {
    id: number;
    name: string;
    row_id: number;
    u_height: number;
    position: number;
}

export interface RackWithDevices extends RackOption {
    devices: PlacedDevice[];
}

export interface PlacedDevice {
    id: number;
    name: string;
    start_u: number;
    u_height: number;
    rack_face: string;
    width_type: string;
}

export interface DestinationSelection {
    datacenterId: number | null;
    roomId: number | null;
    rowId: number | null;
    rackId: number | null;
    startU: number | null;
    rackFace: RackFace;
    widthType: DeviceWidth;
}

/**
 * Map backend width_type to frontend DeviceWidth format.
 */
function mapWidthFromBackend(widthType: string): DeviceWidth {
    switch (widthType) {
        case 'half_left':
            return 'half-left';
        case 'half_right':
            return 'half-right';
        default:
            return 'full';
    }
}

/**
 * Map frontend DeviceWidth to backend width_type format.
 */
function mapWidthToBackend(width: DeviceWidth): string {
    switch (width) {
        case 'half-left':
            return 'half_left';
        case 'half-right':
            return 'half_right';
        default:
            return 'full';
    }
}

/**
 * Composable for managing destination rack and position selection.
 * Provides hierarchical location selection, collision detection, and utilization stats.
 *
 * @param initialHierarchy - Optional initial location hierarchy data
 * @param deviceToMove - The device being moved (for collision detection)
 */
export function useDestinationPicker(
    initialHierarchy?: LocationHierarchy,
    deviceToMove?: Ref<{ id: number; u_height: number } | null>,
) {
    // Selection state
    const selection: Ref<DestinationSelection> = ref({
        datacenterId: null,
        roomId: null,
        rowId: null,
        rackId: null,
        startU: null,
        rackFace: 'front',
        widthType: 'full',
    });

    // Location hierarchy data
    const hierarchy: Ref<LocationHierarchy> = ref(
        initialHierarchy || {
            datacenters: [],
            rooms: [],
            rows: [],
            racks: [],
        },
    );

    // Selected rack details with placed devices
    const selectedRack: Ref<RackWithDevices | null> = ref(null);

    // Loading and error states
    const isLoading: Ref<boolean> = ref(false);
    const error: Ref<string | null> = ref(null);

    /**
     * Filtered rooms based on selected datacenter
     */
    const filteredRooms = computed(() => {
        if (!selection.value.datacenterId) {
            return [];
        }
        return hierarchy.value.rooms.filter(
            (r) => r.datacenter_id === selection.value.datacenterId,
        );
    });

    /**
     * Filtered rows based on selected room
     */
    const filteredRows = computed(() => {
        if (!selection.value.roomId) {
            return [];
        }
        return hierarchy.value.rows.filter(
            (r) => r.room_id === selection.value.roomId,
        );
    });

    /**
     * Filtered racks based on selected row
     */
    const filteredRacks = computed(() => {
        if (!selection.value.rowId) {
            return [];
        }
        return hierarchy.value.racks.filter(
            (r) => r.row_id === selection.value.rowId,
        );
    });

    /**
     * Create an occupation map for collision detection
     */
    function getOccupationMap(
        face: RackFace,
        excludeDeviceId?: number,
    ): Map<number, { full: boolean; halfLeft: boolean; halfRight: boolean }> {
        const map = new Map<
            number,
            { full: boolean; halfLeft: boolean; halfRight: boolean }
        >();

        if (!selectedRack.value) {
            return map;
        }

        const devices = selectedRack.value.devices.filter(
            (d) => d.rack_face === face && d.id !== excludeDeviceId,
        );

        for (const device of devices) {
            for (
                let u = device.start_u;
                u < device.start_u + device.u_height;
                u++
            ) {
                const current = map.get(u) || {
                    full: false,
                    halfLeft: false,
                    halfRight: false,
                };

                const width = mapWidthFromBackend(device.width_type);
                if (width === 'full') {
                    current.full = true;
                } else if (width === 'half-left') {
                    current.halfLeft = true;
                } else if (width === 'half-right') {
                    current.halfRight = true;
                }

                map.set(u, current);
            }
        }

        return map;
    }

    /**
     * Check if a device can be placed at a specific position
     */
    function canPlaceAt(
        startU: number,
        face: RackFace,
        width: DeviceWidth,
        uSize: number,
    ): boolean {
        if (!selectedRack.value) {
            return false;
        }

        const rackHeight = selectedRack.value.u_height;
        const endU = startU + uSize - 1;

        // Check rack bounds
        if (startU < 1 || endU > rackHeight) {
            return false;
        }

        // Get occupation map, excluding the device being moved if applicable
        const excludeId = deviceToMove?.value?.id;
        const occupationMap = getOccupationMap(face, excludeId);

        // Check each U position the device would occupy
        for (let u = startU; u <= endU; u++) {
            const occupied = occupationMap.get(u);

            if (!occupied) {
                continue; // Position is free
            }

            // Check for conflicts based on width
            if (width === 'full') {
                if (occupied.full || occupied.halfLeft || occupied.halfRight) {
                    return false;
                }
            } else if (width === 'half-left') {
                if (occupied.full || occupied.halfLeft) {
                    return false;
                }
            } else if (width === 'half-right') {
                if (occupied.full || occupied.halfRight) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get valid drop positions for the current device
     */
    function getValidDropPositions(
        uSize: number,
    ): { startU: number; isValid: boolean }[] {
        if (!selectedRack.value) {
            return [];
        }

        const positions: { startU: number; isValid: boolean }[] = [];
        const rackHeight = selectedRack.value.u_height;

        for (let u = 1; u <= rackHeight - uSize + 1; u++) {
            positions.push({
                startU: u,
                isValid: canPlaceAt(
                    u,
                    selection.value.rackFace,
                    selection.value.widthType,
                    uSize,
                ),
            });
        }

        return positions;
    }

    /**
     * Calculate utilization statistics for the selected rack
     */
    const utilizationStats = computed<UtilizationStats | null>(() => {
        if (!selectedRack.value) {
            return null;
        }

        const totalU = selectedRack.value.u_height;
        const frontUsedUs = new Set<number>();
        const rearUsedUs = new Set<number>();

        for (const device of selectedRack.value.devices) {
            const usedSet =
                device.rack_face === 'front' ? frontUsedUs : rearUsedUs;
            for (
                let u = device.start_u;
                u < device.start_u + device.u_height;
                u++
            ) {
                usedSet.add(u);
            }
        }

        const frontUsedU = frontUsedUs.size;
        const rearUsedU = rearUsedUs.size;
        const usedU = Math.max(frontUsedU, rearUsedU);
        const availableU = totalU - usedU;
        const utilizationPercent = (usedU / totalU) * 100;

        return {
            totalU,
            usedU,
            availableU,
            utilizationPercent,
            frontUsedU,
            rearUsedU,
        };
    });

    /**
     * Fetch location hierarchy from API
     */
    async function fetchHierarchy(): Promise<void> {
        isLoading.value = true;
        error.value = null;

        try {
            const response = await axios.get('/api/locations/hierarchy');
            hierarchy.value = response.data;
        } catch (err: unknown) {
            const axiosError = err as {
                response?: { data?: { message?: string } };
            };
            error.value =
                axiosError.response?.data?.message ||
                'Failed to fetch locations';
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * Fetch rack details with placed devices
     */
    async function fetchRackDetails(rackId: number): Promise<void> {
        isLoading.value = true;
        error.value = null;

        try {
            const response = await axios.get(`/api/racks/${rackId}/devices`);
            selectedRack.value = {
                ...filteredRacks.value.find((r) => r.id === rackId)!,
                devices: response.data.devices || [],
            };
        } catch (err: unknown) {
            const axiosError = err as {
                response?: { data?: { message?: string } };
            };
            error.value =
                axiosError.response?.data?.message ||
                'Failed to fetch rack details';
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * Set datacenter selection and clear downstream selections
     */
    function setDatacenter(datacenterId: number | null): void {
        selection.value.datacenterId = datacenterId;
        selection.value.roomId = null;
        selection.value.rowId = null;
        selection.value.rackId = null;
        selection.value.startU = null;
        selectedRack.value = null;
    }

    /**
     * Set room selection and clear downstream selections
     */
    function setRoom(roomId: number | null): void {
        selection.value.roomId = roomId;
        selection.value.rowId = null;
        selection.value.rackId = null;
        selection.value.startU = null;
        selectedRack.value = null;
    }

    /**
     * Set row selection and clear downstream selections
     */
    function setRow(rowId: number | null): void {
        selection.value.rowId = rowId;
        selection.value.rackId = null;
        selection.value.startU = null;
        selectedRack.value = null;
    }

    /**
     * Set rack selection and fetch rack details
     */
    async function setRack(rackId: number | null): Promise<void> {
        selection.value.rackId = rackId;
        selection.value.startU = null;

        if (rackId) {
            await fetchRackDetails(rackId);
        } else {
            selectedRack.value = null;
        }
    }

    /**
     * Set position selection
     */
    function setPosition(startU: number | null): void {
        selection.value.startU = startU;
    }

    /**
     * Set rack face selection
     */
    function setRackFace(face: RackFace): void {
        selection.value.rackFace = face;
        // Clear position when face changes
        selection.value.startU = null;
    }

    /**
     * Set width type selection
     */
    function setWidthType(width: DeviceWidth): void {
        selection.value.widthType = width;
        // Clear position when width changes
        selection.value.startU = null;
    }

    /**
     * Reset all selections
     */
    function reset(): void {
        selection.value = {
            datacenterId: null,
            roomId: null,
            rowId: null,
            rackId: null,
            startU: null,
            rackFace: 'front',
            widthType: 'full',
        };
        selectedRack.value = null;
    }

    /**
     * Check if destination selection is complete
     */
    const isComplete = computed(() => {
        return (
            selection.value.rackId !== null &&
            selection.value.startU !== null &&
            selection.value.rackFace !== null &&
            selection.value.widthType !== null
        );
    });

    /**
     * Get destination data formatted for API submission
     */
    const destinationData = computed(() => {
        if (!isComplete.value) {
            return null;
        }

        return {
            destination_rack_id: selection.value.rackId,
            destination_start_u: selection.value.startU,
            destination_rack_face: selection.value.rackFace,
            destination_width_type: mapWidthToBackend(
                selection.value.widthType,
            ),
        };
    });

    return {
        // State
        selection,
        hierarchy,
        selectedRack,
        isLoading,
        error,

        // Computed
        filteredRooms,
        filteredRows,
        filteredRacks,
        utilizationStats,
        isComplete,
        destinationData,

        // Methods
        fetchHierarchy,
        fetchRackDetails,
        setDatacenter,
        setRoom,
        setRow,
        setRack,
        setPosition,
        setRackFace,
        setWidthType,
        reset,
        canPlaceAt,
        getValidDropPositions,
        getOccupationMap,
    };
}
