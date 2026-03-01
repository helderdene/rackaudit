<script setup lang="ts">
import {
    bulkVerify as bulkVerifyAction,
    discrepant as discrepantAction,
    notFound as notFoundAction,
    index as verificationsIndex,
    stats as verificationsStats,
    verify as verifyAction,
} from '@/actions/App/Http/Controllers/Api/AuditDeviceVerificationController';
import AuditController from '@/actions/App/Http/Controllers/AuditController';
import BulkVerifyDevicesButton from '@/components/audits/BulkVerifyDevicesButton.vue';
import DeviceSearchInput from '@/components/audits/DeviceSearchInput.vue';
import DeviceVerificationActionDialog from '@/components/audits/DeviceVerificationActionDialog.vue';
import DeviceVerificationTable from '@/components/audits/DeviceVerificationTable.vue';
import QrScannerModal from '@/components/audits/QrScannerModal.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { FeatureTour } from '@/components/help';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle,
    ClipboardCheck,
    HelpCircle,
    QrCode,
    RefreshCw,
    XCircle,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

interface ProgressStats {
    total: number;
    verified: number;
    not_found: number;
    discrepant: number;
    pending: number;
    empty_racks_total: number;
    empty_racks_verified: number;
    progress_percentage: number;
}

interface AuditData {
    id: number;
    name: string;
    status: string;
    status_label: string;
    type: string;
    type_label: string;
    scope_type: string;
    scope_type_label: string;
    datacenter: {
        id: number;
        name: string;
    };
    room: {
        id: number;
        name: string;
    } | null;
}

interface RoomOption {
    id: number;
    name: string;
}

interface VerificationStatusOption {
    value: string;
    label: string;
}

interface DeviceData {
    id: number;
    name: string;
    asset_tag: string | null;
    serial_number: string | null;
    manufacturer: string | null;
    model: string | null;
    u_height: number;
    start_u: number | null;
}

interface RackData {
    id: number;
    name: string;
}

interface RoomData {
    id: number;
    name: string;
}

interface VerificationData {
    id: number;
    device: DeviceData | null;
    rack: RackData | null;
    room: RoomData | null;
    verification_status: string;
    verification_status_label: string;
    notes: string | null;
    verified_by: {
        id: number;
        name: string;
    } | null;
    verified_at: string | null;
    locked_by: {
        id: number;
        name: string;
    } | null;
    locked_at: string | null;
    is_locked: boolean;
}

interface Props {
    audit: AuditData;
    progress_stats: ProgressStats;
    rooms: RoomOption[];
    verification_statuses: VerificationStatusOption[];
}

const props = defineProps<Props>();

// Shared select styles with touch-friendly sizing
const selectClass =
    'flex h-11 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50';

// State
const isLoading = ref(true);
const verifications = ref<VerificationData[]>([]);
const loadError = ref<string | null>(null);
const stats = ref<ProgressStats>(props.progress_stats);

// Filter state
const roomFilter = ref<string>('');
const rackFilter = ref<string>('');
const verificationStatusFilter = ref<string>('');
const searchQuery = ref<string>('');

// Pagination
const currentPage = ref(1);
const lastPage = ref(1);
const perPage = ref(25);
const total = ref(0);

// Selection state for bulk actions
const selectedIds = ref<Set<number>>(new Set());

// Dialog state
const actionDialogOpen = ref(false);
const actionDialogVerification = ref<VerificationData | null>(null);
const isActionLoading = ref(false);
const actionError = ref<string | null>(null);

// QR Scanner state
const qrScannerOpen = ref(false);
const scannedDeviceId = ref<number | null>(null);

// Echo channel reference
let echoChannel: any = null;

// Available racks for filtering (derived from verifications)
const availableRacks = computed(() => {
    const racksMap = new Map<number, RackData>();
    verifications.value.forEach((v) => {
        if (v.rack && !racksMap.has(v.rack.id)) {
            racksMap.set(v.rack.id, v.rack);
        }
    });
    return Array.from(racksMap.values()).sort((a, b) =>
        a.name.localeCompare(b.name),
    );
});

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: 'Audits',
        href: AuditController.index.url(),
    },
    {
        title: props.audit.name,
        href: AuditController.show.url(props.audit.id),
    },
    {
        title: 'Inventory Execute',
        href: '#',
    },
]);

