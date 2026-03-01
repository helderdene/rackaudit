<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import RealtimeToastContainer from '@/components/notifications/RealtimeToastContainer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useRealtimeUpdates } from '@/composables/useRealtimeUpdates';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Cable, Plug, Server } from 'lucide-vue-next';
import { computed } from 'vue';

interface PortData {
    id: number;
    label: string;
    type: string;
    type_label: string;
    status: string;
    status_label: string;
}

interface DeviceData {
    id: number;
    name: string;
    asset_tag: string | null;
    rack_id: number | null;
    rack_name: string | null;
    datacenter_id?: number | null;
}

interface ConnectionData {
    id: number;
    cable_type: string;
    cable_type_label: string;
    cable_length: string;
    cable_color: string | null;
    path_notes: string | null;
    created_at: string;
    updated_at: string;
    source_port: PortData;
    destination_port: PortData;
    source_device: DeviceData;
    destination_device: DeviceData;
}

interface Props {
    connection: ConnectionData;
}

const props = defineProps<Props>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: 'Connections',
        href: '/connections/diagram',
    },
    {
        title: `Connection #${props.connection.id}`,
        href: '#',
    },
]);

// Get datacenter ID from the source device if available
const datacenterId = computed(
    () => props.connection.source_device.datacenter_id ?? null,
);

// Real-time updates integration
const { pendingUpdates, dismissUpdate, clearUpdates, onDataChange } =
    useRealtimeUpdates(datacenterId.value);

// Register handler for connection changes
onDataChange('connection', (data) => {
    // Toast will be automatically shown via pendingUpdates
    console.log('Connection changed:', data);
});

// Handle toast dismissal
function handleDismissUpdate(id: string): void {
    dismissUpdate(id);
}

// Handle toast refresh
function handleRefresh(): void {
    clearUpdates();
    router.reload();
}

// Handle clear all updates
function handleClearAll(): void {
    clearUpdates();
}

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleString();
}
</script>

<template>
    <Head :title="`Connection #${connection.id}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Header -->
            <div class="flex items-center gap-4">
                <Link href="/connections/diagram">
                    <Button variant="ghost" size="icon" class="size-8">
                        <ArrowLeft class="size-4" />
                    </Button>
                </Link>
                <HeadingSmall
                    :title="`Connection #${connection.id}`"
                    :description="`${connection.source_device.name} to ${connection.destination_device.name}`"
                />
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Connection Details Card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Cable class="size-5" />
                            Cable Details
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Cable Type
                                </p>
                                <p class="font-medium">
                                    {{ connection.cable_type_label }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Cable Length
                                </p>
                                <p class="font-medium">
                                    {{ connection.cable_length }}m
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Cable Color
                                </p>
                                <p class="font-medium">
                                    {{ connection.cable_color || '-' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Created
                                </p>
                                <p class="font-medium">
                                    {{ formatDate(connection.created_at) }}
                                </p>
                            </div>
                        </div>
                        <div v-if="connection.path_notes">
                            <p class="text-sm text-muted-foreground">
                                Path Notes
                            </p>
                            <p class="font-medium">
                                {{ connection.path_notes }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Source Endpoint Card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Server class="size-5" />
                            Source Endpoint
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div>
                            <p class="text-sm text-muted-foreground">Device</p>
                            <Link
                                :href="`/devices/${connection.source_device.id}`"
                                class="font-medium text-primary hover:underline"
                            >
                                {{ connection.source_device.name }}
                            </Link>
                            <p
                                v-if="connection.source_device.asset_tag"
                                class="text-xs text-muted-foreground"
                            >
                                {{ connection.source_device.asset_tag }}
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Port</p>
                            <div class="flex items-center gap-2">
                                <Plug class="size-4 text-muted-foreground" />
                                <span class="font-medium">{{
                                    connection.source_port.label
                                }}</span>
                                <Badge variant="secondary">{{
                                    connection.source_port.type_label
                                }}</Badge>
                            </div>
                        </div>
                        <div v-if="connection.source_device.rack_name">
                            <p class="text-sm text-muted-foreground">Rack</p>
                            <p class="font-medium">
                                {{ connection.source_device.rack_name }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Destination Endpoint Card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Server class="size-5" />
                            Destination Endpoint
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div>
                            <p class="text-sm text-muted-foreground">Device</p>
                            <Link
                                :href="`/devices/${connection.destination_device.id}`"
                                class="font-medium text-primary hover:underline"
                            >
                                {{ connection.destination_device.name }}
                            </Link>
                            <p
                                v-if="connection.destination_device.asset_tag"
                                class="text-xs text-muted-foreground"
                            >
                                {{ connection.destination_device.asset_tag }}
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Port</p>
                            <div class="flex items-center gap-2">
                                <Plug class="size-4 text-muted-foreground" />
                                <span class="font-medium">{{
                                    connection.destination_port.label
                                }}</span>
                                <Badge variant="secondary">{{
                                    connection.destination_port.type_label
                                }}</Badge>
                            </div>
                        </div>
                        <div v-if="connection.destination_device.rack_name">
                            <p class="text-sm text-muted-foreground">Rack</p>
                            <p class="font-medium">
                                {{ connection.destination_device.rack_name }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Real-time Toast Container -->
        <RealtimeToastContainer
            :updates="pendingUpdates"
            @dismiss="handleDismissUpdate"
            @refresh="handleRefresh"
            @clear-all="handleClearAll"
        />
    </AppLayout>
</template>
