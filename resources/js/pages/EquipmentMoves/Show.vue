<script setup lang="ts">
import {
    approve,
    cancel,
    downloadWorkOrder,
    index as movesIndex,
    reject,
} from '@/actions/App/Http/Controllers/EquipmentMoveController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    Cable,
    Calendar,
    CheckCircle,
    Clock,
    Download,
    FileText,
    Loader2,
    MapPin,
    Server,
    User,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface ConnectionSnapshot {
    id: number | null;
    source_port_label: string;
    destination_port_label: string;
    destination_device_name: string;
    cable_type: string | null;
    cable_length: string | null;
    cable_color: string | null;
}

interface MoveData {
    id: number;
    status: string;
    status_label: string;
    is_pending: boolean;
    is_approved: boolean;
    is_executed: boolean;
    is_rejected: boolean;
    is_cancelled: boolean;
    device: {
        id: number;
        name: string;
        asset_tag: string;
        serial_number: string | null;
        manufacturer: string | null;
        model: string | null;
        u_height: number;
        device_type?: { id: number; name: string } | null;
    } | null;
    source_rack: {
        id: number;
        name: string;
        location_path: string;
        datacenter?: { id: number; name: string } | null;
        room?: { id: number; name: string } | null;
        row?: { id: number; name: string } | null;
    } | null;
    destination_rack: {
        id: number;
        name: string;
        location_path: string;
        datacenter?: { id: number; name: string } | null;
        room?: { id: number; name: string } | null;
        row?: { id: number; name: string } | null;
    } | null;
    source_start_u: number | null;
    destination_start_u: number | null;
    source_rack_face: string | null;
    source_rack_face_label: string | null;
    destination_rack_face: string | null;
    destination_rack_face_label: string | null;
    source_width_type: string | null;
    source_width_type_label: string | null;
    destination_width_type: string | null;
    destination_width_type_label: string | null;
    connections_snapshot: ConnectionSnapshot[];
    requester: { id: number; name: string } | null;
    approver: { id: number; name: string } | null;
    operator_notes: string | null;
    approval_notes: string | null;
    requested_at: string | null;
    requested_at_formatted: string | null;
    approved_at: string | null;
    approved_at_formatted: string | null;
    executed_at: string | null;
    executed_at_formatted: string | null;
    can_approve: boolean;
    can_reject: boolean;
    can_cancel: boolean;
}

interface Props {
    move: MoveData;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Equipment Moves',
        href: movesIndex.url(),
    },
    {
        title: `Move #${props.move.id}`,
        href: `/equipment-moves/${props.move.id}`,
    },
];

// Dialog state
const isApproveDialogOpen = ref(false);
const isRejectDialogOpen = ref(false);
const isCancelDialogOpen = ref(false);
const approvalNotes = ref('');
const isProcessing = ref(false);
const actionError = ref<string | null>(null);

/**
 * Get status badge variant
 */
function getStatusVariant(
    status: string,
):
    | 'default'
    | 'secondary'
    | 'destructive'
    | 'outline'
    | 'success'
    | 'warning'
    | 'info' {
    switch (status) {
        case 'pending_approval':
            return 'warning';
        case 'approved':
            return 'info';
        case 'executed':
            return 'success';
        case 'rejected':
            return 'destructive';
        case 'cancelled':
            return 'secondary';
        default:
            return 'outline';
    }
}

/**
 * Get status icon
 */
function getStatusIcon(status: string) {
    switch (status) {
        case 'pending_approval':
            return Clock;
        case 'approved':
            return CheckCircle;
        case 'executed':
            return CheckCircle;
        case 'rejected':
            return XCircle;
        case 'cancelled':
            return XCircle;
        default:
            return Clock;
    }
}

/**
 * Download work order PDF
 */
function handleDownloadWorkOrder(): void {
    window.location.href = downloadWorkOrder.url(props.move.id);
}

/**
 * Perform approve action
 */
