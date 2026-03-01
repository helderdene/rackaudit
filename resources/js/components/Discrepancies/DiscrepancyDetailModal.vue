<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Badge, type BadgeVariants } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { CheckCircle, XCircle, ArrowRight, Server, Cable, Settings, AlertTriangle } from 'lucide-vue-next';

interface PortData {
    id: number;
    label: string;
    type: string | null;
    type_label: string | null;
    device_id: number | null;
    device: {
        id: number;
        name: string;
        asset_tag: string | null;
        rack: {
            id: number;
            name: string;
        } | null;
    } | null;
}

interface DiscrepancyData {
    id: number;
    discrepancy_type: string;
    discrepancy_type_label: string;
    status: string;
    status_label: string;
    title: string | null;
    description: string | null;
    detected_at: string | null;
    datacenter: {
        id: number;
        name: string;
    } | null;
    room: {
        id: number;
        name: string;
    } | null;
    source_port: PortData | null;
    dest_port: PortData | null;
    expected_config: Record<string, unknown> | null;
    actual_config: Record<string, unknown> | null;
    mismatch_details: Record<string, unknown> | null;
}

interface Props {
    discrepancy: DiscrepancyData;
    open: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    close: [];
}>();

// State
const isProcessing = ref(false);
const notes = ref('');
const error = ref<string | null>(null);

// Get type badge variant
const getTypeBadgeVariant = (type: string): BadgeVariants['variant'] => {
    switch (type) {
        case 'missing':
        case 'conflicting':
            return 'destructive';
        case 'unexpected':
        case 'configuration_mismatch':
            return 'warning';
        case 'mismatched':
            return 'info';
        default:
            return 'secondary';
    }
};

// Get status badge variant
const getStatusBadgeVariant = (status: string): BadgeVariants['variant'] => {
    switch (status) {
        case 'open':
            return 'destructive';
        case 'acknowledged':
            return 'warning';
        case 'resolved':
            return 'success';
        case 'in_audit':
            return 'info';
        default:
            return 'secondary';
    }
};

// Check if can acknowledge
const canAcknowledge = computed(() => {
    return props.discrepancy.status === 'open';
});

// Check if can resolve
const canResolve = computed(() => {
    return props.discrepancy.status === 'open' || props.discrepancy.status === 'acknowledged';
});

// Has mismatch details
const hasMismatchDetails = computed(() => {
    return props.discrepancy.mismatch_details && Object.keys(props.discrepancy.mismatch_details).length > 0;
});

// Format port info
const formatPortDisplay = (port: PortData | null) => {
    if (!port) return null;
    return {
        port: port.label,
        type: port.type_label || 'Unknown',
        device: port.device?.name || 'Unknown Device',
        assetTag: port.device?.asset_tag || '-',
        rack: port.device?.rack?.name || '-',
    };
};

// Format date
const formatDate = (dateString: string | null): string => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString();
};

// Acknowledge discrepancy
const acknowledge = async () => {
    isProcessing.value = true;
    error.value = null;

    try {
        const response = await fetch(`/api/discrepancies/${props.discrepancy.id}/acknowledge`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ notes: notes.value || undefined }),
        });

        if (!response.ok) {
            const data = await response.json();
            throw new Error(data.message || 'Failed to acknowledge discrepancy');
        }

        // Refresh the page to show updated status
        router.reload({ preserveScroll: true });
        emit('close');
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'An error occurred';
    } finally {
        isProcessing.value = false;
    }
};

// Resolve discrepancy
const resolve = async () => {
    isProcessing.value = true;
    error.value = null;

    try {
        const response = await fetch(`/api/discrepancies/${props.discrepancy.id}/resolve`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ notes: notes.value || undefined }),
        });

        if (!response.ok) {
            const data = await response.json();
            throw new Error(data.message || 'Failed to resolve discrepancy');
        }

        // Refresh the page to show updated status
        router.reload({ preserveScroll: true });
        emit('close');
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'An error occurred';
    } finally {
        isProcessing.value = false;
    }
};

