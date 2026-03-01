<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Form } from '@inertiajs/vue3';
import AuditController from '@/actions/App/Http/Controllers/AuditController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import AuditTypeSelector from '@/components/audits/AuditTypeSelector.vue';
import ScopeTypeSelector from '@/components/audits/ScopeTypeSelector.vue';
import CascadingLocationSelect from '@/components/audits/CascadingLocationSelect.vue';
import RackMultiSelect from '@/components/audits/RackMultiSelect.vue';
import DeviceMultiSelect from '@/components/audits/DeviceMultiSelect.vue';
import AuditMetadataForm from '@/components/audits/AuditMetadataForm.vue';
import AssigneeMultiSelect from '@/components/audits/AssigneeMultiSelect.vue';
import ImplementationFileStatus from '@/components/audits/ImplementationFileStatus.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

interface DatacenterOption {
    id: number;
    name: string;
    formatted_location: string;
    has_approved_implementation_files: boolean;
}

interface UserOption {
    id: number;
    name: string;
    email: string;
}

interface TypeOption {
    value: string;
    label: string;
}

interface ScopeTypeOption {
    value: string;
    label: string;
}

interface RoomOption {
    id: number;
    name: string;
    rack_count: number;
}

interface Props {
    datacenters: DatacenterOption[];
    assignableUsers: UserOption[];
    auditTypes: TypeOption[];
    scopeTypes: ScopeTypeOption[];
}

const props = defineProps<Props>();

// Form state
const selectedAuditType = ref<string>('');
const selectedScopeType = ref<string>('');
const selectedDatacenterId = ref<number | null>(null);
const selectedRoomId = ref<number | null>(null);
const selectedRackIds = ref<number[]>([]);
const selectedDeviceIds = ref<number[]>([]);
const selectedAssigneeIds = ref<number[]>([]);

// Metadata form state
const auditName = ref<string>('');
const auditDescription = ref<string>('');
const auditDueDate = ref<string>('');

// Implementation file status
const hasApprovedImplementationFile = ref<boolean>(true);

// Rooms loaded from API
const loadedRooms = ref<RoomOption[]>([]);

// Handle rooms loaded event
const handleRoomsLoaded = (rooms: RoomOption[]): void => {
    loadedRooms.value = rooms;
};

// Handle implementation file status change
const handleImplementationFileStatusChanged = (hasApprovedFile: boolean): void => {
    hasApprovedImplementationFile.value = hasApprovedFile;
};

// Show rack selection only for 'racks' scope type
const showRackSelection = computed(() => {
    return selectedScopeType.value === 'racks' && selectedRoomId.value !== null;
});

// Show device selection only when racks scope and racks are selected
const showDeviceSelection = computed(() => {
    return selectedScopeType.value === 'racks' && selectedRackIds.value.length > 0;
});

// Check if form can be submitted (blocked if connection audit without approved file)
const canSubmit = computed(() => {
    if (selectedAuditType.value === 'connection' && !hasApprovedImplementationFile.value) {
        return false;
    }
    return true;
});

// Reset dependent selections when parent changes
watch(selectedScopeType, (newScopeType) => {
    // Reset rack and device selections when scope type changes
    if (newScopeType !== 'racks') {
        selectedRackIds.value = [];
        selectedDeviceIds.value = [];
    }
});

watch(selectedDatacenterId, () => {
    // Room selection is reset by CascadingLocationSelect
    selectedRackIds.value = [];
    selectedDeviceIds.value = [];
});

watch(selectedRoomId, () => {
    // Reset rack and device selections when room changes
    selectedRackIds.value = [];
    selectedDeviceIds.value = [];
});

watch(selectedRackIds, (newRackIds) => {
    // Reset device selections when racks change
    if (newRackIds.length === 0) {
        selectedDeviceIds.value = [];
    }
});
</script>

