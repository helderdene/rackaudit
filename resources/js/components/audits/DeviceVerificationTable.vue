<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    CheckCircle,
    XCircle,
    AlertTriangle,
    Lock,
    ClipboardCheck,
    Server,
    HardDrive,
} from 'lucide-vue-next';

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
    verifications: VerificationData[];
    selectedIds: Set<number>;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'toggle-selection', id: number): void;
    (e: 'toggle-all'): void;
    (e: 'open-action', verification: VerificationData): void;
}>();

// Computed
const selectableVerifications = computed(() =>
    props.verifications.filter((v) => v.verification_status === 'pending' && !v.is_locked)
);

const allSelected = computed(() => {
    if (selectableVerifications.value.length === 0) return false;
    return selectableVerifications.value.every((v) => props.selectedIds.has(v.id));
});

const someSelected = computed(
    () => props.selectedIds.size > 0 && !allSelected.value
);

// Group verifications by rack
const groupedByRack = computed(() => {
    const groups = new Map<string, { rack: RackData | null; room: RoomData | null; verifications: VerificationData[] }>();

    props.verifications.forEach(v => {
        const key = v.rack ? `${v.rack.id}` : 'no-rack';
        if (!groups.has(key)) {
            groups.set(key, {
                rack: v.rack,
                room: v.room,
                verifications: [],
            });
        }
        groups.get(key)!.verifications.push(v);
    });

    // Sort by rack name
    return Array.from(groups.values()).sort((a, b) => {
        if (!a.rack) return 1;
        if (!b.rack) return -1;
        return a.rack.name.localeCompare(b.rack.name);
    });
});

/**
 * Get row classes based on verification status
 */
function getRowClasses(verification: VerificationData): string {
    // Locked by another user - gray out
    if (verification.is_locked) {
        return 'bg-muted/50 opacity-75';
    }

    // Based on verification status
    switch (verification.verification_status) {
        case 'verified':
            return 'bg-green-50/50 dark:bg-green-900/10 border-l-4 border-l-green-500';
        case 'not_found':
            return 'bg-red-50/50 dark:bg-red-900/10 border-l-4 border-l-red-500';
        case 'discrepant':
            return 'bg-yellow-50/50 dark:bg-yellow-900/10 border-l-4 border-l-yellow-500';
        default:
            return '';
    }
}

/**
 * Get card classes based on verification status
 */
function getCardClasses(verification: VerificationData): string {
    // Locked by another user - gray out
    if (verification.is_locked) {
        return 'bg-muted/50 opacity-75';
    }

    // Based on verification status
    switch (verification.verification_status) {
        case 'verified':
            return 'border-l-4 border-l-green-500 bg-green-50/50 dark:bg-green-900/10';
        case 'not_found':
            return 'border-l-4 border-l-red-500 bg-red-50/50 dark:bg-red-900/10';
        case 'discrepant':
            return 'border-l-4 border-l-yellow-500 bg-yellow-50/50 dark:bg-yellow-900/10';
        default:
            return '';
    }
}

/**
 * Get verification status icon
 */
function getStatusIcon(status: string) {
    switch (status) {
        case 'verified':
            return CheckCircle;
        case 'not_found':
            return XCircle;
        case 'discrepant':
            return AlertTriangle;
        default:
            return HardDrive;
    }
}

/**
 * Get verification status color class
 */
function getStatusColorClass(status: string): string {
    switch (status) {
        case 'verified':
            return 'text-green-600 dark:text-green-400';
        case 'not_found':
            return 'text-red-600 dark:text-red-400';
        case 'discrepant':
            return 'text-yellow-600 dark:text-yellow-400';
        default:
            return 'text-gray-600 dark:text-gray-400';
    }
}

/**
 * Get verification status badge variant
 */
function getStatusBadgeVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' | 'warning' {
    switch (status) {
        case 'verified':
            return 'default';
        case 'not_found':
            return 'destructive';
        case 'discrepant':
            return 'warning';
        default:
            return 'outline';
    }
}

/**
 * Format U position for display
 */
function formatUPosition(startU: number | null, uHeight: number): string {
    if (startU === null) return '-';
    if (uHeight === 1) return `U${startU}`;
    return `U${startU}-${startU + uHeight - 1}`;
}

/**
 * Format verified at date
 */
