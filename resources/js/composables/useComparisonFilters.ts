import type { DiscrepancyTypeValue } from '@/types/comparison';
import { computed, onMounted, ref } from 'vue';

/**
 * Filter state for comparison view
 */
export interface ComparisonFiltersState {
    /** Selected discrepancy types for multi-select filter */
    discrepancyTypes: DiscrepancyTypeValue[];
    /** Selected device ID for filtering */
    deviceId: number | null;
    /** Selected rack ID for filtering */
    rackId: number | null;
    /** Whether to show acknowledged discrepancies */
    showAcknowledged: boolean;
}

/**
 * Default filter state
 */
const defaultFilters: ComparisonFiltersState = {
    discrepancyTypes: [],
    deviceId: null,
    rackId: null,
    showAcknowledged: true,
};

/**
 * All available discrepancy types for the multi-select dropdown
 */
export const allDiscrepancyTypes: {
    value: DiscrepancyTypeValue;
    label: string;
}[] = [
    { value: 'matched', label: 'Matched' },
    { value: 'missing', label: 'Missing' },
    { value: 'unexpected', label: 'Unexpected' },
    { value: 'mismatched', label: 'Mismatched' },
    { value: 'conflicting', label: 'Conflicting' },
];

/**
 * Parse URL query parameters into filter state
 */
function parseQueryParams(): ComparisonFiltersState {
    if (typeof window === 'undefined') {
        return { ...defaultFilters };
    }

    const params = new URLSearchParams(window.location.search);
    const filters: ComparisonFiltersState = { ...defaultFilters };

    // Parse discrepancy types (array parameter)
    const discrepancyTypes: DiscrepancyTypeValue[] = [];
    params.getAll('discrepancy_type[]').forEach((type) => {
        if (allDiscrepancyTypes.some((t) => t.value === type)) {
            discrepancyTypes.push(type as DiscrepancyTypeValue);
        }
    });
    // Also check for non-bracketed format
    params.getAll('discrepancy_type').forEach((type) => {
        if (
            allDiscrepancyTypes.some((t) => t.value === type) &&
            !discrepancyTypes.includes(type as DiscrepancyTypeValue)
        ) {
            discrepancyTypes.push(type as DiscrepancyTypeValue);
        }
    });
    if (discrepancyTypes.length > 0) {
        filters.discrepancyTypes = discrepancyTypes;
    }

    // Parse device ID
    const deviceId = params.get('device_id');
    if (deviceId && !isNaN(parseInt(deviceId, 10))) {
        filters.deviceId = parseInt(deviceId, 10);
    }

    // Parse rack ID
    const rackId = params.get('rack_id');
    if (rackId && !isNaN(parseInt(rackId, 10))) {
        filters.rackId = parseInt(rackId, 10);
    }

    // Parse show acknowledged - accept 'false', '0', 'true', '1'
    const showAcknowledged = params.get('show_acknowledged');
    if (showAcknowledged !== null) {
        filters.showAcknowledged =
            showAcknowledged !== 'false' && showAcknowledged !== '0';
    }

    return filters;
}

/**
 * Build URL query parameters from filter state for API requests
 */
function buildQueryParams(
    filters: ComparisonFiltersState,
): Record<string, string | string[]> {
    const params: Record<string, string | string[]> = {};

    if (filters.discrepancyTypes.length > 0) {
        params['discrepancy_type[]'] = filters.discrepancyTypes;
    }

    if (filters.deviceId !== null) {
        params['device_id'] = filters.deviceId.toString();
    }

    if (filters.rackId !== null) {
        params['rack_id'] = filters.rackId.toString();
    }

    // Use '0' instead of 'false' for Laravel's boolean validation compatibility
    if (!filters.showAcknowledged) {
        params['show_acknowledged'] = '0';
    }

    return params;
}

/**
 * Build query string from filter state for URL
 */
function buildQueryString(filters: ComparisonFiltersState): string {
    const params = new URLSearchParams();

    filters.discrepancyTypes.forEach((type) => {
        params.append('discrepancy_type[]', type);
    });

    if (filters.deviceId !== null) {
        params.set('device_id', filters.deviceId.toString());
    }

    if (filters.rackId !== null) {
        params.set('rack_id', filters.rackId.toString());
    }

    // Use '0' instead of 'false' for Laravel's boolean validation compatibility
    if (!filters.showAcknowledged) {
        params.set('show_acknowledged', '0');
    }

    return params.toString();
}

