<script setup lang="ts">
import { update as updateConnection } from '@/actions/App/Http/Controllers/ExpectedConnectionController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import axios from 'axios';
import { Check, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import CableTypeSelect from './CableTypeSelect.vue';
import type { ExpectedConnectionData } from './ConnectionReviewTable.vue';
import DeviceSearchSelect from './DeviceSearchSelect.vue';
import PortSearchSelect from './PortSearchSelect.vue';

interface Props {
    connection: ExpectedConnectionData;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (
        e: 'save',
        connectionId: number,
        data: Partial<ExpectedConnectionData>,
    ): void;
    (e: 'cancel'): void;
}>();

// Form state
const sourceDeviceId = ref<number | null>(
    props.connection.source_device?.id ?? null,
);
const sourcePortId = ref<number | null>(
    props.connection.source_port?.id ?? null,
);
const destDeviceId = ref<number | null>(
    props.connection.dest_device?.id ?? null,
);
const destPortId = ref<number | null>(props.connection.dest_port?.id ?? null);
const cableType = ref<string | null>(props.connection.cable_type ?? null);
const cableLength = ref<number | null>(props.connection.cable_length ?? null);

const isSaving = ref(false);
const saveError = ref<string | null>(null);

// Reset port when device changes
watch(sourceDeviceId, (newValue, oldValue) => {
    if (newValue !== oldValue) {
        sourcePortId.value = null;
    }
});

watch(destDeviceId, (newValue, oldValue) => {
    if (newValue !== oldValue) {
        destPortId.value = null;
    }
});

/**
 * Save changes
 */
async function handleSave(): Promise<void> {
    isSaving.value = true;
    saveError.value = null;

    try {
        const updateData: Record<string, unknown> = {};

        if (sourceDeviceId.value !== props.connection.source_device?.id) {
            updateData.source_device_id = sourceDeviceId.value;
        }
        if (sourcePortId.value !== props.connection.source_port?.id) {
            updateData.source_port_id = sourcePortId.value;
        }
        if (destDeviceId.value !== props.connection.dest_device?.id) {
            updateData.dest_device_id = destDeviceId.value;
        }
        if (destPortId.value !== props.connection.dest_port?.id) {
            updateData.dest_port_id = destPortId.value;
        }
        if (cableType.value !== props.connection.cable_type) {
            updateData.cable_type = cableType.value;
        }
        if (cableLength.value !== props.connection.cable_length) {
            updateData.cable_length = cableLength.value;
        }

        // Only submit if there are changes
        if (Object.keys(updateData).length > 0) {
            await axios.put(
                updateConnection.url(props.connection.id),
                updateData,
            );
        }

        emit(
            'save',
            props.connection.id,
            updateData as Partial<ExpectedConnectionData>,
        );
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.data?.message) {
            saveError.value = error.response.data.message;
        } else {
            saveError.value = 'Failed to save changes.';
        }
    } finally {
        isSaving.value = false;
    }
}

/**
 * Cancel editing
 */
function handleCancel(): void {
    emit('cancel');
}
</script>

<template>
    <tr class="border-b bg-blue-50/50 dark:bg-blue-900/10">
        <!-- Checkbox (disabled during edit) -->
        <td class="px-2 py-3">
            <span class="block size-4" />
        </td>

        <!-- Row Number -->
        <td class="px-3 py-3 text-muted-foreground">
            {{ connection.row_number }}
        </td>

        <!-- Source Device -->
        <td class="px-3 py-3">
            <DeviceSearchSelect
                v-model="sourceDeviceId"
                placeholder="Select source device"
                :initial-device-name="
                    connection.source_device?.name ??
                    connection.match?.source_device?.original
                "
            />
        </td>

        <!-- Source Port -->
        <td class="px-3 py-3">
            <PortSearchSelect
                v-model="sourcePortId"
                :device-id="sourceDeviceId"
                placeholder="Select source port"
                :initial-port-label="
                    connection.source_port?.label ??
                    connection.match?.source_port?.original
                "
            />
        </td>

        <!-- Dest Device -->
        <td class="px-3 py-3">
            <DeviceSearchSelect
                v-model="destDeviceId"
                placeholder="Select dest device"
                :initial-device-name="
                    connection.dest_device?.name ??
                    connection.match?.dest_device?.original
                "
            />
        </td>

        <!-- Dest Port -->
        <td class="px-3 py-3">
            <PortSearchSelect
                v-model="destPortId"
                :device-id="destDeviceId"
                placeholder="Select dest port"
                :initial-port-label="
                    connection.dest_port?.label ??
                    connection.match?.dest_port?.original
                "
            />
        </td>

        <!-- Cable Info -->
        <td class="px-3 py-3">
            <div class="space-y-2">
                <CableTypeSelect v-model="cableType" />
                <Input
                    v-model.number="cableLength"
                    type="number"
                    step="0.1"
                    min="0"
                    placeholder="Length (m)"
                    class="h-8 w-20 text-xs"
                />
            </div>
        </td>

        <!-- Status -->
        <td class="px-3 py-3">
            <span class="text-xs text-muted-foreground">Editing...</span>
        </td>

        <!-- Actions -->
        <td class="px-3 py-3">
            <div class="flex gap-1">
                <Button
                    size="icon"
                    variant="ghost"
                    class="size-7 text-green-600 hover:bg-green-100 hover:text-green-700 dark:hover:bg-green-900/20"
                    :disabled="isSaving"
                    @click="handleSave"
                >
                    <Spinner v-if="isSaving" class="size-3.5" />
                    <Check v-else class="size-3.5" />
                </Button>
                <Button
                    size="icon"
                    variant="ghost"
                    class="size-7 text-muted-foreground hover:text-foreground"
                    :disabled="isSaving"
                    @click="handleCancel"
                >
                    <X class="size-3.5" />
                </Button>
            </div>
            <p v-if="saveError" class="mt-1 text-xs text-red-600">
                {{ saveError }}
            </p>
        </td>
    </tr>
</template>
