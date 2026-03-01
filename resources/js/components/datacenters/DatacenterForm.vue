<script setup lang="ts">
import { computed, ref } from 'vue';
import { Form } from '@inertiajs/vue3';
import DatacenterController from '@/actions/App/Http/Controllers/DatacenterController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import FloorPlanUpload from '@/components/datacenters/FloorPlanUpload.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface DatacenterData {
    id?: number;
    name: string;
    address_line_1: string;
    address_line_2: string | null;
    city: string;
    state_province: string;
    postal_code: string;
    country: string;
    company_name: string | null;
    primary_contact_name: string;
    primary_contact_email: string;
    primary_contact_phone: string;
    secondary_contact_name: string | null;
    secondary_contact_email: string | null;
    secondary_contact_phone: string | null;
    floor_plan_path: string | null;
    floor_plan_url?: string | null;
}

interface Props {
    mode: 'create' | 'edit';
    datacenter?: DatacenterData;
}

const props = withDefaults(defineProps<Props>(), {
    datacenter: () => ({
        name: '',
        address_line_1: '',
        address_line_2: null,
        city: '',
        state_province: '',
        postal_code: '',
        country: '',
        company_name: null,
        primary_contact_name: '',
        primary_contact_email: '',
        primary_contact_phone: '',
        secondary_contact_name: null,
        secondary_contact_email: null,
        secondary_contact_phone: null,
        floor_plan_path: null,
        floor_plan_url: null,
    }),
});

// Determine form action based on mode
const formAction = computed(() => {
    if (props.mode === 'create') {
        return { action: DatacenterController.store.url(), method: 'post' as const };
    }
    return {
        action: DatacenterController.update.url(props.datacenter.id!),
        method: 'post' as const,
    };
});

// Floor plan file handling
const selectedFloorPlan = ref<File | null>(null);
const removeFloorPlan = ref(false);

const handleFloorPlanChange = (file: File | null) => {
    selectedFloorPlan.value = file;
    if (file) {
        removeFloorPlan.value = false;
    }
};

const handleFloorPlanRemove = () => {
    removeFloorPlan.value = true;
    selectedFloorPlan.value = null;
};
</script>