<template>
    <Form
        :action="AuditController.store.url()"
        method="post"
        class="space-y-6 sm:space-y-8"
        #default="{ errors, processing }"
    >
        <!-- Hidden form fields for array values -->
        <template v-for="assigneeId in selectedAssigneeIds" :key="`assignee-${assigneeId}`">
            <input type="hidden" name="assignee_ids[]" :value="assigneeId" />
        </template>
        <template v-for="rackId in selectedRackIds" :key="`rack-${rackId}`">
            <input type="hidden" name="rack_ids[]" :value="rackId" />
        </template>
        <template v-for="deviceId in selectedDeviceIds" :key="`device-${deviceId}`">
            <input type="hidden" name="device_ids[]" :value="deviceId" />
        </template>

        <!-- Hidden fields for select values -->
        <input v-if="selectedDatacenterId" type="hidden" name="datacenter_id" :value="selectedDatacenterId" />
        <input v-if="selectedRoomId" type="hidden" name="room_id" :value="selectedRoomId" />
        <input v-if="selectedAuditType" type="hidden" name="type" :value="selectedAuditType" />
        <input v-if="selectedScopeType" type="hidden" name="scope_type" :value="selectedScopeType" />

        <!-- Section 1: Audit Type Selection -->
        <div class="space-y-3 sm:space-y-4">
            <HeadingSmall
                title="Audit Type"
                description="Select the type of audit you want to perform."
            />

            <AuditTypeSelector
                v-model="selectedAuditType"
                :audit-types="auditTypes"
                :error="errors.type"
            />
            <InputError :message="errors.type" />
        </div>

        <!-- Section 2: Scope Selection -->
        <div class="space-y-3 sm:space-y-4">
            <HeadingSmall
                title="Audit Scope"
                description="Define what will be included in this audit."
            />

            <ScopeTypeSelector
                v-model="selectedScopeType"
                :scope-types="scopeTypes"
                :error="errors.scope_type"
            />
            <InputError :message="errors.scope_type" />

            <!-- Location Selection (Datacenter and Room) -->
            <div v-if="selectedScopeType" class="mt-4 sm:mt-6">
                <CascadingLocationSelect
                    v-model:datacenter-id="selectedDatacenterId"
                    v-model:room-id="selectedRoomId"
                    :datacenters="datacenters"
                    :scope-type="selectedScopeType"
                    :datacenter-error="errors.datacenter_id"
                    :room-error="errors.room_id"
                    @rooms-loaded="handleRoomsLoaded"
                />
            </div>

            <!-- Implementation File Status (only for connection audits) -->
            <div v-if="selectedAuditType === 'connection' && selectedDatacenterId" class="mt-4 sm:mt-6">
                <ImplementationFileStatus
                    :datacenter-id="selectedDatacenterId"
                    :audit-type="selectedAuditType"
                    @status-changed="handleImplementationFileStatusChanged"
                />
            </div>

            <!-- Rack Selection (only for racks scope) -->
            <div v-if="showRackSelection" class="mt-4 sm:mt-6">
                <RackMultiSelect
                    v-model="selectedRackIds"
                    :room-id="selectedRoomId"
                    :datacenter-id="selectedDatacenterId"
                    :error="errors.rack_ids || errors['rack_ids.0']"
                />
            </div>

            <!-- Device Selection (only when racks are selected) -->
            <div v-if="showDeviceSelection" class="mt-4 sm:mt-6">
                <DeviceMultiSelect
                    v-model="selectedDeviceIds"
                    :rack-ids="selectedRackIds"
                    :error="errors.device_ids || errors['device_ids.0']"
                />
            </div>
        </div>

        <!-- Section 3: Audit Details (Metadata) -->
        <div class="space-y-3 sm:space-y-4">
            <AuditMetadataForm
                v-model:name="auditName"
                v-model:description="auditDescription"
                v-model:due-date="auditDueDate"
                :name-error="errors.name"
                :description-error="errors.description"
                :due-date-error="errors.due_date"
            />
        </div>

        <!-- Section 4: Team Assignment -->
        <div class="space-y-3 sm:space-y-4">
            <AssigneeMultiSelect
                v-model="selectedAssigneeIds"
                :users="assignableUsers"
                :error="errors.assignee_ids || errors['assignee_ids.0']"
            />
        </div>

        <!-- Submit Button - Responsive layout -->
        <div class="flex flex-col gap-3 border-t border-border pt-4 sm:flex-row sm:items-center sm:gap-4 sm:pt-6">
            <Button
                type="submit"
                :disabled="processing || !canSubmit"
                class="w-full sm:w-auto"
            >
                <Spinner v-if="processing" class="mr-2 h-4 w-4" />
                {{ processing ? 'Creating Audit...' : 'Create Audit' }}
            </Button>

            <p v-if="!canSubmit && selectedAuditType === 'connection'" class="text-sm text-destructive">
                Cannot create connection audit without an approved implementation file.
            </p>
        </div>
    </Form>
</template>
