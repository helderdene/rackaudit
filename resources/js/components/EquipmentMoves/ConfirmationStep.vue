<script setup lang="ts">
import {
    downloadWorkOrder,
    show,
} from '@/actions/App/Http/Controllers/EquipmentMoveController';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { DeviceData } from '@/types/rooms';
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    CheckCircle,
    Download,
    ExternalLink,
    FileText,
    Loader2,
    MapPin,
    Server,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    device: DeviceData | null;
    destination: {
        destination_rack_id: number | null;
        destination_start_u: number | null;
        destination_rack_face: string;
        destination_width_type: string;
    };
    operatorNotes: string;
    isSubmitting: boolean;
    isSuccess: boolean;
    createdMoveId: number | null;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    notesChanged: [notes: string];
}>();

/**
 * Format rack face for display
 */
function formatRackFace(face: string): string {
    return face.charAt(0).toUpperCase() + face.slice(1);
}

/**
 * Format width type for display
 */
function formatWidthType(widthType: string): string {
    const map: Record<string, string> = {
        full: 'Full Width',
        half_left: 'Half Left',
        half_right: 'Half Right',
    };
    return map[widthType] || widthType;
}

/**
 * Format location for display
 */
function formatLocation(device: DeviceData | null): string {
    if (!device?.rack) return 'Not placed';
    let location = device.rack.name;
    if (device.start_u) {
        location += ` (U${device.start_u})`;
    }
    return location;
}

/**
 * Handle notes input
 */
function handleNotesInput(event: Event): void {
    const value = (event.target as HTMLTextAreaElement).value;
    emit('notesChanged', value);
}

/**
 * Calculate ending U position
 */
const destinationEndU = computed(() => {
    if (!props.destination.destination_start_u || !props.device) return null;
    return props.destination.destination_start_u + props.device.u_height - 1;
});

/**
 * Get move details page URL
 */
const moveDetailsUrl = computed(() => {
    if (!props.createdMoveId) return null;
    return show.url(props.createdMoveId);
});

/**
 * Download work order PDF
 */
function handleDownloadWorkOrder(): void {
    if (props.createdMoveId) {
        window.location.href = downloadWorkOrder.url(props.createdMoveId);
    }
}
</script>

<template>
    <div class="space-y-6">
        <!-- Success State -->
        <div v-if="isSuccess" class="space-y-6">
            <div
                class="rounded-lg border border-green-200 bg-green-50 p-8 text-center dark:border-green-900/50 dark:bg-green-950/30"
            >
                <CheckCircle class="mx-auto h-16 w-16 text-green-500" />
                <h3
                    class="mt-4 text-lg font-medium text-green-800 dark:text-green-400"
                >
                    Move Request Created Successfully
                </h3>
                <p class="mt-2 text-sm text-green-600 dark:text-green-500">
                    Your move request has been submitted and is pending
                    approval.
                </p>
            </div>

            <div class="flex justify-center gap-4">
                <Button
                    v-if="createdMoveId"
                    variant="outline"
                    @click="handleDownloadWorkOrder"
                >
                    <Download class="mr-2 h-4 w-4" />
                    Download Work Order PDF
                </Button>
                <Link v-if="moveDetailsUrl" :href="moveDetailsUrl">
                    <Button>
                        <ExternalLink class="mr-2 h-4 w-4" />
                        View Move Details
                    </Button>
                </Link>
            </div>
        </div>

        <!-- Confirmation Form -->
        <template v-else>
            <div>
                <h3 class="text-lg font-medium">Confirm Move Request</h3>
                <p class="text-sm text-muted-foreground">
                    Review the move details below and add any notes before
                    submitting.
                </p>
            </div>

            <!-- Move Summary -->
            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="text-base">Move Summary</CardTitle>
                </CardHeader>
                <CardContent>
                    <div
                        class="flex flex-col gap-6 sm:flex-row sm:items-center"
                    >
                        <!-- Source -->
                        <div class="flex-1 space-y-3">
                            <div
                                class="flex items-center gap-2 text-sm font-medium text-muted-foreground"
                            >
                                <MapPin class="h-4 w-4" />
                                Current Location
                            </div>
                            <div class="rounded-lg border bg-muted/30 p-4">
                                <div class="flex items-start gap-3">
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded bg-muted"
                                    >
                                        <Server
                                            class="h-5 w-5 text-muted-foreground"
                                        />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium">
                                            {{ device?.name }}
                                        </p>
                                        <p
                                            class="mt-0.5 text-sm text-muted-foreground"
                                        >
                                            {{ formatLocation(device) }}
                                        </p>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <Badge variant="outline"
                                                >{{ device?.u_height }}U</Badge
                                            >
                                            <Badge variant="outline">{{
                                                device?.rack_face_label ||
                                                'Front'
                                            }}</Badge>
                                            <Badge variant="outline">{{
                                                device?.width_type_label ||
                                                'Full Width'
                                            }}</Badge>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Arrow -->
                        <div class="flex items-center justify-center">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10"
                            >
                                <ArrowRight class="h-5 w-5 text-primary" />
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
                                <div class="flex items-start gap-3">
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded bg-primary/10"
                                    >
                                        <Server class="h-5 w-5 text-primary" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium">
                                            Rack #{{
                                                destination.destination_rack_id
                                            }}
                                        </p>
                                        <p
                                            class="mt-0.5 text-sm text-muted-foreground"
                                        >
                                            U{{
                                                destination.destination_start_u
                                            }}
                                            <template
                                                v-if="
                                                    destinationEndU &&
                                                    destinationEndU !==
                                                        destination.destination_start_u
                                                "
                                            >
                                                - U{{ destinationEndU }}
                                            </template>
                                        </p>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <Badge variant="outline"
                                                >{{ device?.u_height }}U</Badge
                                            >
                                            <Badge variant="outline">{{
                                                formatRackFace(
                                                    destination.destination_rack_face,
                                                )
                                            }}</Badge>
                                            <Badge variant="outline">{{
                                                formatWidthType(
                                                    destination.destination_width_type,
                                                )
                                            }}</Badge>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Operator Notes -->
            <div class="space-y-2">
                <Label for="operator-notes" class="flex items-center gap-2">
                    <FileText class="h-4 w-4" />
                    Operator Notes
                    <span class="text-muted-foreground">(Optional)</span>
                </Label>
                <Textarea
                    id="operator-notes"
                    :model-value="operatorNotes"
                    placeholder="Add any notes about this move request..."
                    rows="4"
                    :disabled="isSubmitting"
                    @input="handleNotesInput"
                />
                <p class="text-xs text-muted-foreground">
                    Include any relevant information for the approver, such as
                    the reason for the move or timing requirements.
                </p>
            </div>

            <!-- Approval Notice -->
            <Alert>
                <FileText class="h-4 w-4" />
                <AlertDescription>
                    This move request will be submitted for approval. Once
                    approved, the device will be moved to the new location and
                    any active connections will be automatically disconnected. A
                    work order PDF will be available for download.
                </AlertDescription>
            </Alert>

            <!-- Loading Overlay -->
            <div
                v-if="isSubmitting"
                class="flex items-center justify-center rounded-lg border border-dashed p-8"
            >
                <div class="text-center">
                    <Loader2
                        class="mx-auto h-8 w-8 animate-spin text-primary"
                    />
                    <p class="mt-2 text-sm text-muted-foreground">
                        Creating move request...
                    </p>
                </div>
            </div>
        </template>
    </div>
</template>