async function handleApprove(): Promise<void> {
    isProcessing.value = true;
    actionError.value = null;

    try {
        await axios.post(approve.url(props.move.id), {
            approval_notes: approvalNotes.value || null,
        });
        router.reload({ preserveScroll: true });
        isApproveDialogOpen.value = false;
    } catch (err: unknown) {
        const axiosError = err as {
            response?: { data?: { message?: string } };
        };
        actionError.value =
            axiosError.response?.data?.message || 'Failed to approve move.';
    } finally {
        isProcessing.value = false;
    }
}

/**
 * Perform reject action
 */
async function handleReject(): Promise<void> {
    if (!approvalNotes.value.trim()) {
        actionError.value = 'Please provide a reason for rejection.';
        return;
    }

    isProcessing.value = true;
    actionError.value = null;

    try {
        await axios.post(reject.url(props.move.id), {
            approval_notes: approvalNotes.value,
        });
        router.reload({ preserveScroll: true });
        isRejectDialogOpen.value = false;
    } catch (err: unknown) {
        const axiosError = err as {
            response?: { data?: { message?: string } };
        };
        actionError.value =
            axiosError.response?.data?.message || 'Failed to reject move.';
    } finally {
        isProcessing.value = false;
    }
}

/**
 * Perform cancel action
 */
async function handleCancel(): Promise<void> {
    isProcessing.value = true;
    actionError.value = null;

    try {
        await axios.post(cancel.url(props.move.id));
        router.reload({ preserveScroll: true });
        isCancelDialogOpen.value = false;
    } catch (err: unknown) {
        const axiosError = err as {
            response?: { data?: { message?: string } };
        };
        actionError.value =
            axiosError.response?.data?.message || 'Failed to cancel move.';
    } finally {
        isProcessing.value = false;
    }
}

/**
 * Open dialog and reset state
 */
function openApproveDialog(): void {
    approvalNotes.value = '';
    actionError.value = null;
    isApproveDialogOpen.value = true;
}

function openRejectDialog(): void {
    approvalNotes.value = '';
    actionError.value = null;
    isRejectDialogOpen.value = true;
}

function openCancelDialog(): void {
    actionError.value = null;
    isCancelDialogOpen.value = true;
}

/**
 * Timeline events
 */
const timelineEvents = computed(() => {
    const events: {
        label: string;
        timestamp: string | null;
        icon: typeof Clock;
        status: 'completed' | 'current' | 'pending';
    }[] = [];

    events.push({
        label: 'Requested',
        timestamp: props.move.requested_at_formatted,
        icon: Clock,
        status: 'completed',
    });

    if (props.move.is_approved || props.move.is_executed) {
        events.push({
            label: 'Approved',
            timestamp: props.move.approved_at_formatted,
            icon: CheckCircle,
            status: 'completed',
        });
    }

    if (props.move.is_executed) {
        events.push({
            label: 'Executed',
            timestamp: props.move.executed_at_formatted,
            icon: CheckCircle,
            status: 'completed',
        });
    }

    if (props.move.is_rejected) {
        events.push({
            label: 'Rejected',
            timestamp: props.move.approved_at_formatted,
            icon: XCircle,
            status: 'completed',
        });
    }

    if (props.move.is_cancelled) {
        events.push({
            label: 'Cancelled',
            timestamp:
                props.move.approved_at_formatted ||
                props.move.requested_at_formatted,
            icon: XCircle,
            status: 'completed',
        });
    }

    return events;
});
</script>