<template>
    <Form
        :action="formAction.action"
        :method="formAction.method"
        enctype="multipart/form-data"
        class="space-y-8"
        v-slot="{ errors, processing, recentlySuccessful }"
    >
        <!-- Hidden method field for PUT request in edit mode -->
        <input v-if="mode === 'edit'" type="hidden" name="_method" value="PUT" />

        <!-- Hidden field to indicate floor plan removal -->
        <input
            v-if="removeFloorPlan"
            type="hidden"
            name="remove_floor_plan"
            value="1"
        />

        <!-- Basic Info Section -->
        <div class="space-y-4">
            <HeadingSmall
                title="Basic Information"
                description="Enter the datacenter name and company details."
            />

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="name">Name <span class="text-red-500">*</span></Label>
                    <Input
                        id="name"
                        name="name"
                        type="text"
                        :default-value="datacenter.name"
                        required
                        placeholder="Enter datacenter name"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label for="company_name">Company Name</Label>
                    <Input
                        id="company_name"
                        name="company_name"
                        type="text"
                        :default-value="datacenter.company_name ?? ''"
                        placeholder="Enter company name (optional)"
                    />
                    <InputError :message="errors.company_name" />
                </div>
            </div>
        </div>

        <!-- Location Section -->
        <div class="space-y-4">
            <HeadingSmall
                title="Location"
                description="Enter the physical address of the datacenter."
            />

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="address_line_1">Address Line 1 <span class="text-red-500">*</span></Label>
                    <Input
                        id="address_line_1"
                        name="address_line_1"
                        type="text"
                        :default-value="datacenter.address_line_1"
                        required
                        placeholder="Street address"
                    />
                    <InputError :message="errors.address_line_1" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label for="address_line_2">Address Line 2</Label>
                    <Input
                        id="address_line_2"
                        name="address_line_2"
                        type="text"
                        :default-value="datacenter.address_line_2 ?? ''"
                        placeholder="Suite, floor, building (optional)"
                    />
                    <InputError :message="errors.address_line_2" />
                </div>

                <div class="grid gap-2">
                    <Label for="city">City <span class="text-red-500">*</span></Label>
                    <Input
                        id="city"
                        name="city"
                        type="text"
                        :default-value="datacenter.city"
                        required
                        placeholder="City"
                    />
                    <InputError :message="errors.city" />
                </div>

                <div class="grid gap-2">
                    <Label for="state_province">State/Province <span class="text-red-500">*</span></Label>
                    <Input
                        id="state_province"
                        name="state_province"
                        type="text"
                        :default-value="datacenter.state_province"
                        required
                        placeholder="State or Province"
                    />
                    <InputError :message="errors.state_province" />
                </div>

                <div class="grid gap-2">
                    <Label for="postal_code">Postal Code <span class="text-red-500">*</span></Label>
                    <Input
                        id="postal_code"
                        name="postal_code"
                        type="text"
                        :default-value="datacenter.postal_code"
                        required
                        placeholder="Postal/ZIP code"
                    />
                    <InputError :message="errors.postal_code" />
                </div>

                <div class="grid gap-2">
                    <Label for="country">Country <span class="text-red-500">*</span></Label>
                    <Input
                        id="country"
                        name="country"
                        type="text"
                        :default-value="datacenter.country"
                        required
                        placeholder="Country"
                    />
                    <InputError :message="errors.country" />
                </div>
            </div>
        </div>

        <!-- Primary Contact Section -->
        <div class="space-y-4">
            <HeadingSmall
                title="Primary Contact"
                description="Enter the primary contact person for this datacenter."
            />

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="primary_contact_name">Name <span class="text-red-500">*</span></Label>
                    <Input
                        id="primary_contact_name"
                        name="primary_contact_name"
                        type="text"
                        :default-value="datacenter.primary_contact_name"
                        required
                        placeholder="Contact name"
                    />
                    <InputError :message="errors.primary_contact_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="primary_contact_email">Email <span class="text-red-500">*</span></Label>
                    <Input
                        id="primary_contact_email"
                        name="primary_contact_email"
                        type="email"
                        :default-value="datacenter.primary_contact_email"
                        required
                        placeholder="contact@example.com"
                    />
                    <InputError :message="errors.primary_contact_email" />
                </div>

                <div class="grid gap-2">
                    <Label for="primary_contact_phone">Phone <span class="text-red-500">*</span></Label>
                    <Input
                        id="primary_contact_phone"
                        name="primary_contact_phone"
                        type="tel"
                        :default-value="datacenter.primary_contact_phone"
                        required
                        placeholder="+1-555-123-4567"
                    />
                    <InputError :message="errors.primary_contact_phone" />
                </div>
            </div>
        </div>

        <!-- Secondary Contact Section -->
        <div class="space-y-4">
            <HeadingSmall
                title="Secondary Contact"
                description="Enter an optional secondary contact person."
            />

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="secondary_contact_name">Name</Label>
                    <Input
                        id="secondary_contact_name"
                        name="secondary_contact_name"
                        type="text"
                        :default-value="datacenter.secondary_contact_name ?? ''"
                        placeholder="Contact name (optional)"
                    />
                    <InputError :message="errors.secondary_contact_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="secondary_contact_email">Email</Label>
                    <Input
                        id="secondary_contact_email"
                        name="secondary_contact_email"
                        type="email"
                        :default-value="datacenter.secondary_contact_email ?? ''"
                        placeholder="contact@example.com (optional)"
                    />
                    <InputError :message="errors.secondary_contact_email" />
                </div>

                <div class="grid gap-2">
                    <Label for="secondary_contact_phone">Phone</Label>
                    <Input
                        id="secondary_contact_phone"
                        name="secondary_contact_phone"
                        type="tel"
                        :default-value="datacenter.secondary_contact_phone ?? ''"
                        placeholder="+1-555-123-4567 (optional)"
                    />
                    <InputError :message="errors.secondary_contact_phone" />
                </div>
            </div>
        </div>

        <!-- Floor Plan Section -->
        <div class="space-y-4">
            <HeadingSmall
                title="Floor Plan"
                description="Upload a floor plan image for this datacenter."
            />

            <FloorPlanUpload
                :current-floor-plan-url="datacenter.floor_plan_url"
                :current-floor-plan-path="datacenter.floor_plan_path"
                @file-selected="handleFloorPlanChange"
                @file-removed="handleFloorPlanRemove"
            />
            <InputError :message="errors.floor_plan" />
        </div>

        <!-- Submit Button -->
        <div class="flex items-center gap-4">
            <Button :disabled="processing" type="submit">
                {{ processing ? 'Saving...' : (mode === 'create' ? 'Create Datacenter' : 'Save Changes') }}
            </Button>

            <Transition
                enter-active-class="transition ease-in-out"
                enter-from-class="opacity-0"
                leave-active-class="transition ease-in-out"
                leave-to-class="opacity-0"
            >
                <p v-show="recentlySuccessful" class="text-sm text-neutral-600 dark:text-neutral-400">
                    Saved.
                </p>
            </Transition>
        </div>
    </Form>
</template>
