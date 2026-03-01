<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { edit } from '@/actions/App/Http/Controllers/DeviceController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import DeviceForm from '@/components/devices/DeviceForm.vue';
import DeleteDeviceDialog from '@/components/devices/DeleteDeviceDialog.vue';
import RealtimeToastContainer from '@/components/notifications/RealtimeToastContainer.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { useRealtimeUpdates } from '@/composables/useRealtimeUpdates';
import { type BreadcrumbItem } from '@/types';
import type { RealtimeUpdate } from '@/types/realtime';
import type { DeviceData, DeviceTypeOption, SelectOption } from '@/types/rooms';

interface Props {
    device: DeviceData;
    deviceTypeOptions: DeviceTypeOption[];
    lifecycleStatusOptions: SelectOption[];
    depthOptions: SelectOption[];
    widthTypeOptions: SelectOption[];
    rackFaceOptions: SelectOption[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Devices',
        href: '/devices',
    },
    {
        title: props.device.name,
        href: `/devices/${props.device.id}`,
    },
    {
        title: 'Edit',
        href: edit.url(props.device.id),
    },
];

// Get datacenter ID from device's rack if available
const datacenterId = computed(() => props.device.rack?.datacenter_id ?? null);

// Track whether there's a conflict (another user modified this device)
const hasConflict = ref(false);

// Real-time updates integration
const {
    pendingUpdates,
    dismissUpdate,
    clearUpdates,
    onDataChange,
} = useRealtimeUpdates(datacenterId.value);

// Register handler for device changes - detect if this specific device was modified
onDataChange('device', (data) => {
    // Check if this device was modified by another user
    if (data.entityId === props.device.id) {
        hasConflict.value = true;
    }
    console.log('Device changed:', data);
});

// Transform pending updates to mark conflicts
const updatesWithConflicts = computed<RealtimeUpdate[]>(() => {
    return pendingUpdates.value.map((update) => ({
        ...update,
        isConflict: update.entityType === 'device' && update.entityId === props.device.id,
    }));
});

// Handle toast dismissal
function handleDismissUpdate(id: string): void {
    dismissUpdate(id);
    // If dismissing a conflict update, reset conflict state
    const update = pendingUpdates.value.find((u) => u.id === id);
    if (update && update.entityType === 'device' && update.entityId === props.device.id) {
        hasConflict.value = false;
    }
}

// Handle toast refresh
function handleRefresh(): void {
    clearUpdates();
    hasConflict.value = false;
    router.reload();
}

// Handle clear all updates
function handleClearAll(): void {
    clearUpdates();
    hasConflict.value = false;
}
</script>

<template>
    <Head :title="`Edit ${device.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <HeadingSmall
                title="Edit Device"
                :description="`Update information for ${device.name}.`"
            />

            <div class="max-w-3xl">
                <DeviceForm
                    mode="edit"
                    :device="device"
                    :device-type-options="deviceTypeOptions"
                    :lifecycle-status-options="lifecycleStatusOptions"
                    :depth-options="depthOptions"
                    :width-type-options="widthTypeOptions"
                    :rack-face-options="rackFaceOptions"
                />
            </div>

            <!-- Delete Device Section -->
            <div class="max-w-3xl space-y-6">
                <HeadingSmall
                    title="Delete Device"
                    description="Permanently remove this device from the system."
                />
                <div
                    class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
                >
                    <div class="relative space-y-0.5 text-red-600 dark:text-red-100">
                        <p class="font-medium">Warning</p>
                        <p class="text-sm">
                            Once deleted, this device will be permanently removed from the system.
                            This includes all placement history and specifications.
                        </p>
                    </div>
                    <DeleteDeviceDialog
                        :device-id="device.id"
                        :device-name="device.name"
                    >
                        <Button variant="destructive">
                            Delete Device
                        </Button>
                    </DeleteDeviceDialog>
                </div>
            </div>
        </div>

        <!-- Real-time Toast Container with conflict detection -->
        <RealtimeToastContainer
            :updates="updatesWithConflicts"
            @dismiss="handleDismissUpdate"
            @refresh="handleRefresh"
            @clear-all="handleClearAll"
        />
    </AppLayout>
</template>
