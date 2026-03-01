<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    AlertTriangle,
    Check,
    CheckCircle,
    Edit2,
    Plus,
    X,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ConnectionRowEditor from './ConnectionRowEditor.vue';
import CreateDevicePortDialog from './CreateDevicePortDialog.vue';

export interface ExpectedConnectionData {
    id: number;
    row_number: number;
    source_device: {
        id: number | null;
        name: string | null;
        asset_tag?: string | null;
    } | null;
    source_port: {
        id: number | null;
        label: string | null;
        type?: string | null;
        type_label?: string | null;
    } | null;
    dest_device: {
        id: number | null;
        name: string | null;
        asset_tag?: string | null;
    } | null;
    dest_port: {
        id: number | null;
        label: string | null;
        type?: string | null;
        type_label?: string | null;
    } | null;
    cable_type: string | null;
    cable_type_label: string | null;
    cable_length: number | null;
    status: 'pending_review' | 'confirmed' | 'skipped';
    status_label: string;
    match?: {
        source_device?: {
            original: string;
            confidence: number;
            match_type: string;
        };
        source_port?: {
            original: string;
            confidence: number;
            match_type: string;
        };
        dest_device?: {
            original: string;
            confidence: number;
            match_type: string;
        };
        dest_port?: {
            original: string;
            confidence: number;
            match_type: string;
        };
        overall_match_type?: string;
    };
}

interface Props {
    connections: ExpectedConnectionData[];
    statistics: {
        total: number;
        pending_review: number;
        confirmed: number;
        skipped: number;
    };
    isLoading?: boolean;
    implementationFileId: number;
}

const props = withDefaults(defineProps<Props>(), {
    isLoading: false,
});

const emit = defineEmits<{
    (
        e: 'update-connection',
        connectionId: number,
        data: Partial<ExpectedConnectionData>,
    ): void;
    (e: 'bulk-confirm', connectionIds: number[]): void;
    (e: 'bulk-skip', connectionIds: number[]): void;
    (
        e: 'create-device-port',
        connectionId: number,
        target: 'source' | 'dest',
    ): void;
    (e: 'refresh'): void;
}>();

// Selection state
const selectedIds = ref<Set<number>>(new Set());
const editingRowId = ref<number | null>(null);

// Create device port dialog state
const createDevicePortDialogOpen = ref(false);
const createDevicePortConnectionId = ref<number | null>(null);
const createDevicePortTarget = ref<'source' | 'dest'>('source');

// Computed properties
const allSelected = computed(() => {
    const pendingConnections = props.connections.filter(
        (c) => c.status === 'pending_review',
    );
    return (
        pendingConnections.length > 0 &&
        pendingConnections.every((c) => selectedIds.value.has(c.id))
    );
});

const someSelected = computed(
    () => selectedIds.value.size > 0 && !allSelected.value,
);

const selectedCount = computed(() => selectedIds.value.size);

const exactMatchConnections = computed(() =>
    props.connections.filter(
        (c) =>
            getOverallMatchType(c) === 'exact' && c.status === 'pending_review',
    ),
);

const unmatchedConnections = computed(() =>
    props.connections.filter(
        (c) =>
            getOverallMatchType(c) === 'unrecognized' &&
            c.status === 'pending_review',
    ),
);

/**
 * Determine the overall match type for a connection
 */
function getOverallMatchType(connection: ExpectedConnectionData): string {
    if (connection.match?.overall_match_type) {
        return connection.match.overall_match_type;
    }

    // Determine based on whether all IDs are present
    const hasAllIds =
        connection.source_device?.id &&
        connection.source_port?.id &&
        connection.dest_device?.id &&
        connection.dest_port?.id;

    if (!hasAllIds) {
        return 'unrecognized';
    }

    // Check individual match confidence
    const matches = [
        connection.match?.source_device,
        connection.match?.source_port,
        connection.match?.dest_device,
        connection.match?.dest_port,
    ].filter(Boolean);

    if (matches.length === 0) {
        return 'exact'; // No match data means it was already matched
    }

    const hasSuggested = matches.some((m) => m?.match_type === 'suggested');
    const hasUnrecognized = matches.some(
        (m) => m?.match_type === 'unrecognized',
    );

    if (hasUnrecognized) return 'unrecognized';
    if (hasSuggested) return 'suggested';
    return 'exact';
}

/**
 * Get the CSS classes for a connection row based on match type
 */