/**
 * Composable for managing comparison filter state with URL persistence.
 *
 * Features:
 * - Syncs filter state with URL query parameters
 * - Parses URL params on mount to restore filters
 * - Debounces filter changes to avoid excessive API calls
 * - Provides helper methods for filter manipulation
 *
 * @param options Configuration options
 * @returns Filter state and methods
 */
export function useComparisonFilters(
    options: {
        /** Debounce delay in milliseconds (default: 300) */
        debounceMs?: number;
        /** Callback when filters change */
        onFiltersChange?: (filters: ComparisonFiltersState) => void;
        /** Whether to sync with URL (default: true) */
        syncWithUrl?: boolean;
    } = {},
) {
    const { debounceMs = 300, onFiltersChange, syncWithUrl = true } = options;

    // Filter state
    const filters = ref<ComparisonFiltersState>({ ...defaultFilters });

    // Loading state during filter application
    const isApplyingFilters = ref(false);

    // Debounce timer
    let debounceTimer: ReturnType<typeof setTimeout> | null = null;

    /**
     * Get the current query parameters for API requests
     */
    const queryParams = computed(() => buildQueryParams(filters.value));

    /**
     * Get the query string for URL updates
     */
    const queryString = computed(() => buildQueryString(filters.value));

    /**
     * Check if any filters are active
     */
    const hasActiveFilters = computed(() => {
        return (
            filters.value.discrepancyTypes.length > 0 ||
            filters.value.deviceId !== null ||
            filters.value.rackId !== null ||
            !filters.value.showAcknowledged
        );
    });

    /**
     * Get count of active filters
     */
    const activeFilterCount = computed(() => {
        let count = 0;
        if (filters.value.discrepancyTypes.length > 0) count++;
        if (filters.value.deviceId !== null) count++;
        if (filters.value.rackId !== null) count++;
        if (!filters.value.showAcknowledged) count++;
        return count;
    });

    /**
     * Update URL with current filter state
     */
    function updateUrl(): void {
        if (!syncWithUrl || typeof window === 'undefined') {
            return;
        }

        const baseUrl = window.location.pathname;
        const newQueryString = queryString.value;
        const newUrl = newQueryString
            ? `${baseUrl}?${newQueryString}`
            : baseUrl;

        // Use replaceState to avoid adding to browser history on every filter change
        window.history.replaceState({}, '', newUrl);
    }

    /**
     * Apply filter changes with debouncing
     */
    function applyFilters(): void {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }

        debounceTimer = setTimeout(() => {
            isApplyingFilters.value = true;
            updateUrl();

            if (onFiltersChange) {
                onFiltersChange(filters.value);
            }

            // Reset loading state after a short delay
            setTimeout(() => {
                isApplyingFilters.value = false;
            }, 100);
        }, debounceMs);
    }

    /**
     * Set discrepancy type filter
     */
    function setDiscrepancyTypes(types: DiscrepancyTypeValue[]): void {
        filters.value.discrepancyTypes = [...types];
        applyFilters();
    }

    /**
     * Toggle a discrepancy type in the filter
     */
    function toggleDiscrepancyType(type: DiscrepancyTypeValue): void {
        const index = filters.value.discrepancyTypes.indexOf(type);
        if (index === -1) {
            filters.value.discrepancyTypes.push(type);
        } else {
            filters.value.discrepancyTypes.splice(index, 1);
        }
        applyFilters();
    }

    /**
     * Set device filter
     */
    function setDeviceId(id: number | null): void {
        filters.value.deviceId = id;
        applyFilters();
    }

    /**
     * Set rack filter
     */
    function setRackId(id: number | null): void {
        filters.value.rackId = id;
        applyFilters();
    }

    /**
     * Set show acknowledged filter
     */
    function setShowAcknowledged(value: boolean): void {
        filters.value.showAcknowledged = value;
        applyFilters();
    }

    /**
     * Reset all filters to defaults
     */
    function resetFilters(): void {
        filters.value = { ...defaultFilters };
        applyFilters();
    }

    /**
     * Initialize filters from URL on mount
     */
    function initializeFromUrl(): void {
        if (!syncWithUrl) {
            return;
        }

        const urlFilters = parseQueryParams();
        filters.value = urlFilters;
    }

    // Initialize from URL on mount
    onMounted(() => {
        initializeFromUrl();
    });

    return {
        // State
        filters,
        isApplyingFilters,

        // Computed
        queryParams,
        queryString,
        hasActiveFilters,
        activeFilterCount,

        // Methods
        setDiscrepancyTypes,
        toggleDiscrepancyType,
        setDeviceId,
        setRackId,
        setShowAcknowledged,
        resetFilters,
        initializeFromUrl,
        applyFilters,
    };
}
