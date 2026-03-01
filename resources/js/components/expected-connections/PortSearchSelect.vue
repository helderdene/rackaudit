<script setup lang="ts">
import { index as portsIndex } from '@/actions/App/Http/Controllers/PortController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import axios from 'axios';
import { Search, X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

interface PortOption {
    id: number;
    label: string;
    type: string | null;
    type_label: string | null;
    status: string;
}

interface Props {
    modelValue: number | null;
    deviceId: number | null;
    placeholder?: string;
    initialPortLabel?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Select port',
    initialPortLabel: null,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: number | null): void;
}>();

const searchQuery = ref('');
const isLoading = ref(false);
const ports = ref<PortOption[]>([]);
const selectedPort = ref<PortOption | null>(null);
const showDropdown = ref(false);
const inputRef = ref<HTMLInputElement | null>(null);

// Debounce timer
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

/**
 * Display text for the input
 */
const displayText = computed(() => {
    if (selectedPort.value) {
        return selectedPort.value.label;
    }
    return searchQuery.value;
});

/**
 * Check if component is disabled
 */
const isDisabled = computed(() => !props.deviceId);

/**
 * Fetch ports for the selected device
 */
async function fetchPorts(query: string = ''): Promise<void> {
    if (!props.deviceId) {
        ports.value = [];
        return;
    }

    isLoading.value = true;

    try {
        const response = await axios.get(
            portsIndex.url({ device: props.deviceId }),
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        // Handle different response formats
        let portData: PortOption[] = [];

        if (response.data?.props?.ports?.data) {
            portData = response.data.props.ports.data;
        } else if (Array.isArray(response.data?.data)) {
            portData = response.data.data;
        } else if (Array.isArray(response.data)) {
            portData = response.data;
        }

        ports.value = portData.map(
            (port: {
                id: number;
                label: string;
                type?: string | null;
                type_label?: string | null;
                status: string;
            }) => ({
                id: port.id,
                label: port.label,
                type: port.type ?? null,
                type_label: port.type_label ?? null,
                status: port.status,
            }),
        );

        // Filter by search query if provided
        if (query) {
            const lowerQuery = query.toLowerCase();
            ports.value = ports.value.filter((port) =>
                port.label.toLowerCase().includes(lowerQuery),
            );
        }
    } catch (error) {
        console.error('Error fetching ports:', error);
        ports.value = [];
    } finally {
        isLoading.value = false;
    }
}

/**
 * Handle search input with debounce
 */
function handleSearch(event: Event): void {
    if (isDisabled.value) return;

    const target = event.target as HTMLInputElement;
    searchQuery.value = target.value;
    showDropdown.value = true;

    // Clear selection if user is typing
    if (selectedPort.value && searchQuery.value !== selectedPort.value.label) {
        selectedPort.value = null;
        emit('update:modelValue', null);
    }

    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    searchTimeout = setTimeout(() => {
        fetchPorts(searchQuery.value);
    }, 300);
}

/**
 * Handle input focus
 */
function handleFocus(): void {
    if (isDisabled.value) return;
    showDropdown.value = true;
    if (ports.value.length === 0) {
        fetchPorts(searchQuery.value);
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
 * Select a port
 */
function selectPort(port: PortOption): void {
    selectedPort.value = port;
    searchQuery.value = port.label;
    emit('update:modelValue', port.id);
    showDropdown.value = false;
}

/**
 * Clear selection
 */
function clearSelection(): void {
    selectedPort.value = null;
    searchQuery.value = '';
    emit('update:modelValue', null);
    inputRef.value?.focus();
}

/**
 * Load initial port if we have an ID
 */
async function loadInitialPort(): Promise<void> {
    if (props.modelValue && props.deviceId) {
        isLoading.value = true;
        try {
            const response = await axios.get(
                `/devices/${props.deviceId}/ports/${props.modelValue}`,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            );

            const port =
                response.data?.props?.port ||
                response.data?.data ||
                response.data;
            if (port) {
                selectedPort.value = {
                    id: port.id,
                    label: port.label,
                    type: port.type ?? null,
                    type_label: port.type_label ?? null,
                    status: port.status,
                };
                searchQuery.value = port.label;
            }
        } catch (error) {
            console.error('Error loading initial port:', error);
        } finally {
            isLoading.value = false;
        }
    } else if (props.initialPortLabel) {
        searchQuery.value = props.initialPortLabel;
    }
}

// Watch for device changes - reset port selection
watch(
    () => props.deviceId,
    (newValue, oldValue) => {
        if (newValue !== oldValue) {
            selectedPort.value = null;
            searchQuery.value = '';
            ports.value = [];
            emit('update:modelValue', null);
        }
    },
);

// Watch for external changes to modelValue
watch(
    () => props.modelValue,
    (newValue) => {
        if (newValue === null) {
            selectedPort.value = null;
            searchQuery.value = '';
        } else if (newValue !== selectedPort.value?.id && props.deviceId) {
            loadInitialPort();
        }
    },
);

// Load initial port on mount
onMounted(() => {
    if (props.modelValue && props.deviceId) {
        loadInitialPort();
    } else if (props.initialPortLabel) {
        searchQuery.value = props.initialPortLabel;
    }
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
                :value="isDisabled ? 'Select device first' : displayText"
                :placeholder="placeholder"
                :disabled="isDisabled"
                class="h-8 pr-8 pl-7 text-xs"
                :class="{ 'text-muted-foreground': isDisabled }"
                @input="handleSearch"
                @focus="handleFocus"
                @blur="handleBlur"
            />
            <Button
                v-if="!isDisabled && (selectedPort || searchQuery)"
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
            v-if="showDropdown && !isDisabled"
            class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-md border bg-background p-1 shadow-md"
        >
            <div v-if="isLoading" class="flex items-center justify-center py-4">
                <Spinner class="size-4" />
            </div>

            <div
                v-else-if="ports.length === 0"
                class="px-2 py-4 text-center text-xs text-muted-foreground"
            >
                No ports found.
            </div>

            <button
                v-for="port in ports"
                :key="port.id"
                type="button"
                class="flex w-full cursor-pointer items-center rounded-sm px-2 py-1.5 text-left text-xs hover:bg-accent"
                :class="{ 'bg-accent': selectedPort?.id === port.id }"
                @mousedown.prevent="selectPort(port)"
            >
                <span class="font-medium">{{ port.label }}</span>
                <span v-if="port.type_label" class="ml-1 text-muted-foreground">
                    ({{ port.type_label }})
                </span>
            </button>
        </div>
    </div>
</template>
