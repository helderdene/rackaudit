<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import type { DeviceData } from '@/types/rooms';
import {
    AlertTriangle,
    ArrowRight,
    Cable,
    CheckCircle,
    Server,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface ConnectionData {
    id: number;
    source_port_label: string;
    destination_port_label: string;
    destination_device_name: string;
    cable_type: string | null;
    cable_length: string | null;
    cable_color: string | null;
}

interface DeviceWithConnections extends DeviceData {
    connections?: ConnectionData[];
}

interface Props {
    device: DeviceWithConnections | null;
    isAcknowledged: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    acknowledgedChanged: [value: boolean];
}>();

/**
 * Get connections from device
 */
const connections = computed<ConnectionData[]>(() => {
    return props.device?.connections || [];
});

/**
 * Check if device has any connections
 */
const hasConnections = computed(() => connections.value.length > 0);

/**
 * Format cable type for display
 */
function formatCableType(cableType: string | null): string {
    if (!cableType) return 'Unknown';

    const typeMap: Record<string, string> = {
        fiber_single_mode: 'Fiber SM',
        fiber_multi_mode: 'Fiber MM',
        cat5e: 'Cat5e',
        cat6: 'Cat6',
        cat6a: 'Cat6a',
        cat7: 'Cat7',
        dac: 'DAC',
        power: 'Power',
        serial: 'Serial',
    };

    return typeMap[cableType] || cableType.replace(/_/g, ' ');
}

/**
 * Get cable color badge style
 */
function getCableColorStyle(color: string | null): Record<string, string> {
    if (!color) return {};

    const colorMap: Record<string, { bg: string; text: string }> = {
        blue: {
            bg: 'bg-blue-100 dark:bg-blue-900/30',
            text: 'text-blue-800 dark:text-blue-400',
        },
        red: {
            bg: 'bg-red-100 dark:bg-red-900/30',
            text: 'text-red-800 dark:text-red-400',
        },
        green: {
            bg: 'bg-green-100 dark:bg-green-900/30',
            text: 'text-green-800 dark:text-green-400',
        },
        yellow: {
            bg: 'bg-yellow-100 dark:bg-yellow-900/30',
            text: 'text-yellow-800 dark:text-yellow-400',
        },
        orange: {
            bg: 'bg-orange-100 dark:bg-orange-900/30',
            text: 'text-orange-800 dark:text-orange-400',
        },
        purple: {
            bg: 'bg-purple-100 dark:bg-purple-900/30',
            text: 'text-purple-800 dark:text-purple-400',
        },
        black: { bg: 'bg-gray-800 dark:bg-gray-700', text: 'text-white' },
        white: { bg: 'bg-gray-100 dark:bg-gray-300', text: 'text-gray-800' },
        gray: {
            bg: 'bg-gray-100 dark:bg-gray-600',
            text: 'text-gray-800 dark:text-gray-200',
        },
    };

    return (
        colorMap[color.toLowerCase()] || {
            bg: 'bg-muted',
            text: 'text-foreground',
        }
    );
}

/**
 * Handle checkbox change
 */
function handleAcknowledgeChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    emit('acknowledgedChanged', target.checked);
}
</script>

<template>
    <div class="space-y-6">
        <div>
            <h3 class="text-lg font-medium">Review Connections</h3>
            <p class="text-sm text-muted-foreground">
                Review all active connections on this device. These connections
                will be disconnected when the move is approved.
            </p>
        </div>

        <!-- No Connections State -->
        <div
            v-if="!hasConnections"
            class="rounded-lg border border-dashed p-8 text-center"
        >
            <CheckCircle class="mx-auto h-12 w-12 text-green-500" />
            <h4 class="mt-4 font-medium">No Active Connections</h4>
            <p class="mt-2 text-sm text-muted-foreground">
                This device has no active connections that need to be
                disconnected.
            </p>
        </div>

        <!-- Connections List -->
        <template v-else>
            <Alert>
                <AlertTriangle class="h-4 w-4" />
                <AlertDescription>
                    <strong>{{ connections.length }}</strong> connection{{
                        connections.length !== 1 ? 's' : ''
                    }}
                    will be automatically disconnected when this move is
                    approved.
                </AlertDescription>
            </Alert>

            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Cable class="h-5 w-5" />
                        Active Connections
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="divide-y">
                        <div
                            v-for="connection in connections"
                            :key="connection.id"
                            class="flex items-center gap-4 py-3 first:pt-0 last:pb-0"
                        >
                            <!-- Source Port -->
                            <div class="flex min-w-0 flex-1 items-center gap-2">
                                <div
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded bg-muted"
                                >
                                    <Server
                                        class="h-4 w-4 text-muted-foreground"
                                    />
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">
                                        {{ connection.source_port_label }}
                                    </p>
                                    <p
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{ device?.name }}
                                    </p>
                                </div>
                            </div>

                            <!-- Arrow and Cable Info -->
                            <div
                                class="flex shrink-0 flex-col items-center gap-1"
                            >
                                <div class="flex items-center gap-2">
                                    <Badge variant="outline" class="text-xs">
                                        {{
                                            formatCableType(
                                                connection.cable_type,
                                            )
                                        }}
                                    </Badge>
                                    <ArrowRight
                                        class="h-4 w-4 text-muted-foreground"
                                    />
                                </div>
                                <div class="flex items-center gap-1">
                                    <div
                                        v-if="connection.cable_color"
                                        class="h-3 w-3 rounded-full border"
                                        :class="
                                            getCableColorStyle(
                                                connection.cable_color,
                                            ).bg
                                        "
                                        :title="connection.cable_color"
                                    />
                                    <span
                                        v-if="connection.cable_length"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ connection.cable_length }}
                                    </span>
                                </div>
                            </div>

                            <!-- Destination Port -->
                            <div class="flex min-w-0 flex-1 items-center gap-2">
                                <div
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded bg-muted"
                                >
                                    <Server
                                        class="h-4 w-4 text-muted-foreground"
                                    />
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">
                                        {{ connection.destination_port_label }}
                                    </p>
                                    <p
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{ connection.destination_device_name }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Acknowledgment Checkbox -->
            <div
                class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/50 dark:bg-amber-950/30"
            >
                <div class="flex items-start gap-3">
                    <input
                        id="acknowledge-connections"
                        type="checkbox"
                        :checked="isAcknowledged"
                        @change="handleAcknowledgeChange"
                        class="mt-1 h-4 w-4 shrink-0 cursor-pointer rounded border-gray-300 text-primary focus:ring-primary dark:border-gray-600 dark:bg-gray-700"
                    />
                    <div class="flex-1">
                        <Label
                            for="acknowledge-connections"
                            class="cursor-pointer text-sm leading-tight font-medium"
                        >
                            I understand all connections will be disconnected
                        </Label>
                        <p class="mt-1 text-xs text-muted-foreground">
                            By checking this box, you acknowledge that all
                            {{ connections.length }} connection{{
                                connections.length !== 1 ? 's' : ''
                            }}
                            listed above will be automatically disconnected when
                            the move is approved. Connection details will be
                            preserved in the move history for documentation.
                        </p>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
