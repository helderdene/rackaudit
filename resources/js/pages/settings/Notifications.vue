<script setup lang="ts">
import NotificationPreferencesController from '@/actions/App/Http/Controllers/Settings/NotificationPreferencesController';
import { edit } from '@/routes/notifications';
import { Form, Head } from '@inertiajs/vue3';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Check } from 'lucide-vue-next';

interface NotificationPreferences {
    audit_assignments: boolean;
    finding_updates: boolean;
    approval_requests: boolean;
    discrepancies: boolean;
    scheduled_reports: boolean;
}

interface Props {
    preferences: NotificationPreferences;
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Notification settings',
        href: edit().url,
    },
];

// Define notification categories with human-readable labels and descriptions
const notificationCategories = [
    {
        key: 'audit_assignments' as const,
        label: 'Audit Assignments',
        description: 'Notifications when you are assigned to or removed from an audit',
    },
    {
        key: 'finding_updates' as const,
        label: 'Finding Updates',
        description: 'Notifications about finding status changes, assignments, and due dates',
    },
    {
        key: 'approval_requests' as const,
        label: 'Approval Requests',
        description: 'Notifications when implementation files need your approval or are approved',
    },
    {
        key: 'discrepancies' as const,
        label: 'Discrepancies',
        description: 'Notifications about new discrepancies and threshold alerts',
    },
    {
        key: 'scheduled_reports' as const,
        label: 'Scheduled Reports',
        description: 'Notifications about scheduled report status and failures',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Notification settings" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    title="Notification preferences"
                    description="Control how you receive notifications for different activity types"
                />

                <Form
                    v-bind="NotificationPreferencesController.update.form()"
                    :options="{
                        preserveScroll: true,
                    }"
                    class="space-y-6"
                    v-slot="{ processing, recentlySuccessful }"
                >
                    <!-- Notification Categories Table -->
                    <div class="rounded-lg border">
                        <!-- Header -->
                        <div class="grid grid-cols-[1fr_80px_80px] gap-4 border-b bg-muted/50 px-4 py-3">
                            <div class="text-sm font-medium">Category</div>
                            <div class="text-center text-sm font-medium">In-App</div>
                            <div class="text-center text-sm font-medium">Email</div>
                        </div>

                        <!-- Category Rows -->
                        <div class="divide-y">
                            <div
                                v-for="category in notificationCategories"
                                :key="category.key"
                                class="grid grid-cols-[1fr_80px_80px] items-center gap-4 px-4 py-4"
                            >
                                <!-- Category Info -->
                                <div>
                                    <Label :for="category.key" class="text-sm font-medium">
                                        {{ category.label }}
                                    </Label>
                                    <p class="text-sm text-muted-foreground mt-0.5">
                                        {{ category.description }}
                                    </p>
                                </div>

                                <!-- In-App Column (Always Enabled, Read-only) -->
                                <div class="flex justify-center">
                                    <div
                                        class="flex h-4 w-4 items-center justify-center rounded-[4px] border bg-primary text-primary-foreground"
                                        title="In-app notifications are always enabled"
                                    >
                                        <Check class="h-3 w-3" />
                                    </div>
                                </div>

                                <!-- Email Column (Toggleable) -->
                                <div class="flex justify-center">
                                    <Checkbox
                                        :id="category.key"
                                        :name="category.key"
                                        :default-checked="props.preferences[category.key]"
                                        :value="true"
                                        data-test="email-toggle"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Info Text -->
                    <p class="text-sm text-muted-foreground">
                        In-app notifications cannot be disabled. Email notifications will only be sent when email delivery is configured.
                    </p>

                    <!-- Submit Button -->
                    <div class="flex items-center gap-4">
                        <Button
                            :disabled="processing"
                            data-test="save-preferences-button"
                        >
                            Save preferences
                        </Button>

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="recentlySuccessful"
                                class="text-sm text-neutral-600"
                            >
                                Saved.
                            </p>
                        </Transition>
                    </div>
                </Form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
