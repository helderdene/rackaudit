<script setup lang="ts">
import AuditController from '@/actions/App/Http/Controllers/AuditController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import AuditForm from '@/components/audits/AuditForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

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

interface Props {
    datacenters: DatacenterOption[];
    assignableUsers: UserOption[];
    auditTypes: TypeOption[];
    scopeTypes: ScopeTypeOption[];
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Audits',
        href: AuditController.index.url(),
    },
    {
        title: 'Create Audit',
        href: AuditController.create.url(),
    },
];
</script>

<template>
    <Head title="Create Audit" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 md:p-6">
            <HeadingSmall
                title="Create Audit"
                description="Create a new audit to verify connections or inventory in your datacenter."
            />

            <!-- Responsive container: full width on mobile, max width on larger screens -->
            <div class="w-full max-w-3xl">
                <AuditForm
                    :datacenters="datacenters"
                    :assignable-users="assignableUsers"
                    :audit-types="auditTypes"
                    :scope-types="scopeTypes"
                />
            </div>
        </div>
    </AppLayout>
</template>
