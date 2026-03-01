import { computed, ref, type Ref } from 'vue';
import axios from 'axios';
import type {
    DevicePosition,
    DeviceWidth,
    PlaceholderDevice,
    RackElevationState,
    RackFace,
    UtilizationStats,
} from '@/types/rooms';
import DeviceController from '@/actions/App/Http/Controllers/DeviceController';

/**
 * Map frontend DeviceWidth to backend width_type format.
 * Frontend uses: 'full', 'half-left', 'half-right'
 * Backend uses: 'full', 'half_left', 'half_right'
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
 * Composable for managing rack elevation state and device placement.
 * Handles collision detection, device placement validation, utilization calculation,
 * and API calls for persisting device placement changes.
 *
 * @param initialPlacedDevices - Devices already placed in the rack
 * @param initialUnplacedDevices - Devices available for placement
 * @param rackHeight - Total rack units available
 * @param rackId - Optional rack ID for API calls (when undefined, operates in local-only mode)
 */
export function useRackElevation(
    initialPlacedDevices: PlaceholderDevice[],
    initialUnplacedDevices: PlaceholderDevice[],
    rackHeight: number,
    rackId?: number,
) {
    // State management
    const placedDevices: Ref<PlaceholderDevice[]> = ref([...initialPlacedDevices]);
    const unplacedDevices: Ref<PlaceholderDevice[]> = ref([...initialUnplacedDevices]);
    const draggedDevice: Ref<PlaceholderDevice | null> = ref(null);
    const isLoading: Ref<boolean> = ref(false);
    const error: Ref<string | null> = ref(null);

    /**
     * Get the current elevation state
     */
    const state = computed<RackElevationState>(() => ({
        placedDevices: placedDevices.value,
        unplacedDevices: unplacedDevices.value,
        draggedDevice: draggedDevice.value,
    }));

    /**
     * Get devices on a specific face
     */
    function getDevicesByFace(face: RackFace): PlaceholderDevice[] {
        return placedDevices.value.filter((d) => d.face === face);
    }

    /**
     * Create an occupation map for a specific face
     * Maps each U position to the devices/widths that occupy it
     */
    function getOccupationMap(
        face: RackFace,
        excludeDeviceId?: string,
    ): Map<number, { full: boolean; halfLeft: boolean; halfRight: boolean }> {
        const map = new Map<number, { full: boolean; halfLeft: boolean; halfRight: boolean }>();

        const devices = getDevicesByFace(face).filter((d) => d.id !== excludeDeviceId);

        for (const device of devices) {
            if (device.start_u === undefined) {
                continue;
            }

            for (let u = device.start_u; u < device.start_u + device.u_size; u++) {
                const current = map.get(u) || { full: false, halfLeft: false, halfRight: false };

                if (device.width === 'full') {
                    current.full = true;
                } else if (device.width === 'half-left') {
                    current.halfLeft = true;
                } else if (device.width === 'half-right') {
                    current.halfRight = true;
                }

                map.set(u, current);
            }
        }

        return map;
    }

    /**
     * Check if a device can be placed at a specific position
     * Considers device size, rack bounds, and collision with existing devices
     */
    function canPlaceAt(
        device: PlaceholderDevice,
        startU: number,
        face: RackFace,
        width?: DeviceWidth,
    ): boolean {
        const deviceWidth = width || device.width;
        const endU = startU + device.u_size - 1;

        // Check rack bounds
        if (startU < 1 || endU > rackHeight) {
            return false;
        }

        // Get occupation map, excluding the device itself if it's being moved
        const occupationMap = getOccupationMap(face, device.id);

        // Check each U position the device would occupy
        for (let u = startU; u <= endU; u++) {
            const occupied = occupationMap.get(u);

            if (!occupied) {
                continue; // Position is free
            }

            // Check for conflicts based on width
            if (deviceWidth === 'full') {
                // Full-width devices conflict with any existing device
                if (occupied.full || occupied.halfLeft || occupied.halfRight) {
                    return false;
                }
            } else if (deviceWidth === 'half-left') {
                // Half-left conflicts with full or another half-left
                if (occupied.full || occupied.halfLeft) {
                    return false;
                }
            } else if (deviceWidth === 'half-right') {
                // Half-right conflicts with full or another half-right
                if (occupied.full || occupied.halfRight) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Place a device from unplaced to placed at a specific position.
     * Makes an API call to persist the placement if rackId is provided.
     */
    async function placeDevice(
        device: PlaceholderDevice,
        position: Omit<DevicePosition, 'device_id'>,
    ): Promise<boolean> {
        if (!canPlaceAt(device, position.start_u, position.face, position.width)) {
            return false;
        }

        // If we have a rackId, make API call to persist placement
        if (rackId !== undefined) {
            isLoading.value = true;
            error.value = null;

            try {
                const deviceId = parseInt(device.id, 10);
                await axios.patch(DeviceController.place.url({ device: deviceId }), {
                    rack_id: rackId,
                    start_u: position.start_u,
                    face: position.face,
                    width_type: mapWidthToBackend(position.width),
                });
            } catch (err: unknown) {
                const axiosError = err as { response?: { data?: { message?: string } } };
                error.value = axiosError.response?.data?.message || 'Failed to place device';
                isLoading.value = false;
                return false;
            }

            isLoading.value = false;
        }

        // Update local state
        unplacedDevices.value = unplacedDevices.value.filter((d) => d.id !== device.id);

        const placedDevice: PlaceholderDevice = {
            ...device,
            start_u: position.start_u,
            face: position.face,
            width: position.width,
        };

        placedDevices.value = [...placedDevices.value, placedDevice];

        return true;
    }

    /**
     * Move a placed device to a new position.
     * Makes an API call to persist the move if rackId is provided.
     */
    async function moveDevice(
        device: PlaceholderDevice,
        newPosition: Omit<DevicePosition, 'device_id'>,
    ): Promise<boolean> {
        if (!canPlaceAt(device, newPosition.start_u, newPosition.face, newPosition.width)) {
            return false;
        }

        // If we have a rackId, make API call to persist the move
        if (rackId !== undefined) {
            isLoading.value = true;
            error.value = null;

            try {
                const deviceId = parseInt(device.id, 10);
                await axios.patch(DeviceController.place.url({ device: deviceId }), {
                    rack_id: rackId,
                    start_u: newPosition.start_u,
                    face: newPosition.face,
                    width_type: mapWidthToBackend(newPosition.width),
                });
            } catch (err: unknown) {
                const axiosError = err as { response?: { data?: { message?: string } } };
                error.value = axiosError.response?.data?.message || 'Failed to move device';
                isLoading.value = false;
                return false;
            }

            isLoading.value = false;
        }

        // Update local state
        placedDevices.value = placedDevices.value.map((d) => {
            if (d.id === device.id) {
                return {
                    ...d,
                    start_u: newPosition.start_u,
                    face: newPosition.face,
                    width: newPosition.width,
                };
            }
            return d;
        });

        return true;
    }

    /**
     * Remove a device from the rack (move back to unplaced).
     * Makes an API call to persist the removal if rackId is provided.
     */
    async function removeDevice(device: PlaceholderDevice): Promise<void> {
        const deviceToRemove = placedDevices.value.find((d) => d.id === device.id);
        if (!deviceToRemove) {
            return;
        }

        // If we have a rackId, make API call to persist removal
        if (rackId !== undefined) {
            isLoading.value = true;
            error.value = null;

            try {
                const deviceId = parseInt(device.id, 10);
                await axios.patch(DeviceController.unplace.url({ device: deviceId }));
            } catch (err: unknown) {
                const axiosError = err as { response?: { data?: { message?: string } } };
                error.value = axiosError.response?.data?.message || 'Failed to remove device';
                isLoading.value = false;
                return;
            }

            isLoading.value = false;
        }

        // Update local state
        placedDevices.value = placedDevices.value.filter((d) => d.id !== device.id);

        const unplacedDevice: PlaceholderDevice = {
            id: deviceToRemove.id,
            name: deviceToRemove.name,
            type: deviceToRemove.type,
            u_size: deviceToRemove.u_size,
            width: deviceToRemove.width,
        };

        unplacedDevices.value = [...unplacedDevices.value, unplacedDevice];
    }

    /**
     * Set the currently dragged device
     */
    function setDraggedDevice(device: PlaceholderDevice | null): void {
        draggedDevice.value = device;
    }

    /**
     * Calculate utilization statistics
     */
    const utilizationStats = computed<UtilizationStats>(() => {
        const totalU = rackHeight;

        // Calculate used U by tracking unique occupied slots
        // For half-width devices, we count each half separately
        const frontOccupied = new Set<string>();
        const rearOccupied = new Set<string>();

        for (const device of placedDevices.value) {
            if (device.start_u === undefined || device.face === undefined) {
                continue;
            }

            const occupied = device.face === 'front' ? frontOccupied : rearOccupied;

            for (let u = device.start_u; u < device.start_u + device.u_size; u++) {
                if (device.width === 'full') {
                    // Full-width counts as using the whole U
                    occupied.add(`${u}-full`);
                } else {
                    // Half-width only counts that half
                    occupied.add(`${u}-${device.width}`);
                }
            }
        }

        // Calculate front and rear used U
        // A U is considered "used" on a face if it has any device (full or half)
        const frontUsedUs = new Set<number>();
        const rearUsedUs = new Set<number>();

        for (const key of frontOccupied) {
            const u = parseInt(key.split('-')[0], 10);
            frontUsedUs.add(u);
        }

        for (const key of rearOccupied) {
            const u = parseInt(key.split('-')[0], 10);
            rearUsedUs.add(u);
        }

        const frontUsedU = frontUsedUs.size;
        const rearUsedU = rearUsedUs.size;

        // For overall utilization, we take the maximum of front and rear
        // since devices can be on different faces independently
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
     * Get valid drop positions for a device on a specific face
     */
    function getValidDropPositions(
        device: PlaceholderDevice,
        face: RackFace,
    ): { startU: number; isValid: boolean }[] {
        const positions: { startU: number; isValid: boolean }[] = [];

        for (let u = 1; u <= rackHeight - device.u_size + 1; u++) {
            positions.push({
                startU: u,
                isValid: canPlaceAt(device, u, face),
            });
        }

        return positions;
    }

    /**
     * Check if a specific U slot is a valid drop target for the currently dragged device
     */
    function isValidDropTarget(uNumber: number, face: RackFace, width?: DeviceWidth): boolean {
        if (!draggedDevice.value) {
            return false;
        }

        return canPlaceAt(draggedDevice.value, uNumber, face, width);
    }

    return {
        // State
        state,
        placedDevices,
        unplacedDevices,
        draggedDevice,
        utilizationStats,
        isLoading,
        error,

        // Methods
        getDevicesByFace,
        canPlaceAt,
        placeDevice,
        moveDevice,
        removeDevice,
        setDraggedDevice,
        getValidDropPositions,
        isValidDropTarget,
        getOccupationMap,
    };
}