// Get status badge class
const getStatusBadgeClass = (status: string): string => {
    const baseClasses =
        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium';
    switch (status) {
        case 'pending':
            return `${baseClasses} bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200`;
        case 'in_progress':
            return `${baseClasses} bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200`;
        case 'completed':
            return `${baseClasses} bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200`;
        case 'cancelled':
            return `${baseClasses} bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300`;
        default:
            return `${baseClasses} bg-gray-100 text-gray-800`;
    }
};

// Devices that can be bulk verified (pending and not locked)
const verifiableSelectedCount = computed(() => {
    return verifications.value.filter(
        (v) =>
            selectedIds.value.has(v.id) &&
            v.verification_status === 'pending' &&
            !v.is_locked,
    ).length;
});

/**
 * Load verifications from API
 */
async function loadVerifications(): Promise<void> {
    isLoading.value = true;
    loadError.value = null;

    try {
        const queryParams: Record<string, string> = {
            page: currentPage.value.toString(),
            per_page: perPage.value.toString(),
        };

        if (roomFilter.value) {
            queryParams.room_id = roomFilter.value;
        }
        if (rackFilter.value) {
            queryParams.rack_id = rackFilter.value;
        }
        if (verificationStatusFilter.value) {
            queryParams.verification_status = verificationStatusFilter.value;
        }
        if (searchQuery.value) {
            queryParams.search = searchQuery.value;
        }

        const response = await axios.get(
            verificationsIndex.url(props.audit.id, { query: queryParams }),
        );

        verifications.value = response.data.data || [];
        currentPage.value = response.data.meta?.current_page || 1;
        lastPage.value = response.data.meta?.last_page || 1;
        total.value = response.data.meta?.total || 0;

        // If we have a scanned device ID, scroll to it
        if (scannedDeviceId.value) {
            scrollToDevice(scannedDeviceId.value);
            scannedDeviceId.value = null;
        }
    } catch (error) {
        console.error('Error loading verifications:', error);
        if (axios.isAxiosError(error) && error.response?.data?.message) {
            loadError.value = error.response.data.message;
        } else {
            loadError.value = 'Failed to load verification items.';
        }
    } finally {
        isLoading.value = false;
    }
}

/**
 * Load progress stats
 */