function getRowClasses(connection: ExpectedConnectionData): string {
    if (connection.status === 'confirmed') {
        return 'bg-green-50/50 dark:bg-green-900/10';
    }
    if (connection.status === 'skipped') {
        return 'bg-muted/50 opacity-60';
    }

    const matchType = getOverallMatchType(connection);
    switch (matchType) {
        case 'exact':
            return 'bg-green-50/30 dark:bg-green-900/10 border-l-4 border-l-green-500';
        case 'suggested':
            return 'bg-amber-50/30 dark:bg-amber-900/10 border-l-4 border-l-amber-500';
        case 'unrecognized':
            return 'bg-red-50/30 dark:bg-red-900/10 border-l-4 border-l-red-500';
        default:
            return '';
    }
}

/**
 * Get the match type badge for a cell
 */
function getCellMatchType(match?: {
    match_type: string;
    confidence: number;
}): string | null {
    return match?.match_type ?? null;
}

/**
 * Toggle selection of a single connection
 */
function toggleSelection(id: number): void {
    if (selectedIds.value.has(id)) {
        selectedIds.value.delete(id);
    } else {
        selectedIds.value.add(id);
    }
}

/**
 * Toggle selection of all connections
 */
function toggleAllSelection(): void {
    if (allSelected.value) {
        selectedIds.value.clear();
    } else {
        props.connections
            .filter((c) => c.status === 'pending_review')
            .forEach((c) => selectedIds.value.add(c.id));
    }
}

/**
 * Handle bulk confirm action
 */
function handleBulkConfirm(): void {
    const ids = Array.from(selectedIds.value);
    emit('bulk-confirm', ids);
    selectedIds.value.clear();
}

/**
 * Handle bulk skip action
 */
function handleBulkSkip(): void {
    const ids = Array.from(selectedIds.value);
    emit('bulk-skip', ids);
    selectedIds.value.clear();
}

/**
 * Handle confirm all exact matches
 */
function handleConfirmAllMatched(): void {
    const ids = exactMatchConnections.value.map((c) => c.id);
    emit('bulk-confirm', ids);
}

/**
 * Handle skip all unmatched
 */
function handleSkipAllUnmatched(): void {
    const ids = unmatchedConnections.value.map((c) => c.id);
    emit('bulk-skip', ids);
}

/**
 * Start editing a row
 */
function startEditing(connectionId: number): void {
    editingRowId.value = connectionId;
}

/**
 * Cancel editing
 */
function cancelEditing(): void {
    editingRowId.value = null;
}

/**
 * Handle row update from editor
 */
function handleRowUpdate(
    connectionId: number,
    data: Partial<ExpectedConnectionData>,
): void {
    emit('update-connection', connectionId, data);
    editingRowId.value = null;
}

/**
 * Open create device port dialog
 */
function openCreateDevicePortDialog(
    connectionId: number,
    target: 'source' | 'dest',
): void {
    createDevicePortConnectionId.value = connectionId;
    createDevicePortTarget.value = target;
    createDevicePortDialogOpen.value = true;
}

/**
 * Handle device/port creation success
 */
function handleDevicePortCreated(): void {
    createDevicePortDialogOpen.value = false;
    emit('refresh');
}
</script>