// Get CSRF token from cookies
const getCsrfToken = (): string => {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
};

// Handle dialog close
const handleOpenChange = (open: boolean) => {
    if (!open) {
        emit('close');
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="max-h-[90vh] max-w-2xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Badge :variant="getTypeBadgeVariant(discrepancy.discrepancy_type)">
                        {{ discrepancy.discrepancy_type_label }}
                    </Badge>
                    <span>Discrepancy Details</span>
                </DialogTitle>
                <DialogDescription>
                    Detected at {{ formatDate(discrepancy.detected_at) }}
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-6">
                <!-- Status and Location -->
                <div class="flex flex-wrap items-center gap-4 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="text-muted-foreground">Status:</span>
                        <Badge :variant="getStatusBadgeVariant(discrepancy.status)">
                            {{ discrepancy.status_label }}
                        </Badge>
                    </div>
                    <div v-if="discrepancy.datacenter" class="flex items-center gap-2">
                        <span class="text-muted-foreground">Datacenter:</span>
                        <span class="font-medium">{{ discrepancy.datacenter.name }}</span>
                    </div>
                    <div v-if="discrepancy.room" class="flex items-center gap-2">
                        <span class="text-muted-foreground">Room:</span>
                        <span class="font-medium">{{ discrepancy.room.name }}</span>
                    </div>
                </div>

                <Separator />

                <!-- Connection Comparison -->
                <div class="grid gap-4 md:grid-cols-2">
                    <!-- Source Port -->
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="flex items-center gap-2 text-sm">
                                <Server class="size-4" />
                                Source Port
                            </CardTitle>
                        </CardHeader>
                        <CardContent v-if="formatPortDisplay(discrepancy.source_port)" class="space-y-2 text-sm">
                            <div class="grid grid-cols-2 gap-1">
                                <span class="text-muted-foreground">Port:</span>
                                <span class="font-medium">{{ formatPortDisplay(discrepancy.source_port)?.port }}</span>
                            </div>
                            <div class="grid grid-cols-2 gap-1">
                                <span class="text-muted-foreground">Type:</span>
                                <span>{{ formatPortDisplay(discrepancy.source_port)?.type }}</span>
                            </div>
                            <div class="grid grid-cols-2 gap-1">
                                <span class="text-muted-foreground">Device:</span>
                                <span>{{ formatPortDisplay(discrepancy.source_port)?.device }}</span>
                            </div>
                            <div class="grid grid-cols-2 gap-1">
                                <span class="text-muted-foreground">Rack:</span>
                                <span>{{ formatPortDisplay(discrepancy.source_port)?.rack }}</span>
                            </div>
                        </CardContent>
                        <CardContent v-else class="text-sm text-muted-foreground">
                            No source port data
                        </CardContent>
                    </Card>

                    <!-- Connection Arrow -->
                    <div class="hidden items-center justify-center md:flex">
                        <div class="flex flex-col items-center gap-2">
                            <Cable class="size-6 text-muted-foreground" />
                            <ArrowRight class="size-6 text-muted-foreground" />
                        </div>
                    </div>

                    <!-- Destination Port -->
                    <Card class="md:col-start-2">
                        <CardHeader class="pb-2">
                            <CardTitle class="flex items-center gap-2 text-sm">
                                <Server class="size-4" />
                                Destination Port
                            </CardTitle>
                        </CardHeader>
                        <CardContent v-if="formatPortDisplay(discrepancy.dest_port)" class="space-y-2 text-sm">
                            <div class="grid grid-cols-2 gap-1">
                                <span class="text-muted-foreground">Port:</span>
                                <span class="font-medium">{{ formatPortDisplay(discrepancy.dest_port)?.port }}</span>
                            </div>
                            <div class="grid grid-cols-2 gap-1">
                                <span class="text-muted-foreground">Type:</span>
                                <span>{{ formatPortDisplay(discrepancy.dest_port)?.type }}</span>
                            </div>
                            <div class="grid grid-cols-2 gap-1">
                                <span class="text-muted-foreground">Device:</span>
                                <span>{{ formatPortDisplay(discrepancy.dest_port)?.device }}</span>
                            </div>
                            <div class="grid grid-cols-2 gap-1">
                                <span class="text-muted-foreground">Rack:</span>
                                <span>{{ formatPortDisplay(discrepancy.dest_port)?.rack }}</span>
                            </div>
                        </CardContent>
                        <CardContent v-else class="text-sm text-muted-foreground">
                            No destination port data
                        </CardContent>
                    </Card>
                </div>

                <!-- Configuration Mismatch Details -->
                <Card v-if="hasMismatchDetails">
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm">
                            <Settings class="size-4" />
                            Configuration Differences
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div v-for="(value, key) in discrepancy.mismatch_details" :key="String(key)" class="rounded-lg border bg-muted/50 p-3">
                                <div class="mb-2 font-medium capitalize">{{ String(key).replace(/_/g, ' ') }}</div>
                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <span class="text-muted-foreground">Expected:</span>
                                        <div class="font-medium text-green-600 dark:text-green-400">
                                            {{ (value as Record<string, unknown>)?.expected ?? '-' }}
                                        </div>
                                    </div>
                                    <div>
                                        <span class="text-muted-foreground">Actual:</span>
                                        <div class="font-medium text-red-600 dark:text-red-400">
                                            {{ (value as Record<string, unknown>)?.actual ?? '-' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Expected vs Actual Config (for non-mismatch types) -->
                <div v-if="discrepancy.expected_config || discrepancy.actual_config" class="grid gap-4 md:grid-cols-2">
                    <Card v-if="discrepancy.expected_config">
                        <CardHeader class="pb-2">
                            <CardTitle class="flex items-center gap-2 text-sm text-green-600 dark:text-green-400">
                                <CheckCircle class="size-4" />
                                Expected Configuration
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="text-sm">
                            <pre class="overflow-x-auto rounded bg-muted p-2 text-xs">{{ JSON.stringify(discrepancy.expected_config, null, 2) }}</pre>
                        </CardContent>
                    </Card>
                    <Card v-if="discrepancy.actual_config">
                        <CardHeader class="pb-2">
                            <CardTitle class="flex items-center gap-2 text-sm text-red-600 dark:text-red-400">
                                <XCircle class="size-4" />
                                Actual Configuration
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="text-sm">
                            <pre class="overflow-x-auto rounded bg-muted p-2 text-xs">{{ JSON.stringify(discrepancy.actual_config, null, 2) }}</pre>
                        </CardContent>
                    </Card>
                </div>

                <!-- Description -->
                <div v-if="discrepancy.description" class="space-y-2">
                    <Label class="text-muted-foreground">Notes</Label>
                    <p class="text-sm whitespace-pre-wrap">{{ discrepancy.description }}</p>
                </div>

                <Separator />

                <!-- Action Notes -->
                <div v-if="canAcknowledge || canResolve" class="space-y-2">
                    <Label for="action-notes">Add Notes (Optional)</Label>
                    <Textarea
                        id="action-notes"
                        v-model="notes"
                        placeholder="Add any relevant notes about this discrepancy..."
                        :disabled="isProcessing"
                        rows="3"
                    />
                </div>

                <!-- Error Alert -->
                <Alert v-if="error" variant="destructive">
                    <AlertTriangle class="size-4" />
                    <AlertDescription>{{ error }}</AlertDescription>
                </Alert>
            </div>

            <DialogFooter class="gap-2 sm:gap-0">
                <Button variant="outline" @click="$emit('close')" :disabled="isProcessing">
                    Close
                </Button>
                <Button
                    v-if="canAcknowledge"
                    variant="secondary"
                    :disabled="isProcessing"
                    @click="acknowledge"
                >
                    {{ isProcessing ? 'Processing...' : 'Acknowledge' }}
                </Button>
                <Button
                    v-if="canResolve"
                    variant="default"
                    :disabled="isProcessing"
                    @click="resolve"
                >
                    {{ isProcessing ? 'Processing...' : 'Resolve' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