<template>
    <Head :title="`Move #${move.id}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-4">
                    <Link :href="movesIndex.url()">
                        <Button variant="ghost" size="sm">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Back
                        </Button>
                    </Link>
                    <div>
                        <HeadingSmall
                            :title="`Move #${move.id}`"
                            :description="move.device?.name || 'Equipment Move'"
                        />
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        @click="handleDownloadWorkOrder"
                    >
                        <Download class="mr-2 h-4 w-4" />
                        Work Order PDF
                    </Button>
                    <Badge
                        :variant="getStatusVariant(move.status)"
                        class="text-sm"
                    >
                        <component
                            :is="getStatusIcon(move.status)"
                            class="mr-1 h-4 w-4"
                        />
                        {{ move.status_label }}
                    </Badge>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Main Content -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Move Details Card -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Move Details</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="flex flex-col gap-6 sm:flex-row">
                                <!-- Source -->
                                <div class="flex-1 space-y-3">
                                    <div
                                        class="flex items-center gap-2 text-sm font-medium text-muted-foreground"
                                    >
                                        <MapPin class="h-4 w-4" />
                                        Source Location
                                    </div>
                                    <div
                                        class="rounded-lg border bg-muted/30 p-4"
                                    >
                                        <p class="font-medium">
                                            {{
                                                move.source_rack?.name ||
                                                'Unknown'
                                            }}
                                        </p>
                                        <p
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            {{
                                                move.source_rack
                                                    ?.location_path || '-'
                                            }}
                                        </p>
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <Badge variant="outline"
                                                >U{{
                                                    move.source_start_u
                                                }}</Badge
                                            >
                                            <Badge variant="outline">{{
                                                move.source_rack_face_label ||
                                                'Front'
                                            }}</Badge>
                                            <Badge variant="outline">{{
                                                move.source_width_type_label ||
                                                'Full'
                                            }}</Badge>
                                        </div>
                                    </div>
                                </div>

                                <!-- Arrow -->
                                <div
                                    class="hidden items-center justify-center sm:flex"
                                >
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded-full bg-muted"
                                    >
                                        <ArrowRight
                                            class="h-5 w-5 text-muted-foreground"
                                        />
                                    </div>
                                </div>

                                <!-- Destination -->
                                <div class="flex-1 space-y-3">
                                    <div
                                        class="flex items-center gap-2 text-sm font-medium text-muted-foreground"
                                    >
                                        <MapPin class="h-4 w-4" />
                                        Destination
                                    </div>
                                    <div
                                        class="rounded-lg border border-primary/50 bg-primary/5 p-4"
                                    >
                                        <p class="font-medium">
                                            {{
                                                move.destination_rack?.name ||
                                                'Unknown'
                                            }}
                                        </p>
                                        <p
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            {{
                                                move.destination_rack
                                                    ?.location_path || '-'
                                            }}
                                        </p>
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <Badge variant="outline"
                                                >U{{
                                                    move.destination_start_u
                                                }}</Badge
                                            >
                                            <Badge variant="outline">{{
                                                move.destination_rack_face_label ||
                                                'Front'
                                            }}</Badge>
                                            <Badge variant="outline">{{
                                                move.destination_width_type_label ||
                                                'Full'
                                            }}</Badge>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Device Info Card -->
                    <Card v-if="move.device">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Server class="h-5 w-5" />
                                Device Information
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <p class="text-sm text-muted-foreground">
                                        Name
                                    </p>
                                    <p class="font-medium">
                                        {{ move.device.name }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-sm text-muted-foreground">
                                        Asset Tag
                                    </p>
                                    <p class="font-mono">
                                        {{ move.device.asset_tag }}
                                    </p>
                                </div>
                                <div v-if="move.device.serial_number">
                                    <p class="text-sm text-muted-foreground">
                                        Serial Number
                                    </p>
                                    <p class="font-mono">
                                        {{ move.device.serial_number }}
                                    </p>
                                </div>
                                <div v-if="move.device.device_type">
                                    <p class="text-sm text-muted-foreground">
                                        Device Type
                                    </p>
                                    <p>{{ move.device.device_type.name }}</p>
                                </div>
                                <div v-if="move.device.manufacturer">
                                    <p class="text-sm text-muted-foreground">
                                        Manufacturer
                                    </p>
                                    <p>{{ move.device.manufacturer }}</p>
                                </div>
                                <div v-if="move.device.model">
                                    <p class="text-sm text-muted-foreground">
                                        Model
                                    </p>
                                    <p>{{ move.device.model }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-muted-foreground">
                                        U Height
                                    </p>
                                    <p>{{ move.device.u_height }}U</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Connections Snapshot Card -->
                    <Card
                        v-if="
                            move.connections_snapshot &&
                            move.connections_snapshot.length > 0
                        "
                    >
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Cable class="h-5 w-5" />
                                Connections
                                <Badge variant="secondary">{{
                                    move.connections_snapshot.length
                                }}</Badge>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="mb-4 text-sm text-muted-foreground">
                                These connections
                                {{ move.is_executed ? 'were' : 'will be' }}
                                disconnected during the move.
                            </p>
                            <div class="divide-y rounded-lg border">
                                <div
                                    v-for="(
                                        conn, index
                                    ) in move.connections_snapshot"
                                    :key="index"
                                    class="flex items-center gap-4 p-3"
                                >
                                    <div class="flex-1">
                                        <p class="text-sm font-medium">
                                            {{ conn.source_port_label }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ move.device?.name }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Badge variant="outline">{{
                                            conn.cable_type || 'Unknown'
                                        }}</Badge>
                                        <ArrowRight
                                            class="h-4 w-4 text-muted-foreground"
                                        />
                                    </div>
                                    <div class="flex-1 text-right">
                                        <p class="text-sm font-medium">
                                            {{ conn.destination_port_label }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ conn.destination_device_name }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Notes Card -->
                    <Card v-if="move.operator_notes || move.approval_notes">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <FileText class="h-5 w-5" />
                                Notes
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div v-if="move.operator_notes">
                                <p class="mb-1 text-sm font-medium">
                                    Operator Notes
                                </p>
                                <p
                                    class="rounded-lg bg-muted/50 p-3 text-sm whitespace-pre-wrap"
                                >
                                    {{ move.operator_notes }}
                                </p>
                            </div>
                            <div v-if="move.approval_notes">
                                <p class="mb-1 text-sm font-medium">
                                    Approval Notes
                                </p>
                                <p
                                    class="rounded-lg bg-muted/50 p-3 text-sm whitespace-pre-wrap"
                                >
                                    {{ move.approval_notes }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Actions Card -->
                    <Card
                        v-if="
                            move.can_approve ||
                            move.can_reject ||
                            move.can_cancel
                        "
                    >
                        <CardHeader>
                            <CardTitle>Actions</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <Button
                                v-if="move.can_approve"
                                class="w-full"
                                @click="openApproveDialog"
                            >
                                <CheckCircle class="mr-2 h-4 w-4" />
                                Approve & Execute
                            </Button>
                            <Button
                                v-if="move.can_reject"
                                variant="destructive"
                                class="w-full"
                                @click="openRejectDialog"
                            >
                                <XCircle class="mr-2 h-4 w-4" />
                                Reject
                            </Button>
                            <Button
                                v-if="move.can_cancel"
                                variant="outline"
                                class="w-full"
                                @click="openCancelDialog"
                            >
                                <XCircle class="mr-2 h-4 w-4" />
                                Cancel Request
                            </Button>
                        </CardContent>
                    </Card>

                    <!-- Timeline Card -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Calendar class="h-5 w-5" />
                                Timeline
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="relative space-y-4">
                                <div
                                    v-for="(event, index) in timelineEvents"
                                    :key="index"
                                    class="relative flex gap-4"
                                >
                                    <div
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                                        :class="
                                            event.status === 'completed'
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-muted'
                                        "
                                    >
                                        <component
                                            :is="event.icon"
                                            class="h-4 w-4"
                                        />
                                    </div>
                                    <div class="flex-1 pt-1">
                                        <p class="text-sm font-medium">
                                            {{ event.label }}
                                        </p>
                                        <p
                                            v-if="event.timestamp"
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ event.timestamp }}
                                        </p>
                                    </div>
                                    <!-- Connector line -->
                                    <div
                                        v-if="index < timelineEvents.length - 1"
                                        class="absolute top-8 left-4 h-full w-px -translate-x-1/2 bg-border"
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Request Info Card -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Request Info</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div
                                v-if="move.requester"
                                class="flex items-center gap-3"
                            >
                                <div
                                    class="flex h-8 w-8 items-center justify-center rounded-full bg-muted"
                                >
                                    <User class="h-4 w-4" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium">
                                        Requested by
                                    </p>
                                    <p class="text-sm text-muted-foreground">
                                        {{ move.requester.name }}
                                    </p>
                                </div>
                            </div>
                            <div
                                v-if="move.approver"
                                class="flex items-center gap-3"
                            >
                                <div
                                    class="flex h-8 w-8 items-center justify-center rounded-full bg-muted"
                                >
                                    <User class="h-4 w-4" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium">
                                        {{
                                            move.is_rejected
                                                ? 'Rejected by'
                                                : 'Approved by'
                                        }}
                                    </p>
                                    <p class="text-sm text-muted-foreground">
                                        {{ move.approver.name }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>

        <!-- Approve Dialog -->
        <Dialog v-model:open="isApproveDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Approve & Execute Move</DialogTitle>
                    <DialogDescription>
                        This will approve the move request and immediately
                        execute it. The device will be moved to the destination
                        location.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4">
                    <div class="space-y-2">
                        <Label for="approve-notes">Notes (Optional)</Label>
                        <Textarea
                            id="approve-notes"
                            v-model="approvalNotes"
                            placeholder="Add any notes about this approval..."
                            :disabled="isProcessing"
                        />
                    </div>
                    <Alert v-if="actionError" variant="destructive">
                        <AlertTriangle class="h-4 w-4" />
                        <AlertDescription>{{ actionError }}</AlertDescription>
                    </Alert>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        :disabled="isProcessing"
                        @click="isApproveDialogOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button :disabled="isProcessing" @click="handleApprove">
                        <Loader2
                            v-if="isProcessing"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        {{
                            isProcessing ? 'Processing...' : 'Approve & Execute'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Reject Dialog -->
        <Dialog v-model:open="isRejectDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Reject Move Request</DialogTitle>
                    <DialogDescription>
                        Please provide a reason for rejecting this move request.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4">
                    <div class="space-y-2">
                        <Label for="reject-notes">Rejection Reason</Label>
                        <Textarea
                            id="reject-notes"
                            v-model="approvalNotes"
                            placeholder="Enter the reason for rejection..."
                            :disabled="isProcessing"
                        />
                    </div>
                    <Alert v-if="actionError" variant="destructive">
                        <AlertTriangle class="h-4 w-4" />
                        <AlertDescription>{{ actionError }}</AlertDescription>
                    </Alert>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        :disabled="isProcessing"
                        @click="isRejectDialogOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        :disabled="isProcessing || !approvalNotes.trim()"
                        @click="handleReject"
                    >
                        <Loader2
                            v-if="isProcessing"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        {{ isProcessing ? 'Processing...' : 'Reject' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Cancel Dialog -->
        <Dialog v-model:open="isCancelDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Cancel Move Request</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to cancel this move request? This
                        action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <Alert v-if="actionError" variant="destructive">
                    <AlertTriangle class="h-4 w-4" />
                    <AlertDescription>{{ actionError }}</AlertDescription>
                </Alert>
                <DialogFooter>
                    <Button
                        variant="outline"
                        :disabled="isProcessing"
                        @click="isCancelDialogOpen = false"
                    >
                        Keep Request
                    </Button>
                    <Button
                        variant="destructive"
                        :disabled="isProcessing"
                        @click="handleCancel"
                    >
                        <Loader2
                            v-if="isProcessing"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        {{ isProcessing ? 'Processing...' : 'Cancel Request' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