<template>
    <div class="space-y-4">
        <!-- Summary Statistics -->
        <div
            class="flex flex-wrap items-center gap-4 rounded-lg border bg-muted/30 p-4"
        >
            <div class="flex items-center gap-2">
                <span class="text-sm font-medium">Total:</span>
                <Badge variant="secondary">{{ statistics.total }}</Badge>
            </div>
            <div class="flex items-center gap-2">
                <CheckCircle class="size-4 text-green-600" />
                <span class="text-sm">Exact Matches:</span>
                <Badge
                    variant="outline"
                    class="border-green-500 text-green-700 dark:text-green-400"
                >
                    {{ exactMatchConnections.length }}
                </Badge>
            </div>
            <div class="flex items-center gap-2">
                <AlertTriangle class="size-4 text-amber-600" />
                <span class="text-sm">Suggested:</span>
                <Badge
                    variant="outline"
                    class="border-amber-500 text-amber-700 dark:text-amber-400"
                >
                    {{
                        connections.filter(
                            (c) => getOverallMatchType(c) === 'suggested',
                        ).length
                    }}
                </Badge>
            </div>
            <div class="flex items-center gap-2">
                <XCircle class="size-4 text-red-600" />
                <span class="text-sm">Unrecognized:</span>
                <Badge
                    variant="outline"
                    class="border-red-500 text-red-700 dark:text-red-400"
                >
                    {{ unmatchedConnections.length }}
                </Badge>
            </div>
            <div class="ml-auto flex items-center gap-2">
                <span class="text-sm">Confirmed:</span>
                <Badge>{{ statistics.confirmed }}</Badge>
                <span class="text-sm">Skipped:</span>
                <Badge variant="secondary">{{ statistics.skipped }}</Badge>
            </div>
        </div>

        <!-- Bulk Action Buttons -->
        <div class="flex flex-wrap items-center gap-2">
            <div v-if="selectedCount > 0" class="flex items-center gap-2">
                <span class="text-sm text-muted-foreground">
                    {{ selectedCount }} selected
                </span>
                <Button size="sm" @click="handleBulkConfirm">
                    <Check class="mr-1 size-3.5" />
                    Confirm Selected
                </Button>
                <Button size="sm" variant="outline" @click="handleBulkSkip">
                    <X class="mr-1 size-3.5" />
                    Skip Selected
                </Button>
            </div>
            <div class="ml-auto flex gap-2">
                <Button
                    v-if="exactMatchConnections.length > 0"
                    size="sm"
                    variant="outline"
                    class="border-green-500 text-green-700 hover:bg-green-50 dark:text-green-400 dark:hover:bg-green-900/20"
                    @click="handleConfirmAllMatched"
                >
                    <Check class="mr-1 size-3.5" />
                    Confirm All Matched ({{ exactMatchConnections.length }})
                </Button>
                <Button
                    v-if="unmatchedConnections.length > 0"
                    size="sm"
                    variant="outline"
                    class="border-red-500 text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                    @click="handleSkipAllUnmatched"
                >
                    <X class="mr-1 size-3.5" />
                    Skip All Unmatched ({{ unmatchedConnections.length }})
                </Button>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="isLoading" class="flex items-center justify-center py-12">
            <Spinner class="size-8" />
        </div>

        <!-- Data Table -->
        <div v-else class="overflow-hidden rounded-lg border">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="h-10 w-10 px-2">
                                <Checkbox
                                    :checked="allSelected"
                                    :indeterminate="someSelected"
                                    @update:checked="toggleAllSelection"
                                />
                            </th>
                            <th
                                class="h-10 w-16 px-3 text-left font-medium text-muted-foreground"
                            >
                                Row
                            </th>
                            <th
                                class="h-10 px-3 text-left font-medium text-muted-foreground"
                            >
                                Source Device
                            </th>
                            <th
                                class="h-10 px-3 text-left font-medium text-muted-foreground"
                            >
                                Source Port
                            </th>
                            <th
                                class="h-10 px-3 text-left font-medium text-muted-foreground"
                            >
                                Dest Device
                            </th>
                            <th
                                class="h-10 px-3 text-left font-medium text-muted-foreground"
                            >
                                Dest Port
                            </th>
                            <th
                                class="h-10 w-24 px-3 text-left font-medium text-muted-foreground"
                            >
                                Cable
                            </th>
                            <th
                                class="h-10 w-24 px-3 text-left font-medium text-muted-foreground"
                            >
                                Status
                            </th>
                            <th
                                class="h-10 w-28 px-3 text-left font-medium text-muted-foreground"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <template
                            v-for="connection in connections"
                            :key="connection.id"
                        >
                            <!-- Editing Row -->
                            <ConnectionRowEditor
                                v-if="editingRowId === connection.id"
                                :connection="connection"
                                @save="handleRowUpdate"
                                @cancel="cancelEditing"
                            />

                            <!-- Display Row -->
                            <tr
                                v-else
                                class="border-b transition-colors last:border-b-0 hover:bg-muted/50"
                                :class="getRowClasses(connection)"
                            >
                                <!-- Checkbox -->
                                <td class="px-2 py-3">
                                    <Checkbox
                                        :checked="
                                            selectedIds.has(connection.id)
                                        "
                                        :disabled="
                                            connection.status !==
                                            'pending_review'
                                        "
                                        @update:checked="
                                            toggleSelection(connection.id)
                                        "
                                    />
                                </td>

                                <!-- Row Number -->
                                <td class="px-3 py-3 text-muted-foreground">
                                    {{ connection.row_number }}
                                </td>

                                <!-- Source Device -->
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-2">
                                        <span
                                            v-if="
                                                connection.source_device?.name
                                            "
                                            class="font-medium"
                                        >
                                            {{ connection.source_device.name }}
                                        </span>
                                        <span
                                            v-else
                                            class="text-red-600 dark:text-red-400"
                                        >
                                            <TooltipProvider
                                                :delay-duration="0"
                                            >
                                                <Tooltip>
                                                    <TooltipTrigger as-child>
                                                        <span
                                                            class="flex cursor-help items-center gap-1"
                                                        >
                                                            <XCircle
                                                                class="size-3.5"
                                                            />
                                                            Unrecognized
                                                        </span>
                                                    </TooltipTrigger>
                                                    <TooltipContent
                                                        v-if="
                                                            connection.match
                                                                ?.source_device
                                                                ?.original
                                                        "
                                                    >
                                                        <p>
                                                            Original:
                                                            {{
                                                                connection.match
                                                                    .source_device
                                                                    .original
                                                            }}
                                                        </p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        </span>
                                        <Button
                                            v-if="
                                                !connection.source_device?.id &&
                                                connection.status ===
                                                    'pending_review'
                                            "
                                            size="icon"
                                            variant="ghost"
                                            class="size-6"
                                            @click="
                                                openCreateDevicePortDialog(
                                                    connection.id,
                                                    'source',
                                                )
                                            "
                                        >
                                            <Plus class="size-3" />
                                        </Button>
                                    </div>
                                    <div
                                        v-if="
                                            connection.match?.source_device
                                                ?.original &&
                                            connection.source_device?.name &&
                                            getCellMatchType(
                                                connection.match?.source_device,
                                            ) === 'suggested'
                                        "
                                        class="mt-0.5 text-xs text-amber-600 dark:text-amber-400"
                                    >
                                        Original:
                                        {{
                                            connection.match.source_device
                                                .original
                                        }}
                                        ({{
                                            connection.match.source_device
                                                .confidence
                                        }}% match)
                                    </div>
                                </td>

                                <!-- Source Port -->
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-2">
                                        <span
                                            v-if="connection.source_port?.label"
                                        >
                                            {{ connection.source_port.label }}
                                        </span>
                                        <span
                                            v-else
                                            class="text-red-600 dark:text-red-400"
                                        >
                                            <TooltipProvider
                                                :delay-duration="0"
                                            >
                                                <Tooltip>
                                                    <TooltipTrigger as-child>
                                                        <span
                                                            class="flex cursor-help items-center gap-1"
                                                        >
                                                            <XCircle
                                                                class="size-3.5"
                                                            />
                                                            Unrecognized
                                                        </span>
                                                    </TooltipTrigger>
                                                    <TooltipContent
                                                        v-if="
                                                            connection.match
                                                                ?.source_port
                                                                ?.original
                                                        "
                                                    >
                                                        <p>
                                                            Original:
                                                            {{
                                                                connection.match
                                                                    .source_port
                                                                    .original
                                                            }}
                                                        </p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        </span>
                                    </div>
                                    <div
                                        v-if="
                                            connection.match?.source_port
                                                ?.original &&
                                            connection.source_port?.label &&
                                            getCellMatchType(
                                                connection.match?.source_port,
                                            ) === 'suggested'
                                        "
                                        class="mt-0.5 text-xs text-amber-600 dark:text-amber-400"
                                    >
                                        Original:
                                        {{
                                            connection.match.source_port
                                                .original
                                        }}
                                    </div>
                                </td>

                                <!-- Dest Device -->
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-2">
                                        <span
                                            v-if="connection.dest_device?.name"
                                            class="font-medium"
                                        >
                                            {{ connection.dest_device.name }}
                                        </span>
                                        <span
                                            v-else
                                            class="text-red-600 dark:text-red-400"
                                        >
                                            <TooltipProvider
                                                :delay-duration="0"
                                            >
                                                <Tooltip>
                                                    <TooltipTrigger as-child>
                                                        <span
                                                            class="flex cursor-help items-center gap-1"
                                                        >
                                                            <XCircle
                                                                class="size-3.5"
                                                            />
                                                            Unrecognized
                                                        </span>
                                                    </TooltipTrigger>
                                                    <TooltipContent
                                                        v-if="
                                                            connection.match
                                                                ?.dest_device
                                                                ?.original
                                                        "
                                                    >
                                                        <p>
                                                            Original:
                                                            {{
                                                                connection.match
                                                                    .dest_device
                                                                    .original
                                                            }}
                                                        </p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        </span>
                                        <Button
                                            v-if="
                                                !connection.dest_device?.id &&
                                                connection.status ===
                                                    'pending_review'
                                            "
                                            size="icon"
                                            variant="ghost"
                                            class="size-6"
                                            @click="
                                                openCreateDevicePortDialog(
                                                    connection.id,
                                                    'dest',
                                                )
                                            "
                                        >
                                            <Plus class="size-3" />
                                        </Button>
                                    </div>
                                    <div
                                        v-if="
                                            connection.match?.dest_device
                                                ?.original &&
                                            connection.dest_device?.name &&
                                            getCellMatchType(
                                                connection.match?.dest_device,
                                            ) === 'suggested'
                                        "
                                        class="mt-0.5 text-xs text-amber-600 dark:text-amber-400"
                                    >
                                        Original:
                                        {{
                                            connection.match.dest_device
                                                .original
                                        }}
                                        ({{
                                            connection.match.dest_device
                                                .confidence
                                        }}% match)
                                    </div>
                                </td>

                                <!-- Dest Port -->
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-2">
                                        <span
                                            v-if="connection.dest_port?.label"
                                        >
                                            {{ connection.dest_port.label }}
                                        </span>
                                        <span
                                            v-else
                                            class="text-red-600 dark:text-red-400"
                                        >
                                            <TooltipProvider
                                                :delay-duration="0"
                                            >
                                                <Tooltip>
                                                    <TooltipTrigger as-child>
                                                        <span
                                                            class="flex cursor-help items-center gap-1"
                                                        >
                                                            <XCircle
                                                                class="size-3.5"
                                                            />
                                                            Unrecognized
                                                        </span>
                                                    </TooltipTrigger>
                                                    <TooltipContent
                                                        v-if="
                                                            connection.match
                                                                ?.dest_port
                                                                ?.original
                                                        "
                                                    >
                                                        <p>
                                                            Original:
                                                            {{
                                                                connection.match
                                                                    .dest_port
                                                                    .original
                                                            }}
                                                        </p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        </span>
                                    </div>
                                    <div
                                        v-if="
                                            connection.match?.dest_port
                                                ?.original &&
                                            connection.dest_port?.label &&
                                            getCellMatchType(
                                                connection.match?.dest_port,
                                            ) === 'suggested'
                                        "
                                        class="mt-0.5 text-xs text-amber-600 dark:text-amber-400"
                                    >
                                        Original:
                                        {{
                                            connection.match.dest_port.original
                                        }}
                                    </div>
                                </td>

                                <!-- Cable Info -->
                                <td class="px-3 py-3 text-muted-foreground">
                                    <div>
                                        <span
                                            v-if="connection.cable_type_label"
                                            >{{
                                                connection.cable_type_label
                                            }}</span
                                        >
                                        <span v-else>-</span>
                                    </div>
                                    <div
                                        v-if="connection.cable_length"
                                        class="text-xs"
                                    >
                                        {{ connection.cable_length }}m
                                    </div>
                                </td>

                                <!-- Status -->
                                <td class="px-3 py-3">
                                    <Badge
                                        v-if="connection.status === 'confirmed'"
                                        variant="default"
                                        class="bg-green-600"
                                    >
                                        Confirmed
                                    </Badge>
                                    <Badge
                                        v-else-if="
                                            connection.status === 'skipped'
                                        "
                                        variant="secondary"
                                    >
                                        Skipped
                                    </Badge>
                                    <Badge v-else variant="outline">
                                        Pending
                                    </Badge>
                                </td>

                                <!-- Actions -->
                                <td class="px-3 py-3">
                                    <div
                                        v-if="
                                            connection.status ===
                                            'pending_review'
                                        "
                                        class="flex gap-1"
                                    >
                                        <TooltipProvider :delay-duration="0">
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        class="size-7"
                                                        @click="
                                                            startEditing(
                                                                connection.id,
                                                            )
                                                        "
                                                    >
                                                        <Edit2
                                                            class="size-3.5"
                                                        />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent
                                                    >Edit
                                                    mapping</TooltipContent
                                                >
                                            </Tooltip>
                                        </TooltipProvider>
                                    </div>
                                    <span
                                        v-else
                                        class="text-xs text-muted-foreground"
                                        >-</span
                                    >
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Empty State -->
        <div
            v-if="!isLoading && connections.length === 0"
            class="flex flex-col items-center justify-center py-12 text-center"
        >
            <XCircle class="mb-4 size-12 text-muted-foreground/50" />
            <h3 class="text-lg font-medium">No connections found</h3>
            <p class="mt-1 text-sm text-muted-foreground">
                No expected connections have been parsed from this file yet.
            </p>
        </div>

        <!-- Create Device/Port Dialog -->
        <CreateDevicePortDialog
            v-if="createDevicePortConnectionId"
            :is-open="createDevicePortDialogOpen"
            :connection-id="createDevicePortConnectionId"
            :target="createDevicePortTarget"
            @update:is-open="createDevicePortDialogOpen = $event"
            @created="handleDevicePortCreated"
        />
    </div>
</template>