function formatDate(dateString: string | null): string {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

/**
 * Check if verification can be acted upon
 */
function canAct(verification: VerificationData): boolean {
    return verification.verification_status === 'pending' && !verification.is_locked;
}

/**
 * Get rack progress stats
 */
function getRackProgress(verifications: VerificationData[]): { verified: number; total: number } {
    const verified = verifications.filter(v => v.verification_status === 'verified').length;
    return { verified, total: verifications.length };
}
</script>

<template>
    <div class="space-y-4">
        <!-- Grouped by Rack -->
        <div
            v-for="group in groupedByRack"
            :key="group.rack?.id || 'no-rack'"
            class="overflow-hidden rounded-lg border"
        >
            <!-- Rack Header -->
            <div class="flex items-center justify-between bg-muted/50 px-4 py-2">
                <div class="flex items-center gap-3">
                    <Server class="size-4 text-muted-foreground" />
                    <div>
                        <span class="font-medium">
                            {{ group.rack?.name || 'Unassigned' }}
                        </span>
                        <span v-if="group.room" class="ml-2 text-sm text-muted-foreground">
                            ({{ group.room.name }})
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                    <span>{{ getRackProgress(group.verifications).verified }}/{{ getRackProgress(group.verifications).total }} verified</span>
                    <div class="h-2 w-16 overflow-hidden rounded-full bg-secondary">
                        <div
                            class="h-full bg-green-600 transition-all duration-300"
                            :style="{ width: `${(getRackProgress(group.verifications).verified / getRackProgress(group.verifications).total) * 100}%` }"
                        />
                    </div>
                </div>
            </div>

            <!-- Mobile/Tablet Card View -->
            <div class="space-y-3 p-3 lg:hidden">
                <div
                    v-for="verification in group.verifications"
                    :id="`verification-${verification.id}`"
                    :key="verification.id"
                    class="rounded-lg border bg-card p-4 shadow-sm transition-colors"
                    :class="getCardClasses(verification)"
                >
                    <!-- Card Header: Checkbox + Device Name + Status -->
                    <div class="mb-3 flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <!-- Checkbox -->
                            <div class="pt-0.5">
                                <Checkbox
                                    :checked="selectedIds.has(verification.id)"
                                    :disabled="!canAct(verification)"
                                    class="size-5"
                                    @update:checked="emit('toggle-selection', verification.id)"
                                />
                            </div>
                            <!-- Device Info -->
                            <div class="flex-1 min-w-0">
                                <p v-if="verification.device?.name" class="font-medium text-sm truncate">
                                    {{ verification.device.name }}
                                </p>
                                <p v-else class="text-sm text-muted-foreground">-</p>
                                <p
                                    v-if="verification.device?.manufacturer || verification.device?.model"
                                    class="text-xs text-muted-foreground truncate"
                                >
                                    {{ [verification.device.manufacturer, verification.device.model].filter(Boolean).join(' ') }}
                                </p>
                            </div>
                        </div>
                        <!-- Status Badge -->
                        <div class="flex items-center gap-1.5 shrink-0">
                            <component
                                :is="getStatusIcon(verification.verification_status)"
                                class="size-4"
                                :class="getStatusColorClass(verification.verification_status)"
                            />
                            <Badge :variant="getStatusBadgeVariant(verification.verification_status)" class="text-xs">
                                {{ verification.verification_status_label }}
                            </Badge>
                        </div>
                    </div>

                    <!-- Card Body: Details Grid -->
                    <div class="grid grid-cols-2 gap-2 text-sm mb-3">
                        <!-- Asset Tag -->
                        <div>
                            <span class="text-xs text-muted-foreground">Asset Tag</span>
                            <p class="font-medium truncate">
                                {{ verification.device?.asset_tag || '-' }}
                            </p>
                        </div>
                        <!-- Position -->
                        <div>
                            <span class="text-xs text-muted-foreground">Position</span>
                            <p class="font-medium">
                                {{ verification.device ? formatUPosition(verification.device.start_u, verification.device.u_height) : '-' }}
                            </p>
                        </div>
                    </div>

                    <!-- Lock indicator -->
                    <div
                        v-if="verification.is_locked"
                        class="mb-3 flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400"
                    >
                        <Lock class="size-3" />
                        <span>Locked by {{ verification.locked_by?.name }}</span>
                    </div>

                    <!-- Verified by info -->
                    <div
                        v-else-if="verification.verified_by"
                        class="mb-3 text-xs text-muted-foreground"
                    >
                        Verified by {{ verification.verified_by.name }} on {{ formatDate(verification.verified_at) }}
                    </div>

                    <!-- Card Footer: Action Button -->
                    <div class="flex justify-end">
                        <Button
                            v-if="canAct(verification)"
                            variant="outline"
                            class="min-h-11 min-w-24"
                            @click="emit('open-action', verification)"
                        >
                            <ClipboardCheck class="mr-1.5 size-4" />
                            Verify
                        </Button>
                        <span
                            v-else-if="verification.is_locked"
                            class="text-xs text-muted-foreground"
                        >
                            Wait for lock to expire
                        </span>
                        <span
                            v-else-if="verification.verification_status !== 'pending'"
                            class="flex items-center gap-1 text-xs text-muted-foreground"
                        >
                            <CheckCircle class="size-3" />
                            Completed
                        </span>
                    </div>
                </div>
            </div>

            <!-- Desktop Table View -->
            <div class="hidden overflow-x-auto lg:block">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/30">
                        <tr>
                            <th class="h-10 w-10 px-2">
                                <Checkbox
                                    :checked="allSelected"
                                    :indeterminate="someSelected"
                                    @update:checked="emit('toggle-all')"
                                />
                            </th>
                            <th class="h-10 px-3 text-left font-medium text-muted-foreground">Device Name</th>
                            <th class="h-10 px-3 text-left font-medium text-muted-foreground">Asset Tag</th>
                            <th class="h-10 px-3 text-left font-medium text-muted-foreground">Serial Number</th>
                            <th class="h-10 w-20 px-3 text-left font-medium text-muted-foreground">Position</th>
                            <th class="h-10 w-32 px-3 text-left font-medium text-muted-foreground">Status</th>
                            <th class="h-10 w-28 px-3 text-left font-medium text-muted-foreground">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="verification in group.verifications"
                            :id="`verification-${verification.id}`"
                            :key="verification.id"
                            class="border-b transition-colors hover:bg-muted/50 last:border-b-0"
                            :class="getRowClasses(verification)"
                        >
                            <!-- Checkbox -->
                            <td class="px-2 py-3">
                                <Checkbox
                                    :checked="selectedIds.has(verification.id)"
                                    :disabled="!canAct(verification)"
                                    @update:checked="emit('toggle-selection', verification.id)"
                                />
                            </td>

                            <!-- Device Name -->
                            <td class="px-3 py-3">
                                <div class="flex flex-col">
                                    <span v-if="verification.device?.name" class="font-medium">
                                        {{ verification.device.name }}
                                    </span>
                                    <span v-else class="text-muted-foreground">-</span>
                                    <span
                                        v-if="verification.device?.manufacturer || verification.device?.model"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ [verification.device.manufacturer, verification.device.model].filter(Boolean).join(' ') }}
                                    </span>
                                </div>
                            </td>

                            <!-- Asset Tag -->
                            <td class="px-3 py-3">
                                <span v-if="verification.device?.asset_tag">
                                    {{ verification.device.asset_tag }}
                                </span>
                                <span v-else class="text-muted-foreground">-</span>
                            </td>

                            <!-- Serial Number -->
                            <td class="px-3 py-3">
                                <span v-if="verification.device?.serial_number" class="font-mono text-xs">
                                    {{ verification.device.serial_number }}
                                </span>
                                <span v-else class="text-muted-foreground">-</span>
                            </td>

                            <!-- U Position -->
                            <td class="px-3 py-3">
                                <span v-if="verification.device">
                                    {{ formatUPosition(verification.device.start_u, verification.device.u_height) }}
                                </span>
                                <span v-else class="text-muted-foreground">-</span>
                            </td>

                            <!-- Verification Status -->
                            <td class="px-3 py-3">
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-1.5">
                                        <component
                                            :is="getStatusIcon(verification.verification_status)"
                                            class="size-4"
                                            :class="getStatusColorClass(verification.verification_status)"
                                        />
                                        <Badge :variant="getStatusBadgeVariant(verification.verification_status)">
                                            {{ verification.verification_status_label }}
                                        </Badge>
                                    </div>
                                    <!-- Show lock indicator -->
                                    <TooltipProvider v-if="verification.is_locked" :delay-duration="0">
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <span class="flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400">
                                                    <Lock class="size-3" />
                                                    Locked
                                                </span>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>Locked by {{ verification.locked_by?.name }}</p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                    <!-- Show verified by info -->
                                    <span
                                        v-else-if="verification.verified_by"
                                        class="text-xs text-muted-foreground"
                                    >
                                        by {{ verification.verified_by.name }}
                                    </span>
                                </div>
                            </td>

                            <!-- Actions -->
                            <td class="px-3 py-3">
                                <div v-if="canAct(verification)">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        class="w-full sm:w-auto"
                                        @click="emit('open-action', verification)"
                                    >
                                        <ClipboardCheck class="mr-1 size-3.5" />
                                        <span class="hidden sm:inline">Verify</span>
                                    </Button>
                                </div>
                                <TooltipProvider v-else-if="verification.is_locked" :delay-duration="0">
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <span class="text-xs text-muted-foreground">
                                                Locked by {{ verification.locked_by?.name }}
                                            </span>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>Wait for this user to finish or for the lock to expire (5 min)</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                                <TooltipProvider v-else-if="verification.verification_status !== 'pending'" :delay-duration="0">
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <span class="flex items-center gap-1 text-xs text-muted-foreground">
                                                <CheckCircle class="size-3" />
                                                {{ formatDate(verification.verified_at) }}
                                            </span>
                                        </TooltipTrigger>
                                        <TooltipContent v-if="verification.notes">
                                            <p class="max-w-xs">{{ verification.notes }}</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Empty state when no groups -->
        <div v-if="groupedByRack.length === 0" class="text-center text-muted-foreground py-8">
            No devices to display
        </div>
    </div>
</template>