async function loadStats(): Promise<void> {
    try {
        const response = await axios.get(
            verificationsStats.url(props.audit.id),
        );
        stats.value = response.data.data;
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

/**
 * Handle filter change
 */
function handleFilterChange(): void {
    currentPage.value = 1;
    selectedIds.value.clear();
    loadVerifications();
}

/**
 * Handle search
 */
function handleSearch(query: string): void {
    searchQuery.value = query;
    currentPage.value = 1;
    selectedIds.value.clear();
    loadVerifications();
}

/**
 * Clear filters
 */
function clearFilters(): void {
    roomFilter.value = '';
    rackFilter.value = '';
    verificationStatusFilter.value = '';
    searchQuery.value = '';
    currentPage.value = 1;
    selectedIds.value.clear();
    loadVerifications();
}

/**
 * Open action dialog for a verification
 */
function openActionDialog(verification: VerificationData): void {
    actionDialogVerification.value = verification;
    actionDialogOpen.value = true;
    actionError.value = null;
}

/**
 * Handle verification action (verified, not_found, or discrepant)
 */
async function handleVerificationAction(data: {
    action: 'verified' | 'not_found' | 'discrepant';
    notes: string;
}): Promise<void> {
    if (!actionDialogVerification.value) return;

    isActionLoading.value = true;
    actionError.value = null;

    try {
        let actionUrl: string;

        if (data.action === 'verified') {
            actionUrl = verifyAction.url({
                audit: props.audit.id,
                verification: actionDialogVerification.value.id,
            });
        } else if (data.action === 'not_found') {
            actionUrl = notFoundAction.url({
                audit: props.audit.id,
                verification: actionDialogVerification.value.id,
            });
        } else {
            actionUrl = discrepantAction.url({
                audit: props.audit.id,
                verification: actionDialogVerification.value.id,
            });
        }

        await axios.post(actionUrl, { notes: data.notes || null });

        actionDialogOpen.value = false;
        actionDialogVerification.value = null;

        // Reload data
        await Promise.all([loadVerifications(), loadStats()]);

        // Check if audit status changed to completed
        router.reload({ only: ['audit', 'progress_stats'] });
    } catch (error) {
        console.error('Error processing verification:', error);
        if (axios.isAxiosError(error) && error.response?.data?.message) {
            actionError.value = error.response.data.message;
        } else {
            actionError.value = 'Failed to process verification.';
        }
    } finally {
        isActionLoading.value = false;
    }
}

/**
 * Handle bulk verify action
 */
async function handleBulkVerify(): Promise<{
    verified: number;
    skipped: number;
}> {
    const selectedVerifiableIds = verifications.value
        .filter(
            (v) =>
                selectedIds.value.has(v.id) &&
                v.verification_status === 'pending' &&
                !v.is_locked,
        )
        .map((v) => v.id);

    if (selectedVerifiableIds.length === 0) {
        return { verified: 0, skipped: 0 };
    }

    const response = await axios.post(bulkVerifyAction.url(props.audit.id), {
        verification_ids: selectedVerifiableIds,
    });

    selectedIds.value.clear();

    // Reload data
    await Promise.all([loadVerifications(), loadStats()]);

    // Check if audit status changed
    router.reload({ only: ['audit', 'progress_stats'] });

    return {
        verified: response.data.results.verified_count,
        skipped: response.data.results.skipped_locked_count,
    };
}

/**
 * Toggle selection for a verification
 */
function toggleSelection(id: number): void {
    if (selectedIds.value.has(id)) {
        selectedIds.value.delete(id);
    } else {
        selectedIds.value.add(id);
    }
}

/**
 * Toggle all selections
 */
function toggleAllSelection(): void {
    const pendingVerifications = verifications.value.filter(
        (v) => v.verification_status === 'pending' && !v.is_locked,
    );

    if (pendingVerifications.every((v) => selectedIds.value.has(v.id))) {
        // Deselect all
        pendingVerifications.forEach((v) => selectedIds.value.delete(v.id));
    } else {
        // Select all pending, unlocked
        pendingVerifications.forEach((v) => selectedIds.value.add(v.id));
    }
}

/**
 * Handle QR code scan
 */
function handleQrScan(deviceId: number): void {
    qrScannerOpen.value = false;

    // Check if device is in current filter results
    const foundDevice = verifications.value.find(
        (v) => v.device?.id === deviceId,
    );

    if (foundDevice) {
        // Device in current results, scroll to it
        scrollToDevice(foundDevice.id);
    } else {
        // Device not in current filter, clear filters and search for it
        clearFilters();
        scannedDeviceId.value = deviceId;
        // Verifications will reload and scroll to device after loading
    }
}

/**
 * Scroll to a specific device verification row
 */
function scrollToDevice(verificationId: number): void {
    const element = document.getElementById(`verification-${verificationId}`);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        // Highlight the row temporarily
        element.classList.add('ring-2', 'ring-primary', 'ring-offset-2');
        setTimeout(() => {
            element.classList.remove('ring-2', 'ring-primary', 'ring-offset-2');
        }, 3000);
    }
}

/**
 * Set up Echo channel for real-time updates
 */
function setupEchoChannel(): void {
    // Check if Echo is available (may not be in all environments)
    if (typeof window !== 'undefined' && (window as any).Echo) {
        echoChannel = (window as any).Echo.private(`audit.${props.audit.id}`);

        // Handle device verification completed events
        echoChannel.listen('.device.verified', (data: any) => {
            // Update the verification in the list
            const index = verifications.value.findIndex(
                (v) => v.id === data.verification.id,
            );
            if (index !== -1) {
                verifications.value[index] = data.verification;
            }
            // Refresh stats
            loadStats();
        });

        // Handle device locked events
        echoChannel.listen('.device.locked', (data: any) => {
            const index = verifications.value.findIndex(
                (v) => v.id === data.verification_id,
            );
            if (index !== -1) {
                verifications.value[index].is_locked = true;
                verifications.value[index].locked_by = data.locked_by;
                verifications.value[index].locked_at = data.locked_at;
            }
        });

        // Handle device unlocked events
        echoChannel.listen('.device.unlocked', (data: any) => {
            const index = verifications.value.findIndex(
                (v) => v.id === data.verification_id,
            );
            if (index !== -1) {
                verifications.value[index].is_locked = false;
                verifications.value[index].locked_by = null;
                verifications.value[index].locked_at = null;
            }
        });
    }
}

/**
 * Clean up Echo channel
 */
function cleanupEchoChannel(): void {
    if (echoChannel) {
        echoChannel.stopListening('.device.verified');
        echoChannel.stopListening('.device.locked');
        echoChannel.stopListening('.device.unlocked');
        (window as any).Echo?.leave(`audit.${props.audit.id}`);
        echoChannel = null;
    }
}

// Check if any filters are active
const hasActiveFilters = computed(() => {
    return (
        roomFilter.value ||
        rackFilter.value ||
        verificationStatusFilter.value ||
        searchQuery.value
    );
});

// Lifecycle hooks
onMounted(() => {
    loadVerifications();
    setupEchoChannel();
});

onUnmounted(() => {
    cleanupEchoChannel();
});
</script>

<template>
    <Head :title="`Inventory Execute: ${audit.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 md:gap-6">
            <!-- Header -->
            <div
                data-tour="audit-header"
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="flex items-start gap-4">
                    <Link
                        :href="AuditController.show.url(audit.id)"
                        class="mt-1"
                    >
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-8 min-h-11 min-w-11 md:size-8 md:min-h-8 md:min-w-8 lg:size-8"
                        >
                            <ArrowLeft class="size-4" />
                        </Button>
                    </Link>
                    <div>
                        <HeadingSmall
                            :title="audit.name"
                            description="Verify physical devices against inventory records"
                        />
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span :class="getStatusBadgeClass(audit.status)">
                                {{ audit.status_label }}
                            </span>
                            <Badge variant="outline">{{
                                audit.type_label
                            }}</Badge>
                            <Badge variant="secondary">{{
                                audit.scope_type_label
                            }}</Badge>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <!-- QR Scanner Button - Touch-friendly sizing -->
                    <Button
                        id="qr-scan-btn"
                        variant="outline"
                        class="min-h-11 min-w-11 lg:min-h-9 lg:min-w-0"
                        data-tour-target="qr-scanner"
                        @click="qrScannerOpen = true"
                    >
                        <QrCode class="size-4 lg:mr-1 lg:size-3.5" />
                        <span class="hidden lg:inline">Scan QR</span>
                    </Button>
                    <Button
                        id="inventory-refresh-btn"
                        variant="outline"
                        class="min-h-11 min-w-11 lg:min-h-9 lg:min-w-0"
                        @click="loadVerifications"
                    >
                        <RefreshCw class="size-4 lg:mr-1 lg:size-3.5" />
                        <span class="hidden lg:inline">Refresh</span>
                    </Button>
                </div>
            </div>

            <!-- Progress Card -->
            <Card id="inventory-progress-card" data-tour-target="progress">
                <CardHeader class="pb-3">
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <ClipboardCheck class="size-5" />
                        Verification Progress
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-wrap gap-4 md:gap-6">
                        <div class="flex items-center gap-2">
                            <span
                                class="text-sm font-medium text-muted-foreground"
                                >Total:</span
                            >
                            <Badge variant="secondary" class="text-base">{{
                                stats.total
                            }}</Badge>
                        </div>
                        <div class="flex items-center gap-2">
                            <CheckCircle class="size-4 text-green-600" />
                            <span
                                class="text-sm font-medium text-muted-foreground"
                                >Verified:</span
                            >
                            <Badge class="bg-green-600 text-base">{{
                                stats.verified
                            }}</Badge>
                        </div>
                        <div class="flex items-center gap-2">
                            <XCircle class="size-4 text-red-600" />
                            <span
                                class="text-sm font-medium text-muted-foreground"
                                >Not Found:</span
                            >
                            <Badge variant="destructive" class="text-base">{{
                                stats.not_found
                            }}</Badge>
                        </div>
                        <div class="flex items-center gap-2">
                            <AlertTriangle class="size-4 text-yellow-600" />
                            <span
                                class="text-sm font-medium text-muted-foreground"
                                >Discrepant:</span
                            >
                            <Badge variant="warning" class="text-base">{{
                                stats.discrepant
                            }}</Badge>
                        </div>
                        <div class="flex items-center gap-2">
                            <HelpCircle class="size-4 text-gray-500" />
                            <span
                                class="text-sm font-medium text-muted-foreground"
                                >Pending:</span
                            >
                            <Badge variant="outline" class="text-base">{{
                                stats.pending
                            }}</Badge>
                        </div>
                    </div>

                    <!-- Progress bar - Increased height for tablet readability -->
                    <div v-if="stats.total > 0" class="mt-4">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-muted-foreground"
                                >Completion</span
                            >
                            <span class="font-medium"
                                >{{
                                    stats.progress_percentage.toFixed(1)
                                }}%</span
                            >
                        </div>
                        <div
                            class="mt-2 h-2.5 w-full overflow-hidden rounded-full bg-secondary"
                        >
                            <div
                                class="h-full bg-green-600 transition-all duration-300"
                                :style="{
                                    width: `${stats.progress_percentage}%`,
                                }"
                            />
                        </div>
                    </div>

                    <!-- Empty racks info -->
                    <div
                        v-if="stats.empty_racks_total > 0"
                        class="mt-3 text-sm text-muted-foreground"
                    >
                        Empty racks verified: {{ stats.empty_racks_verified }} /
                        {{ stats.empty_racks_total }}
                    </div>

                    <!-- Completion message -->
                    <div
                        v-if="stats.pending === 0 && stats.total > 0"
                        class="mt-4 flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400"
                    >
                        <CheckCircle class="size-4 shrink-0" />
                        <span
                            >All devices have been verified! The audit is now
                            complete.</span
                        >
                    </div>
                </CardContent>
            </Card>

            <!-- Filter Controls - Stacked on tablet, inline on desktop -->
            <div
                id="inventory-filter-controls"
                class="flex flex-col gap-3 md:gap-4 lg:flex-row lg:flex-wrap lg:items-center"
                data-tour-target="filters"
            >
                <!-- Room Filter -->
                <div v-if="rooms.length > 0" class="flex items-center gap-2">
                    <span
                        class="text-sm font-medium whitespace-nowrap text-muted-foreground"
                        >Room:</span
                    >
                    <select
                        v-model="roomFilter"
                        :class="selectClass"
                        class="w-full lg:w-40"
                        @change="handleFilterChange"
                    >
                        <option value="">All Rooms</option>
                        <option
                            v-for="room in rooms"
                            :key="room.id"
                            :value="room.id"
                        >
                            {{ room.name }}
                        </option>
                    </select>
                </div>

                <!-- Rack Filter -->
                <div
                    v-if="availableRacks.length > 0"
                    data-tour="rack-view"
                    class="flex items-center gap-2"
                >
                    <span
                        class="text-sm font-medium whitespace-nowrap text-muted-foreground"
                        >Rack:</span
                    >
                    <select
                        v-model="rackFilter"
                        :class="selectClass"
                        class="w-full lg:w-40"
                        @change="handleFilterChange"
                    >
                        <option value="">All Racks</option>
                        <option
                            v-for="rack in availableRacks"
                            :key="rack.id"
                            :value="rack.id"
                        >
                            {{ rack.name }}
                        </option>
                    </select>
                </div>

                <!-- Status Filter -->
                <div class="flex items-center gap-2">
                    <span
                        class="text-sm font-medium whitespace-nowrap text-muted-foreground"
                        >Status:</span
                    >
                    <select
                        v-model="verificationStatusFilter"
                        :class="selectClass"
                        class="w-full lg:w-40"
                        @change="handleFilterChange"
                    >
                        <option value="">All</option>
                        <option
                            v-for="status in verification_statuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </option>
                    </select>
                </div>

                <!-- Search Input -->
                <div class="flex-1 lg:max-w-xs">
                    <DeviceSearchInput
                        :model-value="searchQuery"
                        class="h-11 lg:h-9"
                        @search="handleSearch"
                    />
                </div>

                <!-- Clear Filters -->
                <Button
                    v-if="hasActiveFilters"
                    variant="ghost"
                    class="min-h-11 lg:min-h-9"
                    @click="clearFilters"
                >
                    Clear Filters
                </Button>

                <!-- Bulk Actions -->
                <div class="flex items-center gap-2 lg:ml-auto">
                    <BulkVerifyDevicesButton
                        v-if="selectedIds.size > 0"
                        :selected-count="selectedIds.size"
                        :verifiable-count="verifiableSelectedCount"
                        @bulk-verify="handleBulkVerify"
                    />
                </div>
            </div>

            <!-- Loading State -->
            <div
                v-if="isLoading"
                class="flex items-center justify-center py-12"
            >
                <div class="flex flex-col items-center gap-4">
                    <Spinner class="size-8" />
                    <p class="text-sm text-muted-foreground">
                        Loading devices...
                    </p>
                </div>
            </div>

            <!-- Error State -->
            <div
                v-else-if="loadError"
                class="flex flex-col items-center justify-center gap-4 py-12"
            >
                <AlertTriangle class="size-12 text-amber-500" />
                <div class="text-center">
                    <h3 class="text-lg font-medium">Failed to load devices</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ loadError }}
                    </p>
                </div>
                <Button
                    variant="outline"
                    class="min-h-11 lg:min-h-9"
                    @click="loadVerifications"
                    >Try Again</Button
                >
            </div>

            <!-- Device Verification Table -->
            <div id="device-verification-table" data-tour="device-list">
                <DeviceVerificationTable
                    v-if="!isLoading && !loadError"
                    :verifications="verifications"
                    :selected-ids="selectedIds"
                    @toggle-selection="toggleSelection"
                    @toggle-all="toggleAllSelection"
                    @open-action="openActionDialog"
                />
            </div>

            <!-- Empty State -->
            <div
                v-if="!isLoading && !loadError && verifications.length === 0"
                class="flex flex-col items-center justify-center py-12 text-center"
            >
                <ClipboardCheck class="mb-4 size-12 text-muted-foreground/50" />
                <h3 class="text-lg font-medium">No devices found</h3>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{
                        hasActiveFilters
                            ? 'Try adjusting your filters.'
                            : 'No devices to verify for this audit.'
                    }}
                </p>
            </div>

            <!-- Pagination - Touch-friendly controls -->
            <div
                v-if="!isLoading && !loadError && lastPage > 1"
                class="flex flex-col items-center justify-between gap-4 sm:flex-row"
            >
                <p class="text-sm text-muted-foreground">
                    Showing page {{ currentPage }} of {{ lastPage }} ({{
                        total
                    }}
                    devices)
                </p>
                <div class="flex gap-3">
                    <Button
                        variant="outline"
                        class="min-h-11 min-w-20 lg:min-h-9"
                        :disabled="currentPage <= 1"
                        @click="
                            currentPage--;
                            loadVerifications();
                        "
                    >
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        class="min-h-11 min-w-20 lg:min-h-9"
                        :disabled="currentPage >= lastPage"
                        @click="
                            currentPage++;
                            loadVerifications();
                        "
                    >
                        Next
                    </Button>
                </div>
            </div>

            <!-- Device Verification Action Dialog -->
            <DeviceVerificationActionDialog
                v-model:open="actionDialogOpen"
                :verification="actionDialogVerification"
                :is-loading="isActionLoading"
                :error="actionError"
                @submit="handleVerificationAction"
            />

            <!-- QR Scanner Modal -->
            <QrScannerModal v-model:open="qrScannerOpen" @scan="handleQrScan" />
        </div>

        <!-- Feature Tour for Inventory Audit Execution -->
        <FeatureTour
            context-key="audits.execute.inventory"
            :auto-start="true"
        />
    </AppLayout>
</template>
