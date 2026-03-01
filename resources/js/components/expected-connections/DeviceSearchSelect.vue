<script setup lang="ts">
import { index as devicesIndex } from '@/actions/App/Http/Controllers/DeviceController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import axios from 'axios';
import { Search, X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

interface DeviceOption {
    id: number;
    name: string;
    asset_tag: string | null;
}

interface Props {
    modelValue: number | null;
    placeholder?: string;
    initialDeviceName?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Select device',
    initialDeviceName: null,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: number | null): void;
}>();

const searchQuery = ref('');
const isLoading = ref(false);
const devices = ref<DeviceOption[]>([]);
const selectedDevice = ref<DeviceOption | null>(null);
const showDropdown = ref(false);
const inputRef = ref<HTMLInputElement | null>(null);

// Debounce timer
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

/**
 * Display text for the input
 */
const displayText = computed(() => {
    if (selectedDevice.value) {
        return selectedDevice.value.name;
    }
    return searchQuery.value;
});

/**
 * Fetch devices based on search query
 */
async function fetchDevices(query: string = ''): Promise<void> {
    isLoading.value = true;

    try {
        const params: Record<string, string> = {};
        if (query) {
            params.search = query;
        }
        params.per_page = '20';

        const response = await axios.get(devicesIndex.url({ query: params }), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        // Handle different response formats
        let deviceData: DeviceOption[] = [];

        if (response.data?.props?.devices?.data) {
            deviceData = response.data.props.devices.data;
        } else if (Array.isArray(response.data?.data)) {
            deviceData = response.data.data;
        } else if (Array.isArray(response.data)) {
            deviceData = response.data;
        }

        devices.value = deviceData.map(
            (device: {
                id: number;
                name: string;
                asset_tag?: string | null;
            }) => ({
                id: device.id,
                name: device.name,
                asset_tag: device.asset_tag ?? null,
            }),
        );
    } catch (error) {
        console.error('Error fetching devices:', error);
        devices.value = [];
    } finally {
        isLoading.value = false;
    }
}

/**
 * Handle search input with debounce
 */
function handleSearch(event: Event): void {
    const target = event.target as HTMLInputElement;
    searchQuery.value = target.value;
    showDropdown.value = true;

    // Clear selection if user is typing
    if (
        selectedDevice.value &&
        searchQuery.value !== selectedDevice.value.name
    ) {
        selectedDevice.value = null;
        emit('update:modelValue', null);
    }

    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    searchTimeout = setTimeout(() => {
        fetchDevices(searchQuery.value);
    }, 300);
}

/**
 * Handle input focus
 */
function handleFocus(): void {
    showDropdown.value = true;
    if (devices.value.length === 0) {
        fetchDevices(searchQuery.value);
    }
}

/**
 * Handle input blur
 */
function handleBlur(): void {
    // Delay hiding dropdown to allow click events
    setTimeout(() => {
        showDropdown.value = false;
    }, 200);
}

/**
 * Select a device
 */
function selectDevice(device: DeviceOption): void {
    selectedDevice.value = device;
    searchQuery.value = device.name;
    emit('update:modelValue', device.id);
    showDropdown.value = false;
}

/**
 * Clear selection
 */
function clearSelection(): void {
    selectedDevice.value = null;
    searchQuery.value = '';
    emit('update:modelValue', null);
    inputRef.value?.focus();
}

/**
 * Load initial device if we have an ID
 */
async function loadInitialDevice(): Promise<void> {
    if (props.modelValue) {
        isLoading.value = true;
        try {
            const response = await axios.get(`/devices/${props.modelValue}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const device =
                response.data?.props?.device ||
                response.data?.data ||
                response.data;
            if (device) {
                selectedDevice.value = {
                    id: device.id,
                    name: device.name,
                    asset_tag: device.asset_tag ?? null,
                };
                searchQuery.value = device.name;
            }
        } catch (error) {
            console.error('Error loading initial device:', error);
        } finally {
            isLoading.value = false;
        }
    } else if (props.initialDeviceName) {
        searchQuery.value = props.initialDeviceName;
    }
}

// Watch for external changes to modelValue
watch(
    () => props.modelValue,
    (newValue) => {
        if (newValue === null) {
            selectedDevice.value = null;
            searchQuery.value = '';
        } else if (newValue !== selectedDevice.value?.id) {
            loadInitialDevice();
        }
    },
);

// Load initial device on mount
onMounted(() => {
    loadInitialDevice();
});
</script>

<template>
    <div class="relative w-full">
        <div class="relative">
            <Search
                class="pointer-events-none absolute top-1/2 left-2 size-3.5 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                ref="inputRef"
                :value="displayText"
                :placeholder="placeholder"
                class="h-8 pr-8 pl-7 text-xs"
                @input="handleSearch"
                @focus="handleFocus"
                @blur="handleBlur"
            />
            <Button
                v-if="selectedDevice || searchQuery"
                type="button"
                variant="ghost"
                size="icon"
                class="absolute top-0 right-0 h-8 w-8"
                @click="clearSelection"
            >
                <X class="size-3" />
            </Button>
        </div>

        <!-- Dropdown -->
        <div
            v-if="showDropdown"
            class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-md border bg-background p-1 shadow-md"
        >
            <div v-if="isLoading" class="flex items-center justify-center py-4">
                <Spinner class="size-4" />
            </div>

            <div
                v-else-if="devices.length === 0"
                class="px-2 py-4 text-center text-xs text-muted-foreground"
            >
                No devices found.
            </div>

            <button
                v-for="device in devices"
                :key="device.id"
                type="button"
                class="flex w-full cursor-pointer items-center rounded-sm px-2 py-1.5 text-left text-xs hover:bg-accent"
                :class="{ 'bg-accent': selectedDevice?.id === device.id }"
                @mousedown.prevent="selectDevice(device)"
            >
                <span class="font-medium">{{ device.name }}</span>
                <span
                    v-if="device.asset_tag"
                    class="ml-1 text-muted-foreground"
                >
                    ({{ device.asset_tag }})
                </span>
            </button>
        </div>
    </div>
</template>
